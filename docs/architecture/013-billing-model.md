# ADR 013: Billing Model

## Status
**Decided** — Per-aircraft monthly subscription

## Context
Need a billing model that:
- Aligns with aviation industry norms
- Scales with tenant usage
- Is simple to understand
- Encourages adoption

## Decision
**Flat-rate per-aircraft per-month pricing.** Simple and easy to understand. We'll adjust based on market feedback after launch.

## Pricing Structure

### Base Model

```
Monthly Cost = (Number of Active Aircraft) × $49
```

**$49/aircraft/month** — All features included. No tiers, no hidden fees.

### Example Pricing

| Company Profile | Aircraft | Monthly Cost |
|-----------------|----------|--------------|
| Single aircraft owner-pilot | 1 | $49 |
| Small charter (family business) | 2 | $98 |
| Corporate flight department | 3 | $147 |
| Regional charter operator | 5 | $245 |
| Mid-size charter company | 10 | $490 |

### User Limits

**Unlimited users per tenant.** We don't charge per user — just per aircraft.

Typical usage patterns:
- 1 aircraft → 2-5 users (owner + crew)
- 3 aircraft → 10-15 users (pilots, dispatchers, admin)
- 10 aircraft → 30-50 users (larger operations team)

### Feature Comparison

| Feature | Standard | Enterprise |
|---------|----------|------------|
| Flight Planning | ✅ | ✅ |
| Flight Logs | ✅ | ✅ |
| Weight & Balance | ✅ | ✅ |
| Checklists | ✅ | ✅ |
| Client Portal | ✅ | ✅ |
| Reports | ✅ | ✅ |
| API Access | ✅ | ✅ |
| Mobile App (iOS) | ✅ | ✅ |
| Crew Management | ✅ | ✅ |
| FRAT (Phase 2) | ✅ | ✅ |
| Custom Integrations | ❌ | ✅ |
| Dedicated Support | ❌ | ✅ |
| SLA | ❌ | ✅ |
| On-premise option | ❌ | ✅ |

**Note:** Everyone gets the full product. Enterprise is only for companies needing custom work, SLAs, or dedicated support.

## Database Schema

### plans

```php
Schema::create('plans', function (Blueprint $table) {
    $table->id();
    $table->string('name');                    // "Standard", "Enterprise"
    $table->string('slug')->unique();          // standard, enterprise
    $table->decimal('price_per_aircraft', 8, 2); // 49.00
    $table->json('features')->nullable();      // Feature flags (for future use)
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### subscriptions

```php
Schema::create('subscriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
    $table->foreignId('plan_id')->constrained();
    $table->enum('status', ['trialing', 'active', 'past_due', 'cancelled', 'paused']);
    $table->date('trial_ends_at')->nullable();
    $table->date('current_period_start');
    $table->date('current_period_end');
    $table->decimal('discount_percent', 5, 2)->default(0);
    $table->string('discount_reason')->nullable();
    $table->string('payment_method')->nullable();  // stripe, manual, etc.
    $table->string('external_id')->nullable();     // Stripe subscription ID
    $table->timestamps();
});
```

### invoices

```php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('subscription_id')->constrained();
    $table->string('number')->unique();        // INV-2026-0001
    $table->date('date');
    $table->date('due_date');
    $table->decimal('subtotal', 10, 2);
    $table->decimal('discount', 10, 2)->default(0);
    $table->decimal('tax', 10, 2)->default(0);
    $table->decimal('total', 10, 2);
    $table->enum('status', ['draft', 'sent', 'paid', 'overdue', 'cancelled']);
    $table->json('line_items');                // Aircraft breakdown
    $table->string('pdf_path')->nullable();
    $table->timestamps();
});
```

### Line Items Structure

```json
{
    "line_items": [
        {
            "description": "N12345 (Citation XLS+)",
            "aircraft_id": 1,
            "quantity": 1,
            "unit_price": 49.00,
            "total": 49.00
        },
        {
            "description": "N67890 (King Air 350)",
            "aircraft_id": 2,
            "quantity": 1,
            "unit_price": 49.00,
            "total": 49.00
        }
    ],
    "subtotal": 98.00,
    "discount": 0,
    "tax": 0,
    "total": 98.00
}
```

## Billing Logic

### Calculate Monthly Revenue

```php
class Subscription extends Model
{
    public function calculateMRR(): float
    {
        $aircraftCount = $this->tenant->aircraft()
            ->where('status', 'active')
            ->count();
        
        $total = $aircraftCount * $this->plan->price_per_aircraft;
        
        // Apply discount if any
        if ($this->discount_percent > 0) {
            $total = $total * (1 - $this->discount_percent / 100);
        }
        
        return round($total, 2);
    }
    
    public function isInTrial(): bool
    {
        return $this->status === 'trialing' 
            && $this->trial_ends_at 
            && $this->trial_ends_at->isFuture();
    }
}
```

### Generate Invoice

```php
class GenerateMonthlyInvoice
{
    public function execute(Subscription $subscription): Invoice
    {
        $tenant = $subscription->tenant;
        $aircraft = $tenant->aircraft()->where('status', 'active')->get();
        
        $lineItems = $aircraft->map(fn($ac) => [
            'description' => "{$ac->registration} ({$ac->aircraftType->name})",
            'aircraft_id' => $ac->id,
            'quantity' => 1,
            'unit_price' => $subscription->plan->price_per_aircraft,
            'total' => $subscription->plan->price_per_aircraft,
        ]);
        
        $subtotal = $lineItems->sum('total');
        $discount = $subtotal * ($subscription->discount_percent / 100);
        $total = $subtotal - $discount;
        
        return Invoice::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'number' => $this->generateInvoiceNumber(),
            'date' => now(),
            'due_date' => now()->addDays(14),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
            'status' => 'draft',
            'line_items' => $lineItems->toArray(),
        ]);
    }
}
```

## Trial Period

### New Tenant Flow

```php
class CreateTenantWithTrial
{
    public function execute(array $data): Tenant
    {
        $tenant = Tenant::create([
            'name' => $data['company_name'],
            'slug' => Str::slug($data['company_name']),
        ]);
        
        // Start with Standard plan trial
        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => Plan::where('slug', 'standard')->first()->id,
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(14),
            'current_period_start' => now(),
            'current_period_end' => now()->addDays(14),
        ]);
        
        return $tenant;
    }
}
```

### Trial Expiration

```php
// Scheduled command
class CheckTrialExpirations extends Command
{
    public function handle()
    {
        $expiringTrials = Subscription::where('status', 'trialing')
            ->where('trial_ends_at', '<=', now()->addDays(3))
            ->where('trial_ends_at', '>', now())
            ->get();
            
        foreach ($expiringTrials as $subscription) {
            $subscription->tenant->users()
                ->role('admin')
                ->each(fn($user) => $user->notify(new TrialExpiringNotification($subscription)));
        }
        
        // Expire trials
        Subscription::where('status', 'trialing')
            ->where('trial_ends_at', '<=', now())
            ->update(['status' => 'past_due']);
    }
}
```

## Aircraft Count Changes

### Adding Aircraft

```php
// When tenant adds aircraft mid-cycle
class AircraftObserver
{
    public function created(Aircraft $aircraft): void
    {
        // Log for prorated billing
        BillingEvent::create([
            'tenant_id' => $aircraft->tenant_id,
            'type' => 'aircraft_added',
            'aircraft_id' => $aircraft->id,
            'effective_date' => now(),
        ]);
        
        // Notify for next invoice adjustment
        // Actual billing handled at invoice generation
    }
}
```

### Removing Aircraft

```php
public function deleted(Aircraft $aircraft): void
{
    BillingEvent::create([
        'tenant_id' => $aircraft->tenant_id,
        'type' => 'aircraft_removed',
        'aircraft_id' => $aircraft->id,
        'effective_date' => now(),
    ]);
}
```

## Payment Integration (Phase 2)

### Options

| Provider | Pros | Cons |
|----------|------|------|
| **Stripe** | Industry standard, great API | 2.9% + $0.30 fees |
| **Paddle** | Handles tax/VAT | Higher fees, less control |
| **Manual** | No fees | Manual work |

### Initial Approach: Manual Billing

For MVP with ~10 tenants, manual invoicing is acceptable:

```php
// Generate invoice → Send PDF → Receive payment → Mark paid
class MarkInvoicePaid
{
    public function execute(Invoice $invoice, string $paymentMethod, ?string $reference = null): void
    {
        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => $paymentMethod,  // 'bank_transfer', 'check', etc.
            'payment_reference' => $reference,
        ]);
        
        // Update subscription
        $invoice->subscription->update([
            'status' => 'active',
            'current_period_start' => $invoice->date,
            'current_period_end' => $invoice->date->addMonth(),
        ]);
    }
}
```

### Stripe Integration (Later)

```php
// When ready for automated payments
// composer require laravel/cashier

class StripeWebhookController extends Controller
{
    public function handleInvoicePaid(Request $request)
    {
        $stripeInvoice = $request->input('data.object');
        
        $invoice = Invoice::where('external_id', $stripeInvoice['id'])->first();
        $invoice->update(['status' => 'paid']);
    }
}
```

## Marketing Pricing Page

Simple pricing message:

```blade
<section class="pricing">
    <h1>Simple Pricing</h1>
    <p class="subtitle">$49/aircraft/month — all features included</p>
    
    <div class="price-card">
        <h2>$49</h2>
        <p>per aircraft / month</p>
        <ul>
            <li>✅ Flight Planning & Scheduling</li>
            <li>✅ Weight & Balance Calculations</li>
            <li>✅ Digital Flight Logs</li>
            <li>✅ Client Portal & Reporting</li>
            <li>✅ iOS Mobile App</li>
            <li>✅ API Access</li>
            <li>✅ Unlimited Users</li>
            <li>✅ 14-day Free Trial</li>
        </ul>
        <a href="/demo" class="btn-primary">Start Free Trial</a>
    </div>
    
    <div class="enterprise">
        <h3>Enterprise</h3>
        <p>Need custom integrations, SLA, or dedicated support?</p>
        <a href="/contact" class="btn-secondary">Contact Sales</a>
    </div>
</section>
```

## Consequences

### Positive
- ✅ Dead simple to understand
- ✅ Revenue scales with customer size
- ✅ No confusing tiers
- ✅ Easy to calculate and invoice
- ✅ Can adjust pricing later based on feedback

### Negative
- ⚠️ May be leaving money on the table with larger operators
- ⚠️ Need to handle mid-cycle changes
- ⚠️ Manual billing overhead initially

## Future Considerations

- **Volume discount** — Adjust pricing for larger fleets if needed
- **Annual discount** — 2 months free for annual payment (17% off)
- **Add-ons** — Premium support packages, custom integrations
- **Regional pricing** — Adjust for different markets

---

*Last updated: February 2026*

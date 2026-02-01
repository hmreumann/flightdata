# ADR 010: Marketing Site & Demo Strategy

## Status
**Decided** — Public landing page with instant demo option

## Context
Need a public-facing website to:
- Promote FlightData to potential customers
- Allow prospects to experience the product
- Capture leads and convert to paying tenants

## Decision
1. **Public marketing site** at `flightdata.com`
2. **Instant demo** — visitors can try immediately with pre-populated data
3. **Contact form** — for those who prefer guided demo/sales call

## Site Structure

```
flightdata.com/                    → Landing page (hero, features, pricing)
flightdata.com/features            → Detailed feature breakdown
flightdata.com/pricing             → Pricing tiers
flightdata.com/contact             → Contact form
flightdata.com/demo                → Start instant demo
flightdata.com/login               → Portal to select tenant (or redirect)

demo.flightdata.com/               → Demo tenant (read-only or limited)
{tenant}.flightdata.com/           → Real tenant applications
admin.flightdata.com/              → Super admin panel (platform owner)
```

## Demo Strategy

### Option A: Instant Demo (Recommended)

Visitor clicks "Try Demo" and immediately accesses a demo tenant:

```
┌─────────────────────────────────────────────────────────────┐
│                    flightdata.com                            │
│  ┌─────────────────────────────────────────────────────┐    │
│  │     "Streamline Your Flight Operations"              │    │
│  │                                                      │    │
│  │   [Start Free Demo]    [Request Guided Demo]         │    │
│  └─────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
                    │
        ┌───────────┴───────────┐
        ▼                       ▼
┌──────────────────┐    ┌──────────────────┐
│  Instant Demo    │    │  Contact Form    │
│                  │    │                  │
│  → demo.flight.. │    │  → Sales follow  │
│  → Pre-filled    │    │  → Custom demo   │
│  → Limited write │    │  → Enterprise    │
└──────────────────┘    └──────────────────┘
```

### Demo Tenant Implementation

```php
// Special demo tenant
Tenant::create([
    'name' => 'Demo Aviation',
    'slug' => 'demo',
    'is_demo' => true,
    'settings' => [
        'read_only' => false,        // Allow writes but...
        'reset_daily' => true,       // Reset data every 24h
        'max_users' => 5,            // Limit concurrent demo users
        'watermark' => true,         // Show "DEMO" watermark
    ],
]);
```

### Demo User Flow

1. Visitor clicks "Start Free Demo"
2. Redirected to `demo.flightdata.com`
3. Auto-logged in as demo user (or simple registration)
4. Full access to explore features
5. Prompted to "Create Your Account" when ready

### Demo Limitations

| Feature | Demo | Full |
|---------|------|------|
| View all features | ✅ | ✅ |
| Create flights | ✅ (limited) | ✅ |
| W&B calculations | ✅ | ✅ |
| Export PDFs | ❌ Watermarked | ✅ |
| User management | ❌ | ✅ |
| Data persistence | 24h reset | Permanent |
| API access | ❌ | ✅ |

### Option B: Request Demo (Secondary)

For enterprise prospects who prefer:
- Guided walkthrough
- Custom demo with their data
- Sales conversation

```php
// Contact form creates lead
Lead::create([
    'name' => $request->name,
    'email' => $request->email,
    'company' => $request->company,
    'fleet_size' => $request->fleet_size,
    'message' => $request->message,
    'source' => 'demo_request',
]);

// Notify admin
AdminNotification::send(new NewDemoRequest($lead));
```

## Marketing Pages

### Landing Page Sections

```blade
{{-- resources/views/marketing/home.blade.php --}}

{{-- Hero --}}
<section class="hero">
    <h1>Flight Operations, Simplified</h1>
    <p>The complete EFB solution for charter & corporate aviation</p>
    <a href="/demo" class="btn-primary">Start Free Demo</a>
    <a href="/contact" class="btn-secondary">Request Guided Demo</a>
</section>

{{-- Features Grid --}}
<section class="features">
    <div class="feature">Flight Planning</div>
    <div class="feature">Weight & Balance</div>
    <div class="feature">Crew Management</div>
    <div class="feature">Client Reporting</div>
</section>

{{-- Pricing Preview --}}
<section class="pricing-preview">
    <p>Starting at $XX/aircraft/month</p>
    <a href="/pricing">See Pricing</a>
</section>

{{-- Testimonials --}}
{{-- Trust badges (future) --}}
{{-- CTA --}}
```

### Pricing Page

See [ADR 013 - Billing Model](./013-billing-model.md) for pricing details.

## Routes Configuration

```php
// routes/web.php

// Public marketing site
Route::domain('flightdata.com')->group(function () {
    Route::get('/', [MarketingController::class, 'home'])->name('marketing.home');
    Route::get('/features', [MarketingController::class, 'features']);
    Route::get('/pricing', [MarketingController::class, 'pricing']);
    Route::get('/contact', [MarketingController::class, 'contact']);
    Route::post('/contact', [MarketingController::class, 'submitContact']);
    Route::get('/demo', [DemoController::class, 'start']);
    Route::get('/login', [MarketingController::class, 'loginPortal']);
});

// Demo tenant
Route::domain('demo.flightdata.com')
    ->middleware(['web', 'demo'])
    ->group(function () {
        Route::get('/', [DemoController::class, 'dashboard']);
        // ... demo routes with limitations
    });

// Real tenants
Route::domain('{tenant}.flightdata.com')
    ->middleware(['web', 'tenant'])
    ->group(function () {
        // ... tenant routes
    });
```

## Demo Data Seeder

```php
// database/seeders/DemoDataSeeder.php
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'demo')->first();
        
        // Sample aircraft
        $types = [
            ['name' => 'Citation XLS+', 'manufacturer' => 'Cessna'],
            ['name' => 'King Air 350', 'manufacturer' => 'Beechcraft'],
        ];
        
        // Sample flights for next 7 days
        // Sample clients
        // Sample crew
        // Pre-calculated W&B examples
    }
}

// Scheduled task to reset demo
// app/Console/Kernel.php
$schedule->command('demo:reset')->daily()->at('03:00');
```

## Conversion Tracking

```php
// Track demo → paid conversion
class DemoConversionListener
{
    public function handle(TenantCreated $event): void
    {
        if ($event->tenant->source === 'demo') {
            Analytics::track('demo_conversion', [
                'tenant_id' => $event->tenant->id,
                'time_in_demo' => $event->tenant->demo_duration,
            ]);
        }
    }
}
```

## Consequences

### Positive
- ✅ Low friction for prospects
- ✅ Product sells itself through hands-on experience
- ✅ Reduces sales burden
- ✅ 24/7 availability

### Negative
- ⚠️ Demo data maintenance
- ⚠️ Potential for abuse (need rate limiting)
- ⚠️ Must keep demo updated with features

---

*Last updated: February 2026*

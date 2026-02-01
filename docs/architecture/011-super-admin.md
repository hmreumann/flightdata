# ADR 011: Super Admin Panel

## Status
**Decided** — Platform owner admin at `admin.flightdata.com`

## Context
As the platform owner, you need administrative access to:
- Create and manage tenants
- View subscription/billing status
- Impersonate users for support
- Monitor platform health
- Manage demo environment

## Decision
Dedicated **super admin panel** at `admin.flightdata.com`, completely separate from tenant applications.

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                 admin.flightdata.com                         │
│                  (Super Admin Panel)                         │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  • Tenant Management (CRUD, status, settings)       │    │
│  │  • Subscription/Billing Dashboard                    │    │
│  │  • User Impersonation                               │    │
│  │  • Platform Analytics                               │    │
│  │  • Demo Management                                  │    │
│  │  • System Health                                    │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                              │
│  Access: Only platform owner (super-admin role)             │
└─────────────────────────────────────────────────────────────┘
          │
          │ Can impersonate into
          ▼
┌─────────────────────────────────────────────────────────────┐
│              {tenant}.flightdata.com                         │
│                  (Tenant Application)                        │
└─────────────────────────────────────────────────────────────┘
```

## Super Admin Features

### 1. Tenant Management

```php
// Livewire component
class TenantManagement extends Component
{
    public function render()
    {
        return view('admin.tenants.index', [
            'tenants' => Tenant::with(['subscription', 'users'])
                ->withCount('aircraft')
                ->latest()
                ->paginate(20),
        ]);
    }
    
    public function createTenant(array $data)
    {
        $tenant = Tenant::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'settings' => $this->defaultSettings(),
        ]);
        
        // Create admin user for tenant
        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'type' => UserType::Internal,
        ]);
        $admin->assignRole('admin');
        
        // Send welcome email
        $admin->notify(new TenantWelcomeNotification($tenant));
        
        return $tenant;
    }
}
```

### Tenant Dashboard View

| Column | Data |
|--------|------|
| Name | Company name + subdomain link |
| Status | Active / Trial / Suspended / Cancelled |
| Plan | Starter / Professional / Enterprise |
| Aircraft | Count (billable) |
| Users | Count |
| MRR | Monthly revenue |
| Created | Date |
| Actions | Edit, Impersonate, Suspend |

### 2. Subscription Management

```php
class SubscriptionDashboard extends Component
{
    public function render()
    {
        return view('admin.subscriptions.index', [
            'metrics' => [
                'total_mrr' => Subscription::active()->sum('mrr'),
                'total_tenants' => Tenant::active()->count(),
                'total_aircraft' => Aircraft::count(),
                'churn_this_month' => $this->calculateChurn(),
            ],
            'subscriptions' => Subscription::with('tenant')
                ->latest('created_at')
                ->paginate(20),
        ]);
    }
}
```

### 3. User Impersonation

Essential for customer support:

```php
// app/Http/Controllers/Admin/ImpersonationController.php
class ImpersonationController extends Controller
{
    public function impersonate(User $user)
    {
        // Store original admin session
        session()->put('impersonator_id', auth()->id());
        session()->put('impersonator_tenant', null); // Admin has no tenant
        
        // Log impersonation for audit
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'impersonation_started',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'new_values' => ['target_user' => $user->email],
        ]);
        
        // Login as target user
        auth()->login($user);
        
        return redirect()->to(
            "https://{$user->tenant->slug}.flightdata.com"
        );
    }
    
    public function stopImpersonating()
    {
        $originalId = session()->pull('impersonator_id');
        
        AuditLog::create([
            'user_id' => $originalId,
            'action' => 'impersonation_ended',
        ]);
        
        auth()->loginUsingId($originalId);
        
        return redirect()->to('https://admin.flightdata.com');
    }
}
```

### Impersonation Banner

```blade
{{-- Show banner when impersonating --}}
@if(session()->has('impersonator_id'))
    <div class="bg-yellow-500 text-black px-4 py-2 text-center">
        ⚠️ You are impersonating {{ auth()->user()->name }}
        <a href="{{ route('admin.stop-impersonating') }}" class="underline ml-4">
            Stop Impersonating
        </a>
    </div>
@endif
```

### 4. Platform Analytics

```php
class PlatformAnalytics extends Component
{
    public function render()
    {
        return view('admin.analytics.index', [
            'stats' => [
                'flights_today' => Flight::whereDate('scheduled_departure', today())->count(),
                'flights_this_month' => Flight::whereMonth('scheduled_departure', now()->month)->count(),
                'wb_calculations' => WeightBalanceCalculation::count(),
                'active_users_today' => User::whereDate('last_login_at', today())->count(),
            ],
            'growth' => [
                'tenants_by_month' => $this->tenantsByMonth(),
                'mrr_by_month' => $this->mrrByMonth(),
            ],
        ]);
    }
}
```

### 5. Demo Management

```php
class DemoManagement extends Component
{
    public function resetDemo()
    {
        Artisan::call('demo:reset');
        
        $this->dispatch('notify', 'Demo environment reset successfully');
    }
    
    public function render()
    {
        $demoTenant = Tenant::where('slug', 'demo')->first();
        
        return view('admin.demo.index', [
            'demo' => $demoTenant,
            'active_sessions' => $this->activeDemoSessions(),
            'last_reset' => $demoTenant->settings['last_reset'] ?? null,
        ]);
    }
}
```

## Super Admin User Model

```php
// Super admins are users without tenant_id
class User extends Authenticatable
{
    public function isSuperAdmin(): bool
    {
        return $this->tenant_id === null && $this->hasRole('super-admin');
    }
    
    public function isPlatformOwner(): bool
    {
        return $this->isSuperAdmin();
    }
}
```

## Routes Configuration

```php
// routes/web.php

// Super Admin Panel
Route::domain('admin.flightdata.com')
    ->middleware(['web', 'auth', 'super-admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
        
        // Tenant management
        Route::resource('tenants', TenantController::class);
        Route::post('tenants/{tenant}/suspend', [TenantController::class, 'suspend']);
        Route::post('tenants/{tenant}/activate', [TenantController::class, 'activate']);
        
        // Subscriptions
        Route::get('subscriptions', SubscriptionController::class)->name('admin.subscriptions');
        
        // Impersonation
        Route::post('impersonate/{user}', [ImpersonationController::class, 'impersonate'])
            ->name('admin.impersonate');
        Route::post('stop-impersonating', [ImpersonationController::class, 'stopImpersonating'])
            ->name('admin.stop-impersonating');
        
        // Analytics
        Route::get('analytics', AnalyticsController::class)->name('admin.analytics');
        
        // Demo
        Route::get('demo', [DemoController::class, 'index'])->name('admin.demo');
        Route::post('demo/reset', [DemoController::class, 'reset']);
        
        // System
        Route::get('system/health', SystemHealthController::class);
        Route::get('system/logs', LogViewerController::class);
    });
```

## Middleware

```php
// app/Http/Middleware/EnsureSuperAdmin.php
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            abort(403, 'Access denied. Super admin only.');
        }
        
        return $next($request);
    }
}
```

## Database: Super Admin Users

```php
// Super admins have no tenant_id
Schema::table('users', function (Blueprint $table) {
    $table->foreignId('tenant_id')->nullable()->change(); // Allow null for super admins
});

// Seeder for initial super admin
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::create([
            'tenant_id' => null,  // No tenant
            'type' => UserType::Internal,
            'name' => 'Platform Owner',
            'email' => 'admin@flightdata.com',
            'password' => Hash::make('secure-password'),
        ]);
        
        $superAdmin->assignRole('super-admin');
    }
}
```

## Security Considerations

1. **Separate subdomain** — Admin panel isolated from tenant apps
2. **IP restriction** (optional) — Limit admin access to specific IPs
3. **2FA required** — Enforce two-factor for super admins
4. **Audit logging** — Log all admin actions, especially impersonation
5. **Session timeout** — Shorter session for admin panel

```php
// config/session.php - could use separate session for admin
// Or enforce 2FA in middleware

class EnsureTwoFactorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()->two_factor_confirmed_at) {
            return redirect()->route('admin.setup-2fa');
        }
        
        return $next($request);
    }
}
```

## Consequences

### Positive
- ✅ Complete platform control
- ✅ Efficient customer support via impersonation
- ✅ Revenue visibility
- ✅ Audit trail for all admin actions

### Negative
- ⚠️ Additional application to maintain
- ⚠️ Security-critical (must protect well)
- ⚠️ Impersonation must be logged carefully

---

*Last updated: February 2026*

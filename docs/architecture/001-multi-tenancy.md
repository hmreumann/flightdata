# ADR 001: Multi-Tenancy Approach

## Status
**Decided** — Multi-Database with stancl/tenancy

## Context
FlightData will serve multiple aviation companies (tenants). We need to decide between:

1. **Single Database with `tenant_id`** — All tenants share tables, filtered by tenant identifier
2. **Multi-Database** — Each tenant gets their own database ✓

**Target scale:** ~10 tenants initially, potentially 50+ with growth.

## Decision
Use **stancl/tenancy** package with **separate database per tenant**.

### Database Structure

```
┌─────────────────────────────────────────────────────────────┐
│                    CENTRAL DATABASE                         │
│                   (flightdata_central)                      │
├─────────────────────────────────────────────────────────────┤
│  • tenants          - Tenant records                        │
│  • domains          - Subdomain mappings                    │
│  • plans            - Subscription plans                    │
│  • users (admins)   - Platform super admins only            │
│  • platform stats   - Analytics, billing, etc.              │
└─────────────────────────────────────────────────────────────┘
          │
          │ Creates on tenant registration
          ▼
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│  flightdata_    │  │  flightdata_    │  │  flightdata_    │
│  acme_aviation  │  │  skyways        │  │  demo           │
├─────────────────┤  ├─────────────────┤  ├─────────────────┤
│  • users        │  │  • users        │  │  • users        │
│  • aircraft     │  │  • aircraft     │  │  • aircraft     │
│  • flights      │  │  • flights      │  │  • flights      │
│  • clients      │  │  • clients      │  │  • clients      │
│  • ...          │  │  • ...          │  │  • (sample data)│
└─────────────────┘  └─────────────────┘  └─────────────────┘
```

## Rationale

### Why Multi-Database

1. **True data isolation** — Each tenant's data is completely separate
2. **Simpler queries** — No `tenant_id` filtering needed
3. **Per-tenant backups** — Can backup/restore individual tenants
4. **Performance** — No risk of one tenant's queries affecting others
5. **Easier data export** — Can dump entire tenant DB for them
6. **Partner preference** — Team agreed on this approach

### Package Choice: stancl/tenancy

```bash
composer require stancl/tenancy
```

| Feature | stancl/tenancy |
|---------|----------------|
| **Subdomain routing** | ✅ Built-in |
| **Database per tenant** | ✅ Automatic |
| **Queue tenant context** | ✅ Supported |
| **Cache separation** | ✅ Automatic |
| **Central vs tenant tables** | ✅ Configurable |
| **Migrations** | ✅ Separate per tenant |
| **Documentation** | ✅ Excellent |

## Implementation

### Installation

```bash
composer require stancl/tenancy
php artisan tenancy:install
php artisan migrate
```

### Configuration

```php
// config/tenancy.php
return [
    'tenant_model' => \App\Models\Tenant::class,
    
    'central_domains' => [
        '127.0.0.1',
        'localhost',
        'flightdata.com',
        'www.flightdata.com',
        'admin.flightdata.com',  // Super admin panel
    ],
    
    'database' => [
        'prefix' => 'flightdata_',
        'suffix' => '',
    ],
];
```

### Tenant Model

```php
// app/Models/Tenant.php
namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;
    
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'plan_id',
            'trial_ends_at',
            'data',  // JSON column for settings
        ];
    }
}
```

### Route Configuration

```php
// routes/tenant.php (auto-loaded for tenant context)
Route::middleware(['web'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('flights', FlightController::class);
    Route::resource('aircraft', AircraftController::class);
    // ... tenant routes
});

// routes/web.php (central/marketing routes)
Route::get('/', [MarketingController::class, 'home']);
Route::get('/pricing', [MarketingController::class, 'pricing']);
```

### Creating a Tenant

```php
// Creating a new tenant automatically creates their database
$tenant = Tenant::create([
    'id' => 'acme-aviation',  // Used as subdomain
    'name' => 'Acme Aviation',
    'plan_id' => $plan->id,
]);

$tenant->domains()->create(['domain' => 'acme-aviation.flightdata.com']);

// Run tenant migrations
Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id]]);

// Optionally seed
Artisan::call('tenants:seed', ['--tenants' => [$tenant->id]]);
```

### Migrations

```bash
# Central database migrations (tenants, plans, domains, etc.)
database/migrations/

# Tenant database migrations (users, flights, aircraft, etc.)
database/migrations/tenant/
```

## Demo Tenant

The demo tenant (`demo.flightdata.com`) is a special tenant that resets periodically.

### Demo Setup

```php
// Create demo tenant (one-time)
$demo = Tenant::create([
    'id' => 'demo',
    'name' => 'Demo Company',
]);
$demo->domains()->create(['domain' => 'demo.flightdata.com']);
```

### Demo Reset (Scheduled)

```php
// app/Console/Kernel.php
$schedule->command('demo:reset')->hourly();

// app/Console/Commands/ResetDemoTenant.php
class ResetDemoTenant extends Command
{
    protected $signature = 'demo:reset';
    
    public function handle()
    {
        $demo = Tenant::find('demo');
        
        tenancy()->initialize($demo);
        
        // Wipe and reseed
        Artisan::call('migrate:fresh', [
            '--database' => 'tenant',
            '--seed' => true,
            '--seeder' => 'DemoSeeder',
        ]);
        
        $this->info('Demo tenant reset successfully.');
    }
}
```

### Demo Seeder

```php
// database/seeders/DemoSeeder.php
class DemoSeeder extends Seeder
{
    public function run()
    {
        // Create demo admin user
        $admin = User::create([
            'name' => 'Demo Admin',
            'email' => 'admin@demo.flightdata.com',
            'password' => Hash::make('demo123'),
        ]);
        
        // Sample aircraft
        $aircraftType = AircraftType::create([
            'name' => 'Citation XLS+',
            'manufacturer' => 'Cessna',
            'max_pax' => 8,
        ]);
        
        $aircraft = Aircraft::create([
            'registration' => 'N12345',
            'aircraft_type_id' => $aircraftType->id,
            'status' => 'active',
        ]);
        
        // Sample flights, clients, etc.
        // ... realistic demo data
    }
}
```

### Demo Login Page

```blade
{{-- Show demo credentials on login page --}}
@if(tenant()->id === 'demo')
<div class="demo-notice">
    <strong>Demo Mode</strong> — Resets every hour
    <p>Email: admin@demo.flightdata.com</p>
    <p>Password: demo123</p>
</div>
@endif
```

## Consequences

### Positive
- ✅ Complete data isolation between tenants
- ✅ Simplified queries (no tenant_id everywhere)
- ✅ Per-tenant backup/restore capability
- ✅ Easy data export for tenant offboarding
- ✅ Package handles complexity (routing, migrations, etc.)

### Negative
- ⚠️ More databases to manage
- ⚠️ Migrations must run per-tenant
- ⚠️ Cross-tenant queries require extra work
- ⚠️ Slightly more complex local development

### Mitigations
- Use `tenants:migrate` command to batch migrations
- Central database for any cross-tenant analytics
- Forge handles database creation automatically

---

*Last updated: February 2026*

## Review Triggers
Revisit this decision when:
- A tenant contractually requires database isolation
- Database size exceeds 50GB
- Performance issues arise from shared resources
- Tenant count exceeds 100

---

*Last updated: February 2026*

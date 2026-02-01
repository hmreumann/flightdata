# ADR 003: User Roles and Permissions

## Status
**Decided** — Single User Model with Spatie Laravel-Permission

## Context
The system has different user types:
- **Internal:** Pilots, crew, operations staff, admins
- **External:** Passengers, client admins (companies booking flights)

Should these be separate models or unified?

## Decision
**Single `users` table** with a `type` field and role-based permissions via **Spatie Laravel-Permission**.

## Rationale

### Why Single User Model?

| Approach | Pros | Cons |
|----------|------|------|
| **Separate Models** | Clear separation | Duplicate auth logic, complex relations |
| **Single + Roles** | One auth system, flexible | Must filter carefully |

Single model wins because:
- One authentication flow
- Simple relationships (all assignments reference `users.id`)
- Easy to change user type (external → internal hire)
- Laravel's `can()` and `@can` work seamlessly

### User Type vs Role

**Type** = High-level category (internal/external)
**Role** = Specific permissions within that type

```
User Type: internal
├── Role: admin
├── Role: scheduler
├── Role: pilot
├── Role: cabin-crew
└── Role: operations

User Type: external
├── Role: client-admin
└── Role: passenger
```

## Implementation

### Install Spatie Permission

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

### User Model

```php
// app/Models/User.php
namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasRoles, BelongsToTenant;
    
    protected $fillable = [
        'tenant_id',
        'type',
        'name',
        'email',
        'password',
        'phone',
        'employee_id',
        'profile_data',
    ];
    
    protected $casts = [
        'type' => UserType::class,
        'profile_data' => 'array',
        'email_verified_at' => 'datetime',
    ];
    
    // Type helpers
    public function isInternal(): bool
    {
        return $this->type === UserType::Internal;
    }
    
    public function isExternal(): bool
    {
        return $this->type === UserType::External;
    }
    
    // Role helpers
    public function isPilot(): bool
    {
        return $this->hasRole('pilot');
    }
    
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }
    
    // Relations
    public function crewProfile()
    {
        return $this->hasOne(CrewProfile::class);
    }
}
```

### UserType Enum

```php
// app/Enums/UserType.php
namespace App\Enums;

enum UserType: string
{
    case Internal = 'internal';
    case External = 'external';
}
```

### Roles and Permissions Seeder

```php
// database/seeders/RolesPermissionsSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        // Create permissions
        $permissions = [
            // Users
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            
            // Aircraft
            'aircraft.view',
            'aircraft.create',
            'aircraft.edit',
            'aircraft.delete',
            
            // Flights
            'flights.view',
            'flights.create',
            'flights.edit',
            'flights.delete',
            'flights.assign-crew',
            'flights.assign-pax',
            
            // Flight Logs
            'flight-logs.view',
            'flight-logs.create',
            'flight-logs.sign',
            
            // Weight & Balance
            'wb.view',
            'wb.calculate',
            
            // Documents
            'documents.view',
            'documents.upload',
            'documents.delete',
            
            // Reports
            'reports.view',
            'reports.export',
            
            // Settings
            'settings.view',
            'settings.edit',
        ];
        
        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }
        
        // Create roles and assign permissions
        
        // Admin - full access
        Role::create(['name' => 'admin'])
            ->givePermissionTo(Permission::all());
        
        // Scheduler - flight planning
        Role::create(['name' => 'scheduler'])
            ->givePermissionTo([
                'flights.view', 'flights.create', 'flights.edit',
                'flights.assign-crew', 'flights.assign-pax',
                'aircraft.view',
                'users.view',
                'documents.view',
                'reports.view', 'reports.export',
            ]);
        
        // Pilot - flight operations
        Role::create(['name' => 'pilot'])
            ->givePermissionTo([
                'flights.view',
                'flight-logs.view', 'flight-logs.create', 'flight-logs.sign',
                'wb.view', 'wb.calculate',
                'aircraft.view',
                'documents.view',
            ]);
        
        // Cabin Crew - limited flight access
        Role::create(['name' => 'cabin-crew'])
            ->givePermissionTo([
                'flights.view',
                'documents.view',
            ]);
        
        // Operations - ground ops
        Role::create(['name' => 'operations'])
            ->givePermissionTo([
                'flights.view',
                'aircraft.view', 'aircraft.edit',
                'documents.view', 'documents.upload',
            ]);
        
        // Client Admin - external, manages their passengers
        Role::create(['name' => 'client-admin'])
            ->givePermissionTo([
                'flights.view', // Only their flights
            ]);
        
        // Passenger - external, minimal access
        Role::create(['name' => 'passenger'])
            ->givePermissionTo([
                'flights.view', // Only their flights
            ]);
    }
}
```

### Usage Examples

```php
// Controller - check permission
public function store(Request $request)
{
    $this->authorize('flights.create');
    // or
    if (!auth()->user()->can('flights.create')) {
        abort(403);
    }
}

// Policy
class FlightPolicy
{
    public function view(User $user, Flight $flight): bool
    {
        // Internal users with permission can view any tenant flight
        if ($user->isInternal() && $user->can('flights.view')) {
            return true;
        }
        
        // External users can only view flights they're on
        if ($user->isExternal()) {
            return $flight->passengers()->where('user_id', $user->id)->exists()
                || $flight->client_id === $user->client_id;
        }
        
        return false;
    }
}

// Blade
@can('flights.create')
    <button>New Flight</button>
@endcan

@role('pilot')
    <a href="/cockpit">Cockpit View</a>
@endrole

// Livewire
public function mount()
{
    abort_unless(auth()->user()->can('flights.view'), 403);
}
```

### Tenant-Specific Roles

Spatie supports teams, but for simplicity we'll scope by tenant using a custom guard or by prefixing:

```php
// Option 1: Use team feature
// config/permission.php
'teams' => true,

// Usage
$user->assignRole('pilot', $tenant);

// Option 2: Simple approach - roles are global, policies enforce tenant
// Policies always check tenant_id match
```

**Recommended:** Option 2 (simpler) — Roles are global definitions, tenant isolation via policies.

## Consequences

### Positive
- ✅ Single authentication flow
- ✅ Flexible permission system
- ✅ Easy to add new roles
- ✅ Laravel's built-in `can()` and `@can` work seamlessly
- ✅ Spatie package is battle-tested

### Negative
- ⚠️ Must combine type + role checks carefully
- ⚠️ External users need scoped queries (only their flights)

## Common Patterns

### Checking Type + Permission

```php
// Wrong - just checking permission
if ($user->can('flights.view')) { ... }

// Right - type-aware for external users
if ($user->isInternal() && $user->can('flights.view')) {
    // Can see all flights
} elseif ($user->isExternal() && $user->can('flights.view')) {
    // Can only see their flights (enforce in query)
}
```

### Middleware Groups

```php
// routes/web.php
Route::middleware(['auth', 'tenant'])->group(function () {
    
    // Internal only routes
    Route::middleware(['internal'])->group(function () {
        Route::resource('aircraft', AircraftController::class);
        Route::resource('users', UserController::class);
    });
    
    // Both internal and external
    Route::resource('flights', FlightController::class);
});
```

---

*Last updated: February 2026*

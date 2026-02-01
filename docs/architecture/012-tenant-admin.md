# ADR 012: Tenant Admin & Configuration

## Status
**Decided** — Tenant admins can customize roles, permissions, and settings

## Context
Each tenant (aviation company) needs to:
- Configure their instance settings
- Customize roles and permissions
- Manage their users
- View their subscription

## Decision
Tenant admins have a **Settings/Admin section** within their tenant application to manage configuration.

## Tenant Admin Features

```
{tenant}.flightdata.com/settings/
├── /general          → Company info, timezone, units
├── /users            → User management
├── /roles            → Custom roles & permissions
├── /billing          → Subscription, invoices
├── /integrations     → API keys (future)
└── /data             → Export, data mode
```

## Feature Breakdown

### 1. General Settings

```php
// Settings the tenant can configure
$tenantSettings = [
    // Company
    'company_name' => 'Acme Aviation',
    'logo_path' => 'tenants/1/logo.png',
    'timezone' => 'America/New_York',
    
    // Units
    'weight_unit' => 'kg',      // kg, lbs
    'fuel_unit' => 'liters',    // liters, gallons, kg
    'distance_unit' => 'nm',    // nm, km
    
    // Features
    'require_wb_before_departure' => true,
    'allow_flight_log_edits' => false,  // After signing
    
    // Data mode (from ADR 005)
    'data_mode' => 'full',      // full, external
];
```

```php
// Livewire component
class GeneralSettings extends Component
{
    public Tenant $tenant;
    
    public string $company_name;
    public string $timezone;
    public string $weight_unit;
    public string $fuel_unit;
    
    public function mount()
    {
        $this->tenant = tenant();
        $this->company_name = $this->tenant->name;
        $this->timezone = $this->tenant->settings['timezone'] ?? 'UTC';
        $this->weight_unit = $this->tenant->settings['weight_unit'] ?? 'kg';
        $this->fuel_unit = $this->tenant->settings['fuel_unit'] ?? 'liters';
    }
    
    public function save()
    {
        $this->tenant->update([
            'name' => $this->company_name,
            'settings' => array_merge($this->tenant->settings, [
                'timezone' => $this->timezone,
                'weight_unit' => $this->weight_unit,
                'fuel_unit' => $this->fuel_unit,
            ]),
        ]);
        
        $this->dispatch('saved');
    }
}
```

### 2. User Management

Tenant admins manage their own users:

```php
class UserManagement extends Component
{
    public function render()
    {
        return view('livewire.settings.users', [
            'users' => User::where('tenant_id', tenant()->id)
                ->with('roles')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }
    
    public function createUser(array $data)
    {
        $this->authorize('users.create');
        
        $user = User::create([
            'tenant_id' => tenant()->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'type' => $data['type'],
            'password' => Hash::make(Str::random(16)),
        ]);
        
        $user->assignRole($data['role']);
        $user->notify(new WelcomeNotification());
        
        return $user;
    }
    
    public function deactivateUser(User $user)
    {
        $this->authorize('users.delete');
        
        // Soft deactivate, don't delete (for audit trail)
        $user->update(['is_active' => false]);
    }
}
```

### 3. Role & Permission Customization

Tenants can create custom roles and adjust permissions:

```php
class RoleManagement extends Component
{
    // System roles (cannot be deleted)
    protected array $systemRoles = ['admin', 'pilot', 'passenger'];
    
    public function render()
    {
        return view('livewire.settings.roles', [
            'roles' => Role::where('tenant_id', tenant()->id)
                ->orWhereNull('tenant_id')  // System roles
                ->with('permissions')
                ->get(),
            'permissions' => Permission::all()->groupBy(function ($perm) {
                return explode('.', $perm->name)[0]; // Group by module
            }),
        ]);
    }
    
    public function createRole(string $name, array $permissions)
    {
        $this->authorize('settings.edit');
        
        // Create tenant-specific role
        $role = Role::create([
            'name' => $name,
            'tenant_id' => tenant()->id,  // Tenant-scoped
            'guard_name' => 'web',
        ]);
        
        $role->syncPermissions($permissions);
        
        return $role;
    }
    
    public function updateRolePermissions(Role $role, array $permissions)
    {
        // Can't modify system roles
        if (in_array($role->name, $this->systemRoles) && $role->tenant_id === null) {
            // Can only override for their tenant
            $tenantRole = $role->replicate();
            $tenantRole->tenant_id = tenant()->id;
            $tenantRole->save();
            $tenantRole->syncPermissions($permissions);
            return;
        }
        
        $role->syncPermissions($permissions);
    }
}
```

### Permission Groups UI

```blade
{{-- resources/views/livewire/settings/roles.blade.php --}}
<div class="grid grid-cols-2 gap-6">
    {{-- Role List --}}
    <div>
        <h3>Roles</h3>
        @foreach($roles as $role)
            <div class="p-4 border rounded mb-2 cursor-pointer"
                 wire:click="selectRole({{ $role->id }})">
                <span class="font-semibold">{{ $role->name }}</span>
                @if($role->tenant_id === null)
                    <span class="text-xs bg-gray-200 px-2 py-1 rounded">System</span>
                @else
                    <span class="text-xs bg-blue-200 px-2 py-1 rounded">Custom</span>
                @endif
            </div>
        @endforeach
        
        <button wire:click="$toggle('showCreateRole')" class="btn-secondary">
            + Create Custom Role
        </button>
    </div>
    
    {{-- Permissions Editor --}}
    <div>
        <h3>Permissions for: {{ $selectedRole->name }}</h3>
        
        @foreach($permissions as $module => $modulePermissions)
            <div class="mb-4">
                <h4 class="font-semibold capitalize">{{ $module }}</h4>
                @foreach($modulePermissions as $permission)
                    <label class="flex items-center gap-2">
                        <input type="checkbox" 
                               wire:model="selectedPermissions"
                               value="{{ $permission->name }}"
                               @if($selectedRole->tenant_id === null && in_array($selectedRole->name, $systemRoles))
                                   disabled
                               @endif
                        >
                        {{ $permission->name }}
                    </label>
                @endforeach
            </div>
        @endforeach
        
        <button wire:click="savePermissions" class="btn-primary">
            Save Permissions
        </button>
    </div>
</div>
```

### 4. Billing & Subscription

Tenant admins can view (but not modify) their subscription:

```php
class BillingSettings extends Component
{
    public function render()
    {
        $tenant = tenant();
        $subscription = $tenant->subscription;
        
        return view('livewire.settings.billing', [
            'subscription' => $subscription,
            'aircraft_count' => Aircraft::where('status', 'active')->count(),
            'current_mrr' => $subscription->calculateMRR(),
            'invoices' => $tenant->invoices()->latest()->take(12)->get(),
            'next_invoice_date' => $subscription->next_billing_date,
        ]);
    }
}
```

### Billing View

```blade
<div class="space-y-6">
    {{-- Current Plan --}}
    <div class="bg-white p-6 rounded-lg shadow">
        <h3>Current Plan: {{ $subscription->plan->name }}</h3>
        <p class="text-3xl font-bold">
            ${{ $subscription->calculateMRR() }}/month
        </p>
        <p class="text-gray-600">
            {{ $aircraft_count }} aircraft × ${{ $subscription->plan->price_per_aircraft }}
        </p>
        <p>Next billing: {{ $next_invoice_date->format('M j, Y') }}</p>
        
        <a href="mailto:billing@flightdata.com" class="text-blue-600">
            Contact us to change plan
        </a>
    </div>
    
    {{-- Invoice History --}}
    <div class="bg-white p-6 rounded-lg shadow">
        <h3>Invoice History</h3>
        <table>
            @foreach($invoices as $invoice)
                <tr>
                    <td>{{ $invoice->date->format('M Y') }}</td>
                    <td>${{ $invoice->amount }}</td>
                    <td>
                        <span class="badge {{ $invoice->status }}">
                            {{ $invoice->status }}
                        </span>
                    </td>
                    <td>
                        <a href="{{ $invoice->pdf_url }}">Download</a>
                    </td>
                </tr>
            @endforeach
        </table>
    </div>
</div>
```

### 5. Custom User Types

Beyond default roles, tenants can create custom user types:

```php
// Example: Tenant wants "Maintenance" role
// They create it in Role Management with specific permissions:
[
    'aircraft.view',
    'aircraft.edit',  // Update maintenance status
    'documents.view',
    'documents.upload', // Upload maintenance docs
]

// Example: Tenant wants "Client Coordinator" role
// Internal user who manages specific clients
[
    'clients.view',
    'clients.edit',
    'flights.view',
    'flights.create',
    'reports.view',
]
```

## Authorization in Tenant Context

```php
// Policy respects tenant-specific permissions
class FlightPolicy
{
    public function create(User $user): bool
    {
        // Check tenant-scoped permission
        return $user->hasPermissionTo('flights.create');
    }
    
    public function sign(User $user, Flight $flight): bool
    {
        // Only PIC can sign, and must have permission
        return $user->hasPermissionTo('flight-logs.sign')
            && $flight->isPIC($user);
    }
}
```

## Settings Routes

```php
// Within tenant routes
Route::prefix('settings')->middleware(['auth', 'can:settings.view'])->group(function () {
    Route::get('/', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('/general', GeneralSettings::class)->name('settings.general');
    Route::get('/users', UserManagement::class)->name('settings.users');
    Route::get('/roles', RoleManagement::class)->name('settings.roles');
    Route::get('/billing', BillingSettings::class)->name('settings.billing');
});
```

## Default vs Custom Roles

```
Default Roles (System):
├── admin         → Full tenant access
├── scheduler     → Flight planning
├── pilot         → Flight operations
├── cabin-crew    → Limited flight access
├── operations    → Ground operations
├── client-admin  → External, manages company pax
└── passenger     → External, minimal access

Custom Roles (Tenant-created):
├── maintenance   → Aircraft maintenance staff
├── accountant    → Billing & reports only
├── trainee-pilot → Pilot with limited permissions
└── [any custom]  → Tenant defines
```

## Consequences

### Positive
- ✅ Tenants can adapt to their workflow
- ✅ Self-service reduces support burden
- ✅ Flexibility attracts diverse customers

### Negative
- ⚠️ Complex permission system to maintain
- ⚠️ Support complexity when debugging permissions
- ⚠️ Must document clearly for tenant admins

---

*Last updated: February 2026*

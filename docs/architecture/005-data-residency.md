# ADR 005: Data Residency Strategy

## Status
**Decided** — US region initially, EU expansion planned

## Context
FlightData will be sold worldwide. Different countries have data localization laws that may require data to be stored within their borders.

## Decision
1. **Start with US region** (single database)
2. **Design for multi-region** from day one (tenant `region` field)
3. **Add EU region** when demand justifies
4. **Avoid China/Russia** initially (require local infrastructure)

## Research Summary

### Countries by Feasibility

| Region | Can Serve from US/EU? | Notes |
|--------|----------------------|-------|
| 🇺🇸 USA | ✅ Yes | Primary market |
| 🇪🇺 EU | ✅ Yes | GDPR compliance required |
| 🇬🇧 UK | ✅ Yes | UK GDPR |
| 🇦🇺 Australia | ✅ Yes | Standard compliance |
| 🇨🇦 Canada | ✅ Yes | PIPEDA compliance |
| 🇧🇷 Brazil | ✅ Yes | LGPD (similar to GDPR) |
| 🇮🇳 India | ✅ Yes (for now) | Monitor regulatory changes |
| 🇦🇪 UAE | ✅ Generally yes | Contractual safeguards |
| 🇨🇳 **China** | ❌ **No** | Requires local servers + legal entity |
| 🇷🇺 **Russia** | ❌ **No** | Requires local servers |

### What Laws Actually Require

| Law | Requires Local Storage? | Actual Requirement |
|-----|------------------------|-------------------|
| GDPR (EU) | No | Adequate protection, DPA, SCCs |
| PIPL (China) | **Yes** | Must store in China |
| Russia 242-FZ | **Yes** | Primary DB in Russia |
| LGPD (Brazil) | No | GDPR-like safeguards |
| DPDPA (India) | No | Blacklist approach |

## Implementation

### Database Design

```php
// tenants table includes region
Schema::create('tenants', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->string('region')->default('us'); // 'us', 'eu', 'ap'
    // ...
});
```

### Multi-Region Architecture (Future)

```
Phase 1 (Now):
┌─────────────────────────────┐
│     US Region (Primary)      │
│  ┌─────────┐  ┌──────────┐  │
│  │ Laravel │  │PostgreSQL│  │
│  │  App    │  │   DB     │  │
│  └─────────┘  └──────────┘  │
│     All tenants              │
└─────────────────────────────┘

Phase 2+ (EU Expansion):
┌─────────────────────────────┐    ┌─────────────────────────────┐
│     US Region                │    │     EU Region               │
│  ┌─────────┐  ┌──────────┐  │    │  ┌─────────┐  ┌──────────┐  │
│  │ Laravel │  │PostgreSQL│  │    │  │ Laravel │  │PostgreSQL│  │
│  │  App    │  │   DB     │  │    │  │  App    │  │   DB     │  │
│  └─────────┘  └──────────┘  │    │  └─────────┘  └──────────┘  │
│  US tenants only             │    │  EU tenants only            │
└─────────────────────────────┘    └─────────────────────────────┘
```

### Database Connection Switching (Future)

```php
// config/database.php
'connections' => [
    'us' => [
        'driver' => 'pgsql',
        'host' => env('DB_HOST_US', '127.0.0.1'),
        'database' => env('DB_DATABASE_US', 'flightdata'),
        // ...
    ],
    'eu' => [
        'driver' => 'pgsql',
        'host' => env('DB_HOST_EU'),
        'database' => env('DB_DATABASE_EU', 'flightdata'),
        // ...
    ],
],

// Middleware to switch connection
class SetTenantDatabase
{
    public function handle($request, Closure $next)
    {
        $tenant = tenant();
        
        if ($tenant) {
            $connection = $tenant->region; // 'us' or 'eu'
            Config::set('database.default', $connection);
            DB::purge();
        }
        
        return $next($request);
    }
}
```

## Personal Data Strategy

### Flexible Data Mode

Tenants can choose how personal data is stored:

```php
// Tenant settings
{
    "data_mode": "full"     // Store all personal data (default)
    // OR
    "data_mode": "external" // Store only IDs, tenant maintains mapping
}
```

### Full Mode (Default)
All personal data stored in FlightData:
- Crew names, licenses, contact info
- Passenger names, contact info
- Complete records

**Best for:** Small operators without their own systems

### External Mode
Only operational data in FlightData:
- Crew identified by `employee_id`
- Passengers identified by `passenger_id`
- Tenant maintains ID-to-name mapping in their systems

**Best for:** Larger operators with existing HR/CRM systems, or those requiring data minimization

### Implementation

```php
// User model adapts based on tenant data_mode
class User extends Authenticatable
{
    public function getDisplayNameAttribute(): string
    {
        if (tenant()?->data_mode === 'external' && $this->employee_id) {
            return $this->employee_id; // Just show ID
        }
        return $this->name;
    }
}

// Views check data mode
@if(tenant()->data_mode === 'full')
    <p>{{ $user->name }}</p>
    <p>{{ $user->phone }}</p>
@else
    <p>ID: {{ $user->employee_id }}</p>
@endif
```

## GDPR Compliance (EU Tenants)

Even serving from US, EU tenants need:

### 1. Data Processing Agreement (DPA)
Template agreement for EU customers covering:
- What data we process
- How it's protected
- Sub-processors used
- Data subject rights

### 2. Standard Contractual Clauses (SCCs)
EU-approved contract clauses for international transfers.

### 3. Technical Measures
- Encryption at rest and in transit
- Access logging
- Data export capability
- Data deletion capability

### 4. User Rights Implementation

```php
// GDPR data export
class GdprController extends Controller
{
    public function export(User $user)
    {
        $this->authorize('export', $user);
        
        $data = [
            'user' => $user->toArray(),
            'flights' => $user->flights()->get(),
            'documents' => $user->documents()->get(),
            'audit_logs' => AuditLog::where('user_id', $user->id)->get(),
        ];
        
        return response()->json($data)
            ->header('Content-Disposition', 'attachment; filename="user_data.json"');
    }
    
    public function delete(User $user)
    {
        $this->authorize('delete', $user);
        
        // Soft delete + anonymize
        $user->update([
            'name' => 'Deleted User',
            'email' => 'deleted_' . $user->id . '@deleted.local',
            'phone' => null,
            'profile_data' => null,
        ]);
        $user->delete();
        
        return redirect()->back()->with('success', 'User data deleted');
    }
}
```

## Consequences

### Positive
- ✅ Simpler initial deployment
- ✅ Lower infrastructure cost
- ✅ Covers 80%+ of target market
- ✅ Architecture ready for expansion
- ✅ Flexible personal data handling

### Negative
- ⚠️ Cannot serve China/Russia without significant investment
- ⚠️ EU customers may prefer EU hosting (sales concern)
- ⚠️ Must maintain GDPR compliance documentation

## Roadmap

| Phase | Region | Trigger |
|-------|--------|---------|
| 1 | US only | MVP launch |
| 2 | + EU | 5+ EU tenants or enterprise deal |
| 3 | + Asia-Pacific | Significant APAC demand |
| Future | China | Major contract with local partnership |

---

*Last updated: February 2026*

# ADR 001: Multi-Tenancy Approach

## Status
**Decided** — Single Database with `tenant_id`

## Context
FlightData will serve multiple aviation companies (tenants). We need to decide between:

1. **Single Database with `tenant_id`** — All tenants share tables, filtered by tenant identifier
2. **Multi-Database** — Each tenant gets their own database

**Target scale:** ~10 tenants initially, potentially 50+ with growth.

## Decision
Use **single database with `tenant_id` column** on all tenant-scoped tables.

## Rationale

### Comparison

| Aspect | Single DB (tenant_id) | Multi-Database |
|--------|----------------------|----------------|
| **Setup Complexity** | Simple | Complex |
| **New Tenant Onboarding** | Instant (create record) | Requires DB provisioning |
| **Maintenance** | Single migration set | Run migrations per DB |
| **Cross-tenant Reporting** | Easy (if needed) | Complex |
| **Cost** | Lower (one DB instance) | Higher (multiple DBs) |
| **Data Isolation** | Application-level | Database-level |
| **Scaling** | Good for 10-100 tenants | Better for 100+ |

### Why Single DB for FlightData

1. **Target scale** — 10-50 tenants doesn't justify multi-DB complexity
2. **Faster MVP** — Ship sooner without DB provisioning infrastructure
3. **Simpler operations** — One backup, one migration, one connection
4. **Compliance acceptable** — Aviation regulations don't require DB-level isolation (see ADR 007)

### Security Measures

Data isolation is enforced at application level:

1. **Global Scope** — All queries automatically filtered by `tenant_id`
2. **Middleware** — Set tenant context on every request
3. **Model Boot** — Auto-assign `tenant_id` on creation
4. **Query Logging** — Monitor for cross-tenant access attempts

## Implementation

### BelongsToTenant Trait

```php
// app/Traits/BelongsToTenant.php
namespace App\Traits;

use App\Models\Tenant;
use App\Scopes\TenantScope;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        // Auto-filter all queries by tenant
        static::addGlobalScope(new TenantScope);
        
        // Auto-assign tenant_id on creation
        static::creating(function ($model) {
            if (auth()->check() && !$model->tenant_id) {
                $model->tenant_id = auth()->user()->tenant_id;
            }
        });
    }
    
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
```

### TenantScope

```php
// app/Scopes/TenantScope.php
namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->has('current_tenant')) {
            $builder->where($model->getTable() . '.tenant_id', app('current_tenant')->id);
        }
    }
}
```

### Middleware

```php
// app/Http/Middleware/ResolveTenant.php
namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

class ResolveTenant
{
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost();
        $subdomain = explode('.', $host)[0];
        
        // Skip for main domain
        if ($subdomain === 'www' || $subdomain === 'flightdata') {
            return $next($request);
        }
        
        $tenant = Tenant::where('slug', $subdomain)->firstOrFail();
        
        app()->instance('current_tenant', $tenant);
        
        return $next($request);
    }
}
```

## Consequences

### Positive
- ✅ Faster time to market
- ✅ Simpler deployment and maintenance
- ✅ Lower infrastructure costs
- ✅ Instant tenant onboarding
- ✅ Easy cross-tenant analytics (internal use)

### Negative
- ⚠️ Must be vigilant about tenant data isolation
- ⚠️ Large tenants might need migration later
- ⚠️ Shared database limits affect all tenants

### Migration Path
If a tenant requires complete isolation (contractual/regulatory), we can:
1. Extract their data to a dedicated database
2. Configure their subdomain to use different connection
3. Keep architecture flexible for hybrid approach

## Review Triggers
Revisit this decision when:
- A tenant contractually requires database isolation
- Database size exceeds 50GB
- Performance issues arise from shared resources
- Tenant count exceeds 100

---

*Last updated: February 2026*

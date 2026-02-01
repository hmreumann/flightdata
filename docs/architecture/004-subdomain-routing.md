# ADR 004: Subdomain-Based Tenant Routing

## Status
**Decided** — Subdomain routing (`tenant.flightdata.com`)

## Context
With multiple tenant companies, how do users access their tenant?

Options:
1. `flightdata.com/acme-aviation/dashboard` (path-based)
2. `acme-aviation.flightdata.com/dashboard` (subdomain) ✓
3. `flightdata.com?tenant=acme-aviation` (query param)

## Decision
Use **subdomain-based routing**: `{tenant}.flightdata.com`

## Rationale

| Aspect | Path-based | Subdomain | Query param |
|--------|-----------|-----------|-------------|
| **URL Cleanliness** | Cluttered | Clean | Ugly |
| **Isolation Feel** | Shared app | Branded experience | Shared app |
| **SSL** | One cert | Wildcard cert | One cert |
| **Routing** | Prefix groups | Domain groups | Manual parsing |
| **White-label** | Hard | Easy (custom domains) | Hard |
| **Industry Standard** | Less common | Very common for SaaS | Rare |

## How It Works

**All subdomains point to the same Laravel application.**

```
DNS Configuration:
*.flightdata.com  →  Server IP (A record)
flightdata.com    →  Server IP (A record)

All these hit the same Laravel app:
- acme-aviation.flightdata.com
- skyways.flightdata.com
- demo.flightdata.com
```

## Implementation

### Domain Structure

```
flightdata.com              → Marketing site (public)
app.flightdata.com          → Login portal (choose tenant)
{tenant}.flightdata.com     → Tenant application
api.flightdata.com          → API (tenant from auth token)
```

### Route Configuration

```php
// routes/web.php

// Marketing site (no tenant)
Route::domain('flightdata.com')->group(function () {
    Route::get('/', [MarketingController::class, 'home']);
    Route::get('/pricing', [MarketingController::class, 'pricing']);
    Route::get('/contact', [MarketingController::class, 'contact']);
});

// Tenant application
Route::domain('{tenant}.flightdata.com')
    ->middleware(['web', 'tenant'])
    ->group(function () {
        
        // Auth routes (tenant context needed for login)
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        
        // Protected routes
        Route::middleware(['auth'])->group(function () {
            Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
            Route::resource('flights', FlightController::class);
            Route::resource('aircraft', AircraftController::class);
            // ... more routes
        });
    });
```

### Tenant Resolution Middleware

```php
// app/Http/Middleware/ResolveTenant.php
namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->route('tenant');
        
        if (!$tenant) {
            abort(404, 'Tenant not found');
        }
        
        $tenantModel = Tenant::where('slug', $tenant)->first();
        
        if (!$tenantModel) {
            abort(404, 'Tenant not found');
        }
        
        // Store tenant in container
        app()->instance('current_tenant', $tenantModel);
        
        // Share with views
        view()->share('currentTenant', $tenantModel);
        
        return $next($request);
    }
}

// Register in Kernel.php
protected $middlewareAliases = [
    'tenant' => \App\Http\Middleware\ResolveTenant::class,
];
```

### Tenant Helper

```php
// app/helpers.php (autoloaded via composer.json)

if (!function_exists('tenant')) {
    function tenant(): ?\App\Models\Tenant
    {
        return app()->has('current_tenant') ? app('current_tenant') : null;
    }
}

if (!function_exists('tenant_route')) {
    function tenant_route(string $name, array $parameters = []): string
    {
        $tenant = tenant();
        return route($name, array_merge(['tenant' => $tenant?->slug], $parameters));
    }
}
```

### URL Generation

```php
// In controllers/views, generate tenant-aware URLs
$url = route('flights.show', [
    'tenant' => tenant()->slug,
    'flight' => $flight->id
]);

// Or use helper
$url = tenant_route('flights.show', ['flight' => $flight->id]);

// In Blade
<a href="{{ tenant_route('flights.index') }}">Flights</a>
```

## Local Development

### Option 1: Edit /etc/hosts

```
# /etc/hosts
127.0.0.1  flightdata.test
127.0.0.1  acme.flightdata.test
127.0.0.1  demo.flightdata.test
```

### Option 2: Laravel Valet (Recommended)

```bash
cd flightdata
valet link

# Valet automatically handles *.flightdata.test
# Visit: http://acme.flightdata.test
```

### Option 3: Laravel Herd

Similar to Valet, Herd handles wildcard subdomains automatically.

### Environment Config

```env
# .env
APP_URL=http://flightdata.test

# For subdomain routing
SESSION_DOMAIN=.flightdata.test
SANCTUM_STATEFUL_DOMAINS=*.flightdata.test
```

## Production Setup

### DNS Configuration

```
# At your DNS provider (Cloudflare, Route53, etc.)
Type: A
Name: @
Value: YOUR_SERVER_IP

Type: A
Name: *
Value: YOUR_SERVER_IP

# Or use CNAME for flexibility
Type: CNAME
Name: *
Value: flightdata.com
```

### Nginx Configuration

```nginx
# /etc/nginx/sites-available/flightdata
server {
    listen 80;
    listen 443 ssl http2;
    
    server_name flightdata.com *.flightdata.com;
    
    root /var/www/flightdata/public;
    index index.php;
    
    # SSL (wildcard certificate)
    ssl_certificate /etc/letsencrypt/live/flightdata.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/flightdata.com/privkey.pem;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### Wildcard SSL with Let's Encrypt

```bash
# Using certbot with DNS challenge
sudo certbot certonly \
    --manual \
    --preferred-challenges dns \
    -d "flightdata.com" \
    -d "*.flightdata.com"

# You'll need to add a TXT record to verify domain ownership
# Laravel Forge handles this automatically
```

### Laravel Forge Setup

1. Create server on DigitalOcean
2. Add site: `flightdata.com`
3. Enable SSL → Let's Encrypt → Check "Wildcard"
4. Forge handles DNS challenge automatically

## API Subdomain

```php
// routes/api.php
Route::domain('api.flightdata.com')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        // Tenant resolved from authenticated user's tenant_id
        Route::apiResource('flights', Api\FlightController::class);
    });
});
```

For API, tenant comes from the authenticated user, not the subdomain:

```php
// app/Http/Middleware/SetTenantFromUser.php
public function handle(Request $request, Closure $next)
{
    if ($user = $request->user()) {
        $tenant = $user->tenant;
        app()->instance('current_tenant', $tenant);
    }
    
    return $next($request);
}
```

## Consequences

### Positive
- ✅ Professional, branded feel for each tenant
- ✅ Clean URL structure
- ✅ Easy white-labeling potential (custom domains later)
- ✅ Industry standard for SaaS
- ✅ Session isolation per subdomain

### Negative
- ⚠️ Wildcard SSL certificate required
- ⚠️ DNS configuration needed
- ⚠️ Local dev requires hosts file or Valet
- ⚠️ Slightly more complex routing setup

## Future: Custom Domains

Enterprise tenants may want their own domain:

```
flights.acme-aviation.com → acme-aviation.flightdata.com
```

Implementation:
1. Add `custom_domain` column to tenants table
2. Tenant adds CNAME pointing to our server
3. Generate SSL certificate for their domain
4. Middleware checks both subdomain and custom domain

---

*Last updated: February 2026*

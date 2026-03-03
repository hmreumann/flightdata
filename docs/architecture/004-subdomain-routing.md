# ADR 004: Subdomain-Based Tenant Routing

## Status
**Decided** — Subdomain routing via stancl/tenancy (`tenant.flightdata.com`)

## Context
With multiple tenant companies, how do users access their tenant?

Options:
1. `flightdata.com/acme-aviation/dashboard` (path-based)
2. `acme-aviation.flightdata.com/dashboard` (subdomain) ✓
3. `flightdata.com?tenant=acme-aviation` (query param)

## Decision
Use **subdomain-based routing**: `{tenant}.flightdata.com`

Handled automatically by **stancl/tenancy** package (see ADR 001).

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

**All subdomains point to the same Laravel application. stancl/tenancy handles tenant identification and database switching automatically.**

```
DNS Configuration:
*.flightdata.com  →  Server IP (A record)
flightdata.com    →  Server IP (A record)

All these hit the same Laravel app:
- acme-aviation.flightdata.com  → Tenant DB: flightdata_acme_aviation
- skyways.flightdata.com        → Tenant DB: flightdata_skyways
- demo.flightdata.com           → Tenant DB: flightdata_demo
```

## Implementation

### Domain Structure

```
flightdata.com              → Marketing site (central DB)
admin.flightdata.com        → Super admin panel (central DB)
demo.flightdata.com         → Demo tenant (resets hourly)
{tenant}.flightdata.com     → Tenant application (tenant DB)
api.flightdata.com          → API (tenant from auth token)
```

### Central Domains Configuration

```php
// config/tenancy.php
'central_domains' => [
    '127.0.0.1',
    'localhost',
    'flightdata.test',           // Local dev
    'flightdata.com',            // Marketing
    'www.flightdata.com',        // Marketing
    'admin.flightdata.com',      // Super admin
    'api.flightdata.com',        // API (tenant from token)
],
```

### Route Files

```php
// routes/web.php — Central routes (marketing, super admin)
Route::get('/', [MarketingController::class, 'home']);
Route::get('/pricing', [MarketingController::class, 'pricing']);
Route::get('/contact', [MarketingController::class, 'contact']);

// Admin subdomain
Route::domain('admin.' . config('app.domain'))->group(function () {
    Route::middleware(['auth', 'super-admin'])->group(function () {
        Route::get('/', [SuperAdminController::class, 'dashboard']);
        Route::resource('tenants', TenantController::class);
    });
});

// routes/tenant.php — Tenant routes (auto-loaded by stancl/tenancy)
Route::middleware(['web'])->group(function () {
    // Auth
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
    // Protected
    Route::middleware(['auth'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::resource('flights', FlightController::class);
        Route::resource('aircraft', AircraftController::class);
    });
});
```

### Tenant Identification (Automatic)

stancl/tenancy handles this via `InitializeTenancyByDomain` middleware:

```php
// app/Providers/TenancyServiceProvider.php (auto-generated)
public function boot()
{
    $this->configureRequests();
    $this->configureJobs();
    // ...
}
```

No manual middleware needed — the package:
1. Extracts subdomain from request
2. Looks up tenant in `tenants` table via `domains` relationship
3. Switches database connection to tenant's database
4. Sets `tenant()` helper for use in code

### Accessing Current Tenant

```php
// In controllers, models, anywhere
$tenant = tenant();
$tenantName = tenant('name');

// In Blade
{{ tenant()->name }}
```

## Local Development

### Option 1: Laravel Valet (Recommended)

```bash
cd flightdata
valet link

# Valet automatically handles *.flightdata.test
# Visit: http://acme.flightdata.test
```

### Option 2: Edit /etc/hosts

```
# /etc/hosts
127.0.0.1  flightdata.test
127.0.0.1  acme.flightdata.test
127.0.0.1  demo.flightdata.test
127.0.0.1  admin.flightdata.test
```

### Environment Config

```env
# .env
APP_URL=http://flightdata.test
APP_DOMAIN=flightdata.test

# For subdomain sessions
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

For the API, tenant is resolved from the authenticated user's token, not the subdomain:

```php
// routes/api.php
Route::domain('api.' . config('app.domain'))->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('flights', Api\FlightController::class);
        Route::apiResource('aircraft', Api\AircraftController::class);
    });
});
```

The API uses a custom middleware to initialize tenancy from the user:

```php
// app/Http/Middleware/InitializeTenancyFromUser.php
use Stancl\Tenancy\Tenancy;

class InitializeTenancyFromUser
{
    public function handle(Request $request, Closure $next)
    {
        if ($user = $request->user()) {
            // User's tenant_id stored when they were created
            $tenant = Tenant::find($user->tenant_id);
            
            if ($tenant) {
                tenancy()->initialize($tenant);
            }
        }
        
        return $next($request);
    }
}
```

**Note:** API users need their `tenant_id` stored in the central `users` table or as a claim in their token.

## Consequences

### Positive
- ✅ Professional, branded feel for each tenant
- ✅ Clean URL structure
- ✅ Automatic database switching via stancl/tenancy
- ✅ Easy white-labeling potential (custom domains later)
- ✅ Industry standard for SaaS
- ✅ Session isolation per subdomain

### Negative
- ⚠️ Wildcard SSL certificate required
- ⚠️ DNS configuration needed
- ⚠️ Local dev requires hosts file or Valet
- ⚠️ Package dependency (stancl/tenancy)

## Future: Custom Domains

Enterprise tenants may want their own domain:

```
flights.acme-aviation.com → acme-aviation.flightdata.com
```

stancl/tenancy supports this via the `domains` table:

```php
// Add custom domain for tenant
$tenant->domains()->create(['domain' => 'flights.acme-aviation.com']);

// Tenant adds CNAME record:
// flights.acme-aviation.com → CNAME → flightdata.com
```

SSL handled via Forge or Certbot for each custom domain.

---

*Last updated: February 2026*

# ADR 014: Coding Conventions

## Status
**Active**

## App Name & Branding

### Never Hardcode App Name

The app name may change in the future (trademark issues, rebranding). Always use:

```php
// In PHP/Laravel
config('app.name')

// In Blade templates
{{ config('app.name') }}

// In JavaScript (pass from Blade)
window.AppName = '{{ config('app.name') }}';
```

### Environment Configuration

```env
# .env
APP_NAME="FlightData"
APP_URL=https://flightdata.com

# These may change:
APP_DOMAIN=flightdata.com
APP_SUPPORT_EMAIL=support@flightdata.com
```

### Usage Examples

```php
// ❌ Wrong - hardcoded
$subject = "Welcome to FlightData";
$url = "https://flightdata.com/demo";

// ✅ Correct - from config
$subject = "Welcome to " . config('app.name');
$url = "https://" . config('app.domain') . "/demo";
```

```blade
{{-- ❌ Wrong --}}
<title>FlightData - Dashboard</title>
<a href="https://flightdata.com">Visit FlightData</a>

{{-- ✅ Correct --}}
<title>{{ config('app.name') }} - Dashboard</title>
<a href="https://{{ config('app.domain') }}">Visit {{ config('app.name') }}</a>
```

### Custom Config for Domain

```php
// config/app.php
return [
    'name' => env('APP_NAME', 'FlightData'),
    'domain' => env('APP_DOMAIN', 'flightdata.com'),
    'support_email' => env('APP_SUPPORT_EMAIL', 'support@flightdata.com'),
    // ...
];
```

### In Documentation

Documentation files (like these ADRs) can use the current name for clarity, but code must always use config values.

## Other Conventions

### Naming

| Type | Convention | Example |
|------|------------|---------|
| Models | Singular PascalCase | `Flight`, `Aircraft` |
| Tables | Plural snake_case | `flights`, `aircraft` |
| Controllers | PascalCase + Controller | `FlightController` |
| Livewire | PascalCase | `FlightList`, `CreateFlight` |
| Routes | kebab-case | `flight-logs`, `weight-balance` |
| Config keys | snake_case | `price_per_aircraft` |

### File Organization

```
app/
├── Actions/          # Single-purpose classes
├── Enums/            # PHP enums
├── Http/
│   ├── Controllers/  # Minimal with Livewire
│   ├── Livewire/     # Livewire components
│   └── Middleware/
├── Models/
├── Policies/
├── Services/         # Business logic
└── Traits/
```

---

*Last updated: February 2026*

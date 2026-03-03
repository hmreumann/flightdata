# ADR 008: Technology Stack

## Status
**Decided**

## Overview

| Layer | Technology | Notes |
|-------|------------|-------|
| **Backend** | Laravel 11 | PHP 8.3+ |
| **Auth** | Laravel Breeze | Login, register, password reset |
| **UI Framework** | Filament 3 | All authenticated user views |
| **CSS** | Tailwind CSS | Bundled with Filament |
| **Multi-tenancy** | stancl/tenancy | Separate DB per tenant |
| **Database** | PostgreSQL | One per tenant |
| **Cache/Queue** | Redis | Single instance |
| **Mobile** | Swift/SwiftUI | Phase 2, iOS native |
| **Hosting** | DigitalOcean + Forge | See ADR 009 |

## Backend: Laravel 11

### Why Laravel?

- Mature, well-documented framework
- Excellent ORM (Eloquent) for complex relations
- Built-in queue system for notifications
- Strong authentication ecosystem
- Large package ecosystem

### Required Packages

```json
{
    "require": {
        "php": "^8.3",
        "laravel/framework": "^11.0",
        "laravel/breeze": "^2.0",
        "filament/filament": "^3.0",
        "stancl/tenancy": "^3.0",
        "laravel/sanctum": "^4.0",
        "spatie/laravel-permission": "^6.0",
        "barryvdh/laravel-dompdf": "^2.0",
        "maatwebsite/excel": "^3.1"
    }
}
```

### Project Structure

```
app/
├── Actions/               # Single-purpose action classes
│   ├── Flights/
│   │   ├── CreateFlight.php
│   │   └── CalculateWeightBalance.php
│   └── Users/
├── Enums/                 # PHP enums
│   ├── UserType.php
│   ├── FlightStatus.php
│   └── AircraftStatus.php
├── Filament/              # Filament panels & resources
│   ├── Admin/             # Super admin panel
│   │   ├── Resources/
│   │   └── Pages/
│   ├── App/               # Tenant panel
│   │   ├── Resources/
│   │   │   ├── FlightResource.php
│   │   │   ├── AircraftResource.php
│   │   │   └── UserResource.php
│   │   ├── Pages/
│   │   │   └── Dashboard.php
│   │   └── Widgets/
│   └── Client/            # External client portal (optional)
├── Http/
│   ├── Controllers/
│   │   └── Api/           # API controllers
│   └── Middleware/
├── Models/
│   ├── Tenant.php
│   ├── User.php
│   ├── Flight.php
│   └── ...
├── Policies/              # Authorization policies
├── Services/              # Business logic services
│   ├── WeightBalanceService.php
│   └── SyncService.php
└── Traits/
    └── Auditable.php
```

## Frontend: Filament 3

### Why Filament?

- Complete UI framework (tables, forms, actions, widgets, dashboards)
- Built on Livewire 3 (reactive without separate JS framework)
- Beautiful, consistent UI out of the box
- Excellent multi-tenancy support
- Rapid development — resources generated from models
- Built-in role/permission integration
- Works for **all users**, not just admins (despite the "admin panel" marketing)

### When to Use Filament vs Custom Views

| View Type | Technology | Reason |
|-----------|------------|--------|
| Super admin panel | Filament | CRUD for tenants, billing |
| Tenant admin views | Filament | User management, settings |
| Pilot/dispatcher views | Filament | Flights, aircraft, scheduling |
| Client portal | Filament | Bookings, flight history |
| **Marketing site** | Custom Blade | Unique branding, SEO |
| **Landing pages** | Custom Blade | Public, no auth |
| **Specialized UX** | Custom Livewire | Drag-drop scheduler, maps (if needed) |

### Architecture Pattern

```
┌─────────────────────────────────────────────────────────────┐
│                    Filament Panels                          │
│                                                             │
│  ┌─────────────────┐  ┌─────────────────┐                  │
│  │   Admin Panel   │  │   Tenant Panel  │                  │
│  │ (Super Admins)  │  │ (All Tenant     │                  │
│  │                 │  │  Users: admin,  │                  │
│  │ admin.domain.com│  │  pilots, crew)  │                  │
│  └─────────────────┘  │                 │                  │
│                       │ {tenant}.domain │                  │
│  ┌─────────────────┐  └─────────────────┘                  │
│  │  Client Portal  │                                       │
│  │ (External users │  (Optional separate panel or          │
│  │  - passengers)  │   same panel with role-based nav)     │
│  └─────────────────┘                                       │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                    Resources                         │   │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐            │   │
│  │  │ Flights  │ │ Aircraft │ │  Users   │  ...       │   │
│  │  │ Resource │ │ Resource │ │ Resource │            │   │
│  │  └──────────┘ └──────────┘ └──────────┘            │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                  Custom Blade Views                         │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  Marketing Site (flightdata.com)                     │   │
│  │  - Home, Pricing, Features, Contact, Demo signup     │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### Multi-Panel Setup

```php
// app/Providers/Filament/AdminPanelProvider.php
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->domain('admin.' . config('app.domain'))
            ->path('')
            ->login()
            ->colors(['primary' => Color::Indigo])
            ->discoverResources(in: app_path('Filament/Admin/Resources'))
            ->discoverPages(in: app_path('Filament/Admin/Pages'));
    }
}

// app/Providers/Filament/AppPanelProvider.php
class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('app')
            ->path('')
            ->login()
            ->tenant(Tenant::class)  // Multi-tenancy support
            ->colors(['primary' => Color::Blue])
            ->discoverResources(in: app_path('Filament/App/Resources'))
            ->discoverPages(in: app_path('Filament/App/Pages'));
    }
}
```

### Resource Example

```php
// app/Filament/App/Resources/FlightResource.php
namespace App\Filament\App\Resources;

use App\Models\Flight;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;

class FlightResource extends Resource
{
    protected static ?string $model = Flight::class;
    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';
    protected static ?string $navigationGroup = 'Operations';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Select::make('aircraft_id')
                ->relationship('aircraft', 'registration')
                ->required(),
            Forms\Components\Select::make('client_id')
                ->relationship('client', 'name'),
            Forms\Components\DateTimePicker::make('scheduled_departure')
                ->required(),
            Forms\Components\DateTimePicker::make('scheduled_arrival')
                ->required(),
            Forms\Components\Select::make('status')
                ->options(FlightStatus::class)
                ->default('draft'),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->formatStateUsing(fn ($state) => 'FLT-' . str_pad($state, 3, '0', STR_PAD_LEFT))
                    ->sortable(),
                Tables\Columns\TextColumn::make('aircraft.registration')
                    ->searchable(),
                Tables\Columns\TextColumn::make('scheduled_departure')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'draft',
                        'info' => 'scheduled',
                        'warning' => 'active',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(FlightStatus::class),
                Tables\Filters\Filter::make('scheduled_departure')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ]);
    }
}
```

### Filament + stancl/tenancy

Filament has built-in tenant support, but we use stancl/tenancy for database switching:

```php
// In TenancyServiceProvider
// stancl/tenancy handles DB switching
// Filament's tenant() just controls panel access

Tenancy::$shouldInitializeTenancy = function () {
    return request()->is('*') && ! request()->is('admin*');
};
```

## Database: PostgreSQL

### Why PostgreSQL over MySQL?

| Feature | PostgreSQL | MySQL |
|---------|-----------|-------|
| JSONB columns | ✅ Excellent | ⚠️ Good |
| Full-text search | ✅ Built-in | ⚠️ Limited |
| Schema per tenant (future) | ✅ Yes | ❌ No |
| Array columns | ✅ Yes | ❌ No |
| Partial indexes | ✅ Yes | ❌ No |

### Configuration

```php
// config/database.php
'default' => 'pgsql',

'connections' => [
    'pgsql' => [
        'driver' => 'pgsql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '5432'),
        'database' => env('DB_DATABASE', 'flightdata'),
        'username' => env('DB_USERNAME', 'flightdata'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8',
        'prefix' => '',
        'schema' => 'public',
    ],
],
```

## Cache & Queue: Redis

### Single Redis Instance

```env
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Queue Workers

```php
// Queues used
'default'       // General jobs
'notifications' // Email/push notifications
'sync'          // Mobile sync operations
'exports'       // PDF/Excel exports
```

## Mobile: Swift/SwiftUI (Phase 2)

### Why Native Swift?

| Consideration | Native Swift | React Native | Flutter |
|---------------|-------------|--------------|---------|
| Offline storage | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| Background sync | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ |
| iPad optimization | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ |
| Aviation trust | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ |

### iOS Stack (Phase 2)

```
Swift 5.9+
├── SwiftUI          # UI framework
├── SwiftData        # Offline storage (Core Data successor)
├── Alamofire        # HTTP networking
├── BGTaskScheduler  # Background sync
└── KeychainAccess   # Secure token storage
```

### API Integration

Swift will consume the same Sanctum API:

```swift
// Example API client
class FlightDataAPI {
    private let baseURL: URL
    private let session: URLSession
    
    func getFlights() async throws -> [Flight] {
        var request = URLRequest(url: baseURL.appendingPathComponent("/api/v1/flights"))
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        
        let (data, _) = try await session.data(for: request)
        return try JSONDecoder().decode(FlightsResponse.self, from: data).data
    }
}
```

## Development Tools

### Required
- PHP 8.3+
- Composer 2.x
- Node.js 20+ (for Vite)
- PostgreSQL 15+
- Redis 7+

### Recommended
- Laravel Herd or Valet (local development)
- TablePlus (database GUI)
- Ray (debugging)
- Laravel Telescope (local debugging)

### VS Code Extensions
- Laravel Extension Pack
- Tailwind CSS IntelliSense
- Alpine.js IntelliSense
- PHP Intelephense

## Testing Strategy

### Backend Testing (Pest)

```php
// tests/Feature/FlightTest.php
it('can create a flight', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $user->assignRole('scheduler');
    
    actingAs($user);
    
    livewire(CreateFlight::class)
        ->set('client_id', Client::factory()->for($tenant)->create()->id)
        ->set('aircraft_id', Aircraft::factory()->for($tenant)->create()->id)
        ->set('scheduled_departure', now()->addDay())
        ->call('save')
        ->assertHasNoErrors();
        
    expect(Flight::count())->toBe(1);
});
```

### API Testing

```php
// tests/Feature/Api/FlightApiTest.php
it('returns flights for authenticated user', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    
    Flight::factory()->for($user->tenant)->count(3)->create();
    
    $response = getJson('/api/v1/flights', [
        'Authorization' => "Bearer {$token}"
    ]);
    
    $response->assertOk()
        ->assertJsonCount(3, 'data');
});
```

## Consequences

### Positive
- ✅ Mature, stable technology choices
- ✅ Strong Laravel ecosystem
- ✅ Livewire reduces frontend complexity
- ✅ Native iOS provides best user experience
- ✅ PostgreSQL enables future optimizations

### Negative
- ⚠️ PHP has smaller talent pool than JS
- ⚠️ Native iOS requires separate skill set
- ⚠️ PostgreSQL less common than MySQL (hosting)

---

*Last updated: February 2026*

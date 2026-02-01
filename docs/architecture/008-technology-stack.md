# ADR 008: Technology Stack

## Status
**Decided**

## Overview

| Layer | Technology | Notes |
|-------|------------|-------|
| **Backend** | Laravel 11 | PHP 8.3+ |
| **Frontend** | Livewire 3 + Alpine.js | SPA-like with wire:navigate |
| **CSS** | Tailwind CSS | Via Vite |
| **Auth** | Jetstream + Fortify | With Spatie Permission |
| **Database** | PostgreSQL | Better for multi-tenant |
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
        "laravel/jetstream": "^5.0",
        "laravel/sanctum": "^4.0",
        "livewire/livewire": "^3.0",
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
├── Http/
│   ├── Controllers/
│   │   ├── Api/          # API controllers
│   │   └── Web/          # Web controllers (minimal with Livewire)
│   ├── Livewire/         # Livewire components
│   │   ├── Flights/
│   │   ├── Aircraft/
│   │   └── Dashboard.php
│   └── Middleware/
│       ├── ResolveTenant.php
│       └── EnsureUserIsInternal.php
├── Models/
│   ├── Tenant.php
│   ├── User.php
│   ├── Flight.php
│   └── ...
├── Policies/             # Authorization policies
├── Scopes/               # Query scopes (TenantScope)
├── Services/             # Business logic services
│   ├── WeightBalanceService.php
│   └── SyncService.php
└── Traits/
    ├── BelongsToTenant.php
    └── Auditable.php
```

## Frontend: Livewire 3 + Alpine.js

### Why Livewire?

- No separate JavaScript framework to maintain
- Full Laravel integration
- Real-time updates without complex setup
- `wire:navigate` provides SPA-like experience

### Architecture Pattern

```
┌─────────────────────────────────────────────────────────────┐
│                    Blade Layout                              │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  <nav> with wire:navigate links                      │    │
│  └─────────────────────────────────────────────────────┘    │
│  ┌─────────────────────────────────────────────────────┐    │
│  │                 Livewire Component                   │    │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  │    │
│  │  │ PHP Class   │  │ Blade View  │  │ Alpine.js   │  │    │
│  │  │ (Backend)   │──│ (Template)  │──│ (UI State)  │  │    │
│  │  └─────────────┘  └─────────────┘  └─────────────┘  │    │
│  └─────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
```

### Component Structure

```php
// app/Http/Livewire/Flights/FlightList.php
namespace App\Http\Livewire\Flights;

use App\Models\Flight;
use Livewire\Component;
use Livewire\WithPagination;

class FlightList extends Component
{
    use WithPagination;
    
    public string $search = '';
    public string $status = '';
    public string $sortField = 'scheduled_departure';
    public string $sortDirection = 'desc';
    
    protected $queryString = ['search', 'status'];
    
    public function render()
    {
        $flights = Flight::query()
            ->when($this->search, fn($q) => $q->search($this->search))
            ->when($this->status, fn($q) => $q->where('status', $this->status))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(20);
            
        return view('livewire.flights.flight-list', [
            'flights' => $flights,
        ]);
    }
}
```

### SPA-like Navigation

```blade
{{-- layouts/app.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <nav>
        {{-- wire:navigate for instant navigation --}}
        <a href="{{ route('dashboard') }}" wire:navigate>Dashboard</a>
        <a href="{{ route('flights.index') }}" wire:navigate>Flights</a>
        <a href="{{ route('aircraft.index') }}" wire:navigate>Aircraft</a>
    </nav>
    
    <main>
        {{ $slot }}
    </main>
    
    @livewireScripts
</body>
</html>
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

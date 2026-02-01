# ADR 002: API Strategy for Mobile App

## Status
**Decided** — Laravel Sanctum REST API

## Context
The iOS mobile app (Phase 2) needs to communicate with the Laravel backend. The API must support:
- Offline capability for crew during flights
- Secure authentication
- Data synchronization
- Shared business logic with web app

## Decision
Use **Laravel Sanctum** for API authentication with a **RESTful API** design.

## Rationale

### Why Sanctum?

| Option | Pros | Cons |
|--------|------|------|
| **Sanctum** | Simple, built-in, token-based | No OAuth2 flows |
| **Passport** | Full OAuth2 | Overkill for first-party app |
| **JWT** | Stateless | Extra package, refresh complexity |

Sanctum is ideal because:
- Already included with Laravel/Jetstream
- Token-based auth perfect for mobile
- Same codebase — reuse models, validation, business logic
- Personal Access Tokens with abilities (scopes)

### Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     iPad App (Phase 2)                       │
│                   Native Swift/SwiftUI                       │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐  │
│  │  SwiftData  │  │  Alamofire  │  │  Background Tasks   │  │
│  │  (Offline)  │  │  (API)      │  │  (BGTaskScheduler)  │  │
│  └─────────────┘  └─────────────┘  └─────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                              │
                              │ HTTPS + Bearer Token
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                      Laravel API                             │
│  ┌─────────────────────────────────────────────────────┐    │
│  │              routes/api.php                          │    │
│  │                                                      │    │
│  │  /api/v1/flights      → FlightController            │    │
│  │  /api/v1/aircraft     → AircraftController          │    │
│  │  /api/v1/documents    → DocumentController          │    │
│  │  /api/v1/sync         → SyncController              │    │
│  │  /api/v1/wb/calculate → WeightBalanceController     │    │
│  └─────────────────────────────────────────────────────┘    │
│                              │                               │
│                              │ Shares                        │
│                              ▼                               │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐  │
│  │   Models    │  │ Validation  │  │  Business Logic     │  │
│  │  (Eloquent) │  │   Rules     │  │  (Services/Actions) │  │
│  └─────────────┘  └─────────────┘  └─────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

### API Versioning

```
/api/v1/flights
/api/v1/aircraft
/api/v1/documents
```

Version in URL for clarity. New versions only when breaking changes required.

## Implementation

### Enable API in Jetstream

```php
// config/jetstream.php
'features' => [
    Features::api(),  // Uncomment this
    // ...
],
```

### API Routes Structure

```php
// routes/api.php
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'tenant'])->group(function () {
    
    // User & Auth
    Route::get('/user', fn() => auth()->user()->load('crewProfile'));
    
    // Core Resources
    Route::apiResource('bases', BaseController::class);
    Route::apiResource('aircraft', AircraftController::class);
    Route::apiResource('aircraft-types', AircraftTypeController::class);
    Route::apiResource('flights', FlightController::class);
    Route::apiResource('documents', DocumentController::class);
    Route::apiResource('checklists', ChecklistController::class);
    
    // Flight Operations
    Route::get('/flights/{flight}/legs', [FlightLegController::class, 'index']);
    Route::post('/flights/{flight}/legs', [FlightLegController::class, 'store']);
    
    // Weight & Balance
    Route::post('/wb/calculate', [WeightBalanceController::class, 'calculate']);
    
    // Sync (for offline)
    Route::post('/sync', [SyncController::class, 'push']);
    Route::get('/sync/pull', [SyncController::class, 'pull']);
    Route::get('/sync/changes', [SyncController::class, 'changes']);
});
```

### Token Abilities (Scopes)

```php
// When creating tokens, assign abilities
$token = $user->createToken('ipad-app', [
    'flights:read',
    'flights:write',
    'aircraft:read',
    'documents:read',
    'wb:calculate',
]);

// In controllers, check abilities
public function store(Request $request)
{
    if (!$request->user()->tokenCan('flights:write')) {
        abort(403);
    }
    // ...
}
```

### API Response Format

Consistent JSON structure:

```json
// Success
{
    "data": { ... },
    "meta": {
        "timestamp": "2026-02-01T12:00:00Z",
        "version": "1.0"
    }
}

// Collection
{
    "data": [ ... ],
    "meta": {
        "total": 100,
        "page": 1,
        "per_page": 20
    }
}

// Error
{
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "The given data was invalid.",
        "details": {
            "registration": ["The registration has already been taken."]
        }
    }
}
```

### iOS Integration Example

```swift
// Swift - API Client
class FlightDataAPI {
    private let baseURL = "https://acme.flightdata.app/api/v1"
    private var token: String
    
    func getFlights() async throws -> [Flight] {
        var request = URLRequest(url: URL(string: "\(baseURL)/flights")!)
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        
        let (data, _) = try await URLSession.shared.data(for: request)
        let response = try JSONDecoder().decode(FlightsResponse.self, from: data)
        return response.data
    }
}
```

## Sync Strategy

See [ADR 006 - Offline Sync](./006-offline-sync.md) for detailed sync implementation.

### Key Endpoints

```
POST /api/v1/sync
- Push local changes to server
- Body: { changes: [...], last_sync: timestamp }
- Response: { accepted: [...], conflicts: [...] }

GET /api/v1/sync/pull?since={timestamp}
- Get all changes since timestamp
- Response: { data: [...], deleted: [...], timestamp }

GET /api/v1/sync/changes?models=flights,aircraft&since={timestamp}
- Get specific model changes
```

## Consequences

### Positive
- ✅ Single source of truth (Laravel models)
- ✅ Consistent validation between web and API
- ✅ Easy to maintain and extend
- ✅ Good Laravel ecosystem support
- ✅ Personal Access Tokens work great for mobile

### Negative
- ⚠️ Need to build/maintain separate iOS app
- ⚠️ Offline sync adds complexity
- ⚠️ Must handle API versioning for app updates

## Phase 1 Preparation
Even though iOS is Phase 2, in Phase 1 we will:
1. Enable Sanctum API feature
2. Create API routes alongside web routes
3. Build controllers that return JSON
4. Design models with sync-friendly `updated_at` indexes
5. Add `sync_id` UUID field for conflict resolution

---

*Last updated: February 2026*

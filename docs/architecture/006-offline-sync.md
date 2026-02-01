# ADR 006: Offline Sync Strategy

## Status
**Decided** — Last-write-wins with conflict logging

## Context
The iOS app (Phase 2) must work offline for pilots during flights. They need access to:
- Flight plan data
- Flight log (to complete in real-time)
- Weight and balance
- Checklists
- Documents

Changes made offline must sync when connectivity returns.

## Decision
1. **Last-write-wins** — Accept the most recent change by timestamp
2. **Log all conflicts** — Store both versions for audit
3. **Notify users** — Show conflicts in UI, optionally email
4. **Immutable calculations** — W&B records never updated, only new created

## Sync Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      iPad (Offline)                          │
│  ┌─────────────────────────────────────────────────────┐    │
│  │                 SwiftData/Core Data                  │    │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────────────┐   │    │
│  │  │ Flights  │  │ W&B Calc │  │ Pending Changes  │   │    │
│  │  │  (copy)  │  │ (local)  │  │   (queue)        │   │    │
│  │  └──────────┘  └──────────┘  └──────────────────┘   │    │
│  └─────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
                              │
                              │ When online
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    Sync Process                              │
│  1. Push pending changes with timestamps                     │
│  2. Server compares with current data                        │
│  3. Apply last-write-wins                                    │
│  4. Log conflicts to sync_conflicts table                    │
│  5. Return accepted changes + conflicts                      │
│  6. Pull server changes since last sync                      │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    Laravel Server                            │
│  ┌─────────────────────────────────────────────────────┐    │
│  │                    PostgreSQL                        │    │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────────────┐   │    │
│  │  │ flights  │  │ wb_calcs │  │ sync_conflicts   │   │    │
│  │  │          │  │          │  │                  │   │    │
│  │  └──────────┘  └──────────┘  └──────────────────┘   │    │
│  └─────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
```

## Data Categories

### Always Sync to Device (Read)
Data that should be available offline:
- Assigned flights (upcoming 7 days)
- Aircraft details
- Checklists for assigned aircraft types
- Documents (PDFs cached)
- W&B templates

### Sync Back to Server (Write)
Data modified offline:
- Flight log entries
- W&B calculations
- Checklist completions
- Times (ATD, ATA)

### Never Offline
- User management
- Aircraft management
- Client management
- Historical flights (> 30 days)

## Conflict Resolution

### Last-Write-Wins Strategy

```php
// SyncController.php
public function push(Request $request)
{
    $changes = $request->input('changes');
    $accepted = [];
    $conflicts = [];
    
    foreach ($changes as $change) {
        $model = $this->resolveModel($change['model'], $change['id']);
        
        if (!$model) {
            // New record, just create
            $model = $this->createModel($change);
            $accepted[] = $model;
            continue;
        }
        
        // Compare timestamps
        $serverTime = $model->updated_at;
        $clientTime = Carbon::parse($change['updated_at']);
        
        if ($clientTime > $serverTime) {
            // Client wins - apply changes
            $oldValues = $model->toArray();
            $model->update($change['data']);
            $accepted[] = $model;
            
        } elseif ($clientTime < $serverTime) {
            // Server wins - log conflict
            $conflict = SyncConflict::create([
                'tenant_id' => tenant()->id,
                'user_id' => auth()->id(),
                'model_type' => $change['model'],
                'model_id' => $change['id'],
                'local_data' => $change['data'],
                'server_data' => $model->toArray(),
                'resolution' => 'server_wins',
            ]);
            $conflicts[] = $conflict;
            
        } else {
            // Same timestamp - compare data
            if ($change['data'] !== $model->toArray()) {
                // Conflict with same timestamp (rare)
                // Server wins by default
                $conflicts[] = SyncConflict::create([...]);
            }
        }
    }
    
    return response()->json([
        'accepted' => $accepted,
        'conflicts' => $conflicts,
        'server_time' => now(),
    ]);
}
```

### Conflict Notification

```php
// When conflict detected, notify user
class SyncConflictNotification extends Notification
{
    public function __construct(
        public SyncConflict $conflict
    ) {}
    
    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }
    
    public function toArray($notifiable): array
    {
        return [
            'type' => 'sync_conflict',
            'message' => "Your changes to {$this->conflict->model_type} were overwritten",
            'conflict_id' => $this->conflict->id,
            'model_type' => $this->conflict->model_type,
            'model_id' => $this->conflict->model_id,
        ];
    }
}
```

### UI Display

```php
// Livewire component to show conflicts
class SyncConflictsBanner extends Component
{
    public function render()
    {
        $conflicts = SyncConflict::where('user_id', auth()->id())
            ->whereNull('resolved_at')
            ->latest()
            ->take(5)
            ->get();
            
        return view('livewire.sync-conflicts-banner', [
            'conflicts' => $conflicts,
        ]);
    }
    
    public function acknowledge($conflictId)
    {
        SyncConflict::find($conflictId)->update([
            'resolved_at' => now(),
        ]);
    }
}
```

## API Endpoints

### Push Changes

```
POST /api/v1/sync
Authorization: Bearer {token}

{
    "changes": [
        {
            "model": "flight_logs",
            "id": 123,
            "action": "update",
            "data": {
                "fuel_used": 450,
                "remarks": "Smooth flight"
            },
            "updated_at": "2026-02-01T14:30:00Z"
        }
    ],
    "device_id": "iPad-ABC123",
    "last_sync": "2026-02-01T12:00:00Z"
}

Response:
{
    "accepted": [...],
    "conflicts": [
        {
            "id": 456,
            "model_type": "flight_logs",
            "model_id": 123,
            "local_data": {...},
            "server_data": {...},
            "resolution": "server_wins"
        }
    ],
    "server_time": "2026-02-01T14:30:05Z"
}
```

### Pull Changes

```
GET /api/v1/sync/pull?since=2026-02-01T12:00:00Z&models=flights,aircraft,checklists

Response:
{
    "data": {
        "flights": [...],
        "aircraft": [...],
        "checklists": [...]
    },
    "deleted": {
        "flights": [101, 102],
        "checklists": [5]
    },
    "server_time": "2026-02-01T14:30:05Z"
}
```

## Sync-Friendly Database Design

### Required Fields

All syncable models should have:

```php
Schema::create('flights', function (Blueprint $table) {
    // ... other columns
    
    // Sync support
    $table->uuid('sync_id')->unique();  // Device-generated ID for new records
    $table->timestamp('synced_at')->nullable();
    $table->softDeletes();  // Track deletions
    
    // Timestamps with precision
    $table->timestamp('created_at', 6);  // Microsecond precision
    $table->timestamp('updated_at', 6);
});
```

### Sync ID for Offline Creation

When creating records offline, device generates UUID:

```swift
// iOS - create flight log offline
let flightLog = FlightLog(
    syncId: UUID().uuidString,  // Generated on device
    flightLegId: leg.id,
    entries: [...],
    createdAt: Date()
)
```

Server uses `sync_id` to deduplicate:

```php
public function createOrUpdateBySyncId(array $data)
{
    return FlightLog::updateOrCreate(
        ['sync_id' => $data['sync_id']],
        $data
    );
}
```

## Weight & Balance Special Case

W&B calculations are **immutable** for compliance:

```php
// WeightBalanceCalculation - never updated
class WeightBalanceCalculation extends Model
{
    // No updated_at, only created_at
    const UPDATED_AT = null;
    
    // Prevent updates
    public static function boot()
    {
        parent::boot();
        
        static::updating(function ($model) {
            throw new \Exception('W&B calculations cannot be modified');
        });
    }
}
```

If pilot recalculates offline, a new record is created:
- Old calculation preserved
- New calculation has reference to previous
- Both synced to server

## Phase 1 Preparation

Even before iOS app, prepare the backend:

1. **Add sync fields to migrations**
   - `sync_id` UUID column
   - Microsecond timestamp precision
   
2. **Build sync API endpoints**
   - Test with Postman/curl
   - Document for iOS development
   
3. **Create SyncConflict model/migration**
   
4. **Add indexes for sync queries**
   ```sql
   CREATE INDEX idx_flights_updated ON flights(tenant_id, updated_at);
   CREATE INDEX idx_flights_sync ON flights(sync_id);
   ```

## Consequences

### Positive
- ✅ Simple conflict resolution rule
- ✅ Full audit trail of conflicts
- ✅ Users informed of overwrites
- ✅ Works with intermittent connectivity

### Negative
- ⚠️ "Server wins" may frustrate users occasionally
- ⚠️ Requires careful timestamp management
- ⚠️ Offline duration affects conflict likelihood

## Alternatives Considered

### CRDTs (Conflict-free Replicated Data Types)
- Complex to implement
- Overkill for this use case
- Would require different data model

### Manual Merge
- User chooses which version to keep
- Better UX but more complex UI
- Could add later for specific fields

### Operational Transform
- Good for collaborative editing
- Unnecessary for flight log data
- High implementation cost

---

*Last updated: February 2026*

# ADR 007: EFB Compliance

## Status
**Decided** — Build compliance features with PDF export

## Context
FlightData is an Electronic Flight Bag (EFB) application. Aviation authorities require operational approval for EFB use. We need to support our tenants' approval processes.

## Regulatory Framework

### Classification

| Authority | Reference | Key Points |
|-----------|-----------|------------|
| **FAA** | AC 120-76D | Type A/B classification, OpSpecs approval |
| **EASA** | AMC 20-25 | Similar structure, operational evaluation |
| **ICAO** | Doc 10020 | International harmonization |

### EFB Application Types

| Type | Description | Our Features |
|------|-------------|--------------|
| **Type A** | Static documents (PDFs, manuals) | ✅ Checklists, documents |
| **Type B** | Dynamic calculations | ✅ Weight & Balance |

**Type B applications require operator-specific approval.**

## What We Provide

FlightData is the **software provider**. Each tenant (operator) must obtain their own operational approval from their aviation authority.

We provide:
1. **Compliance Documentation** — PDF exports explaining our methodology
2. **Audit Trails** — Immutable logs of all calculations and changes
3. **Validation Records** — Test results for W&B calculations
4. **Data Integrity Reports** — Proof of data accuracy

## Key Compliance Features

### 1. Weight & Balance Compliance

```php
// All W&B calculations are immutable
class WeightBalanceCalculation extends Model
{
    // Fields stored
    protected $fillable = [
        'flight_leg_id',
        'aircraft_id',
        'calculated_by',     // User who performed calculation
        'inputs',            // All input values (JSON)
        'outputs',           // Results (JSON)
        'is_within_limits',  // Pass/fail
        'aircraft_config',   // Aircraft config snapshot
        'envelope_data',     // CG envelope used
        'pdf_path',          // Generated PDF
    ];
    
    // Never allow updates
    protected static function booted()
    {
        static::updating(fn() => throw new \Exception('W&B records are immutable'));
    }
}
```

**Inputs JSON structure:**
```json
{
    "basic_empty_weight": 4500,
    "basic_empty_weight_cg": 145.5,
    "fuel": {
        "main_tanks": 1200,
        "aux_tanks": 0
    },
    "crew": [
        {"position": "pilot", "weight": 180},
        {"position": "copilot", "weight": 175}
    ],
    "passengers": [
        {"seat": "1A", "weight": 170},
        {"seat": "1B", "weight": 155}
    ],
    "cargo": {
        "forward": 50,
        "aft": 30
    }
}
```

**Outputs JSON structure:**
```json
{
    "zero_fuel_weight": 5260,
    "zero_fuel_cg": 146.2,
    "takeoff_weight": 6460,
    "takeoff_cg": 147.1,
    "landing_weight": 5860,
    "landing_cg": 146.8,
    "limits": {
        "max_takeoff_weight": 7000,
        "max_landing_weight": 6500,
        "forward_cg_limit": 140.0,
        "aft_cg_limit": 155.0
    },
    "within_envelope": true
}
```

### 2. Audit Logging

Every change to safety-critical data is logged:

```php
// app/Traits/Auditable.php
trait Auditable
{
    protected static function bootAuditable()
    {
        static::created(function ($model) {
            AuditLog::create([
                'tenant_id' => tenant()?->id,
                'user_id' => auth()->id(),
                'auditable_type' => get_class($model),
                'auditable_id' => $model->id,
                'action' => 'created',
                'new_values' => $model->toArray(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });
        
        static::updated(function ($model) {
            AuditLog::create([
                'tenant_id' => tenant()?->id,
                'user_id' => auth()->id(),
                'auditable_type' => get_class($model),
                'auditable_id' => $model->id,
                'action' => 'updated',
                'old_values' => $model->getOriginal(),
                'new_values' => $model->toArray(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });
    }
}
```

### 3. Data Currency Tracking

Documents and data must show revision dates:

```php
// Document model
class Document extends Model
{
    protected $fillable = [
        'tenant_id',
        'title',
        'type',
        'file_path',
        'revision',          // "Rev 3", "Amendment 5"
        'effective_date',    // When this revision became active
        'expiry_date',       // When it expires (if applicable)
        'source',            // Where data came from
    ];
    
    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date < now();
    }
}
```

### 4. Record Retention

Per 14 CFR Part 121 requirements:

| Record Type | Minimum Retention |
|-------------|------------------|
| Load manifests (W&B) | 3 months |
| Dispatch releases | 3 months |
| Flight logs | 3 months |
| Maintenance records | Until aircraft sold |
| Crew records | 6 months after separation |

```php
// Soft deletes + retention policy
class Flight extends Model
{
    use SoftDeletes;
    
    // Prevent hard delete of recent records
    protected static function booted()
    {
        static::forceDeleting(function ($flight) {
            if ($flight->scheduled_departure > now()->subMonths(3)) {
                throw new \Exception('Cannot delete flights less than 3 months old');
            }
        });
    }
}
```

## Compliance PDF Exports

### W&B Calculation Report

Generated for each calculation:

```php
class WeightBalancePdfExport
{
    public function generate(WeightBalanceCalculation $calc): string
    {
        $pdf = PDF::loadView('pdf.weight-balance', [
            'calculation' => $calc,
            'aircraft' => $calc->aircraft,
            'flight' => $calc->flightLeg->flight,
            'calculated_by' => $calc->calculatedBy,
            'timestamp' => $calc->created_at,
        ]);
        
        $path = "wb-calculations/{$calc->id}.pdf";
        Storage::put($path, $pdf->output());
        
        $calc->update(['pdf_path' => $path]);
        
        return $path;
    }
}
```

**PDF includes:**
- Flight details (date, route, aircraft)
- All input values
- Calculated weights and CG positions
- CG envelope diagram with plotted points
- Pass/fail status
- Timestamp and user who calculated
- Calculation methodology reference

### Compliance Documentation Package

For operator approval submissions:

```php
class CompliancePackageExport
{
    public function generate(Tenant $tenant): string
    {
        $documents = [
            $this->systemOverview(),
            $this->wbMethodology(),
            $this->dataIntegrityReport($tenant),
            $this->auditCapabilities(),
            $this->sampleCalculations($tenant),
        ];
        
        // Combine into single PDF
        return $this->mergePdfs($documents);
    }
    
    private function wbMethodology(): string
    {
        // Explains calculation methodology
        // Input validation rules
        // Output verification process
        // Error handling
    }
    
    private function dataIntegrityReport(Tenant $tenant): string
    {
        // Data sources used
        // Update/revision procedures
        // Backup and recovery
    }
}
```

### Audit Trail Export

```php
class AuditTrailExport
{
    public function generate(
        Tenant $tenant,
        Carbon $startDate,
        Carbon $endDate,
        ?string $modelType = null
    ): string {
        $query = AuditLog::where('tenant_id', $tenant->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('user');
            
        if ($modelType) {
            $query->where('auditable_type', $modelType);
        }
        
        $logs = $query->orderBy('created_at')->get();
        
        return PDF::loadView('pdf.audit-trail', [
            'logs' => $logs,
            'tenant' => $tenant,
            'period' => [$startDate, $endDate],
        ])->output();
    }
}
```

## Data Validation

### W&B Input Validation

```php
class WeightBalanceValidator
{
    public function validate(array $inputs, Aircraft $aircraft): array
    {
        $errors = [];
        $type = $aircraft->aircraftType;
        
        // Check total weight
        $totalWeight = $this->calculateTotalWeight($inputs);
        if ($totalWeight > $type->mtow) {
            $errors[] = "Total weight ({$totalWeight} kg) exceeds MTOW ({$type->mtow} kg)";
        }
        
        // Check CG is within envelope
        $cg = $this->calculateCG($inputs, $aircraft);
        if (!$this->isWithinEnvelope($cg, $totalWeight, $type->wb_envelope)) {
            $errors[] = "CG position ({$cg}) is outside approved envelope";
        }
        
        // Check fuel
        if ($inputs['fuel']['total'] > $type->max_fuel) {
            $errors[] = "Fuel quantity exceeds maximum capacity";
        }
        
        return $errors;
    }
}
```

## Implementation Checklist

### Phase 1 Requirements

- [ ] Immutable W&B calculation records
- [ ] Audit logging on all models
- [ ] Soft deletes with retention checks
- [ ] Document revision tracking
- [ ] W&B PDF generation
- [ ] Basic audit trail export

### Phase 2 Additions

- [ ] Full compliance package generator
- [ ] CG envelope visualization
- [ ] Automated validation test suite
- [ ] Data currency warnings
- [ ] Expiring document alerts

## Consequences

### Positive
- ✅ Supports operator approval process
- ✅ Full audit trail for investigations
- ✅ Immutable records for legal protection
- ✅ Professional compliance documentation

### Negative
- ⚠️ Storage growth from audit logs
- ⚠️ Cannot delete/modify certain records
- ⚠️ PDF generation adds complexity

## Disclaimer

```
FlightData provides tools to support flight operations.
Each operator is responsible for obtaining appropriate
operational approval from their aviation authority.
FlightData does not guarantee regulatory compliance;
operators must verify calculations and procedures
meet their specific regulatory requirements.
```

---

*Last updated: February 2026*

# FlightData - Database Schema

## Overview

**Multi-database architecture** using stancl/tenancy:
- **Central database** (`flightdata_central`) — tenants, domains, plans, super admins
- **Tenant databases** (`flightdata_{tenant}`) — each tenant's operational data

No `tenant_id` needed in tenant tables — each tenant has their own database.

## Per-Tenant Sequential Numbering

For better UX, user-facing entities have a `number` column that's sequential **per tenant**. Since each tenant has their own database, this is automatic — just use auto-increment or manual sequencing.

| Table | Column | Display Format | Example |
|-------|--------|----------------|----------|
| `flights` | `number` | FLT-{number} | FLT-001 |
| `clients` | `number` | CLT-{number} | CLT-042 |
| `invoices` | `number` | INV-{year}-{number} | INV-2026-001 |

**Note:** Aircraft use registration (N12345) as their identifier — no sequential number needed.

---

## Central Database Tables

These tables exist only in `flightdata_central`:

### tenants
Our customers (aviation companies). Managed by stancl/tenancy.

| Column | Type | Description |
|--------|------|-------------|
| `id` | string PK | Tenant identifier (slug) — e.g., "acme-aviation" |
| `name` | string | Company display name |
| `data` | jsonb | Settings, config, metadata |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Data JSON structure:**
```json
{
  "timezone": "America/New_York",
  "weight_unit": "kg",
  "fuel_unit": "liters",
  "logo_path": "tenants/acme/logo.png",
  "trial_ends_at": "2026-02-15",
  "plan_id": 1
}
```

---

### domains
Subdomain-to-tenant mapping. Managed by stancl/tenancy.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `domain` | string unique | e.g., "acme-aviation.flightdata.com" |
| `tenant_id` | string FK | References tenants.id |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

---

### plans
Subscription plans (central — shared across tenants).

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `name` | string | "Standard", "Enterprise" |
| `slug` | string unique | |
| `price_per_aircraft` | decimal | 49.00 |
| `features` | jsonb nullable | Feature flags |
| `is_active` | boolean | |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

---

### super_admins
Platform administrators (us). Separate from tenant users.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `name` | string | |
| `email` | string unique | |
| `password` | string | |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

---

## Tenant Database Tables

These tables exist in each tenant database (`flightdata_{tenant}`):

## Entity Relationship Diagram (Simplified)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         CENTRAL DATABASE                                    │
│  ┌─────────────┐     ┌─────────────┐     ┌─────────────┐                    │
│  │   tenants   │────▶│   domains   │     │    plans    │                    │
│  └─────────────┘     └─────────────┘     └─────────────┘                    │
│                                                                              │
│  ┌─────────────┐                                                            │
│  │super_admins │                                                            │
│  └─────────────┘                                                            │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                    TENANT DATABASE (one per tenant)                         │
│                                                                              │
│  ┌─────────────┐     ┌─────────────┐     ┌─────────────┐                    │
│  │    users    │────▶│crew_profiles│     │   clients   │                    │
│  └──────┬──────┘     └─────────────┘     └──────┬──────┘                    │
│         │                                        │                          │
│         │ (roles via Spatie)                     │                          │
│         ▼                                        ▼                          │
│  ┌─────────────┐     ┌─────────────┐     ┌─────────────┐                    │
│  │    bases    │◀────│  aircraft   │     │   flights   │                    │
│  └─────────────┘     └──────┬──────┘     └──────┬──────┘                    │
│                             │                   │                           │
│                             ▼                   ▼                           │
│                      ┌─────────────┐     ┌─────────────┐                    │
│                      │aircraft_type│     │ flight_legs │                    │
│                      └─────────────┘     └─────────────┘                    │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Core Tenant Tables

### users
All tenant users (internal and external).

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `type` | enum | 'internal', 'external' |
| `name` | string | |
| `email` | string unique | |
| `password` | string | |
| `phone` | string nullable | |
| `employee_id` | string nullable | Internal ID (for external data mode) |
| `profile_data` | jsonb nullable | Flexible extra data |
| `email_verified_at` | timestamp nullable | |
| `two_factor_*` | ... | Jetstream 2FA columns |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Indexes:** `email`, `type`

---

### roles / permissions / model_has_roles / etc.
Spatie Laravel-Permission tables. Standard structure — no `tenant_id` needed since each tenant has own database.

---

### bases
Airports/locations where tenant operates.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `name` | string | "New York JFK" |
| `icao_code` | string | "KJFK" |
| `iata_code` | string nullable | "JFK" |
| `country` | string | |
| `timezone` | string | "America/New_York" |
| `coordinates` | point nullable | Lat/lng |
| `is_active` | boolean | |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

---

### aircraft_types
Aircraft models with performance data.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `name` | string | "Citation XLS+" |
| `manufacturer` | string | "Cessna" |
| `model` | string | "560XL" |
| `category` | string | "jet", "turboprop", "helicopter" |
| `max_pax` | integer | |
| `mtow` | decimal | Max takeoff weight |
| `max_fuel` | decimal | |
| `performance_data` | jsonb | Speeds, ranges, etc. |
| `wb_envelope` | jsonb | Weight & balance envelope data |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

---

### aircraft
Individual airframes in the fleet.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `aircraft_type_id` | bigint FK | |
| `base_id` | bigint FK | Home base |
| `registration` | string | "N12345" |
| `serial_number` | string nullable | |
| `status` | enum | 'active', 'maintenance', 'inactive' |
| `configuration` | jsonb | Seat layout, equipment |
| `empty_weight` | decimal | Basic empty weight |
| `empty_weight_cg` | decimal | Empty weight CG |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

---

### clients
Tenant's customers (who book flights).

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | Internal ID (for DB relations) |
| `number` | unsigned int | Sequential (CLT-001, CLT-002...) |
| `type` | enum | 'individual', 'corporate' |
| `name` | string | Person name or company name |
| `company_name` | string nullable | If individual works for company |
| `contact_email` | string | |
| `contact_phone` | string nullable | |
| `billing_info` | jsonb nullable | Address, tax ID, etc. |
| `notes` | text nullable | |
| `is_active` | boolean | |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

---

### crew_profiles
Extended info for crew members (linked to users).

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `user_id` | bigint FK unique | |
| `base_id` | bigint FK nullable | Home base |
| `license_number` | string nullable | |
| `license_type` | string nullable | ATPL, CPL, PPL |
| `ratings` | jsonb | Type ratings, endorsements |
| `medical_class` | string nullable | |
| `medical_expiry` | date nullable | |
| `documents` | jsonb nullable | License scans, etc. |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

---

### flights
Flight bookings/operations.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | Internal ID (for DB relations) |
| `number` | unsigned int | Sequential (what users see: 1, 2, 3...) |
| `client_id` | bigint FK nullable | Who booked |
| `aircraft_id` | bigint FK nullable | Assigned aircraft |
| `status` | enum | 'draft', 'scheduled', 'active', 'completed', 'cancelled' |
| `departure_base_id` | bigint FK | First leg origin |
| `arrival_base_id` | bigint FK | Last leg destination |
| `scheduled_departure` | datetime | |
| `scheduled_arrival` | datetime | |
| `notes` | text nullable | |
| `created_by` | bigint FK | User who created |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

---

### flight_legs
Individual segments of a flight.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `flight_id` | bigint FK | |
| `sequence` | integer | Leg order (1, 2, 3...) |
| `origin_icao` | string | |
| `destination_icao` | string | |
| `scheduled_departure` | datetime | ETD |
| `scheduled_arrival` | datetime | ETA |
| `actual_departure` | datetime nullable | ATD |
| `actual_arrival` | datetime nullable | ATA |
| `block_time` | integer nullable | Minutes |
| `flight_time` | integer nullable | Minutes |
| `fuel_planned` | decimal nullable | |
| `fuel_actual` | decimal nullable | |
| `route` | text nullable | Flight plan route |
| `remarks` | text nullable | |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

---

### flight_crew
Crew assignments per leg.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `flight_leg_id` | bigint FK | |
| `user_id` | bigint FK | |
| `role` | enum | 'pic', 'sic', 'cabin' |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Unique:** `flight_leg_id` + `user_id`

---

### flight_passengers
Passenger manifest per leg.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `flight_leg_id` | bigint FK | |
| `user_id` | bigint FK nullable | If passenger has account |
| `client_id` | bigint FK nullable | Associated client |
| `name` | string | |
| `weight` | decimal | For W&B |
| `seat` | string nullable | Seat assignment |
| `special_requirements` | text nullable | |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

---

### flight_logs
Completed flight data entered by crew.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `flight_leg_id` | bigint FK unique | |
| `entries` | jsonb | Flight log entries |
| `fuel_uplift` | decimal nullable | |
| `fuel_used` | decimal nullable | |
| `oil_added` | decimal nullable | |
| `defects` | text nullable | |
| `signed_by` | bigint FK nullable | PIC user_id |
| `signed_at` | datetime nullable | |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

---

### weight_balance_calculations
Immutable record of W&B calculations (for compliance).

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `flight_leg_id` | bigint FK | |
| `calculated_by` | bigint FK | User who ran calculation |
| `aircraft_id` | bigint FK | |
| `inputs` | jsonb | All input values used |
| `outputs` | jsonb | Results (weights, CG, limits) |
| `is_within_limits` | boolean | Pass/fail |
| `pdf_path` | string nullable | Generated PDF |
| `created_at` | timestamp | Immutable |

**Note:** Never update these records. Create new calculation for changes.

---

### documents
Files attached to various entities.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `documentable_type` | string | Polymorphic (Aircraft, Flight, etc.) |
| `documentable_id` | bigint | |
| `type` | string | 'checklist', 'manual', 'certificate', etc. |
| `title` | string | |
| `file_path` | string | |
| `revision` | string nullable | Document revision |
| `effective_date` | date nullable | |
| `expiry_date` | date nullable | |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

---

### checklists
Checklist templates by aircraft type.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `aircraft_type_id` | bigint FK nullable | Null = all types |
| `name` | string | "Pre-flight", "Before Start" |
| `category` | string | 'normal', 'emergency', 'abnormal' |
| `items` | jsonb | Checklist items array |
| `is_active` | boolean | |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Items JSON structure:**
```json
[
  {"order": 1, "item": "Parking Brake", "response": "SET"},
  {"order": 2, "item": "Fuel Quantity", "response": "CHECK"}
]
```

---

### notifications
Laravel's built-in notifications table.

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid PK | |
| `type` | string | Notification class |
| `notifiable_type` | string | |
| `notifiable_id` | bigint | |
| `data` | jsonb | Notification content |
| `read_at` | timestamp nullable | |
| `created_at` | timestamp | |

---

### audit_logs
Immutable record of all data changes (compliance).

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `user_id` | bigint FK nullable | |
| `auditable_type` | string | Model changed |
| `auditable_id` | bigint | |
| `action` | enum | 'created', 'updated', 'deleted' |
| `old_values` | jsonb nullable | Before state |
| `new_values` | jsonb nullable | After state |
| `ip_address` | string nullable | |
| `user_agent` | string nullable | |
| `created_at` | timestamp | |

**Note:** No `updated_at` — these records are immutable.

---

### sync_conflicts
Log of offline sync conflicts (for iOS app).

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `user_id` | bigint FK | Who synced |
| `model_type` | string | |
| `model_id` | bigint | |
| `local_data` | jsonb | Device version |
| `server_data` | jsonb | Server version |
| `resolution` | enum | 'local_wins', 'server_wins', 'merged' |
| `resolved_at` | timestamp nullable | |
| `created_at` | timestamp | |

---

## Indexes Strategy

With multi-database tenancy, no `tenant_id` indexes needed. Focus on query patterns:

```sql
-- Flights by date range
CREATE INDEX idx_flights_scheduled ON flights(scheduled_departure);

-- Aircraft by status
CREATE INDEX idx_aircraft_status ON aircraft(status);

-- Users by type
CREATE INDEX idx_users_type ON users(type);
```

---

## Soft Deletes

Consider soft deletes (`deleted_at`) for:
- `users` — May need to preserve for audit
- `aircraft` — Historical flight records reference
- `clients` — Historical flight records reference
- `flights` — Never hard delete

---

*Last updated: February 2026*

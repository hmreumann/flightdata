# FlightData - Glossary

## Core Business Terms

### Tenant
The aviation company subscribing to FlightData. Our direct customer.

**Examples:** "Acme Aviation", "SkyCharter Ltd", "Corporate Jets Inc"

**In code:** `Tenant` model, `tenant_id` foreign key, `{slug}.flightdata.com` subdomain

---

### Client
The tenant's customer — a person or company who books/charters flights.

**Types:**
- **Individual** — Single person booking flights
- **Corporate** — Company that books flights for their employees

**Examples:** "John Smith" (individual), "Acme Corp" (corporate client booking executive travel)

**In code:** `Client` model with `type` enum (individual/corporate)

---

### User
Anyone with login credentials. Includes both tenant staff and external parties.

**Types:**
- **Internal** — Tenant employees (admin, crew, schedulers, ops)
- **External** — Outside parties (passengers, client admins)

**In code:** `User` model with `type` enum (internal/external)

---

## Aviation Terms

### Base
An airport/location where the tenant operates from. Aircraft are assigned to bases.

**Examples:** KJFK, EGLL, LFPG

---

### Aircraft Type
A model/variant of aircraft with specific performance characteristics.

**Examples:** Cessna Citation XLS, Airbus H145, King Air 350

---

### Aircraft
A specific airframe with a registration number, belonging to a fleet.

**Examples:** N12345, G-ABCD, VH-XYZ

---

### Flight
A planned or executed journey, potentially with multiple legs.

**Contains:** Client info, aircraft assignment, crew, passengers, legs

---

### Leg (Flight Leg)
A single segment of a flight from origin to destination.

**Examples:** KJFK → KBOS, KBOS → CYYZ

**Contains:** Times (scheduled/actual), fuel, crew, passengers for that segment

---

### Crew
Personnel operating the aircraft.

**Roles:**
- **PIC** — Pilot in Command
- **SIC** — Second in Command (Co-pilot)
- **Cabin** — Flight attendant

---

### Pax (Passengers)
People being transported on a flight. Can be linked to a User account or just name/weight.

---

### W&B (Weight and Balance)
Calculations ensuring aircraft is within weight limits and center of gravity envelope.

**Critical:** Incorrect W&B can cause accidents. Type B EFB function requiring operational approval.

---

### FRAT (Flight Risk Assessment Tool)
Checklist/scoring system to evaluate flight risk before departure.

**Phase 2 feature**

---

## Technical Terms

### EFB (Electronic Flight Bag)
Software replacing paper documents and calculations in the cockpit.

**Types:**
- **Type A** — Static documents (manuals, charts in PDF)
- **Type B** — Dynamic calculations (W&B, performance) — requires approval

---

### Multi-tenancy
Architecture where multiple customers share one application instance but have isolated data.

**Our approach:** Single database with `tenant_id` column + global scopes

---

### ADR (Architecture Decision Record)
Document capturing an important architectural decision, its context, and consequences.

**Location:** `/docs/architecture/`

---

## Roles Reference

### Internal Roles
| Role | Access Level |
|------|--------------|
| `admin` | Full tenant access, user management |
| `scheduler` | Flight planning, crew/pax assignment |
| `pilot` | Flight operations, flight logs |
| `cabin-crew` | Passenger manifest, cabin operations |
| `operations` | Ground operations, aircraft status |

### External Roles
| Role | Access Level |
|------|--------------|
| `client-admin` | Manage company's passengers, view flight history |
| `passenger` | View own flights, update profile |

---

*Last updated: February 2026*

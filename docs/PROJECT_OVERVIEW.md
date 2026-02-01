# FlightData - Project Overview

## Vision

A flight operations support application (Electronic Flight Bag) designed to be sold as a SaaS product. The initial release focuses on core functionality for small-to-medium aviation operators, with additional features added based on user feedback.

## Business Model

- **Multi-tenant SaaS** — Multiple aviation companies as subscribers
- **Target Market** — Small to medium operators (charter, corporate, aerial work)
- **Initial Target** — ~10 tenants, scalable architecture for growth
- **Pricing** — Subscription-based (TBD)

## Target Users

### Internal Users (Tenant Staff)
| Role | Description |
|------|-------------|
| **Admin** | Tenant administrator, full access |
| **Scheduler/Dispatcher** | Flight planning and scheduling |
| **Pilot** | Flight crew, cockpit operations |
| **Cabin Crew** | Flight attendants |
| **Operations** | Ground operations staff |

### External Users
| Role | Description |
|------|-------------|
| **Client Admin** | Company booking flights, manages their passengers |
| **Passenger** | Individual travelers |

## Platform Requirements

### Web Application (Phase 1)
- **Framework:** Laravel 11 with Jetstream/Fortify
- **Frontend:** Livewire 3 + Alpine.js + Tailwind CSS
- **Architecture:** SPA-like experience using `wire:navigate`
- **Database:** PostgreSQL with multi-tenant architecture
- **API:** REST API ready for mobile (Sanctum)

### Mobile Application (Phase 2)
- **Platform:** iOS (iPad primary, iPhone secondary)
- **Technology:** Native Swift/SwiftUI
- **Offline Support:** Critical for crew during flights
- **Sync Strategy:** Queue changes locally, sync when connected

## Feature Roadmap

### Phase 1 - MVP (Sellable Version)

| Module | Features |
|--------|----------|
| **Marketing** | Public landing page, pricing page, contact form |
| **Demo** | Instant demo environment with sample data |
| **Super Admin** | Tenant management, impersonation, billing dashboard |
| **Foundation** | Tenants, subdomain routing, user authentication |
| **Users & Permissions** | Internal/external users, roles, Spatie permissions |
| **Tenant Admin** | Settings, user management, role customization |
| **Setup** | Bases, aircraft types, aircraft registrations |
| **People** | Crew profiles, client management (individual + corporate) |
| **Flight Operations** | Flight planning, flight legs, crew/pax assignment |
| **Flight Execution** | Flight log, times, fuel, remarks |
| **Weight & Balance** | W&B calculator with PDF export |
| **Documents** | Checklists, flight documents (Type A EFB) |
| **Reporting** | Client flight history PDF |
| **Compliance** | Audit logs, calculation records, compliance PDF exports |
| **Notifications** | In-app + email notifications (all-on) |
| **Billing** | Per-aircraft subscription, invoicing, trial period |
| **API** | Sanctum REST API (ready for iOS) |

### Phase 2 - Post-Launch

| Priority | Feature | Rationale |
|----------|---------|-----------|
| **2A** | iOS App (Swift/SwiftUI) | Offline capability for pilots |
| **2A** | Flight Risk Assessment (FRAT) | Safety-critical, often required |
| **2A** | Crew roster & duty time tracking | Legal compliance (flight/duty limits) |
| **2B** | Crew recency/medical/proficiency | Training compliance |
| **2B** | Pax course recency (HUET, etc.) | Offshore/charter requirement |
| **2B** | Notification preferences | User customization |
| **2C** | Pax boarding pass generation | Nice-to-have |
| **2C** | Pax luggage tracking | Nice-to-have |
| **2C** | EU region deployment | GDPR optimization |
| **Future** | Android app | Market expansion |

## Success Metrics

- [ ] First paying tenant onboarded
- [ ] 10 tenants milestone
- [ ] iOS app launched
- [ ] EU region deployed
- [ ] 50 tenants milestone

---

*Last updated: February 2026*

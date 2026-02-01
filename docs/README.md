# FlightData - Documentation

> Electronic Flight Bag (EFB) SaaS Application

This folder contains all planning, architecture decisions, and specifications for the FlightData application.

## Quick Reference

| Aspect | Decision |
|--------|----------|
| **Multi-tenancy** | Single database with `tenant_id` |
| **Web Stack** | Laravel 11, Livewire 3, Alpine.js, Tailwind |
| **Mobile** | Native Swift/SwiftUI (Phase 2) |
| **API** | Laravel Sanctum REST API |
| **Auth/Permissions** | Spatie Laravel-Permission |
| **Hosting** | DigitalOcean + Laravel Forge |
| **Database** | PostgreSQL |
| **Region** | US initially, EU expansion planned |

## Contents

### Core Documentation
- [Project Overview](./PROJECT_OVERVIEW.md) - Vision, goals, and phases
- [Glossary](./GLOSSARY.md) - Terminology definitions
- [Database Schema](./DATABASE_SCHEMA.md) - Tables and relationships

### Architecture Decision Records (ADRs)
- [001 - Multi-Tenancy](./architecture/001-multi-tenancy.md)
- [002 - API Strategy](./architecture/002-api-strategy.md)
- [003 - User Roles & Permissions](./architecture/003-user-roles-permissions.md)
- [004 - Subdomain Routing](./architecture/004-subdomain-routing.md)
- [005 - Data Residency](./architecture/005-data-residency.md)
- [006 - Offline Sync Strategy](./architecture/006-offline-sync.md)
- [007 - EFB Compliance](./architecture/007-efb-compliance.md)
- [008 - Technology Stack](./architecture/008-technology-stack.md)
- [009 - Hosting Infrastructure](./architecture/009-hosting.md)
- [010 - Marketing Site & Demo](./architecture/010-marketing-demo.md)
- [011 - Super Admin Panel](./architecture/011-super-admin.md)
- [012 - Tenant Admin & Config](./architecture/012-tenant-admin.md)
- [013 - Billing Model](./architecture/013-billing-model.md)
- [014 - Coding Conventions](./architecture/014-coding-conventions.md)

## Development Phases

### Phase 1 - MVP (Web Application)
Core flight operations with web interface. API-ready for future iOS app.

**Target:** Sellable first version

### Phase 2 - Mobile & Advanced Features
iOS app (Swift), flight risk assessment, crew management, passenger features.

---

*Last updated: February 2026*

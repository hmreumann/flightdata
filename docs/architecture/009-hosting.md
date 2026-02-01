# ADR 009: Hosting Infrastructure

## Status
**Decided** — DigitalOcean + Laravel Forge

## Context
Need reliable, affordable hosting for a multi-tenant Laravel SaaS with:
- Wildcard SSL for subdomains
- Queue workers
- PostgreSQL database
- Redis cache
- Easy deployment

## Decision
Use **DigitalOcean** for infrastructure with **Laravel Forge** for server management.

## Why Not Hostinger?

| Aspect | Hostinger | DigitalOcean + Forge |
|--------|-----------|---------------------|
| **Wildcard SSL** | Manual, painful | One-click |
| **Queue Workers** | DIY Supervisor | Built-in UI |
| **Deployment** | Manual SSH/git | Zero-downtime deploy |
| **Database Backups** | Manual | Scheduled to S3 |
| **Security Updates** | Manual | Automatic |
| **Support** | Generic hosting | Laravel-specific |
| **Cost** | ~$10-25/mo | ~$35/mo |

**Conclusion:** The extra $10-20/month saves significant DevOps time.

## Architecture

### Phase 1: Single Server

```
┌─────────────────────────────────────────────────────────────┐
│              DigitalOcean Droplet ($24/mo)                  │
│                   4GB RAM / 2 vCPU                          │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                    Ubuntu 22.04                      │   │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────────────┐   │   │
│  │  │  Nginx   │  │   PHP    │  │   PostgreSQL     │   │   │
│  │  │  (SSL)   │  │  8.3-FPM │  │      15          │   │   │
│  │  └──────────┘  └──────────┘  └──────────────────┘   │   │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────────────┐   │   │
│  │  │  Redis   │  │Supervisor│  │   Laravel App    │   │   │
│  │  │  (Queue) │  │(Workers) │  │                  │   │   │
│  │  └──────────┘  └──────────┘  └──────────────────┘   │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
                              │
                    Laravel Forge ($12/mo)
                    - Deployment
                    - SSL Management
                    - Worker Monitoring
```

### Phase 2: Separated Database (20+ tenants)

```
┌──────────────────────────┐    ┌──────────────────────────┐
│   App Server ($24/mo)    │    │ Managed DB ($15-50/mo)   │
│  - Nginx                 │───▶│ DigitalOcean Managed     │
│  - PHP                   │    │ PostgreSQL               │
│  - Redis                 │    │ - Auto backups           │
│  - Workers               │    │ - Failover               │
└──────────────────────────┘    └──────────────────────────┘
```

### Phase 3: High Availability (50+ tenants)

```
┌─────────────────────────────────────────────────────────────┐
│                    Load Balancer                             │
└───────────────────────┬─────────────────────────────────────┘
                        │
        ┌───────────────┼───────────────┐
        ▼               ▼               ▼
┌──────────────┐ ┌──────────────┐ ┌──────────────┐
│  App Server  │ │  App Server  │ │  App Server  │
│     #1       │ │     #2       │ │     #3       │
└──────────────┘ └──────────────┘ └──────────────┘
        │               │               │
        └───────────────┼───────────────┘
                        ▼
              ┌──────────────────┐
              │ Managed Postgres │
              │   (Primary)      │
              └────────┬─────────┘
                       │
              ┌────────┴─────────┐
              │ Read Replica     │
              │   (Optional)     │
              └──────────────────┘
```

## Cost Breakdown

### Phase 1 (MVP)

| Item | Monthly Cost |
|------|--------------|
| DigitalOcean Droplet (4GB) | $24 |
| Laravel Forge | $12 |
| Domain | ~$1 (annualized) |
| **Total** | **~$37/month** |

### Phase 2 (Growth)

| Item | Monthly Cost |
|------|--------------|
| DigitalOcean Droplet (8GB) | $48 |
| Managed PostgreSQL | $15-50 |
| Laravel Forge | $12 |
| Spaces (backups/files) | $5 |
| **Total** | **~$80-115/month** |

## Setup Guide

### 1. Create DigitalOcean Account

1. Sign up at digitalocean.com
2. Create a project "FlightData"
3. Note your API token for Forge

### 2. Connect Laravel Forge

1. Sign up at forge.laravel.com ($12/mo)
2. Connect DigitalOcean account
3. Create server:
   - Region: NYC1 (or nearest)
   - Size: 4GB RAM
   - PHP 8.3
   - PostgreSQL
   - Redis

### 3. Add Site

1. In Forge, add site: `flightdata.com`
2. Repository: your GitHub repo
3. Branch: `main`

### 4. Configure Wildcard SSL

1. Site → SSL → Let's Encrypt
2. Check "Wildcard" option
3. Domains: `flightdata.com`, `*.flightdata.com`
4. Forge handles DNS verification

### 5. Set Up Queue Workers

1. Site → Queue → Add Worker
2. Connection: redis
3. Queue: default,notifications,exports
4. Processes: 2

### 6. Configure Environment

```env
APP_NAME=FlightData
APP_ENV=production
APP_URL=https://flightdata.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=flightdata
DB_USERNAME=forge
DB_PASSWORD=secret

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_DOMAIN=.flightdata.com

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
```

### 7. Deploy Script

Forge default + customizations:

```bash
cd /home/forge/flightdata.com
git pull origin $FORGE_SITE_BRANCH

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm ci
npm run build

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan icons:cache

php artisan queue:restart
```

## DNS Configuration

### At Your Registrar

```
Type: A
Name: @
Value: [Droplet IP]
TTL: 3600

Type: A
Name: *
Value: [Droplet IP]
TTL: 3600
```

### Or Use DigitalOcean DNS

1. Point nameservers to DigitalOcean
2. Manage DNS in DO dashboard
3. Forge can auto-configure

## Backup Strategy

### Database Backups

```bash
# Forge scheduled backups
# Site → Backups → Add Backup Configuration
# - Frequency: Daily
# - Time: 03:00 UTC
# - Retention: 7 days
# - Storage: DigitalOcean Spaces
```

### File Backups

```bash
# Storage files (uploads, documents)
# Configure in Laravel:
FILESYSTEM_DISK=do_spaces

# config/filesystems.php
'do_spaces' => [
    'driver' => 's3',
    'key' => env('DO_SPACES_KEY'),
    'secret' => env('DO_SPACES_SECRET'),
    'region' => env('DO_SPACES_REGION'),
    'bucket' => env('DO_SPACES_BUCKET'),
    'endpoint' => env('DO_SPACES_ENDPOINT'),
],
```

## Monitoring

### Forge Monitoring (Included)

- Server health (CPU, RAM, disk)
- Queue worker status
- SSL expiration alerts

### Optional: Laravel Telescope (Dev)

```bash
composer require laravel/telescope --dev
php artisan telescope:install
```

### Optional: Sentry (Production Errors)

```bash
composer require sentry/sentry-laravel
```

## Scaling Triggers

| Metric | Current Limit | Action |
|--------|--------------|--------|
| CPU > 80% sustained | 2 vCPU | Upgrade droplet |
| RAM > 80% sustained | 4 GB | Upgrade droplet |
| DB connections > 100 | Shared | Move to managed DB |
| Response time > 500ms | - | Add caching, optimize |
| Tenants > 50 | - | Consider load balancer |

## Alternative: Laravel Vapor

If DevOps becomes burdensome, consider Vapor:

| Aspect | Forge + DO | Vapor |
|--------|-----------|-------|
| **Server management** | Minimal | Zero |
| **Scaling** | Manual | Automatic |
| **Cost (low traffic)** | Lower | Higher |
| **Cost (high traffic)** | Higher | Lower |
| **Complexity** | Low | Medium |

Migration to Vapor is straightforward if needed later.

## Consequences

### Positive
- ✅ Affordable for MVP (~$37/month)
- ✅ Forge handles SSL, deployments, workers
- ✅ Easy to scale up
- ✅ Full server control if needed
- ✅ Strong Laravel integration

### Negative
- ⚠️ Still some server management required
- ⚠️ Manual scaling (not auto)
- ⚠️ Single point of failure initially

---

*Last updated: February 2026*

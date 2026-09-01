# Turtle — Tenant Management Portal

A web application for managing rental properties, tenants, leases, maintenance tickets, rent payments, and tenancy applications. Designed for Docker and as a Home Assistant app.

## Quick Start

### Docker

```bash
git clone --branch stable https://github.com/jessica12ryan/Turtle.git
cd Turtle
docker compose up -d --build
open http://localhost
```

The first boot presents a setup wizard. Choose **New Installation** to configure site information or **Restore Backup** to upload a `.turtle` backup.

**Email testing:** http://localhost:8025 (Mailpit)

### Home Assistant App

Turtle is available as a Home Assistant app in two variants:

| Add-on | Channel | Source |
|--------|---------|--------|
| **Turtle** | Stable | `turtle-ha/` |
| **Turtle (Dev)** | Development | `turtle-ha-dev/` (builds from `master`) |

Both support **ingress** (embedded in HA UI) and **direct access** via port.

## Features

- **Properties** — manage details, photos, heating type, security deposits, listing status
- **Tenants** — main/secondary tenants, lease dates, scheduled move-out, auto-archive
- **Leases & Documents** — upload with auto-titling, document types, email with attachments
- **Maintenance Tickets** — create, assign, comment, status tracking, file attachments
- **Rent Dashboard** — per-property payment tracking, status badges (paid/partial/unpaid)
- **Tenancy Applications** — public submission form, review workflow, convert to tenant
- **AI Assistant** — natural-language queries about properties, tenants, tickets
- **Calendar** — move-in, lease end, and scheduled move-out dates
- **Resources** — shared links page, general and staff-only categories
- **Backup & Restore** — full system backup (`.turtle` format) via admin settings
- **Email** — lightweight SMTP client, notification preferences per role
- **Role-Based Access** — Admin, Landlord, Property Manager, Maintenance, Tenant

## Permissions

Access control uses route middleware and granular permissions configurable in **Settings → Permissions**.

| Role | Scope |
|------|-------|
| **Admin** | Unrestricted access |
| **Landlord** | Full management of properties, tenants, staff, leases, tickets, rent |
| **Property Manager** | Assigned properties, tenants, tickets, rent |
| **Maintenance** | Tickets (view, update status, comment) |
| **Tenant** | Own tickets, assigned leases, rent status, resources |

## Email

Configured via **Settings → General** (SMTP) and **Settings → Notifications** (per-role preferences).

- **Docker dev**: Bundled Mailpit at `mailpit:1025`, UI at `localhost:8025`
- **HA add-on**: Mailpit bundled in-container, configurable port
- **Custom SMTP**: Set in Settings UI or `.env` file

## Localization

- Languages: English, French, Spanish — configurable in Settings or per-user
- Timezone: global default + per-user override
- Country: Canada or United States (provinces/states, postal/zip formats)
- NTP sync: configurable server, cached hourly, drift alert on dashboard

## Project Structure

```
www/                  Apache document root — controllers, views, core framework
database/             Schema (schema.sql), seed data (seed.sql), migrations (migrate.sh)
docker/php/           Dockerfile + entrypoint + PHP config
turtle-ha/            Home Assistant production add-on
turtle-ha-dev/        Home Assistant development add-on
docker-compose.yml    Local development environment
update.sh             Update script (git pull + docker compose up)
```

## Updating

**In-app** (admin): Settings → Updates → Apply Update (runs `git pull` + migrations).

**Manual**:
```bash
git checkout stable && git pull && docker compose up -d --build
```

**HA add-on**: Rebuild the add-on or use the in-app updater inside the container.

## Persistent Data

- MySQL database → `mysql-data` Docker volume
- Uploaded files (leases, photos, tickets) → `turtle-storage` Docker volume

## License

Dual-licensed: **AGPL-3.0** (`LICENSE-AGPL.md`) for the hosted application (network use) and **MIT** (`LICENSE-MIT.md`) for reusable library code. See `LICENSE-AGPL.md` for AGPL terms and `LICENSE-MIT.md` for MIT terms.

Maintainer: jessica12ryan — jessica12ryan@outlook.com

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for development setup, coding standards, and pull request guidelines.

## Releases

- **`stable`** — Tagged releases for production use
- **`master`** — Active development branch

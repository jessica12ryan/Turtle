# Turtle — Tenant Management Portal

A web application for managing rental properties, tenants, leases, and maintenance tickets.

## Quick Start

```bash
git clone https://github.com/jessica12ryan/Turtle.git
cd Turtle
docker compose up -d --build
open http://localhost
```

The first boot presents a setup wizard where you create your account (Landlord or IT Admin) and optionally load sample data.

**Email testing:** http://localhost:8025 (Mailpit)

## Roles

| Role | Permissions |
|---|---|
| **IT Admin** | Full system access: create/edit/archive/delete everything |
| **Landlord** | Create/edit/archive properties, tenants, leases, tickets, and staff |
| **Property Manager** | Create/edit/archive properties, tenants, leases, tickets |
| **Maintenance** | View assigned tickets, update ticket status, add comments |
| **Tenant** | View assigned properties/leases, create/view tickets for their property |

## Project Structure

```
www/              Apache document root — controllers, views, core framework
database/         Schema and seed data
docker/php/       Dockerfile + entrypoint + PHP config
docker-compose.yml
```

## Updating

```bash
git pull
docker compose up -d --build
```

## Persistent Data

- MySQL database → `mysql-data` Docker volume
- Uploaded files → `turtle-storage` Docker volume

To reset everything: `docker compose down -v`

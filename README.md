# Turtle — Tenant Management Portal

A web application for managing rental properties, tenants, leases, and maintenance tickets.

## Quick Start

```bash
git clone https://github.com/jessica12ryan/Turtle.git
cd Turtle
docker compose up -d
open http://localhost
```

That's it. The first build will take a few minutes (installing PHP dependencies, running migrations, seeding data). Subsequent starts are instant.

**Email testing:** http://localhost:8025 (Mailpit)

## Default Logins

| Email | Password | Role |
|---|---|---|
| admin@turtleapp.com | password | Landlord |
| manager@turtleapp.com | password | Property Manager |

## Project Structure

```
www/              Apache document root (the only public directory)
app/              PHP controllers, models, enums, middleware
config/           Server-side configuration (not web-accessible)
database/         Migrations and seeders
routes/           URL definitions
resources/views/  Blade templates
storage/          Logs, cache, uploaded files (persistent volume)
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

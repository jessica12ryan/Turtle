# Turtle — Tenant Management Portal

A web application for managing rental properties, tenants, leases, and maintenance tickets.

Built with **Laravel 13** + **MySQL 8** + **Apache**. Runs entirely in Docker.

## Quick Start

```bash
# 1. Clone and enter
git clone https://github.com/jessica12ryan/Turtle.git turtle
cd turtle

# 2. Create environment file
cp .env.example .env

# 3. Build and start containers
docker compose build --no-cache app
docker compose up -d

# 4. Install PHP dependencies
docker compose exec app composer install

# 5. Generate app key
docker compose exec app php artisan key:generate

# 6. Generate database key (for encryption)
docker compose exec app php artisan key:generate --show

# 7. Run migrations and seed data
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed

# 8. Install and build frontend assets
docker compose exec app npm install
docker compose exec app npm run build

# 9. Start queue worker (for emails)
docker compose exec -d app php artisan queue:work

# 10. Open the app
open http://localhost
```

**Email testing:** open http://localhost:8025 (Mailpit)

## Default Logins

| Email | Password | Role |
|---|---|---|
| admin@turtleapp.com | password | Landlord |
| manager@turtleapp.com | password | Property Manager |

## Services

| Container | Service | Ports |
|---|---|---|
| `app` | PHP 8.3 + Apache (mod_php) | 80 |
| `mysql` | MySQL 8.0 | 3306 |
| `mailpit` | Email testing (SMTP + UI) | 1025 / 8025 |

## Project Structure

```
turtle/
├── www/              ← Apache document root (the only public directory)
│   ├── index.php     ← Laravel entry point
│   ├── .htaccess     ← URL rewriting rules
│   └── build/        ← Compiled CSS/JS assets
├── app/              ← PHP controllers, models, enums, middleware
├── config/           ← Server-side configuration (not web-accessible)
├── database/         ← Migrations and seeders
│   └── migrations/   ← 12 migration files
├── routes/           ← URL definitions (web.php, auth.php, console.php)
├── resources/views/  ← Blade templates
├── storage/          ← Logs, cache, uploaded files (persistent volume)
├── docker/           ← Dockerfile + PHP config
│   └── php/
│       ├── Dockerfile
│       └── php.ini
├── docker-compose.yml
├── update.sh         ← One-command update script
└── .github/workflows/ ← CI pipeline
```

## Key Features

### Role-Based Access

| Role | Capabilities |
|---|---|
| **Landlord** | Full access — companies, properties, tenants, leases, tickets |
| **Property Manager** | Same as Landlord minus company-level settings |
| **Maintenance** | View assigned tickets, comment, update status |
| **Tenant** | Own dashboard, create tickets, view leases |

### Tenant Onboarding

1. Landlord/PM goes to Tenants → Invite Tenant
2. Enters email + property assignment
3. System creates account with temp password
4. Email sent (check Mailpit at localhost:8025)
5. Tenant logs in, forced to change password immediately

### Ticket System

- Tenants create tickets with category and priority
- Staff can assign to maintenance, add internal notes
- Status workflow: open → in_progress → resolved → closed
- Email notifications on status changes and assignment

### Archival (Soft Delete)

- Nothing is permanently deleted
- Tenants can be moved out (scheduled or immediate)
- Archived data hidden from tenants but visible to staff
- Scheduled move-outs processed daily via cron

## Updating

```bash
./update.sh
```

This pulls latest code, rebuilds containers, installs deps, runs migrations, and clears cache.

## Persistent Data

Data survives container rebuilds:

- **MySQL database** → `mysql-data` Docker volume
- **Uploaded files** (leases, IDs) → `turtle-storage` Docker volume

To wipe everything: `docker compose down -v`

## Environment Variables

See `.env.example` for all available settings. Key ones:

| Variable | Default | Description |
|---|---|---|
| `DB_DATABASE` | turtle | MySQL database name |
| `DB_USERNAME` | turtle | MySQL user |
| `DB_PASSWORD` | turtle | MySQL password |
| `MAIL_HOST` | mailpit | SMTP host (container name) |
| `MAIL_PORT` | 1025 | SMTP port |

# Turtle — Tenant Management Portal

A web application for managing rental properties, tenants, leases, and maintenance tickets.

## Quick Start (Docker)

```bash
# 1. Clone and enter the project
git clone <repo-url> turtle
cd turtle

# 2. Copy environment file
cp .env.example .env

# 3. Start Docker containers
docker compose up -d

# 4. Install dependencies (inside container)
docker compose exec laravel.test composer install
docker compose exec laravel.test npm install

# 5. Build frontend assets
docker compose exec laravel.test npm run build

# 6. Run migrations and seed data
docker compose exec laravel.test php artisan migrate
docker compose exec laravel.test php artisan db:seed

# 7. Start queue worker (for emails)
docker compose exec -d laravel.test php artisan queue:work

# 8. Open in browser
open http://localhost
```

## Using Laravel Sail (Alternative)

If you prefer using the `sail` command instead of raw `docker compose`:

```bash
# Start containers
./vendor/bin/sail up -d

# Run commands
./vendor/bin/sail composer install
./vendor/bin/sail npm install && ./vendor/bin/sail npm run build
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed
./vendor/bin/sail artisan queue:work &

open http://localhost
```

## Default Login

After seeding, log in with:

| Email | Password | Role |
|---|---|---|
| admin@turtleapp.com | password | Landlord |
| manager@turtleapp.com | password | Property Manager |

## Forgot Password Flow

1. Click "Forgot your password?" on the login page
2. Enter the email address
3. Check Mailpit at http://localhost:8025 for the reset link
4. Follow the link to reset your password

## Tenant Invite Flow

1. Log in as Landlord or Property Manager
2. Go to Tenants → Invite Tenant
3. Enter name, email, property
4. An email with temporary password is sent (check Mailpit)
5. Tenant logs in and is forced to change password

## Deployment

### Option A: Docker on a VM

```bash
# On your VM (Ubuntu/Debian):
sudo apt update && sudo apt install docker.io docker-compose git -y
git clone <repo-url> turtle && cd turtle
docker compose up -d
```

### Option B: Update existing deployment

```bash
./update.sh
```

This pulls latest changes, rebuilds containers, runs migrations, and clears cache.

## Architecture

- **www/** — Web server document root (only publicly accessible directory)
- **app/** — PHP controllers, models, enums, middleware, notifications
- **config/** — Server-side configuration (not web-accessible)
- **database/** — Migrations and seeders
- **routes/** — URL route definitions
- **storage/** — Uploaded files, logs, cache (persistent via Docker volumes)

## Data Persistence

- MySQL data: stored in Docker volume `sail-mysql`
- Uploaded files: stored in Docker volume `sail-storage`
- Both survive `docker compose down` and `docker compose up -d --build`
- To fully reset: `docker compose down -v`

# Turtle — Tenant Management Portal

A web application for managing rental properties, tenants, leases, and maintenance tickets.

## Quick Start

```bash
# 1. Clone and enter the project
git clone <repo-url> turtle
cd turtle

# 2. Copy environment file
cp .env.example .env
# (or use the included .env)

# 3. Start containers
docker compose up -d

# 4. Install PHP dependencies
docker compose exec app composer install

# 5. Run migrations and seed data
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed

# 6. Install and build frontend assets
docker compose exec app npm install
docker compose exec app npm run build

# 7. Start queue worker (for emails)
docker compose exec -d app php artisan queue:work

# 8. Open in browser
open http://localhost
```

## Default Login

After seeding, log in with:

| Email | Password | Role |
|---|---|---|
| admin@turtleapp.com | password | Landlord |
| manager@turtleapp.com | password | Property Manager |

## Forgot Password / Email Testing

1. Click "Forgot your password?" on the login page
2. Enter the email address
3. Check Mailpit at **http://localhost:8025** for the reset link
4. Follow the link to reset your password

## Tenant Invite Flow

1. Log in as Landlord or Property Manager
2. Go to Tenants → Invite Tenant
3. Enter name, email, property
4. An email with temporary password is sent (check Mailpit at http://localhost:8025)
5. Tenant logs in and is forced to change password

## Updating

```bash
./update.sh
```

This pulls latest changes, rebuilds containers, installs dependencies, runs migrations, and clears cache.

## Data Persistence

- MySQL data: `mysql-data` Docker volume
- Uploaded files: `turtle-storage` Docker volume
- Both survive `docker compose down` and `docker compose up -d --build`
- To fully reset: `docker compose down -v`

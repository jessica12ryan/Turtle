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

### In-app (recommended)
1. Go to **Settings → Updates** (admin only)
2. Click **Check Now** to see available updates
3. Click **Apply Update** — runs `git pull` + migrations automatically
4. Reload the page when complete

### Manual
```bash
git pull
docker compose up -d --build
```

## Email Configuration

Set SMTP credentials in `.env` (development uses Mailpit on port 1025):

```
MAIL_HOST=smtp.domain.com
MAIL_PORT=587
MAIL_USERNAME=your@email.com
MAIL_PASSWORD=your-password
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME=Turtle
```

**Free SMTP options:** Brevo (300/day), Mailtrap (4k/month), Mailjet (6k/month)

## Persistent Data

- MySQL database → `mysql-data` Docker volume
- Uploaded documents (leases) → `turtle-storage` Docker volume
- Property photos → `turtle-storage` Docker volume (falls back to `/tmp` if unwritable)

To reset everything: `docker compose down -v`

## Property Photos

Upload from the property edit page. Supported formats: JPG, PNG, GIF, WebP.
- **Set as Main** — shown as thumbnail on the property index page
- **Download** — each photo has a download button
- Storage falls back to `sys_get_temp_dir()` when the Docker volume is not writable

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

### Via Settings UI (recommended)
Go to **Settings → General** (admin only) to configure SMTP credentials in the browser. Settings are stored in the database and override `.env` values.

### Via .env (fallback)
```
MAIL_HOST=smtp.domain.com
MAIL_PORT=587
MAIL_USERNAME=your@email.com
MAIL_PASSWORD=your-password
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME=Turtle
```

**Free SMTP options:** Brevo (300/day), Mailtrap (4k/month), Mailjet (6k/month)

## Resources

A shared links page available to all users. Admins, landlords, and property managers can add, edit, and delete resource links (URLs with optional descriptions). Accessible from the top navigation bar.

## Calendar

Shows tenant move-in dates (green), move-out dates (red), and lease end dates (yellow) on an interactive monthly calendar. Not available to tenants. Data is fetched via a JSON API endpoint at `/calendar/events` for extensibility (future notices, evictions, etc.).

## Persistent Data

- MySQL database → `mysql-data` Docker volume
- Uploaded documents (leases) → `turtle-storage` Docker volume
- Property photos → `turtle-storage` Docker volume (falls back to `/tmp` if unwritable)

## Restoring Archived Items

Only **IT Admins** can restore archived records.
- **Property restore** — cascades to tenants, leases, and tickets (reverse of archive cascade)
- **Individual restore** — tenants, leases, tickets, and staff can be restored one at a time
- Restore buttons appear on index pages when "Show archived" is active

## Tenant Management

- Adding a tenant requires **Lease Start** (date) and optionally **Lease End** (date)
- Email addresses are unique across the entire system — even archived records block re-use with a "Email exists in archived tenant/staff member" warning
- Tenant names on the property detail page link to the tenant's profile

## Property Photos

Upload from the property edit page. Supported formats: JPG, PNG, GIF, WebP.
- **Set as Main** — shown as thumbnail on the property index page
- **Download** — each photo has a download button
- Storage falls back to `sys_get_temp_dir()` when the Docker volume is not writable

# Turtle — Tenant Management Portal

A web application for managing rental properties, tenants, leases, and maintenance tickets.

## Quick Start

```bash
git clone https://github.com/jessica12ryan/Turtle.git
cd Turtle
docker compose up -d --build
open http://localhost
```

The first boot presents a multi-step setup wizard where you configure site information (name, logo, timezone, SMTP), create your admin account, and optionally load sample data.

**Email testing:** http://localhost:8025 (Mailpit)

## Permissions

Access control uses a two-layer system: **route middleware** (which pages a role can reach) and **granular permissions** (what actions a role can take on those pages).

Default permissions are assigned per role, but admins can override them in **Settings → Permissions** by switching from "Use defaults" to "Custom" and toggling individual permissions for each role.

| Role | Typical access |
|---|---|
| **Admin** | Unrestricted — bypasses all permission checks |
| **Landlord** | Properties, tenants, leases, tickets, staff, resources, calendar |
| **Property Manager** | Properties, tenants, leases, tickets, resources, calendar |
| **Maintenance** | Tickets (view assigned, update status, comment) |
| **Tenant** | Own tickets, assigned leases/units, resources |

## Project Structure

```
www/              Apache document root — controllers, views, core framework
database/         Schema and seed data
docker/php/       Dockerfile + entrypoint + PHP config
docker-compose.yml
```

## Updating

### In-app (recommended)
1. Go to **Settings → Updates** (admin only) — the page auto-checks for updates on load
2. If an update is available, click **Apply Update** — runs `git pull` + migrations automatically
3. Each step tracks its exit code; only non-zero exit codes produce error output in the progress view
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

## Route Middleware

Page-level access is enforced by middleware strings on route definitions:
- `role:admin` — admin only
- `role:admin,landlord` — admin or landlord
- `role:admin,landlord,property_manager` — admin, landlord, or property manager
- `role:landlord,property_manager` — admin, landlord, or property manager
- `role:tenant` — tenant only
- `role:staff` — any non-tenant role (admin, landlord, property_manager, maintenance)

## Resources

A shared links page available to all users. Admins, landlords, and property managers can add, edit, and delete resource links (URLs with optional descriptions). Accessible from the top navigation bar.

## Tenant Lease Dates & Scheduled Move-Out

When creating a tenant, Lease Start is required and Lease End is optional (leave blank for month-to-month). A Scheduled Move-Out date can also be set.

- **Main tenants** control lease dates for their property. Only main tenants can edit Lease Start/End and Scheduled Move-Out dates on the edit page.
- **Secondary tenants** (non-main) inherit lease dates from the main tenant. Their date fields are read-only on both create and edit, with a note: "Lease dates must be changed on main tenant."
- When a main tenant is **archived** (moved out) or **deleted**, all secondary tenants on the same property are also archived/deleted, along with their linked leases. Restoring a main tenant also restores secondary tenants.
- When a **Scheduled Move-Out** date is reached (compared against `CURDATE()`), the tenant is auto-archived once per hour, along with cascade for main tenants (secondary tenants + leases).
- The auto-archive check runs on every page load but is rate-limited to once per hour. The last check time is stored in the `settings` table as `last_moveout_check`.

## Timezone & NTP

The application maintains its own timezone and NTP configuration for accurate time tracking:

- **Timezone** is configurable globally in **Settings → General** (admin only) and is applied via `date_default_timezone_set()` at boot. Default: `America/New_York`.
- **Per-user timezone override** — All create/edit forms for staff and tenants include a Timezone dropdown with "Use default timezone" as the default option. Users can also set their own timezone on the **Profile** page. When set, the user's timezone overrides the global default for that user. The timezone is applied in the Router's `auth` middleware after login verification.
- **NTP Server** is checked on the home page for admin users. The default server is `time.gov` (via `https://time.gov/actualtime.cgi`). Results are cached for 1 hour in the `settings` table.
- The check uses PHP's `curl` extension (preferred) or falls back to `file_get_contents()` if curl is unavailable. Set `timezone` to blank to disable the check entirely.
- The Dockerfile installs `php-curl` via `docker-php-ext-install curl` for reliable HTTPS requests. After updating the Dockerfile, rebuild the container with `docker compose build php` then `docker compose up -d`.
- If the NTP server cannot be reached, the function also tries `www.google.com` as a fallback (parsing the `Date` response header). If all external HTTP requests fail, the environment is assumed offline and the warning is shown only briefly.
- A yellow warning alert appears if the system time drifts more than 60 seconds from NTP time.
- The NTP server URL is configurable in **Settings → General**. Setting it to blank disables the check entirely.
- All scheduled move-out checks use `CURDATE()` in MySQL (database time), not system time, to avoid timezone drift issues.

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

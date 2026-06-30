# Turtle — Tenant Management Portal

A web application for managing rental properties, tenants, leases, maintenance tickets, and rent payments.

## Quick Start (Docker)

```bash
git clone https://github.com/jessica12ryan/Turtle.git
cd Turtle
docker compose up -d --build
open http://localhost
```

The first boot presents a setup wizard. Choose **New Installation** to configure site information (name, logo, localization, timezone, SMTP), create your admin account, and optionally load sample data. Choose **Restore Backup** to upload a `.turtle` backup file and restore a previous installation.

**Email testing:** http://localhost:8025 (Mailpit)

## Quick Start (Home Assistant Add-on)

Turtle is available as a Home Assistant add-on in two variants:

| Add-on | Dockerfile | Description |
|--------|-----------|-------------|
| **Turtle** | `turtle-ha/` | Stable production build |
| **Turtle (Dev)** | `turtle-ha-dev/` | Development channel — builds from `development` branch |

Both add-ons support **ingress** (embedded in HA UI) and **direct access** via port. The build config (`build.yaml`) and add-on config (`config.yaml`) follow the standard HA add-on structure. An AppArmor profile (`apparmor.txt`) is included and auto-loaded.

## Setup Wizard

The first boot redirects to `/setup`. Two paths are available:

1. **New Installation** — 5-step wizard:
   - Step 1: Site name, logo, NTP server
   - Step 2: Default country, language, timezone
   - Step 3: Admin account creation
   - Step 4: SMTP configuration (optional, can be skipped)
   - Step 5: Load sample data toggle + finish
2. **Restore Backup** — upload a `.turtle` file, then log in with restored credentials.

## Permissions

Access control uses a two-layer system: **route middleware** (which pages a role can reach) and **granular permissions** (what actions a role can take on those pages).

Default permissions are assigned per role, but admins can override them in **Settings → Permissions** by switching from "Use defaults" to "Custom" and toggling individual permissions for each role.

| Role | Typical access |
|---|---|
| **Admin** | Unrestricted — bypasses all permission checks |
| **Landlord** | Properties, tenants, leases, tickets, staff, resources, calendar, rent dashboard, AI Assistant, tenancy applications |
| **Property Manager** | Assigned properties, their tenants, leases, tickets, resources, calendar, rent dashboard, AI Assistant, tenancy applications |
| **Maintenance** | Tickets (view assigned, update status, comment) |
| **Tenant** | Own tickets, assigned leases/units, resources, rent status |

## Project Structure

```
www/                  Apache document root — controllers, views, core framework
database/             Schema (schema.sql), seed data (seed.sql), migrations (migrate.sh)
docker/php/           Dockerfile + entrypoint + PHP config
turtle-ha/            Home Assistant production add-on (Dockerfile, config.yaml, apparmor.txt, rootfs/)
turtle-ha-dev/        Home Assistant dev add-on (same structure, development channel)
docker-compose.yml    Docker Compose for local development
update.sh             Update script for HA add-on containers
```

## Rent Tracking

Rent amounts and due days are configured per-property. Payments are recorded against the main tenant of each property.

- **Rent Dashboard** at `/rent` — shows total expected/collected, per-property status (paid/partial/unpaid), and quick links
- **Property detail** — rent summary with status badge and payment history; record payment form (auto-linked to main tenant)
- **My Rent** card on tenant home page — per-property rent status at a glance
- **Permissions** — `rents.access`, `rents.payments.create`, `rents.payments.edit`, `rents.payments.archive`, `rents.payments.restore`. Delete is hardcoded to admin role only.
- Payments cascade with tenant archives/restores — archiving a tenant or property archives its linked payments

## Backup & Restore

Admins can create and restore full system backups via **Settings → Backup & Restore**.

- Format: `.turtle` file (standard zip archive)
- Contents: full database dump (`SHOW CREATE TABLE` + `SELECT *`), uploaded files, `.env`
- Restore drops all existing tables and re-imports, then logs out the current user
- The setup wizard also supports restore on first boot (no admin login required)

## Updating

### In-app (recommended for Docker)
1. Go to **Settings → Updates** (admin only) — the page auto-checks for updates on load
2. If an update is available, click **Apply Update** — runs `git pull` + migrations automatically
3. Each step tracks its exit code; only non-zero exit codes produce error output in the progress view
4. Reload the page when complete

### Manual
```bash
git pull
docker compose up -d --build
```

### HA Add-on
The add-on runs `git pull` at container boot (before schema load) to ensure fresh code despite Docker build caching. Run-time updates can also be triggered via **Settings → Updates** using the in-app updater, which runs `git pull` as the apache user.

## Email Configuration

The app includes a lightweight SMTP client (no external mail library). Templates use the custom site logo if one has been uploaded via **Settings → General**.

### Default (Docker dev)
Mail runs through the bundled **Mailpit** container — no configuration needed:
- Host: `mailpit`, Port: `1025`, no authentication
- Web UI at http://localhost:8025

### Via Settings UI (recommended)
Go to **Settings → General** (admin only) to configure SMTP credentials in the browser. Settings are stored in the database and override `.env` values. Leave username/password blank to connect without authentication.

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
- When a main tenant is **archived** (moved out) or **deleted**, all secondary tenants on the same property are also archived/deleted, along with their linked leases and payments. Restoring a main tenant also restores secondary tenants and payments.
- When a **Scheduled Move-Out** date is reached (compared against `CURDATE()`), the tenant is auto-archived once per hour, along with cascade for main tenants (secondary tenants + leases + payments).
- The auto-archive check runs on every page load but is rate-limited to once per hour. The last check time is stored in the `settings` table as `last_moveout_check`.

## Timezone & NTP

The application maintains its own timezone, default country, and NTP configuration for accurate time tracking:

- **Localization** — country + timezone configurable in **Settings → General** (admin only). Default country pre-selects Canada or the US when adding new properties. Timezone is applied via `date_default_timezone_set()` at boot. Default country: `US`, default timezone: `America/New_York`.
- **Per-user timezone override** — Staff and tenant create/edit forms include a Timezone dropdown. Users can also set their own timezone on the **Profile** page. When set, it overrides the global default.
- Property addresses support **Canada** (provinces, A1A 1A1 postal codes) and **the United States** (states, 12345 zip codes). Select the country on the property form to switch between region lists and label formats.
- **NTP Server** is checked on the home page for admin users. Default: `time.gov`. Results cached for 1 hour. Falls back to `www.google.com` (parsing `Date` header). A yellow warning appears if system time drifts >60 seconds.
- All scheduled move-out checks use `CURDATE()` in MySQL (database time), not system time, to avoid timezone drift issues.

## Calendar

Shows tenant move-in dates (green), lease end dates (yellow), and scheduled move-out dates (orange) on an interactive monthly calendar. Not available to tenants. Data is fetched via a JSON API endpoint at `/calendar/events` for extensibility (future notices, evictions, etc.).

## Persistent Data

- MySQL database → `mysql-data` Docker volume
- Uploaded documents (leases) → `turtle-storage` Docker volume
- Property photos → `turtle-storage` Docker volume (falls back to `/tmp` if unwritable)

## Restoring Archived Items

Only **IT Admins** can restore archived records.
- **Property restore** — cascades to tenants, leases, tickets, and payments (reverse of archive cascade)
- **Individual restore** — tenants, leases, tickets, and staff can be restored one at a time; tenant restore also restores linked payments
- Restore buttons appear on index pages when "Show archived" is active

## Tenant Management

- Adding a tenant requires **Lease Start** (date) and optionally **Lease End** (date)
- Email addresses are unique across the entire system — even archived records block re-use with a "Email exists in archived tenant/staff member" warning
- Tenant names on the property detail page link to the tenant's profile
- Phone numbers are required on tenant create/edit forms

## Heating Type

Properties include a **Heating Type** field (required). Options: Oil - Forced Air, Oil - Hot Water, Electric, Propane, Natural Gas, Other. Appears as a dropdown on the property create/edit forms and is displayed on the property detail page under Property Details.

## Security Deposit

Properties include a **Security Deposit** field (optional, appears after rent fields on create/edit forms). The deposit amount is displayed on the property detail page under Property Details.

When recording a payment, a **Security Deposit** checkbox is available. Marking a payment as a deposit:
- Shows a **Deposit Paid** badge in the payment history table
- Uses the `is_security_deposit` column in the `payments` table
- The deposit amount is set per-property, but paid on a per-tenant basis so archived tenants retain their deposit payment history

### Permissions
- `rents.access`, `rents.payments.create`, `rents.payments.edit`, `rents.payments.archive`, `rents.payments.restore`
- Delete is hardcoded to admin role only

## Lease Type

Each tenancy has a **Lease Type** field on the `property_tenant` pivot table. Available options:
- **Fixed Term** — a specific start and end date
- **Year to Year** — renews annually
- **Month to Month** — renews monthly
- **Week to Week** — renews weekly
- **Other** — custom arrangement

The lease type appears as a required dropdown on the main tenant's create/edit form. Secondary tenants inherit lease dates from the main tenant, and the lease type is set only for the main tenant. On the property detail page, the lease type is displayed next to the main tenant name.

## Tenancy Applications

Prospective tenants can submit tenancy applications through a public form linked from the login page. Admins can enable/disable the form and add notes to applicants via **Settings → Applications** (moved up in the sidebar between General and Permissions).

### Application Form Sections
- **Property** — optional property selector with note "If you were given a property ID, enter it here."
- **Applicant Information** — last name, first name, middle name(s), birth date, phone, email
- **Current Address** — street, apt/suite, city/town, province/state, postal/zip code, date moved in, reason for leaving
- **Other Tenants (18+)** — repeatable section with full name, birth date, phone, email, relationship; each person gets their own address, employment, background, emergency contact, and other info sections
- **Other Occupants (Under 18)** — repeatable with name, age, relationship
- **Employment & Income Information** — occupation, employer, address, start date, supervisor, phone, other income sources
- **Emergency Contact** — name, relationship, phone
- **Background Information** — three yes/no questions with details (evicted, convicted, refused rent)
- **Personal References** — repeatable with name, relationship, phone
- **Other Information** — free text

### Management
- Accessible to admins, landlords, and property managers via the **Applications** link in the top navigation bar
- Admins can update application status (pending, reviewed, accepted, rejected) and add internal notes
- Applications can be archived/restored
- When applications are disabled, a friendly message is shown instead of a 404 error
- The `tenant_applications` table is auto-created on first access if it does not exist, and the `archived_at` column is auto-added if missing

## Property Photos

Upload from the property edit page. Supported formats: JPG, PNG, GIF, WebP.
- **Set as Main** — shown as thumbnail on the property index page
- **Download** — each photo has a download button
- Storage falls back to `sys_get_temp_dir()` when the Docker volume is not writable

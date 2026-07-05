# Turtle — Tenant Management Portal

A web application for managing rental properties, tenants, leases, maintenance tickets, and rent payments.

## Quick Start (Docker)

```bash
git clone --branch stable https://github.com/jessica12ryan/Turtle.git
cd Turtle
docker compose up -d --build
open http://localhost
```

The first boot presents a setup wizard. Choose **New Installation** to configure site information (name, logo, localization, timezone, SMTP), create your admin account, and optionally load sample data. Choose **Restore Backup** to upload a `.turtle` backup file and restore a previous installation.

**Email testing:** http://localhost:8025 (Mailpit web UI)

In the Home Assistant add-on, Mailpit is bundled inside the container (port 8025 mapped by default). The SMTP port defaults to 1025 and is configurable via the add-on options (`mailpit_port`).

## Quick Start (Home Assistant Add-on)

Turtle is available as a Home Assistant add-on in two variants:

| Add-on | Dockerfile | Description |
|--------|-----------|-------------|
| **Turtle** | `turtle-ha/` | Stable production build |
| **Turtle (Dev)** | `turtle-ha-dev/` | Development channel — builds from `master` branch |

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
| **Landlord** | Properties, tenants, leases, tickets, staff, resources, calendar, rent dashboard, AI Assistant, tenancy applications (archive/restore) |
| **Property Manager** | Assigned properties, their tenants, leases, tickets, resources, calendar, rent dashboard, AI Assistant, tenancy applications (view/edit) |
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
update.sh             Update script (git pull + docker compose up)
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

## Releases

Stable releases are tagged and maintained on the `stable` branch. Development (bleeding-edge) happens on `master`. To switch channels:

- **Stable** (default): `git checkout stable`
- **Development**: `git checkout master`

On Home Assistant, install **Turtle** for stable or **Turtle (Dev)** for the latest master.

## Updating

### In-app (recommended for Docker)
1. Go to **Settings → Updates** (admin only) — the page auto-checks for updates on load
2. Toggle **Update Channel** — **Stable** pulls from the `stable` branch (tagged releases); **Development** pulls from `master`
3. If an update is available, click **Apply Update** — runs `git pull` + migrations automatically
4. Each step tracks its exit code; only non-zero exit codes produce error output in the progress view
5. Reload the page when complete

### Manual
```bash
git checkout stable   # or master for development
git pull
docker compose up -d --build
```

### HA Add-on
The add-on clones the configured branch at build time. Run-time updates can be triggered via **Settings → Updates** using the in-app updater, which runs `git pull` inside the container.

## Email Configuration

The app includes a lightweight SMTP client (no external mail library). Templates use the custom site logo if one has been uploaded via **Settings → General**.

Email links (password reset, ticket notifications, welcome emails, document uploads) use the **Site URL** setting from **Settings → General → Branding**. If no Site URL is configured, links fall back to the internal server address (`$_SERVER['HTTP_HOST']`).

### Default (Docker dev)
Mail runs through the bundled **Mailpit** container — no configuration needed:
- Host: `mailpit`, Port: `1025`, no authentication
- Web UI at http://localhost:8025

### Default (HA Add-on)
Mailpit is bundled inside the add-on container and starts automatically. Leave `mail_host` empty in the add-on options to use it:
- Host: `127.0.0.1`, Port: `1025` (configurable via `mailpit_port`), no authentication
- Web UI at `http://homeassistant.local:8025` (or your HA host address)
- The SMTP port is configurable through the add-on configuration (`mailpit_port` option)

### Via Settings UI (recommended)
Go to **Settings → General** (admin only) to configure SMTP credentials in the browser. Settings are stored in the database and override `.env` values. Leave username/password blank to connect without authentication.

**Email Notifications** can be configured in **Settings → Notifications**, where admins can choose which roles receive each type of email (ticket assigned, ticket status updated, document uploaded, welcome emails, password reset, onboarding). The page uses a "Use default" / "Custom" toggle, matching the pattern of the Permissions page.

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

Resources can be categorized as **General** (visible to all users with `resources.access`) or **Staff** (visible only to staff members — admins, landlords, property managers, and maintenance). Tenants never see Staff Resources. The type is selected on the create/edit resource forms.

## Document Types

When uploading a document, a **Document Type** dropdown appears after the property/tenant section. Options: Lease Agreement, Rental Unit Condition, Government Issued Photo ID, Security Deposit Claim, Notice to Quit, Notice to Enter, and Other.

All selections except **Other** automatically populate and **gray out** the Title field. If **Government Issued Photo ID** is selected, radio buttons for **all active tenants** at the chosen property appear, and the title is automatically set to `ID - <TENANT_NAME>` (in all caps) based on the selected tenant. Selecting **Other** keeps the title field editable for a custom entry.

The document type is displayed as a badge on the document detail page and as a column in the document list.

When **"Send this document to tenant by email"** is checked during upload, the uploaded files are **attached directly to the email** (instead of sending a download link).

## Tenant Lease Dates & Scheduled Move-Out

When creating a tenant, Lease Start is required and Lease End is optional (leave blank for month-to-month). A Scheduled Move-Out date can also be set.

- **Main tenants** control lease dates for their property. Only main tenants can edit Lease Start/End and Scheduled Move-Out dates on the edit page.
- **Secondary tenants** (non-main) inherit lease dates from the main tenant. Their date fields are read-only on both create and edit, with a note: "Lease dates must be changed on main tenant."
- When a main tenant is **archived** (moved out) or **deleted**, all secondary tenants on the same property are also archived/deleted, along with their linked leases and payments. Restoring a main tenant also restores secondary tenants and payments.
- When a **Scheduled Move-Out** date is reached (compared against `CURDATE()`), the tenant is auto-archived once per hour, along with cascade for main tenants (secondary tenants + leases + payments).
- The auto-archive check runs on every page load but is rate-limited to once per hour. The last check time is stored in the `settings` table as `last_moveout_check`.

## Timezone & NTP

The application maintains its own timezone, default country, and NTP configuration for accurate time tracking:

- **Localization** — country + timezone configurable in **Settings → General** (admin only). Default country pre-selects Canada or the US when adding new properties. Timezone is applied via `date_default_timezone_set()` at boot. Default country: `CA`, default timezone: `America/New_York`.
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

### Main vs. Secondary Tenants

When creating a tenant, checking **Make this the main tenant** enables editing of the lease date fields, lease type, and emergency contact fields. If unchecked (secondary tenant), the lease start, lease end, scheduled move out, lease type, and emergency contact fields are hidden or **grayed out** and populated from the main tenant of the selected property. Secondary tenants do **not** store their own lease dates — all date data is fetched from the main tenant at display time, so updating the main tenant's dates automatically applies to all secondary tenants on that property.

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

The lease type appears as a required dropdown on the main tenant's create/edit form. The lease type is set only for the main tenant. On the property detail page, the lease type is displayed under **Property Details** when a tenant is assigned, and also shown next to the main tenant's name in the Tenants section.

## Emergency Contact

Main tenants can optionally provide an **Emergency Contact Name** and **Emergency Contact Phone** on their create/edit form (fields appear after the Lease Type dropdown). These fields are only visible for main tenants and are stored on the `property_tenant` pivot table. The phone number uses the same `(###) ###-####` mask as the tenant's primary phone field. Emergency contact information is displayed on the tenant's show page.

## Tenancy Applications

Prospective tenants can submit tenancy applications through a public form linked from the login page. Admins can enable/disable the form and add notes to applicants via **Settings → Applications** (moved up in the sidebar between General and Permissions).

### Application Form Sections
- **Property** — optional property selector with note "If you were given a property ID, enter it here."
- **Expected Move In Date** — required; pre-fills Lease Start on conversion
- **Applicant Information** — last name, first name, middle name(s), birth date, phone, email
- **Government Issued Photo ID** — required upload (JPEG, PNG, GIF, WebP, or PDF) for primary applicant and each other tenant
- **Current Address** — street, apt/suite, city/town, province/state, postal/zip code, date moved in (required), reason for leaving (required)
- **Other Tenants (18+)** — repeatable with full name, birth date, phone, email, relationship, photo ID; each person gets their own address (date moved in and reason for leaving are required), employment, background, emergency contact, and other info sections
- **Other Occupants (Under 18)** — repeatable with name, age, relationship
- **Employment & Income Information** — occupation, employer, address, start date, supervisor, phone, other income sources
- **Emergency Contact** — name, relationship, phone
- **Background Information** — three yes/no questions with details (evicted, convicted, refused rent)
- **Personal References** — repeatable with name, relationship, phone
- **Other Information** — free text

### Permissions

| Permission | Admin | Landlord | Property Manager |
|---|---|---|---|
| `applications.view` | ✅ | ✅ | ✅ (scoped to assigned properties) |
| `applications.edit` | ✅ | ✅ | ✅ |
| `applications.archive` | ✅ | ✅ | ❌ |
| `applications.restore` | ✅ | ✅ | ❌ |
| `applications.delete` | ✅ | ❌ | ❌ |

- **Property Managers** only see applications for properties they are assigned to (via `property_manager_id` on the `properties` table).
- **Landlords** can view, update status, add notes, archive, and restore applications, but cannot delete them.
- **Admin** has unrestricted access (bypasses all permission checks), including permanent deletion.

### Statuses
Applications have one of four statuses: **New**, **In Progress**, **Accepted**, or **Rejected**. Admins and users with `applications.edit` can update the status via the show page.

### Archive / Restore
Applications can be archived (hidden from the default list) and restored. Landlords and admins can archive and restore; only admins can permanently delete.

### Delete
Permanent deletion is admin-only. The delete button appears on the show page and on archived entries in the index. This cannot be undone.

### Application-to-Tenant Conversion

Users with `applications.edit` permission can convert an accepted application into a tenant by navigating to `/applications/{id}/convert` (there is no dedicated button — use the URL directly).

The convert page is split into two columns:
- **Left column** — read-only review of the application data (applicant info, address, employment, emergency contact, other tenants)
- **Right column** — tenant creation form with pre-filled fields from the application (name, email, phone, property, emergency contact)

On conversion:
- A **tenant account** is created for the primary applicant (main tenant) with a welcome email option
- **Secondary tenant accounts** are created for each other tenant listed on the application
- The **Expected Move In Date** pre-fills the **Lease Start** field
- Each **Government Issued Photo ID** upload creates a separate lease document in `/leases` titled **`ID - FULL-NAME-ALL-CAPS`** (e.g., `ID - JOHN MICHAEL DOE`)
- The Full Name field has `autocomplete="off"` to prevent browser autofill from overriding the server-provided value
- Stale form data from other pages is prevented from leaking into the convert form via session tracking (`_old_app_id`)

### Settings
Admins can toggle the application form on/off and add custom notes for applicants via **Settings → Applications**. When disabled, a friendly message is shown instead of a 404 error.

The `tenant_applications` table is auto-created on first access if it does not exist, and the `archived_at` column is auto-added if missing.

## Property Photos

Upload from the property edit page. Supported formats: JPG, PNG, GIF, WebP.
- **Set as Main** — shown as thumbnail on the property index page
- **Download** — each photo has a download button
- Storage falls back to `sys_get_temp_dir()` when the Docker volume is not writable

# Turtle — Tenant Management Portal

A web application for managing rental properties, tenants, leases, maintenance tickets, rent payments, and tenancy applications. Designed for Docker and as a Home Assistant app.

## Quick Start

### Docker

```bash
git clone --branch stable https://github.com/jessica12ryan/Turtle.git
cd Turtle
docker compose up -d --build
open http://localhost
```

The first boot presents a setup wizard. Choose **New Installation** to configure site information or **Restore Backup** to upload a `.turtle` backup.

**Email testing:** http://localhost:8025 (Mailpit)

### Home Assistant App

Turtle is available as a Home Assistant app in two variants:

| Add-on | Channel | Source |
|--------|---------|--------|
| **Turtle** | Stable | `turtle-ha/` |
| **Turtle (Dev)** | Development | `turtle-ha-dev/` (builds from `master`) |

Both support **ingress** (embedded in HA UI) and **direct access** via port.

## Features

- **Properties** — manage details, photos, heating type, security deposits, listing status
- **Tenants** — main/secondary tenants, lease dates, scheduled move-out, auto-archive
- **Leases & Documents** — upload with auto-titling, document types, email with attachments
- **Maintenance Tickets** — create, assign, comment, status tracking, file attachments, archive/restore with conflict resolution
- **Rent Dashboard** — per-property payment tracking, status badges (paid/partial/unpaid)
- **Tenancy Applications** — public submission form, review workflow, convert to tenant
- **AI Assistant** — natural-language queries about properties, tenants, tickets
- **Calendar** — move-in, lease end, and scheduled move-out dates
- **Resources** — shared links page, general and staff-only categories
- **Backup & Restore** — full system backup (`.turtle` format) via admin settings
- **Messaging** — side overlay with online users, direct messages, unread badges, sound alerts, rate limiting, admin toggle, and searchable conversation history in Settings
- **Public Listings & Guest Chats** — anonymous visitors see listed properties with per-property ad copy and can start a private two-way conversation with the property team; staff reply from the Guest Chats inbox
- **Profile** — personal details, preferences, and profile picture
- **Email** — lightweight SMTP client, email and in-app notification preferences per role
- **Role-Based Access** — Admin, Landlord, Property Manager, Maintenance, Tenant

## Permissions

Access control uses route middleware and granular permissions configurable in **Settings → Permissions**.

| Role | Scope |
|------|-------|
| **Admin** | Unrestricted access |
| **Landlord** | Full management of properties, tenants, staff, leases, tickets, rent |
| **Property Manager** | Assigned properties, tenants, tickets, rent |
| **Maintenance** | Tickets (view, update status, comment) |
| **Tenant** | Own tickets, assigned leases, rent status, resources |

### Public listings and visitor chats

Visitors who are not signed in see the public property listings at `/` instead of the login form; a **Sign In** link is shown in the page header. Only properties marked as listed (and not archived) appear, with their main photo and the manager-authored ad description. Edit the description under the property's edit page; placeholders `{{name}}`, `{{address}}`, `{{city}}`, `{{province}}`, `{{rent}}`, `{{heating}}` and `{{deposit}}` are replaced when the card is rendered.

A visitor can message the property team from any card. This starts a private two-way conversation: the browser receives a long-lived cookie and a shareable URL, while the app stores only a SHA-256 hash of the access token. Only the holder of that URL can read the conversation. The visitor is also emailed the private link when they first write, so they can return even after clearing browser data; the "Return to a conversation" panel on `/listings` lists conversations saved in the current browser and can email a fresh link to the address used. Requesting a fresh link issues an additional token; earlier links keep working, and only hashes of all issued tokens are stored. Staff with the `guest_chats.access` permission (landlords, property managers and admins by default) read and reply from **Guest Chats**; replies are scoped to properties the staff member manages. New visitor messages also create an in-app notification and an email to the property's landlord, property manager and admins.

## Email

Configured via **Settings → General** (SMTP) and **Settings → Notifications** (per-role preferences).

- **Docker dev**: Bundled Mailpit at `mailpit:1025`, UI at `localhost:8025`
- **HA add-on**: Mailpit bundled in-container, configurable port
- **Custom SMTP**: Set in Settings UI or `.env` file

### Gmail OAuth2 (XOAUTH2)

1. In Google Cloud Console, create an **OAuth client ID** (Web application) with the Gmail API scope `https://mail.google.com/`.
2. Copy the exact **Redirect URI** shown in **Settings → General → Email (SMTP)** into the client's *Authorized redirect URIs*.
3. Save the Client ID and Client Secret in Turtle, choose **OAuth2 (Google)** as the authentication method, and click **Connect with Google**.
4. Set the SMTP host to `smtp.gmail.com`, port `587` (STARTTLS) or `465` (implicit TLS), and leave the password blank.

Alternatively, paste a refresh token generated outside Turtle into the **Refresh Token** field. Tokens are stored in the settings table, so they persist in Docker and the HA add-on. The add-on also accepts `mail_auth_mode`, `mail_oauth_client_id`, `mail_oauth_client_secret`, `mail_oauth_refresh_token` and `mail_oauth_email` options.

## Localization

- Languages: English, French, Spanish — configurable in Settings or per-user
- Timezone: global default + per-user override
- Country: Canada or United States (provinces/states, postal/zip formats)
- NTP sync: configurable server, cached hourly, drift alert on dashboard

## Project Structure

```
www/                  Apache document root — controllers, views, core framework
database/             Schema (schema.sql), seed data (seed.sql), migrations (migrate.sh)
docker/php/           Dockerfile + entrypoint + PHP config
turtle-ha/            Home Assistant production add-on
turtle-ha-dev/        Home Assistant development add-on
docker-compose.yml    Local development environment
update.sh             Update script (git pull + docker compose up)
```

## Updating

**In-app** (admin): Settings → Updates → Apply Update (runs `git pull` + migrations).

**Manual**:
```bash
git checkout stable && git pull && docker compose up -d --build
```

**HA add-on**: Rebuild the add-on or use the in-app updater inside the container.

## Persistent Data

- MySQL database → `mysql-data` Docker volume
- Uploaded files (leases, photos, tickets) → `turtle-storage` Docker volume

## License

Dual-licensed: **AGPL-3.0** (`LICENSE-AGPL.md`) for the hosted application (network use) and **MIT** (`LICENSE-MIT.md`) for reusable library code. See `LICENSE-AGPL.md` for AGPL terms and `LICENSE-MIT.md` for MIT terms.

Maintainer: jessica12ryan — jessica12ryan@outlook.com

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for development setup, coding standards, and pull request guidelines.

## Releases

- **`stable`** — Tagged releases for production use
- **`master`** — Active development branch

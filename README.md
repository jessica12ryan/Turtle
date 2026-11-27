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

**Email testing:** http://localhost:8025 (Mailpit). The UI is bound to `127.0.0.1` by default. To open it from another device on the same LAN, set `MAILPIT_UI_BIND` to this host's LAN IP in `.env` (for example `MAILPIT_UI_BIND=192.168.1.10`) and recreate the container. Do **not** set it to `0.0.0.0` — that would expose the UI (and its API) beyond the local network. SMTP stays on loopback; the app reaches it over the Compose network. If you access Mailpit through a DNS name rather than an IP, add that name to `MAILPIT_ALLOWED_HOSTS` to preserve DNS-rebinding protection.

The Home Assistant add-on publishes the same UI on port 8025, but only accepts connections from loopback and RFC 1918 private networks (`10/8`, `172.16/12`, `192.168/16`). All other source addresses receive HTTP 403.

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
- **Rent Dashboard** — per-property payment tracking by defined pay period (due day through the day before the next due day), status badges (paid/partial/unpaid/exempt), and period exemptions that only admins and landlords can mark
- **Tenancy Applications** — public submission form, review workflow, convert to tenant. The conversion dropdown offers vacant properties in your scope whether or not they are currently listed.
- **AI Assistant** — natural-language queries about properties, tenants, tickets. Admin-enabled per install; tenants never have access. Choose one provider in Settings → AI Assistant: OpenAI, Claude (Anthropic), Gemini (Google), OpenRouter, OpenCode (Zen gateway), or OpenCode Server (self-hosted HttpApi at e.g. `http://192.168.2.4:4096`, authenticated with HTTP Basic using username `opencode` and the configured password (a fallback to an empty username is attempted on 401)). Each provider keeps its own API key, and the model list can be refreshed from the provider before saving. Property and tenant identifiers are pseudonymized before any data leaves the installation; responses may be inaccurate.
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

Visitors who are not signed in see the public property listings at `/` instead of the login form; a **Sign In** link is shown in the page header. Only properties marked as listed (and not archived) appear, with their main photo and the manager-authored ad description. Edit the description under the property's edit page; placeholders `{{name}}`, `{{address}}`, `{{city}}`, `{{province}}`, `{{rent}}`, `{{heating}}` and `{{deposit}}` are replaced when the card is rendered. Long descriptions are clamped to three lines with a **See more** link that reveals the full text and, for properties with more than one photo, a thumbnail gallery. Those properties also show a **View all N photos** badge over the main photo; either the badge or a thumbnail opens a lightbox with previous/next navigation. Every photo URL is still authorised server-side for listed properties only.

A visitor can message the property team from any card. This starts a private two-way conversation: the browser receives a long-lived cookie and a shareable URL, while the app stores only a SHA-256 hash of the access token. Only the holder of that URL can read the conversation. Guests are identified by the email address they use, and have **one conversation per property** — messaging the same property again appends to that thread. The visitor is emailed the private link when they first write, plus a single "hub" link at `/listings/conversations/{token}` that lists all of their property conversations; the hub link also works after browser data is cleared. The "Return to a conversation" panel on `/listings` lists chats saved in the current browser and can email a fresh hub link to the address used. Issuing another link never invalidates earlier links, and only hashes of all issued tokens are stored. Staff with the `guest_chats.access` permission (landlords, property managers and admins by default) read and reply from **Guest Chats**; replies are scoped to properties the staff member manages. Every new visitor message creates an in-app notification and email for the assigned property manager only — or the landlord when the property has no manager; admins are not notified. Every staff reply emails the guest with a fresh direct link to the conversation.

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

**In-app** (admin): Settings → Updates → Apply Update (runs `git pull` + migrations). The admin dashboard checks for available updates automatically at most every six hours; Settings → Updates → Check for Updates runs an immediate check.

**Manual**:
```bash
git checkout stable && git pull && docker compose up -d --build
```

**HA add-on**: Rebuild the add-on or use the in-app updater inside the container.

`update.sh` (from a git checkout) is another option. Its temporary backup excludes `.env`, is deleted if the update fails, and a failed migration now aborts startup instead of being reported as a warning. Optional supply-chain checks can be enabled with environment variables:

| Variable | Effect |
| --- | --- |
| `UPDATE_EXPECTED_REMOTE` | Require `origin` to match this URL before fetching. |
| `UPDATE_EXPECTED_SHA` | Require the fetched branch tip to begin with this commit SHA. |
| `UPDATE_VERIFY_SIGNATURES=1` | Require the updated HEAD commit to have a verifiable Git signature. |

These checks are off by default so existing installations continue to update normally. The unauthenticated restore wizard at `/setup/restore` is only available on a fresh install with no active accounts; after that, use Settings → Backup & Restore.

Outbound fetches (update checks and NTP time) are restricted to public HTTPS endpoints: plain HTTP, embedded credentials, and private/loopback/link-local addresses are refused, including after redirects.

## Persistent Data

- MySQL database → `mysql-data` Docker volume
- Uploaded files (leases, photos, tickets) → `turtle-storage` Docker volume
- MySQL/MariaDB error logs are readable only by the web group (`0640`); the Settings → Logging page uses that group access, so no world-readable log file is needed.

## License

Dual-licensed: **AGPL-3.0** (`LICENSE-AGPL.md`) for the hosted application (network use) and **MIT** (`LICENSE-MIT.md`) for reusable library code. See `LICENSE-AGPL.md` for AGPL terms and `LICENSE-MIT.md` for MIT terms.

Maintainer: jessica12ryan — jessica12ryan@outlook.com

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for development setup, coding standards, and pull request guidelines.

## Releases

- **`stable`** — Tagged releases for production use
- **`master`** — Active development branch

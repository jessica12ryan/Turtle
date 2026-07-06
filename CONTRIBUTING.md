# Contributing to Turtle

Thank you for considering contributing to Turtle! This document outlines the guidelines and workflows for contributing.

## Table of Contents

- [Development Setup](#development-setup)
- [Branching Strategy](#branching-strategy)
- [Coding Standards](#coding-standards)
- [Security](#security)
- [Pull Request Process](#pull-request-process)

## Development Setup

### Docker (recommended)

```bash
git clone https://github.com/jessica12ryan/Turtle.git
cd Turtle
docker compose up -d --build
open http://localhost
```

The first boot presents a setup wizard. Choose **New Installation** to configure the site and create your admin account.

**Mailpit** (email testing): http://localhost:8025

### Home Assistant App

The app can be tested locally by pointing the app repository to your local clone. See the `turtle-ha/` and `turtle-ha-dev/` directories for app configurations.

## Branching Strategy

- **`stable`** — Tagged releases intended for production use. Only security fixes and critical patches are backported.
- **`master`** — Active development. All pull requests target `master`.

Feature branches should be named descriptively:

- `feat/short-description` for new features
- `fix/short-description` for bug fixes
- `refactor/short-description` for code improvements

## Coding Standards

### PHP

- Follow existing patterns in the codebase — mimic style of neighboring files.
- All user-facing strings must use the `__()` translation function and have entries in all language files (`www/lang/en.php`, `fr.php`, `es.php`).
- Use parameterized queries (`?` placeholders) for all database queries. Never concatenate user input into SQL strings.
- Escape output with `h()` (htmlspecialchars wrapper) in views. Never echo raw user input.
- All POST handlers must call `verify_csrf()` to validate the CSRF token.
- Use `redirectBack()` instead of `redirect(isset($_SERVER['HTTP_REFERER']) ...)` for open-redirect safety.

### Views

- New UI strings must use `<?= __('...') ?>` and be added to all three language files.
- Maintain compatibility with Home Assistant ingress — paths should use `asset()`, `base_url()`, and the ingress rewriting in `index.php`.

### JavaScript & CSS

- Keep JS in `www/assets/js/` and CSS in `www/assets/css/`.
- Tooltips use the `data-tooltip` attribute pattern defined in the global stylesheet.

## Security

If you discover a security vulnerability, **do not open a public issue**. Email the repository owner directly or use the GitHub Security Advisory tab.

### Security Best Practices

- All file uploads validate both extension **and** MIME type server-side (`finfo_file()`).
- Filenames in `Content-Disposition` headers must be sanitized with `sanitize_filename()` to prevent CRLF injection.
- Session idle timeout is enforced at 1 hour.
- Session cookies use `HttpOnly`, `SameSite=Lax`, and `Secure` (when HTTPS).

## Pull Request Process

1. Create a feature branch from `master`.
2. Make your changes following the coding standards above.
3. Run `php -l` on all modified PHP files to verify syntax.
4. If adding UI strings, update all three language files (`en.php`, `fr.php`, `es.php`).
5. Update documentation if your change affects public APIs, configuration, or UI behavior.
6. Open a pull request against `master` with a clear description of the change.
7. Ensure the PR description explains any database schema changes or migration requirements.

### PR Checklist

- [ ] PHP syntax check passes on all modified files
- [ ] UI strings use `__()` with translations in all 3 languages
- [ ] No breaking changes to existing functionality
- [ ] Home Assistant ingress compatibility maintained
- [ ] Documentation updated if needed

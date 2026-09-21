# Security Policy

## Supported Versions

Only the latest release receives security patches. Older versions are not supported.

| Version        | Supported |
|----------------|-----------|
| master/dev     | ✅        |
| stable/latest  | ✅        |
| < latest       | ❌        |

## Reporting a Vulnerability

If you discover a security issue in this repo, please open a private issue or
contact the maintainer via the GitHub repository at
https://github.com/jessica12ryan/fpp-os/security/advisories

You can expect:

1. **Acknowledgment** within 48 hours
2. **Regular updates** on progress (usually weekly)
3. A **fix and advisory** coordinated before public disclosure

Reports are reviewed and triaged within 7 days. If accepted, a patch is released as soon as a fix is ready.

Please do **not** report security issues through the public issue tracker if they
could be exploited before a fix is released.

## Security Practices

- Dependencies are updated weekly via Dependabot
- Use HTTPS in production; never disable SSL verification
- Keep the application containerized with the provided Docker setup
- Rotate secrets (APP_KEY, database passwords, SMTP credentials) on a regular schedule
- Enable two-factor authentication for admin accounts (if supported by your authentication provider)

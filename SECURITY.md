# Security Policy

## Supported Versions

Only the latest release receives security patches. Older versions are not supported.

| Version | Supported |
|---------|-----------|
| latest  | ✅        |
| < latest | ❌       |

## Reporting a Vulnerability

Report vulnerabilities privately by emailing the maintainer (see commit history or the project's GitHub profile for contact info). **Do not open a public issue.**

You can expect:

1. **Acknowledgment** within 48 hours
2. **Regular updates** on progress (usually weekly)
3. A **fix and advisory** coordinated before public disclosure

Reports are reviewed and triaged within 7 days. If accepted, a patch is released as soon as a fix is ready.

## Security Practices

- Dependencies are updated weekly via Dependabot
- Use HTTPS in production; never disable SSL verification
- Keep the application containerized with the provided Docker setup
- Rotate secrets (APP_KEY, database passwords, SMTP credentials) on a regular schedule
- Enable two-factor authentication for admin accounts (if supported by your authentication provider)

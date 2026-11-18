# Security Policy

Workerless runs inside your production application, so we treat security as a core feature, not an afterthought.

## Supported versions

| Version | Supported |
| ------- | --------- |
| 1.x     | Yes       |
| < 1.0   | No (pre-release) |

## Reporting a vulnerability

**Please do not open a public issue for security problems.**

Report it privately through GitHub:
**[Report a vulnerability](https://github.com/himacode-company/workerless/security/advisories/new)**

Please include:

- The Workerless, Laravel and PHP versions.
- Your server type (LiteSpeed, Apache, Nginx + PHP-FPM, ...).
- Steps to reproduce, and what an attacker could do.

What happens next:

1. We confirm we received your report.
2. We investigate and keep you updated.
3. We release a fix and publish a security advisory, crediting you unless you prefer to stay anonymous.

## Security design

- The self-call endpoint accepts no input and is protected by an HMAC signature derived from `APP_KEY`, with a short expiry and replay protection.
- The self-call URL comes from your config (`APP_URL`), never from the incoming request.
- Workerless never runs shell commands: no `exec`, no `proc_open`, no `PHP_BINARY`.
- The dashboard is closed by default outside the `local` environment. It opens only for logged-in users listed in `WORKERLESS_USER_IDS` or allowed by the `viewWorkerless` gate. Everyone else gets a 404.
- Job payloads are never shown in the dashboard and never stored in the history table.

# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-06-20

First release. Laravel 404 email alerts, now with full reporting on every "not
so great" request, a secured dashboard, and a JSON API.

### Added

#### 404 email alerts
- Instant email to your admins when a visitor hits a missing URL, including the
  URL, HTTP method, referer, IP address, user agent and timestamp.
- Per-URL throttling and ignore patterns to prevent floods from bots/scanners.

#### Reporting on failed requests
- Records every `4xx`/`5xx` response to the database for reporting. Recording
  skips silently until the migration has run, so it never spams your logs.
- `php artisan page-not-found:report` emails a digest — totals, the 4xx/5xx
  split, counts by status, and top paths/IPs/user-agents — with `--hours`,
  `--since`, `--to`, `--prune` and `--dry` options.
- The digest auto-registers on Laravel's scheduler (configurable frequency,
  time and pruning); no console-kernel edits required.

#### Dashboard & JSON API (off by default)
- Self-contained HTML dashboard at `/page-not-found` and a read-only JSON
  endpoint at `/api/page-not-found`, both supporting a `?hours=` window.

#### Access control & bot protection
- "Sign in with Google" protection for the dashboard and API, restricted to a
  configured email allow-list (fails closed when none is set). OAuth is
  hand-rolled via the HTTP client — no Socialite dependency.
- reCAPTCHA (v2 checkbox or v3 score) guarding the login entry point.

### Notes
- Every feature is toggleable from `.env` with safe defaults; emails only send
  once recipients are configured.
- Requires PHP `>= 8.4` and Laravel `^13.6`.

[1.0.0]: https://github.com/jeylabs/404-email-alert/releases/tag/v1.0.0

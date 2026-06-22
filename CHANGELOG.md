# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-06-22

Deeper insight into recorded requests, real-time alerting, and delivery beyond
email.

### Added

#### Multi-channel notifications
- The instant 404 alert, the digest report and the spike alerts are now Laravel
  notifications, deliverable to **Slack, Microsoft Teams, Discord and a generic
  JSON webhook** in addition to email. The mail channel reuses the existing
  templates; chat channels post to an incoming-webhook URL and are enabled per
  provider. Configure via the new `channels` config section.

#### Threshold / spike alerting
- Near-real-time alerts when error volume crosses a configurable threshold
  (e.g. "more than 25 server errors in 5 minutes"), with a per-rule cooldown.
- Evaluated as requests are recorded (rate-limited by `check_interval`) and via
  the new `page-not-found:monitor` command (with `--dry`), auto-scheduled every
  minute when enabled. Disabled by default; configured under `alerts`.

#### Time-series & trends
- The report now includes a zero-filled time-series bucketed by minute/hour/day,
  with a 4xx/5xx split per bucket. The dashboard renders a "Requests over time"
  chart, the digest email gains a "Busiest periods" summary, and the API exposes
  the data under `series`.

#### Bot vs human & referer classification
- Each recorded request is classified at write time as a bot/scanner or a human
  (extendable via `record.bot_user_agents`), and its referer as internal /
  external / direct. The report, dashboard and digest surface both splits;
  internal referers highlight your own pages linking to dead URLs.

#### Dashboard drill-down & filtering
- New `/page-not-found/requests` view lists the individual hits behind the
  aggregates, filterable by exact path, path search, status, window and
  human/bot. Top paths on the dashboard link into it.

### Changed
- Recording is now asynchronous by default: the database write is dispatched to
  a queued job (`RecordBadRequest`) so error responses are not slowed. With the
  `sync` queue connection it runs inline as before. Toggle with
  `PAGE_NOT_FOUND_RECORD_QUEUE`.
- Declared the `illuminate/database`, `illuminate/console`, `illuminate/routing`
  and `guzzlehttp/guzzle` dependencies the package relies on.

### Upgrade notes
- Run `php artisan migrate` — this release adds `is_bot` and `referer_internal`
  columns to the request log table. Recording for the new columns is skipped
  (and logged) until the migration has run.

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

[1.1.0]: https://github.com/jeylabs/404-email-alert/releases/tag/v1.1.0
[1.0.0]: https://github.com/jeylabs/404-email-alert/releases/tag/v1.0.0

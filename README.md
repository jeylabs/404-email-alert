# 404-email-alert

Laravel Page Not Found (404) Email Alerts — plus reporting on every not-so-great
request.

This package emails your system administrator whenever a visitor hits a URL that
does not exist (an HTTP `404` response). Each alert includes the requested URL,
HTTP method, referer, IP address, user agent and timestamp, so you can quickly
spot broken links and suspicious scanning activity.

On top of the instant 404 alert, it also **records every failed request** (any
`4xx` or `5xx` response) to the database and can email you a **periodic digest**
— counts by status code, the 4xx/5xx split, and the top offending paths, IP
addresses and user agents — so you get the bigger picture, not just one email
per missing page.

## Requirements

* PHP `>= 8.4`
* Laravel `^13.6`

## Installation

Install via Composer:

```bash
composer require jeylabs/404-email-alert
```

The service provider is registered automatically through Laravel package
auto-discovery — no manual registration required.

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Jeylabs\PageNotFoundEmailAlert\PageNotFoundEmailAlertServiceProvider" --tag=config
```

This creates `config/page-not-found-email-alert.php`. At a minimum, set the
recipient address(es). You can do this in `.env`:

```dotenv
PAGE_NOT_FOUND_ALERT_ENABLED=true
PAGE_NOT_FOUND_ALERT_TO=admin@example.com,ops@example.com
PAGE_NOT_FOUND_ALERT_SUBJECT="404 Page Not Found Alert"
PAGE_NOT_FOUND_ALERT_THROTTLE=60
```

### Options

| Key        | Description                                                                                     |
|------------|-------------------------------------------------------------------------------------------------|
| `enabled`  | Master on/off switch for alerts.                                                                 |
| `to`       | One or more recipient addresses (comma separated via env, or an array in the config file).       |
| `from`     | The from address/name. Defaults to your app's `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME`.            |
| `subject`  | Subject line of the alert email.                                                                 |
| `throttle` | Minutes to suppress duplicate alerts for the same URL (prevents flooding). `0` disables it.      |
| `ignore`   | Request paths that should never trigger an alert. Supports `*` wildcards (e.g. `favicon.ico`).   |

Alerts require a configured mail driver. If no recipients are set, or `enabled`
is `false`, no email is sent.

## Reporting on not-so-great requests

It works out of the box: install the package, run `php artisan migrate`, and
every failed (`4xx`/`5xx`) request is recorded. A daily digest is scheduled
automatically and starts sending the moment recipients are configured — no
changes to your console kernel required. Every part is toggleable from `.env`,
and `vendor:publish --tag=config` gives you the full file to tune.

At a glance — features and their default state:

| Feature                | Default | Toggle (env)                       |
|------------------------|---------|------------------------------------|
| Instant 404 email      | on*     | `PAGE_NOT_FOUND_ALERT_ENABLED`     |
| Recording 4xx/5xx      | on      | `PAGE_NOT_FOUND_RECORD_ENABLED`    |
| Digest report          | on*     | `PAGE_NOT_FOUND_REPORT_ENABLED`    |
| Auto-scheduled digest  | on*     | `PAGE_NOT_FOUND_REPORT_SCHEDULE`   |
| Threshold/spike alerts | off     | `PAGE_NOT_FOUND_ALERTS_ENABLED`    |
| HTML dashboard         | off     | `PAGE_NOT_FOUND_DASHBOARD_ENABLED` |
| JSON API               | off     | `PAGE_NOT_FOUND_API_ENABLED`       |
| Google-login gate      | on      | `PAGE_NOT_FOUND_AUTH_ENABLED`      |
| reCAPTCHA on login     | off     | `PAGE_NOT_FOUND_RECAPTCHA_ENABLED` |

\* Emails only go out once recipients are set, so the email features are safe to
leave enabled before you've configured them.

Every `4xx`/`5xx` response is recorded to the database so it can be summarised
into a digest. The package ships a migration which runs automatically (Laravel
discovers it via the service provider); you can publish it to customise the
schema:

```bash
php artisan vendor:publish --provider="Jeylabs\PageNotFoundEmailAlert\PageNotFoundEmailAlertServiceProvider" --tag=migrations
```

### Recording options

| Key                     | Description                                                                                          |
|-------------------------|------------------------------------------------------------------------------------------------------|
| `record.enabled`        | Master on/off switch for recording failed requests.                                                  |
| `record.table`          | The table failed requests are stored in (default `page_not_found_request_logs`).                     |
| `record.statuses`       | Exact status codes to record. Empty = everything at/above `minimum_status` (all 4xx/5xx).            |
| `record.minimum_status` | When `statuses` is empty, the lowest status code to record (default `400`).                          |
| `record.retention_days` | How many days of history to keep, used by `--prune` (default `30`, `0` disables).                    |
| `record.queue.enabled`  | Record asynchronously via a queued job instead of writing inline (default `true`).                   |
| `record.queue.connection` | Queue connection to dispatch on (`null` = the app's default).                                      |
| `record.queue.queue`    | Queue name to dispatch on (`null` = the default queue).                                              |

The `ignore` patterns used for alerts also apply to recording.

### Asynchronous recording

By default the database write is pushed onto a queue (`RecordBadRequest` job) so
it never adds latency to error responses:

```dotenv
PAGE_NOT_FOUND_RECORD_QUEUE=true
PAGE_NOT_FOUND_RECORD_QUEUE_CONNECTION=redis   # optional, defaults to your default connection
PAGE_NOT_FOUND_RECORD_QUEUE_NAME=monitoring    # optional, defaults to the default queue
```

With a real queue connection (`database`, `redis`, `sqs`) the write runs on a
worker; with the `sync` connection it runs inline exactly as before. **If your
default queue connection is not `sync`, make sure a worker is running** —
otherwise records will sit unprocessed. To always write synchronously inside the
request, set `PAGE_NOT_FOUND_RECORD_QUEUE=false`.

### Threshold / spike alerts

Beyond the per-URL 404 email and the periodic digest, the package can send a
near-real-time alert when error volume crosses a threshold — e.g. *"more than 25
server errors in 5 minutes"* — so you hear about an outage or attack as it
happens. Rules are evaluated as requests are recorded (rate-limited so the check
runs at most once per `check_interval` seconds) and, when scheduled, on a fixed
cadence too. A per-rule cooldown prevents repeat emails.

Disabled by default (the thresholds are traffic-specific). Enable and tune:

```dotenv
PAGE_NOT_FOUND_ALERTS_ENABLED=true
PAGE_NOT_FOUND_ALERTS_TO=ops@example.com   # falls back to report.to, then the alert "to"
PAGE_NOT_FOUND_ALERTS_COOLDOWN=30          # minutes between repeat alerts per rule
```

Rules live in the published config under `alerts.rules`; each has a `name`, a
status range (`min_status`/`max_status`) or explicit `statuses` list, a
`threshold` count and a `window` in minutes:

```php
'rules' => [
    ['name' => 'Server error spike', 'min_status' => 500, 'threshold' => 25, 'window' => 5],
    ['name' => 'Client error surge', 'min_status' => 400, 'max_status' => 499, 'threshold' => 200, 'window' => 5],
],
```

The `page-not-found:monitor` command evaluates the rules on demand; add `--dry`
to print each rule's current count and breach status without sending anything.
When `alerts.schedule.enabled` is on (default), the monitor is auto-registered on
the scheduler every minute, so alerts fire reliably even with bursty traffic
(requires `schedule:run` to be running).

### The report command

```bash
php artisan page-not-found:report
```

Compiles the recorded requests for a window (the last 24 hours by default) and
emails the digest. Options:

| Option            | Description                                                                  |
|-------------------|------------------------------------------------------------------------------|
| `--hours=`        | Number of hours to include (defaults to `report.period_hours`).              |
| `--since=`        | Only include records on/after this date-time (overrides `--hours`).          |
| `--to=`           | Override the recipient address(es). Repeatable.                              |
| `--prune`         | Delete records older than `record.retention_days` after reporting.           |
| `--dry`           | Render the report to the console instead of emailing it.                     |

Recipients are resolved from `--to`, then `report.to`
(`PAGE_NOT_FOUND_REPORT_TO`), then the alert `to` addresses.

### Reporting options

| Key                     | Description                                                                |
|-------------------------|----------------------------------------------------------------------------|
| `report.to`             | Digest recipients. Falls back to the alert `to` addresses when empty.      |
| `report.subject`        | Subject line of the digest email.                                          |
| `report.period_hours`   | Default window (hours) included in each report.                            |
| `report.limit`          | Number of rows shown in each "top" breakdown.                              |
| `report.send_when_empty`| Whether to send a report even when nothing was recorded (default `false`). |

### Automatic scheduling

By default the package registers the report command on Laravel's scheduler for
you, so you only need the scheduler itself to be running (`php artisan
schedule:run` via cron, or `schedule:work` locally). Tune it from `.env`:

```dotenv
PAGE_NOT_FOUND_REPORT_SCHEDULE=true       # set false to schedule it yourself
PAGE_NOT_FOUND_REPORT_FREQUENCY=daily     # hourly|daily|twiceDaily|weekly|monthly|<cron>
PAGE_NOT_FOUND_REPORT_TIME=08:00          # for daily/weekly/monthly
PAGE_NOT_FOUND_REPORT_SCHEDULE_PRUNE=true # also prune old records each run
```

`frequency` also accepts a raw cron expression, e.g.
`PAGE_NOT_FOUND_REPORT_FREQUENCY="0 */6 * * *"` for every six hours.

Prefer to wire it up manually? Set `PAGE_NOT_FOUND_REPORT_SCHEDULE=false` and add
it to `routes/console.php` yourself:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('page-not-found:report --prune')->dailyAt('07:00');
```

Customise the digest email by publishing the views (see below); the digest
template is `report.blade.php`.

## Dashboard & JSON API

The same data can be browsed in-app via an HTML dashboard, or consumed as JSON.
Both are **disabled by default** because they expose request data (URLs, IPs,
user agents) — enable them and put them behind appropriate middleware before
using them in production.

```dotenv
PAGE_NOT_FOUND_DASHBOARD_ENABLED=true
PAGE_NOT_FOUND_API_ENABLED=true
```

| Key                    | Description                                                                       |
|------------------------|-----------------------------------------------------------------------------------|
| `dashboard.enabled`    | Serve the HTML dashboard.                                                          |
| `dashboard.path`       | URI prefix for the dashboard (default `page-not-found`).                           |
| `dashboard.middleware` | Middleware applied to the dashboard route (default `['web']` — add `auth` etc.).   |
| `api.enabled`          | Serve the read-only JSON endpoint.                                                 |
| `api.path`             | URI prefix for the API (default `api/page-not-found`).                             |
| `api.middleware`       | Middleware applied to the API route (default `['api']`).                           |

Both accept a `?hours=` query parameter to change the window (e.g.
`/page-not-found?hours=168` for the last 7 days). The dashboard view can be
customised by publishing the views; its template is `dashboard.blade.php`.

The dashboard opens with a **"Requests over time"** chart — a zero-filled,
stacked (4xx/5xx) time-series bucketed by minute/hour/day depending on the
window — and the digest email includes a **"Busiest periods"** summary. The same
data is exposed under `series` in the API payload (`series.unit` and an array of
`series.points`, each with `period`, `total`, `client_errors`, `server_errors`),
so you can build your own charts.

#### Bot vs human & referer insight

Every recorded request is classified at write time:

* **Bot vs human** — the user agent is matched against a built-in list of
  crawlers, HTTP clients, headless browsers and security scanners (extend it via
  `record.bot_user_agents`). This separates real broken links from scanner noise.
* **Referer** — classified as **internal** (the referer host matches your app's
  host), **external**, or **direct** (no referer). *Internal referers are your
  own pages linking to dead URLs — the most actionable 404s.*

The dashboard surfaces both as summary cards plus a **"Top referers"** table, the
digest email includes the splits, and the API exposes them under `traffic`
(`humans`/`bots`/`unknown`), `referers` (`internal`/`external`/`direct`) and
`top_referers`.

#### Drill-down & filtering

Click any path on the dashboard to open **`/page-not-found/requests`** — a
paginated list of the individual hits behind the aggregates. It supports
filtering by exact path, free-text path search, status code, window, and
human/bot, all via query parameters (e.g.
`/page-not-found/requests?search=wp-admin&bot=1`). The route lives in the
dashboard group, so it inherits the same access control.

### Access control: Sign in with Google

The dashboard and API are protected by **Sign in with Google** out of the box
(`auth.enabled` defaults to `true`). Only the email addresses you allow-list can
view the dashboard or call the API — anyone else is redirected to a login screen
or, for the API, receives a `401`. The same browser session grants access to
both, so once you've signed in for the dashboard the API works too.

1. In the [Google Cloud console](https://console.cloud.google.com/apis/credentials)
   create an **OAuth 2.0 Client ID** (type: *Web application*).
2. Add the callback as an *Authorised redirect URI* — by default
   `https://your-app.test/page-not-found/auth/callback`.
3. Configure `.env`:

```dotenv
PAGE_NOT_FOUND_AUTH_ENABLED=true
PAGE_NOT_FOUND_AUTH_EMAILS=you@yourcompany.com,ops@yourcompany.com
PAGE_NOT_FOUND_GOOGLE_CLIENT_ID=xxxx.apps.googleusercontent.com
PAGE_NOT_FOUND_GOOGLE_CLIENT_SECRET=your-secret
```

| Key                   | Description                                                                  |
|-----------------------|------------------------------------------------------------------------------|
| `auth.enabled`        | Protect the dashboard/API with Google login (default `true`).                |
| `auth.allowed_emails` | The Google emails permitted to sign in. **Empty = nobody** (locked down).    |
| `auth.path`           | URI prefix for the login/callback routes (default `page-not-found/auth`).    |
| `auth.google.*`       | OAuth client id/secret, and an optional redirect URI override.               |

No allow-listed email matches? Access is denied — so the feature fails closed.
You can still set `middleware` on the routes for an extra layer (VPN/IP).

### Bot protection: reCAPTCHA

The login entry point can be guarded with Google reCAPTCHA (v2 checkbox or v3).
Add your keys and enable it:

```dotenv
PAGE_NOT_FOUND_RECAPTCHA_ENABLED=true
PAGE_NOT_FOUND_RECAPTCHA_SITE_KEY=your-site-key
PAGE_NOT_FOUND_RECAPTCHA_SECRET_KEY=your-secret-key
# v3 only — minimum passing score (0.0–1.0):
PAGE_NOT_FOUND_RECAPTCHA_MIN_SCORE=0.5
```

When enabled, the login form renders the reCAPTCHA widget and the redirect to
Google is rejected unless the captcha verifies (for v3, the score must meet
`min_score`). This keeps bots from hammering the OAuth flow.

### Customising the email template

To change the email layout, publish the view and edit it:

```bash
php artisan vendor:publish --provider="Jeylabs\PageNotFoundEmailAlert\PageNotFoundEmailAlertServiceProvider" --tag=views
```

The view is published to `resources/views/vendor/page-not-found-email-alert/email.blade.php`.

## How it works

The package appends a lightweight global middleware to the HTTP kernel. After
each request is handled, the middleware inspects the response:

* When it is a `404`, not in the ignore list, and the URL has not already
  triggered an alert within the throttle window, the instant alert email is
  dispatched.
* When it is any `4xx`/`5xx` (and not ignored), it is recorded for reporting —
  by default via a queued job so the response is never slowed by a database
  write. The user agent (bot vs human) and referer (internal/external/direct)
  are classified as the row is written. Recording is skipped silently until the
  migration has run, so it never spams your logs.

Mail and storage failures are caught, logged, and never interfere with the
response returned to the user. The digest command and the dashboard/API read
back the recorded rows and aggregate them on demand.

## License

MIT

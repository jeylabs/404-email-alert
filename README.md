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

The `ignore` patterns used for alerts also apply to recording.

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

### Scheduling

Add the command to your scheduler to receive the digest automatically. In
`routes/console.php` (Laravel 11+):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('page-not-found:report --prune')->dailyAt('07:00');
```

Customise the digest email by publishing the views (see below); the digest
template is `report.blade.php`.

### Customising the email template

To change the email layout, publish the view and edit it:

```bash
php artisan vendor:publish --provider="Jeylabs\PageNotFoundEmailAlert\PageNotFoundEmailAlertServiceProvider" --tag=views
```

The view is published to `resources/views/vendor/page-not-found-email-alert/email.blade.php`.

## How it works

The package appends a lightweight global middleware to the HTTP kernel. After
each request is handled, the middleware inspects the response: when it is a
`404`, the request is not in the ignore list, and the URL has not already
triggered an alert within the throttle window, an email is dispatched. Mail
failures are logged and never interfere with the response returned to the user.

## License

MIT

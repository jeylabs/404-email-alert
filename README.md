# 404-email-alert

Laravel Page Not Found (404) Email Alerts.

This package emails your system administrator whenever a visitor hits a URL that
does not exist (an HTTP `404` response). Each alert includes the requested URL,
HTTP method, referer, IP address, user agent and timestamp, so you can quickly
spot broken links and suspicious scanning activity.

## Requirements

* PHP `>= 8.5`
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

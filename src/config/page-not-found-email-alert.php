<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Master switch for the 404 email alerts. When disabled no alerts are
    | sent, regardless of the rest of the configuration below.
    |
    */

    'enabled' => env('PAGE_NOT_FOUND_ALERT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Recipients
    |--------------------------------------------------------------------------
    |
    | One or more email addresses that should receive the 404 alerts. You may
    | provide a comma separated list via the environment variable, or edit
    | this array directly after publishing the config.
    |
    */

    'to' => array_values(array_filter(array_map('trim', explode(
        ',',
        env('PAGE_NOT_FOUND_ALERT_TO', '')
    )))),

    /*
    |--------------------------------------------------------------------------
    | From Address
    |--------------------------------------------------------------------------
    |
    | The address the alert is sent from. Defaults to your application's
    | configured mail "from" address.
    |
    */

    'from' => [
        'address' => env('PAGE_NOT_FOUND_ALERT_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')),
        'name'    => env('PAGE_NOT_FOUND_ALERT_FROM_NAME', env('MAIL_FROM_NAME', 'Page Not Found Alert')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Subject
    |--------------------------------------------------------------------------
    */

    'subject' => env('PAGE_NOT_FOUND_ALERT_SUBJECT', '404 Page Not Found Alert'),

    /*
    |--------------------------------------------------------------------------
    | Throttle
    |--------------------------------------------------------------------------
    |
    | Number of minutes to suppress duplicate alerts for the same URL. This
    | prevents a flood of emails when a bot or scanner repeatedly hits the
    | same missing page. Set to 0 to disable throttling entirely.
    |
    */

    'throttle' => env('PAGE_NOT_FOUND_ALERT_THROTTLE', 60),

    /*
    |--------------------------------------------------------------------------
    | Ignored Paths
    |--------------------------------------------------------------------------
    |
    | Request paths that should never trigger an alert. Patterns support
    | the "*" wildcard (matched with Str::is), e.g. "admin/*" or "*.php".
    |
    */

    'ignore' => [
        'favicon.ico',
        'robots.txt',
        'apple-touch-icon*.png',
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Recording
    |--------------------------------------------------------------------------
    |
    | In addition to the instant 404 email above, the package can record every
    | "not so great" request (any 4xx/5xx response) to the database so it can
    | be aggregated into a periodic report. The same "ignore" patterns above
    | apply to recording.
    |
    */

    'record' => [

        // Master switch for recording requests for reporting.
        'enabled' => env('PAGE_NOT_FOUND_RECORD_ENABLED', true),

        // The database table the requests are stored in.
        'table' => env('PAGE_NOT_FOUND_RECORD_TABLE', 'page_not_found_request_logs'),

        // Exact status codes to record. Leave empty to capture everything at or
        // above "minimum_status" (i.e. all 4xx and 5xx responses).
        'statuses' => [],

        // When "statuses" is empty, record any response with a status >= this.
        'minimum_status' => 400,

        // Days of history to keep. Used by `page-not-found:report --prune`.
        // Set to 0 to disable pruning.
        'retention_days' => env('PAGE_NOT_FOUND_RECORD_RETENTION', 30),

    ],

    /*
    |--------------------------------------------------------------------------
    | Reporting
    |--------------------------------------------------------------------------
    |
    | Settings for the `page-not-found:report` command, which compiles the
    | recorded requests into a digest email (counts by status, top paths,
    | IPs and user agents). Schedule the command to receive it periodically.
    |
    */

    'report' => [

        // Master switch for the reporting feature. When disabled the digest is
        // never scheduled automatically (you can still run the command by hand).
        'enabled' => env('PAGE_NOT_FOUND_REPORT_ENABLED', true),

        // Recipients for the digest. Falls back to the alert "to" addresses
        // above when left empty.
        'to' => array_values(array_filter(array_map('trim', explode(
            ',',
            (string) env('PAGE_NOT_FOUND_REPORT_TO', '')
        )))),

        // Subject line of the report email.
        'subject' => env('PAGE_NOT_FOUND_REPORT_SUBJECT', 'Not So Great Requests Report'),

        // Default window (in hours) included in each report.
        'period_hours' => env('PAGE_NOT_FOUND_REPORT_PERIOD', 24),

        // Number of rows to show in each "top" breakdown.
        'limit' => env('PAGE_NOT_FOUND_REPORT_LIMIT', 20),

        // Whether to still send a report when no failed requests were recorded.
        'send_when_empty' => env('PAGE_NOT_FOUND_REPORT_SEND_WHEN_EMPTY', false),

        /*
        |----------------------------------------------------------------------
        | Automatic Scheduling
        |----------------------------------------------------------------------
        |
        | When enabled, the package registers `page-not-found:report` on
        | Laravel's scheduler for you — no need to touch your console kernel.
        | It only emails once recipients are configured, so it is safe to
        | leave on. Requires the scheduler to be running (`schedule:run`).
        |
        */

        'schedule' => [

            // Register the digest on the scheduler automatically.
            'enabled' => env('PAGE_NOT_FOUND_REPORT_SCHEDULE', true),

            // How often to send: "hourly", "daily", "twiceDaily", "weekly",
            // "monthly", or any raw cron expression (e.g. "0 */6 * * *").
            'frequency' => env('PAGE_NOT_FOUND_REPORT_FREQUENCY', 'daily'),

            // Time of day for daily/weekly/monthly frequencies (24h, "HH:MM").
            'time' => env('PAGE_NOT_FOUND_REPORT_TIME', '08:00'),

            // Also prune records past the retention period on each run.
            'prune' => env('PAGE_NOT_FOUND_REPORT_SCHEDULE_PRUNE', true),

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    |
    | An in-app HTML page visualising the recorded requests. It exposes the
    | same data as the digest, so it is DISABLED by default — enable it and
    | protect the route with appropriate middleware (e.g. "auth", or a
    | gate/IP restriction) before turning it on in production.
    |
    */

    'dashboard' => [

        'enabled' => env('PAGE_NOT_FOUND_DASHBOARD_ENABLED', false),

        // The URI the dashboard is served from, e.g. "/page-not-found".
        'path' => env('PAGE_NOT_FOUND_DASHBOARD_PATH', 'page-not-found'),

        // Middleware applied to the dashboard route. Add "auth" (or similar)
        // to keep it private.
        'middleware' => ['web'],

    ],

    /*
    |--------------------------------------------------------------------------
    | JSON API
    |--------------------------------------------------------------------------
    |
    | A read-only JSON endpoint returning the aggregated report, handy for
    | building your own dashboards or widgets. Also DISABLED by default; add
    | auth middleware before exposing it.
    |
    */

    'api' => [

        'enabled' => env('PAGE_NOT_FOUND_API_ENABLED', false),

        // The URI the JSON endpoint is served from, e.g. "/api/page-not-found".
        'path' => env('PAGE_NOT_FOUND_API_PATH', 'api/page-not-found'),

        // Middleware applied to the API route.
        'middleware' => ['api'],

    ],

    /*
    |--------------------------------------------------------------------------
    | Access Control (Google Login)
    |--------------------------------------------------------------------------
    |
    | When enabled, the dashboard and JSON API are protected by "Sign in with
    | Google" and only the configured email addresses may view them. Create an
    | OAuth client ID in the Google Cloud console and add the callback URL
    | (default "<app>/page-not-found/auth/callback") as an authorised redirect.
    |
    */

    'auth' => [

        // Protect the dashboard/API with Google login. Strongly recommended
        // whenever either is exposed.
        'enabled' => env('PAGE_NOT_FOUND_AUTH_ENABLED', true),

        // URI prefix for the login/callback routes.
        'path' => env('PAGE_NOT_FOUND_AUTH_PATH', 'page-not-found/auth'),

        // The Google email addresses allowed to sign in. Comma separated via
        // the environment, or an array here. Empty = nobody (locked down).
        'allowed_emails' => array_values(array_filter(array_map('trim', explode(
            ',',
            (string) env('PAGE_NOT_FOUND_AUTH_EMAILS', '')
        )))),

        'google' => [
            'client_id'     => env('PAGE_NOT_FOUND_GOOGLE_CLIENT_ID'),
            'client_secret' => env('PAGE_NOT_FOUND_GOOGLE_CLIENT_SECRET'),
            // Optional: override the OAuth redirect URI (e.g. behind a proxy).
            'redirect'      => env('PAGE_NOT_FOUND_GOOGLE_REDIRECT'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | reCAPTCHA
    |--------------------------------------------------------------------------
    |
    | Protects the login entry point from bots. Supports both reCAPTCHA v2
    | (checkbox) and v3 (a "min_score" threshold is applied when Google returns
    | a score). Provide the site/secret keys from the reCAPTCHA admin console.
    |
    */

    'recaptcha' => [

        'enabled'    => env('PAGE_NOT_FOUND_RECAPTCHA_ENABLED', false),
        'site_key'   => env('PAGE_NOT_FOUND_RECAPTCHA_SITE_KEY'),
        'secret_key' => env('PAGE_NOT_FOUND_RECAPTCHA_SECRET_KEY'),
        'min_score'  => env('PAGE_NOT_FOUND_RECAPTCHA_MIN_SCORE', 0.5),

    ],

];

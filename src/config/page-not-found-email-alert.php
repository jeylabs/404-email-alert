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

    ],

];

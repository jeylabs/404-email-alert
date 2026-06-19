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

];

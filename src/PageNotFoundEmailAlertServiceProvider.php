<?php

namespace Jeylabs\PageNotFoundEmailAlert;

use Illuminate\Support\Facades\Mail;

use Illuminate\Support\ServiceProvider;

class PageNotFoundEmailAlertServiceProvider extends ServiceProvider
{
    public function register()
    {

        //dd('404 Email Alert');

    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/page-not-found-email-alert.php' => config_path('page-not-found-email-alert.php'),
        ]);

        $this->mergeConfigFrom(
            __DIR__.'/config/page-not-found-email-alert.php', 'page-not-found-email-alert'
        );

        if (! $this->app->routesAreCached()) {
            require __DIR__.'/Http/routes.php';
        }
    }
}

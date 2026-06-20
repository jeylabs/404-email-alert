<?php

namespace Jeylabs\PageNotFoundEmailAlert;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Jeylabs\PageNotFoundEmailAlert\Console\SendRequestReport;
use Jeylabs\PageNotFoundEmailAlert\Http\Middleware\PageNotFoundEmailAlert;

class PageNotFoundEmailAlertServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/config/page-not-found-email-alert.php',
            'page-not-found-email-alert'
        );
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'page-not-found-email-alert');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/config/page-not-found-email-alert.php' => config_path('page-not-found-email-alert.php'),
            ], 'config');

            $this->publishes([
                __DIR__.'/resources/views' => resource_path('views/vendor/page-not-found-email-alert'),
            ], 'views');

            $this->publishes([
                __DIR__.'/database/migrations' => database_path('migrations'),
            ], 'migrations');

            $this->commands([
                SendRequestReport::class,
            ]);
        }

        $this->registerMiddleware();
    }

    /**
     * Append the alert middleware to the global HTTP middleware stack so that
     * every response is inspected for a 404 status code.
     *
     * @return void
     */
    protected function registerMiddleware()
    {
        if (! $this->app->bound(Kernel::class)) {
            return;
        }

        $this->app->make(Kernel::class)->pushMiddleware(PageNotFoundEmailAlert::class);
    }
}

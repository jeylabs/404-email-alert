<?php

namespace Jeylabs\PageNotFoundEmailAlert;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Jeylabs\PageNotFoundEmailAlert\Console\SendRequestReport;
use Jeylabs\PageNotFoundEmailAlert\Http\Controllers\AuthController;
use Jeylabs\PageNotFoundEmailAlert\Http\Controllers\ReportController;
use Jeylabs\PageNotFoundEmailAlert\Http\Middleware\EnsureAllowedGoogleUser;
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
        $this->registerRoutes();
        $this->registerSchedule();
    }

    /**
     * Register the dashboard and JSON API routes when each is enabled. Both are
     * controller routes (not closures) so they remain route-cache friendly.
     *
     * @return void
     */
    protected function registerRoutes()
    {
        $dashboard = (array) config('page-not-found-email-alert.dashboard', []);
        $api = (array) config('page-not-found-email-alert.api', []);

        $dashboardOn = (bool) ($dashboard['enabled'] ?? false);
        $apiOn = (bool) ($api['enabled'] ?? false);

        if (! $dashboardOn && ! $apiOn) {
            return;
        }

        $authEnabled = (bool) config('page-not-found-email-alert.auth.enabled', false);

        if ($authEnabled) {
            $this->registerAuthRoutes();
        }

        if ($dashboardOn) {
            Route::group([
                'prefix'     => $dashboard['path'] ?? 'page-not-found',
                'middleware' => $this->protectedMiddleware($dashboard['middleware'] ?? ['web'], $authEnabled),
            ], function () {
                Route::get('/', [ReportController::class, 'index'])
                    ->name('page-not-found.dashboard');
            });
        }

        if ($apiOn) {
            Route::group([
                'prefix'     => $api['path'] ?? 'api/page-not-found',
                'middleware' => $this->protectedMiddleware($api['middleware'] ?? ['api'], $authEnabled),
            ], function () {
                Route::get('/', [ReportController::class, 'data'])
                    ->name('page-not-found.api');
            });
        }
    }

    /**
     * Register the Google login / OAuth callback routes.
     *
     * @return void
     */
    protected function registerAuthRoutes()
    {
        Route::group([
            'prefix'     => config('page-not-found-email-alert.auth.path', 'page-not-found/auth'),
            'middleware' => 'web',
        ], function () {
            Route::get('login', [AuthController::class, 'login'])->name('page-not-found.login');
            Route::post('redirect', [AuthController::class, 'redirect'])->name('page-not-found.auth.redirect');
            Route::get('callback', [AuthController::class, 'callback'])->name('page-not-found.auth.callback');
            Route::post('logout', [AuthController::class, 'logout'])->name('page-not-found.logout');
        });
    }

    /**
     * Build the middleware stack for a protected route. When access control is
     * on, the allow-list gate is appended and — for stateless groups such as
     * the API — session middleware is prepended so the login is remembered.
     *
     * @param  array  $configured
     * @param  bool  $authEnabled
     * @return array
     */
    protected function protectedMiddleware(array $configured, $authEnabled)
    {
        if (! $authEnabled) {
            return $configured;
        }

        $session = in_array('web', $configured, true) ? [] : [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
        ];

        return array_values(array_unique(array_merge(
            $session,
            $configured,
            [EnsureAllowedGoogleUser::class]
        )));
    }

    /**
     * Register the report command on Laravel's scheduler so digests are sent
     * automatically. Registration is deferred until the scheduler is resolved,
     * so it has no effect on regular web requests.
     *
     * @return void
     */
    protected function registerSchedule()
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $report = (array) config('page-not-found-email-alert.report', []);
            $scheduleConfig = (array) ($report['schedule'] ?? []);

            if (! ($report['enabled'] ?? false) || ! ($scheduleConfig['enabled'] ?? false)) {
                return;
            }

            $command = 'page-not-found:report';

            if ($scheduleConfig['prune'] ?? false) {
                $command .= ' --prune';
            }

            $event = $schedule->command($command)->withoutOverlapping();

            $this->applyFrequency($event, $scheduleConfig);
        });
    }

    /**
     * Apply the configured frequency to a scheduled event.
     *
     * @param  \Illuminate\Console\Scheduling\Event  $event
     * @param  array  $scheduleConfig
     * @return void
     */
    protected function applyFrequency($event, array $scheduleConfig)
    {
        $frequency = $scheduleConfig['frequency'] ?? 'daily';
        $time = $scheduleConfig['time'] ?? '08:00';

        switch ($frequency) {
            case 'hourly':
                $event->hourly();
                break;
            case 'twiceDaily':
                $event->twiceDaily();
                break;
            case 'weekly':
                $event->weeklyOn(1, $time);
                break;
            case 'monthly':
                $event->monthlyOn(1, $time);
                break;
            case 'daily':
                $event->dailyAt($time);
                break;
            default:
                // Treat any other value as a raw cron expression.
                $event->cron($frequency);
                break;
        }
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

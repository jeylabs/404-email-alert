<?php

namespace Jeylabs\PageNotFoundEmailAlert\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Jeylabs\PageNotFoundEmailAlert\Models\RequestLog;
use Jeylabs\PageNotFoundEmailAlert\Notifications\ThresholdAlertNotification;
use Jeylabs\PageNotFoundEmailAlert\Reporting\ThresholdMonitor;

class ThresholdAlertTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('page-not-found-email-alert.alerts.enabled', true);
        $app['config']->set('page-not-found-email-alert.alerts.to', ['ops@example.com']);
        $app['config']->set('page-not-found-email-alert.alerts.cooldown', 30);
        $app['config']->set('page-not-found-email-alert.alerts.rules', [
            ['name' => 'Server error spike', 'min_status' => 500, 'threshold' => 3, 'window' => 5],
        ]);
    }

    protected function seedStatus(int $status, int $count, int $minutesAgo = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            RequestLog::create([
                'status_code' => $status,
                'method'      => 'GET',
                'url'         => 'https://example.com/x',
                'path'        => 'x',
                'ip'          => '1.2.3.4',
                'user_agent'  => 'phpunit',
                'created_at'  => Carbon::now()->subMinutes($minutesAgo),
            ]);
        }
    }

    public function test_it_alerts_when_a_rule_is_breached()
    {
        Notification::fake();
        $this->seedStatus(500, 3);

        $triggered = app(ThresholdMonitor::class)->evaluate();

        $this->assertCount(1, $triggered);
        Notification::assertSentOnDemand(
            ThresholdAlertNotification::class,
            function ($notification, $channels, $notifiable) {
                return in_array('ops@example.com', (array) ($notifiable->routes['mail'] ?? []))
                    && $notification->alert['count'] === 3
                    && $notification->alert['name'] === 'Server error spike';
            }
        );
    }

    public function test_it_does_not_alert_below_the_threshold()
    {
        Notification::fake();
        $this->seedStatus(500, 2);

        $this->assertSame([], app(ThresholdMonitor::class)->evaluate());
        Notification::assertNothingSent();
    }

    public function test_it_ignores_records_outside_the_window()
    {
        Notification::fake();
        $this->seedStatus(500, 5, 30); // 30 minutes ago, window is 5

        $this->assertSame([], app(ThresholdMonitor::class)->evaluate());
        Notification::assertNothingSent();
    }

    public function test_cooldown_suppresses_repeat_alerts()
    {
        Notification::fake();
        $this->seedStatus(500, 4);

        app(ThresholdMonitor::class)->evaluate();
        app(ThresholdMonitor::class)->evaluate(); // within cooldown

        Notification::assertSentOnDemandTimes(ThresholdAlertNotification::class, 1);
    }

    public function test_realtime_evaluation_is_rate_limited_by_check_interval()
    {
        Notification::fake();
        config()->set('page-not-found-email-alert.alerts.cooldown', 0); // isolate the interval gate
        $this->seedStatus(500, 5);

        app(ThresholdMonitor::class)->maybeEvaluate();   // runs, fires
        Cache::put('pnf:monitor:last-run', true, 60);    // simulate interval not elapsed
        app(ThresholdMonitor::class)->maybeEvaluate();   // suppressed by interval

        Notification::assertSentOnDemandTimes(ThresholdAlertNotification::class, 1);
    }

    public function test_a_recorded_500_triggers_a_realtime_alert()
    {
        Notification::fake();
        config()->set('page-not-found-email-alert.alerts.realtime.check_interval', 0);
        \Illuminate\Support\Facades\Route::get('/boom', fn () => response('x', 500));

        // Three 5xx hits cross the threshold of 3.
        $this->get('/boom');
        $this->get('/boom');
        $this->get('/boom');

        Notification::assertSentOnDemand(ThresholdAlertNotification::class);
    }

    public function test_monitor_command_dry_run_reports_without_sending()
    {
        Notification::fake();
        $this->seedStatus(500, 3);

        $this->artisan('page-not-found:monitor', ['--dry' => true])
            ->expectsOutputToContain('BREACHED')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }
}

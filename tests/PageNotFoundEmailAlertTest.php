<?php

namespace Jeylabs\PageNotFoundEmailAlert\Tests;

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Jeylabs\PageNotFoundEmailAlert\Notifications\PageNotFoundAlertNotification;

class PageNotFoundEmailAlertTest extends TestCase
{
    public function test_it_sends_an_alert_on_a_404_response()
    {
        Notification::fake();

        $this->get('/this-page-does-not-exist')->assertNotFound();

        Notification::assertSentOnDemand(
            PageNotFoundAlertNotification::class,
            function ($notification, $channels, $notifiable) {
                return in_array('mail', $channels)
                    && in_array('admin@example.com', (array) ($notifiable->routes['mail'] ?? []))
                    && strpos($notification->data['url'], 'this-page-does-not-exist') !== false;
            }
        );
    }

    public function test_it_does_not_send_an_alert_for_a_successful_response()
    {
        Notification::fake();

        Route::get('/exists', function () {
            return 'ok';
        });

        $this->get('/exists')->assertOk();

        Notification::assertNothingSent();
    }

    public function test_it_does_not_send_when_disabled()
    {
        config()->set('page-not-found-email-alert.enabled', false);

        Notification::fake();

        $this->get('/missing')->assertNotFound();

        Notification::assertNothingSent();
    }

    public function test_it_does_not_send_when_no_channels_are_configured()
    {
        config()->set('page-not-found-email-alert.to', []);

        Notification::fake();

        $this->get('/missing')->assertNotFound();

        Notification::assertNothingSent();
    }

    public function test_it_ignores_configured_paths()
    {
        Notification::fake();

        $this->get('/favicon.ico')->assertNotFound();

        Notification::assertNothingSent();
    }

    public function test_it_throttles_repeated_alerts_for_the_same_url()
    {
        config()->set('page-not-found-email-alert.throttle', 60);

        Notification::fake();

        $this->get('/repeated-missing')->assertNotFound();
        $this->get('/repeated-missing')->assertNotFound();

        Notification::assertSentOnDemandTimes(PageNotFoundAlertNotification::class, 1);
    }
}

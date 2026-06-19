<?php

namespace Jeylabs\PageNotFoundEmailAlert\Tests;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Jeylabs\PageNotFoundEmailAlert\Mail\PageNotFound;

class PageNotFoundEmailAlertTest extends TestCase
{
    public function test_it_sends_an_alert_on_a_404_response()
    {
        Mail::fake();

        $this->get('/this-page-does-not-exist')->assertNotFound();

        Mail::assertSent(PageNotFound::class, function ($mail) {
            return $mail->hasTo('admin@example.com')
                && strpos($mail->data['url'], 'this-page-does-not-exist') !== false;
        });
    }

    public function test_it_does_not_send_an_alert_for_a_successful_response()
    {
        Mail::fake();

        Route::get('/exists', function () {
            return 'ok';
        });

        $this->get('/exists')->assertOk();

        Mail::assertNothingSent();
    }

    public function test_it_does_not_send_when_disabled()
    {
        config()->set('page-not-found-email-alert.enabled', false);

        Mail::fake();

        $this->get('/missing')->assertNotFound();

        Mail::assertNothingSent();
    }

    public function test_it_does_not_send_when_no_recipients_are_configured()
    {
        config()->set('page-not-found-email-alert.to', []);

        Mail::fake();

        $this->get('/missing')->assertNotFound();

        Mail::assertNothingSent();
    }

    public function test_it_ignores_configured_paths()
    {
        Mail::fake();

        $this->get('/favicon.ico')->assertNotFound();

        Mail::assertNothingSent();
    }

    public function test_it_throttles_repeated_alerts_for_the_same_url()
    {
        config()->set('page-not-found-email-alert.throttle', 60);

        Mail::fake();

        $this->get('/repeated-missing')->assertNotFound();
        $this->get('/repeated-missing')->assertNotFound();

        Mail::assertSent(PageNotFound::class, 1);
    }
}

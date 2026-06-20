<?php

namespace Jeylabs\PageNotFoundEmailAlert\Tests;

use Illuminate\Support\Facades\Route;
use Jeylabs\PageNotFoundEmailAlert\Models\RequestLog;

class RequestRecordingTest extends TestCase
{
    public function test_it_records_a_404_request()
    {
        $this->get('/missing-page')->assertNotFound();

        $this->assertSame(1, RequestLog::count());

        $log = RequestLog::first();

        $this->assertSame(404, $log->status_code);
        $this->assertSame('GET', $log->method);
        $this->assertStringContainsString('missing-page', $log->url);
    }

    public function test_it_records_server_errors()
    {
        Route::get('/boom', function () {
            return response('kaboom', 500);
        });

        $this->get('/boom')->assertStatus(500);

        $this->assertSame(1, RequestLog::where('status_code', 500)->count());
    }

    public function test_it_does_not_record_successful_responses()
    {
        Route::get('/ok', fn () => 'fine');

        $this->get('/ok')->assertOk();

        $this->assertSame(0, RequestLog::count());
    }

    public function test_it_does_not_record_ignored_paths()
    {
        $this->get('/favicon.ico')->assertNotFound();

        $this->assertSame(0, RequestLog::count());
    }

    public function test_it_does_not_record_when_recording_disabled()
    {
        config()->set('page-not-found-email-alert.record.enabled', false);

        $this->get('/missing-page')->assertNotFound();

        $this->assertSame(0, RequestLog::count());
    }

    public function test_it_records_every_hit_even_when_email_is_throttled()
    {
        config()->set('page-not-found-email-alert.throttle', 60);

        $this->get('/repeated-missing')->assertNotFound();
        $this->get('/repeated-missing')->assertNotFound();

        $this->assertSame(2, RequestLog::count());
    }

    public function test_it_honours_an_explicit_status_allow_list()
    {
        config()->set('page-not-found-email-alert.record.statuses', [403]);

        Route::get('/forbidden', fn () => response('no', 403));

        $this->get('/missing-page')->assertNotFound();
        $this->get('/forbidden')->assertStatus(403);

        $this->assertSame(0, RequestLog::where('status_code', 404)->count());
        $this->assertSame(1, RequestLog::where('status_code', 403)->count());
    }
}

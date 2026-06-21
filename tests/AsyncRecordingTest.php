<?php

namespace Jeylabs\PageNotFoundEmailAlert\Tests;

use Illuminate\Support\Facades\Queue;
use Jeylabs\PageNotFoundEmailAlert\Jobs\RecordBadRequest;
use Jeylabs\PageNotFoundEmailAlert\Models\RequestLog;

class AsyncRecordingTest extends TestCase
{
    public function test_a_failed_request_dispatches_the_recording_job()
    {
        Queue::fake();

        $this->get('/missing-page')->assertNotFound();

        Queue::assertPushed(RecordBadRequest::class, function ($job) {
            return $job->attributes['status_code'] === 404
                && str_contains($job->attributes['url'], 'missing-page');
        });
    }

    public function test_the_job_persists_the_record_when_handled()
    {
        (new RecordBadRequest([
            'status_code' => 500,
            'method'      => 'GET',
            'url'         => 'https://example.com/boom',
            'path'        => 'boom',
            'referer'     => null,
            'ip'          => '1.2.3.4',
            'user_agent'  => 'phpunit',
        ]))->handle();

        $this->assertSame(1, RequestLog::where('status_code', 500)->count());
    }

    public function test_dispatch_uses_the_configured_connection_and_queue()
    {
        config()->set('page-not-found-email-alert.record.queue.connection', 'redis');
        config()->set('page-not-found-email-alert.record.queue.queue', 'monitoring');

        Queue::fake();

        $this->get('/missing-page')->assertNotFound();

        Queue::assertPushed(RecordBadRequest::class, function ($job) {
            return $job->connection === 'redis' && $job->queue === 'monitoring';
        });
    }

    public function test_synchronous_mode_writes_inline_without_a_job()
    {
        config()->set('page-not-found-email-alert.record.queue.enabled', false);

        Queue::fake();

        $this->get('/missing-page')->assertNotFound();

        Queue::assertNothingPushed();
        $this->assertSame(1, RequestLog::where('status_code', 404)->count());
    }
}

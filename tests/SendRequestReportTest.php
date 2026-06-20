<?php

namespace Jeylabs\PageNotFoundEmailAlert\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Jeylabs\PageNotFoundEmailAlert\Mail\RequestReport;
use Jeylabs\PageNotFoundEmailAlert\Models\RequestLog;

class SendRequestReportTest extends TestCase
{
    protected function seedLogs(): void
    {
        $rows = [
            ['status_code' => 404, 'path' => 'a', 'ip' => '1.1.1.1'],
            ['status_code' => 404, 'path' => 'a', 'ip' => '1.1.1.1'],
            ['status_code' => 404, 'path' => 'b', 'ip' => '2.2.2.2'],
            ['status_code' => 500, 'path' => 'c', 'ip' => '2.2.2.2'],
        ];

        foreach ($rows as $row) {
            RequestLog::create(array_merge([
                'method'     => 'GET',
                'url'        => 'https://example.com/'.$row['path'],
                'referer'    => null,
                'user_agent' => 'phpunit',
                'created_at' => Carbon::now()->subMinutes(5),
            ], $row));
        }
    }

    public function test_it_emails_a_report_with_aggregates()
    {
        Mail::fake();
        $this->seedLogs();

        $this->artisan('page-not-found:report', ['--to' => ['ops@example.com']])
            ->assertSuccessful();

        Mail::assertSent(RequestReport::class, function ($mail) {
            $report = $mail->report;

            return $mail->hasTo('ops@example.com')
                && $report['total'] === 4
                && $report['client_errors'] === 3
                && $report['server_errors'] === 1
                && $report['by_status'][0]['status'] === 404
                && $report['by_status'][0]['count'] === 3
                && $report['top_paths'][0]['path'] === 'a';
        });
    }

    public function test_dry_run_renders_to_console_without_sending()
    {
        Mail::fake();
        $this->seedLogs();

        $this->artisan('page-not-found:report', ['--dry' => true])
            ->expectsOutputToContain('Total: 4')
            ->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_it_skips_sending_when_there_is_nothing_to_report()
    {
        Mail::fake();

        $this->artisan('page-not-found:report')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_it_excludes_records_outside_the_window()
    {
        Mail::fake();

        RequestLog::create([
            'status_code' => 404,
            'method'      => 'GET',
            'url'         => 'https://example.com/old',
            'path'        => 'old',
            'ip'          => '9.9.9.9',
            'user_agent'  => 'phpunit',
            'created_at'  => Carbon::now()->subDays(3),
        ]);

        $this->artisan('page-not-found:report', ['--hours' => 24])->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_it_prunes_old_records()
    {
        Mail::fake();
        config()->set('page-not-found-email-alert.record.retention_days', 7);

        RequestLog::create([
            'status_code' => 404,
            'method'      => 'GET',
            'url'         => 'https://example.com/old',
            'path'        => 'old',
            'ip'          => '9.9.9.9',
            'user_agent'  => 'phpunit',
            'created_at'  => Carbon::now()->subDays(30),
        ]);

        $this->seedLogs();

        $this->artisan('page-not-found:report', ['--prune' => true])->assertSuccessful();

        $this->assertSame(0, RequestLog::where('path', 'old')->count());
        $this->assertSame(4, RequestLog::count());
    }
}

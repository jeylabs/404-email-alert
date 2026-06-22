<?php

namespace Jeylabs\PageNotFoundEmailAlert\Tests;

use Illuminate\Support\Carbon;
use Jeylabs\PageNotFoundEmailAlert\Models\RequestLog;
use Jeylabs\PageNotFoundEmailAlert\Reporting\ReportBuilder;

class TimeSeriesTest extends TestCase
{
    protected function log(int $status, Carbon $at): void
    {
        RequestLog::create([
            'status_code' => $status,
            'method'      => 'GET',
            'url'         => 'https://example.com/x',
            'path'        => 'x',
            'ip'          => '1.2.3.4',
            'user_agent'  => 'phpunit',
            'created_at'  => $at,
        ]);
    }

    public function test_series_buckets_by_hour_for_a_day_window_and_zero_fills()
    {
        $now = Carbon::now();
        $this->log(404, $now->copy()->subHours(2));
        $this->log(404, $now->copy()->subHours(2)->addMinutes(3)); // same hour bucket
        $this->log(500, $now->copy()->subHours(2));

        $report = app(ReportBuilder::class)->build($now->copy()->subHours(24), $now->copy());
        $series = $report['series'];

        $this->assertSame('hour', $series['unit']);

        // Totals across the series match the underlying data.
        $this->assertSame(3, collect($series['points'])->sum('total'));
        $this->assertSame(2, collect($series['points'])->sum('client_errors'));
        $this->assertSame(1, collect($series['points'])->sum('server_errors'));

        // The window is zero-filled into a continuous series of ~24-25 buckets.
        $this->assertGreaterThanOrEqual(24, count($series['points']));
        $this->assertTrue(collect($series['points'])->contains(fn ($p) => $p['total'] === 0));

        // The busiest hour holds all three events.
        $this->assertSame(3, collect($series['points'])->max('total'));
    }

    public function test_unit_is_minute_for_short_windows_and_day_for_long_windows()
    {
        $builder = app(ReportBuilder::class);
        $now = Carbon::now();

        $this->assertSame('minute', $builder->series($now->copy()->subHour(), $now->copy())['unit']);
        $this->assertSame('day', $builder->series($now->copy()->subDays(7), $now->copy())['unit']);
    }

    public function test_series_is_present_in_the_report_payload()
    {
        $report = app(ReportBuilder::class)->build(Carbon::now()->subHours(24), Carbon::now());

        $this->assertArrayHasKey('series', $report);
        $this->assertArrayHasKey('unit', $report['series']);
        $this->assertArrayHasKey('points', $report['series']);
    }
}

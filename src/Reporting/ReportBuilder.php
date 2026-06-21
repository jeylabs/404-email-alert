<?php

namespace Jeylabs\PageNotFoundEmailAlert\Reporting;

use Illuminate\Support\Carbon;
use Jeylabs\PageNotFoundEmailAlert\Models\RequestLog;

/**
 * Compiles recorded "not so great" requests into an aggregated report. Shared
 * by the report command, the JSON API and the dashboard so they always agree.
 */
class ReportBuilder
{
    /**
     * Build the aggregated report payload for the given window.
     *
     * @param  \Illuminate\Support\Carbon  $start
     * @param  \Illuminate\Support\Carbon  $end
     * @param  int  $limit
     * @return array
     */
    public function build(Carbon $start, Carbon $end, $limit = 20)
    {
        $limit = max(1, (int) $limit);
        $window = fn () => RequestLog::query()->between($start, $end);

        $total = $window()->count();

        $byStatus = $window()
            ->selectRaw('status_code, COUNT(*) as aggregate')
            ->groupBy('status_code')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row) => [
                'status' => (int) $row->status_code,
                'count'  => (int) $row->aggregate,
            ])
            ->all();

        $clientErrors = collect($byStatus)
            ->filter(fn ($row) => $row['status'] >= 400 && $row['status'] < 500)
            ->sum('count');

        $serverErrors = collect($byStatus)
            ->filter(fn ($row) => $row['status'] >= 500)
            ->sum('count');

        $topPaths = $window()
            ->selectRaw('path, COUNT(*) as aggregate, MAX(created_at) as last_seen')
            ->groupBy('path')
            ->orderByDesc('aggregate')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'path'      => $row->path,
                'count'     => (int) $row->aggregate,
                'last_seen' => (string) $row->last_seen,
            ])
            ->all();

        $topIps = $window()
            ->whereNotNull('ip')
            ->selectRaw('ip, COUNT(*) as aggregate')
            ->groupBy('ip')
            ->orderByDesc('aggregate')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'ip'    => $row->ip,
                'count' => (int) $row->aggregate,
            ])
            ->all();

        $topUserAgents = $window()
            ->whereNotNull('user_agent')
            ->selectRaw('user_agent, COUNT(*) as aggregate')
            ->groupBy('user_agent')
            ->orderByDesc('aggregate')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'user_agent' => $row->user_agent,
                'count'      => (int) $row->aggregate,
            ])
            ->all();

        return [
            'from'            => $start->toDateTimeString(),
            'to'              => $end->toDateTimeString(),
            'hours'           => (int) round($start->diffInMinutes($end) / 60),
            'total'           => $total,
            'client_errors'   => (int) $clientErrors,
            'server_errors'   => (int) $serverErrors,
            'by_status'       => $byStatus,
            'top_paths'       => $topPaths,
            'top_ips'         => $topIps,
            'top_user_agents' => $topUserAgents,
            'series'          => $this->series($start, $end),
        ];
    }

    /**
     * Build a zero-filled time-series of failed requests across the window,
     * bucketed by minute/hour/day depending on the window length. Each point
     * carries the total plus the 4xx/5xx split so the trend can be charted.
     *
     * @param  \Illuminate\Support\Carbon  $start
     * @param  \Illuminate\Support\Carbon  $end
     * @return array
     */
    public function series(Carbon $start, Carbon $end)
    {
        $unit = $this->resolveUnit($start, $end);
        $counts = $this->bucketCounts($start, $end, $unit);

        $points = [];
        $cursor = $this->floor($start->copy(), $unit);

        // Guard against pathological windows producing an unbounded series.
        for ($i = 0; $cursor->lessThanOrEqualTo($end) && $i < 1500; $i++) {
            $key = $cursor->format('Y-m-d H:i:s');
            $bucket = $counts[$key] ?? ['client' => 0, 'server' => 0];

            $points[] = [
                'period'        => $key,
                'client_errors' => (int) $bucket['client'],
                'server_errors' => (int) $bucket['server'],
                'total'         => (int) $bucket['client'] + (int) $bucket['server'],
            ];

            $this->step($cursor, $unit);
        }

        return [
            'unit'   => $unit,
            'points' => $points,
        ];
    }

    /**
     * Aggregate counts per time bucket, keyed by the normalised bucket
     * timestamp. Uses a driver-specific SQL expression where available and
     * falls back to bucketing in PHP for other databases.
     *
     * @param  \Illuminate\Support\Carbon  $start
     * @param  \Illuminate\Support\Carbon  $end
     * @param  string  $unit
     * @return array<string, array{client: int, server: int}>
     */
    protected function bucketCounts(Carbon $start, Carbon $end, $unit)
    {
        $window = RequestLog::query()->between($start, $end);
        $expression = $this->bucketExpression($unit);

        if ($expression === null) {
            return $this->bucketCountsInPhp($start, $end, $unit);
        }

        $rows = $window
            ->selectRaw($expression.' as bucket')
            ->selectRaw('SUM(CASE WHEN status_code >= 400 AND status_code < 500 THEN 1 ELSE 0 END) as client_errors')
            ->selectRaw('SUM(CASE WHEN status_code >= 500 THEN 1 ELSE 0 END) as server_errors')
            ->groupByRaw($expression)
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row->bucket] = [
                'client' => (int) $row->client_errors,
                'server' => (int) $row->server_errors,
            ];
        }

        return $counts;
    }

    /**
     * Portable fallback: pull the minimal columns and bucket them in PHP.
     *
     * @param  \Illuminate\Support\Carbon  $start
     * @param  \Illuminate\Support\Carbon  $end
     * @param  string  $unit
     * @return array<string, array{client: int, server: int}>
     */
    protected function bucketCountsInPhp(Carbon $start, Carbon $end, $unit)
    {
        $counts = [];

        RequestLog::query()->between($start, $end)
            ->select(['status_code', 'created_at'])
            ->cursor()
            ->each(function ($row) use (&$counts, $unit) {
                if (! $row->created_at) {
                    return;
                }

                $key = $this->floor($row->created_at->copy(), $unit)->format('Y-m-d H:i:s');
                $counts[$key] ??= ['client' => 0, 'server' => 0];

                if ($row->status_code >= 500) {
                    $counts[$key]['server']++;
                } elseif ($row->status_code >= 400) {
                    $counts[$key]['client']++;
                }
            });

        return $counts;
    }

    /**
     * Choose a bucket unit yielding a sensible number of points for the window.
     *
     * @param  \Illuminate\Support\Carbon  $start
     * @param  \Illuminate\Support\Carbon  $end
     * @return string  minute|hour|day
     */
    protected function resolveUnit(Carbon $start, Carbon $end)
    {
        $hours = max(1, (int) $start->diffInHours($end));

        if ($hours <= 2) {
            return 'minute';
        }

        if ($hours <= 72) {
            return 'hour';
        }

        return 'day';
    }

    /**
     * The driver-specific SQL expression normalising created_at to a bucket
     * timestamp string, or null when the driver is not directly supported.
     *
     * @param  string  $unit
     * @return string|null
     */
    protected function bucketExpression($unit)
    {
        $driver = RequestLog::query()->getConnection()->getDriverName();

        $formats = [
            'sqlite' => [
                'minute' => "strftime('%Y-%m-%d %H:%M:00', created_at)",
                'hour'   => "strftime('%Y-%m-%d %H:00:00', created_at)",
                'day'    => "strftime('%Y-%m-%d 00:00:00', created_at)",
            ],
            'mysql' => [
                'minute' => "DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:00')",
                'hour'   => "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')",
                'day'    => "DATE_FORMAT(created_at, '%Y-%m-%d 00:00:00')",
            ],
            'mariadb' => [
                'minute' => "DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:00')",
                'hour'   => "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')",
                'day'    => "DATE_FORMAT(created_at, '%Y-%m-%d 00:00:00')",
            ],
            'pgsql' => [
                'minute' => "to_char(created_at, 'YYYY-MM-DD HH24:MI:00')",
                'hour'   => "to_char(created_at, 'YYYY-MM-DD HH24:00:00')",
                'day'    => "to_char(created_at, 'YYYY-MM-DD 00:00:00')",
            ],
        ];

        return $formats[$driver][$unit] ?? null;
    }

    /**
     * Round a date down to the start of its bucket.
     *
     * @param  \Illuminate\Support\Carbon  $date
     * @param  string  $unit
     * @return \Illuminate\Support\Carbon
     */
    protected function floor(Carbon $date, $unit)
    {
        return match ($unit) {
            'minute' => $date->startOfMinute(),
            'day'    => $date->startOfDay(),
            default  => $date->startOfHour(),
        };
    }

    /**
     * Advance a cursor by one bucket.
     *
     * @param  \Illuminate\Support\Carbon  $date
     * @param  string  $unit
     * @return void
     */
    protected function step(Carbon $date, $unit)
    {
        match ($unit) {
            'minute' => $date->addMinute(),
            'day'    => $date->addDay(),
            default  => $date->addHour(),
        };
    }

    /**
     * Resolve a [start, end] window from an optional hour count / "since"
     * date-time, falling back to the configured default period.
     *
     * @param  int|null  $hours
     * @param  string|null  $since
     * @param  array  $reportConfig
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    public function window($hours, $since, array $reportConfig)
    {
        $end = Carbon::now();

        if (! empty($since)) {
            return [Carbon::parse($since), $end];
        }

        $hours = (int) ($hours ?: ($reportConfig['period_hours'] ?? 24));

        return [$end->copy()->subHours(max(1, $hours)), $end];
    }
}

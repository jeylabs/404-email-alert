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
        ];
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

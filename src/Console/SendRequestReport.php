<?php

namespace Jeylabs\PageNotFoundEmailAlert\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Jeylabs\PageNotFoundEmailAlert\Mail\RequestReport;
use Jeylabs\PageNotFoundEmailAlert\Models\RequestLog;

class SendRequestReport extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'page-not-found:report
        {--hours= : Number of hours to include in the report (defaults to config)}
        {--since= : Only include records on/after this date-time (overrides --hours)}
        {--to=* : Override the recipient address(es)}
        {--prune : Delete records older than the configured retention after reporting}
        {--dry : Render the report to the console instead of emailing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compile and email a report of recent 4xx/5xx (not so great) requests.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $config = (array) config('page-not-found-email-alert', []);
        $reportConfig = (array) ($config['report'] ?? []);

        $end = Carbon::now();
        $start = $this->resolveStart($end, $reportConfig);
        $limit = (int) ($reportConfig['limit'] ?? 20);

        $report = $this->compile($start, $end, max(1, $limit));

        if ($this->option('dry')) {
            $this->renderToConsole($report);

            $this->maybePrune($config);

            return self::SUCCESS;
        }

        if ($report['total'] === 0 && ! ($reportConfig['send_when_empty'] ?? false)) {
            $this->info('No failed requests recorded for the selected period; nothing to send.');

            $this->maybePrune($config);

            return self::SUCCESS;
        }

        $recipients = $this->resolveRecipients($config, $reportConfig);

        if (empty($recipients)) {
            $this->warn('No report recipients configured; skipping. Set PAGE_NOT_FOUND_REPORT_TO or pass --to.');

            return self::FAILURE;
        }

        Mail::to($recipients)->send(new RequestReport($report, $config));

        $this->info(sprintf(
            'Sent report covering %d failed request(s) to %s.',
            $report['total'],
            implode(', ', $recipients)
        ));

        $this->maybePrune($config);

        return self::SUCCESS;
    }

    /**
     * Determine the start of the reporting window.
     *
     * @param  \Illuminate\Support\Carbon  $end
     * @param  array  $reportConfig
     * @return \Illuminate\Support\Carbon
     */
    protected function resolveStart(Carbon $end, array $reportConfig)
    {
        if ($since = $this->option('since')) {
            return Carbon::parse($since);
        }

        $hours = (int) ($this->option('hours') ?: ($reportConfig['period_hours'] ?? 24));

        return $end->copy()->subHours(max(1, $hours));
    }

    /**
     * Build the aggregated report payload for the given window.
     *
     * @param  \Illuminate\Support\Carbon  $start
     * @param  \Illuminate\Support\Carbon  $end
     * @param  int  $limit
     * @return array
     */
    protected function compile(Carbon $start, Carbon $end, $limit)
    {
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
     * Resolve the recipient list, falling back through the configuration.
     *
     * @param  array  $config
     * @param  array  $reportConfig
     * @return array
     */
    protected function resolveRecipients(array $config, array $reportConfig)
    {
        $recipients = (array) ($this->option('to') ?: []);

        if (empty($recipients)) {
            $recipients = (array) ($reportConfig['to'] ?? []);
        }

        if (empty($recipients)) {
            $recipients = (array) ($config['to'] ?? []);
        }

        return array_values(array_filter($recipients));
    }

    /**
     * Optionally prune records older than the configured retention period.
     *
     * @param  array  $config
     * @return void
     */
    protected function maybePrune(array $config)
    {
        if (! $this->option('prune')) {
            return;
        }

        $days = (int) (($config['record']['retention_days'] ?? 0));

        if ($days <= 0) {
            return;
        }

        $deleted = RequestLog::where('created_at', '<', Carbon::now()->subDays($days))->delete();

        $this->info(sprintf('Pruned %d record(s) older than %d day(s).', $deleted, $days));
    }

    /**
     * Render the report as tables in the console (used by --dry).
     *
     * @param  array  $report
     * @return void
     */
    protected function renderToConsole(array $report)
    {
        $this->line(sprintf('<info>Window:</info> %s → %s', $report['from'], $report['to']));
        $this->line(sprintf(
            '<info>Total:</info> %d  (4xx: %d, 5xx: %d)',
            $report['total'],
            $report['client_errors'],
            $report['server_errors']
        ));

        if ($report['total'] === 0) {
            $this->info('No failed requests recorded for the selected period.');

            return;
        }

        $this->newLine();
        $this->line('<comment>By status code</comment>');
        $this->table(['Status', 'Count'], array_map(
            fn ($row) => [$row['status'], $row['count']],
            $report['by_status']
        ));

        $this->line('<comment>Top paths</comment>');
        $this->table(['Path', 'Count', 'Last seen'], array_map(
            fn ($row) => [$row['path'], $row['count'], $row['last_seen']],
            $report['top_paths']
        ));

        if (! empty($report['top_ips'])) {
            $this->line('<comment>Top IP addresses</comment>');
            $this->table(['IP', 'Count'], array_map(
                fn ($row) => [$row['ip'], $row['count']],
                $report['top_ips']
            ));
        }
    }
}

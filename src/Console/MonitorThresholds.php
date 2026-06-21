<?php

namespace Jeylabs\PageNotFoundEmailAlert\Console;

use Illuminate\Console\Command;
use Jeylabs\PageNotFoundEmailAlert\Reporting\ThresholdMonitor;

class MonitorThresholds extends Command
{
    /**
     * @var string
     */
    protected $signature = 'page-not-found:monitor
        {--dry : Show each rule\'s current count without sending alerts}';

    /**
     * @var string
     */
    protected $description = 'Evaluate threshold/spike alert rules against recently recorded requests.';

    /**
     * @return int
     */
    public function handle(ThresholdMonitor $monitor)
    {
        if (! config('page-not-found-email-alert.alerts.enabled', false)) {
            $this->warn('Threshold alerts are disabled (alerts.enabled = false).');

            return self::SUCCESS;
        }

        if ($this->option('dry')) {
            $rows = array_map(fn ($r) => [
                $r['name'],
                $r['count'],
                $r['threshold'],
                $r['window'].'m',
                $r['breached'] ? 'BREACHED' : 'ok',
            ], $monitor->preview());

            $this->table(['Rule', 'Count', 'Threshold', 'Window', 'Status'], $rows);

            return self::SUCCESS;
        }

        $triggered = $monitor->evaluate();

        if (empty($triggered)) {
            $this->info('No thresholds breached.');

            return self::SUCCESS;
        }

        foreach ($triggered as $rule) {
            $this->warn(sprintf(
                'ALERT: %s — %d in %dm (threshold %d).',
                $rule['name'],
                $rule['count'],
                $rule['window'],
                $rule['threshold']
            ));
        }

        return self::SUCCESS;
    }
}

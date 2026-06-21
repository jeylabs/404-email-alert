<?php

namespace Jeylabs\PageNotFoundEmailAlert\Reporting;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Jeylabs\PageNotFoundEmailAlert\Mail\ThresholdAlert;
use Jeylabs\PageNotFoundEmailAlert\Models\RequestLog;

/**
 * Evaluates threshold/spike rules against recently recorded requests and emails
 * an alert when a rule is breached. Shared by the recording path (near
 * real-time, rate-limited) and the `page-not-found:monitor` command.
 */
class ThresholdMonitor
{
    /**
     * Evaluate if enabled and the realtime check-interval has elapsed. Cheap to
     * call on every recorded request: it short-circuits on a single cache read.
     *
     * @return array  the rules that fired
     */
    public function maybeEvaluate()
    {
        $config = $this->config();

        if (! ($config['enabled'] ?? false)) {
            return [];
        }

        $realtime = (array) ($config['realtime'] ?? []);

        if (! ($realtime['enabled'] ?? false)) {
            return [];
        }

        $interval = (int) ($realtime['check_interval'] ?? 60);

        if ($interval > 0) {
            if (Cache::get('pnf:monitor:last-run')) {
                return [];
            }

            Cache::put('pnf:monitor:last-run', true, $interval);
        }

        return $this->evaluate();
    }

    /**
     * Evaluate every rule now, sending alerts for any that are breached (and
     * not on cooldown).
     *
     * @return array  the rules that fired
     */
    public function evaluate()
    {
        $config = $this->config();

        if (! ($config['enabled'] ?? false) || ! RequestLog::tableExists()) {
            return [];
        }

        $triggered = [];

        foreach ((array) ($config['rules'] ?? []) as $index => $rule) {
            $window = (int) ($rule['window'] ?? 5);
            $threshold = (int) ($rule['threshold'] ?? 0);

            if ($window <= 0 || $threshold <= 0) {
                continue;
            }

            $count = $this->countFor($rule, $window);

            if ($count < $threshold || $this->onCooldown($index, $rule)) {
                continue;
            }

            $this->markCooldown($index, $rule, $config);

            $payload = $this->payload($index, $rule, $count, $window, $threshold);
            $this->dispatchAlert($payload, $config);

            $triggered[] = $payload;
        }

        return $triggered;
    }

    /**
     * Compute the current count for every rule without sending anything or
     * touching cooldowns. Used by `page-not-found:monitor --dry`.
     *
     * @return array
     */
    public function preview()
    {
        $config = $this->config();
        $rules = (array) ($config['rules'] ?? []);
        $out = [];

        foreach ($rules as $index => $rule) {
            $window = (int) ($rule['window'] ?? 5);
            $threshold = (int) ($rule['threshold'] ?? 0);
            $count = ($window > 0 && RequestLog::tableExists()) ? $this->countFor($rule, $window) : 0;

            $out[] = [
                'name'      => $rule['name'] ?? ('Rule #'.($index + 1)),
                'count'     => $count,
                'threshold' => $threshold,
                'window'    => $window,
                'breached'  => $threshold > 0 && $count >= $threshold,
            ];
        }

        return $out;
    }

    /**
     * Count matching records within the rule's window.
     *
     * @param  array  $rule
     * @param  int  $window
     * @return int
     */
    protected function countFor(array $rule, $window)
    {
        $query = RequestLog::query()
            ->where('created_at', '>=', Carbon::now()->subMinutes($window));

        $statuses = array_map('intval', (array) ($rule['statuses'] ?? []));

        if (! empty($statuses)) {
            $query->whereIn('status_code', $statuses);
        } else {
            $query->where('status_code', '>=', (int) ($rule['min_status'] ?? 400));

            if (! empty($rule['max_status'])) {
                $query->where('status_code', '<=', (int) $rule['max_status']);
            }
        }

        return $query->count();
    }

    /**
     * Build the alert payload for a breached rule.
     */
    protected function payload($index, array $rule, $count, $window, $threshold)
    {
        return [
            'name'       => $rule['name'] ?? ('Rule #'.($index + 1)),
            'count'      => $count,
            'threshold'  => $threshold,
            'window'     => $window,
            'min_status' => $rule['min_status'] ?? null,
            'max_status' => $rule['max_status'] ?? null,
            'statuses'   => array_values((array) ($rule['statuses'] ?? [])),
            'since'      => Carbon::now()->subMinutes($window)->toDateTimeString(),
        ];
    }

    /**
     * @return bool
     */
    protected function onCooldown($index, array $rule)
    {
        return (bool) Cache::get($this->cooldownKey($index, $rule));
    }

    /**
     * @return void
     */
    protected function markCooldown($index, array $rule, array $config)
    {
        $minutes = (int) ($config['cooldown'] ?? 30);

        if ($minutes <= 0) {
            return;
        }

        Cache::put($this->cooldownKey($index, $rule), true, $minutes * 60);
    }

    /**
     * @return string
     */
    protected function cooldownKey($index, array $rule)
    {
        return 'pnf:monitor:cooldown:'.md5($index.'|'.json_encode($rule));
    }

    /**
     * @return void
     */
    protected function dispatchAlert(array $payload, array $config)
    {
        $recipients = $this->recipients($config);

        if (empty($recipients)) {
            return;
        }

        try {
            Mail::to($recipients)->send(
                new ThresholdAlert($payload, (array) config('page-not-found-email-alert', []))
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send threshold alert: '.$e->getMessage(), [
                'exception' => $e,
                'rule'      => $payload['name'],
            ]);
        }
    }

    /**
     * Resolve recipients: alert list, then report list, then the 404 alert list.
     *
     * @return array
     */
    protected function recipients(array $config)
    {
        $root = (array) config('page-not-found-email-alert', []);

        $recipients = (array) ($config['to'] ?? []);

        if (empty($recipients)) {
            $recipients = (array) ($root['report']['to'] ?? []);
        }

        if (empty($recipients)) {
            $recipients = (array) ($root['to'] ?? []);
        }

        return array_values(array_filter($recipients));
    }

    /**
     * @return array
     */
    protected function config()
    {
        return (array) config('page-not-found-email-alert.alerts', []);
    }
}

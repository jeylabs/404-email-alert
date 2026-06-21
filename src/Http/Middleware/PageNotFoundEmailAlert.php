<?php

namespace Jeylabs\PageNotFoundEmailAlert\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Jeylabs\PageNotFoundEmailAlert\Jobs\RecordBadRequest;
use Jeylabs\PageNotFoundEmailAlert\Mail\PageNotFound;
use Jeylabs\PageNotFoundEmailAlert\Models\RequestLog;
use Symfony\Component\HttpFoundation\Response;

class PageNotFoundEmailAlert
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($response instanceof Response) {
            $status = $response->getStatusCode();

            if ($this->shouldRecord($request, $status)) {
                $this->record($request, $status);
            }

            if ($this->shouldAlert($request, $status)) {
                $this->sendAlert($request);
            }
        }

        return $response;
    }

    /**
     * Determine whether the given response should be recorded for reporting.
     * Any client (4xx) or server (5xx) error qualifies by default; the exact
     * set of status codes can be narrowed via configuration.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $status
     * @return bool
     */
    protected function shouldRecord(Request $request, $status)
    {
        $config = $this->config();
        $record = (array) ($config['record'] ?? []);

        if (! ($record['enabled'] ?? false)) {
            return false;
        }

        $statuses = array_map('intval', (array) ($record['statuses'] ?? []));

        if (! empty($statuses)) {
            if (! in_array((int) $status, $statuses, true)) {
                return false;
            }
        } elseif ((int) $status < (int) ($record['minimum_status'] ?? 400)) {
            return false;
        }

        return ! $this->isIgnored($request, $config['ignore'] ?? []);
    }

    /**
     * Persist a record of the request/response so it can be aggregated into a
     * report later. Storage failures are logged but never break the response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $status
     * @return void
     */
    protected function record(Request $request, $status)
    {
        $attributes = [
            'status_code' => (int) $status,
            'method'      => $request->method(),
            'url'         => $request->fullUrl(),
            'path'        => $request->path(),
            'referer'     => $request->headers->get('referer'),
            'ip'          => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ];

        try {
            $queue = (array) ($this->config()['record']['queue'] ?? []);

            if ($queue['enabled'] ?? false) {
                $this->dispatchRecord($attributes, $queue);

                return;
            }

            // Synchronous fallback: write inline within the request.
            if (RequestLog::tableExists()) {
                RequestLog::create($attributes);

                app(\Jeylabs\PageNotFoundEmailAlert\Reporting\ThresholdMonitor::class)->maybeEvaluate();
            }
        } catch (\Throwable $e) {
            Log::error('Failed to record bad request: '.$e->getMessage(), [
                'exception' => $e,
                'url'       => $attributes['url'],
                'status'    => $status,
            ]);
        }
    }

    /**
     * Dispatch the recording job onto the configured queue/connection.
     *
     * @param  array  $attributes
     * @param  array  $queue
     * @return void
     */
    protected function dispatchRecord(array $attributes, array $queue)
    {
        $job = new RecordBadRequest($attributes);

        if (! empty($queue['connection'])) {
            $job->onConnection($queue['connection']);
        }

        if (! empty($queue['queue'])) {
            $job->onQueue($queue['queue']);
        }

        dispatch($job);
    }

    /**
     * Determine whether an alert should be sent for this request/response pair.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $status
     * @return bool
     */
    protected function shouldAlert(Request $request, $status)
    {
        $config = $this->config();

        if (! ($config['enabled'] ?? false)) {
            return false;
        }

        if ((int) $status !== 404) {
            return false;
        }

        if (empty($config['to'])) {
            return false;
        }

        if ($this->isIgnored($request, $config['ignore'] ?? [])) {
            return false;
        }

        return ! $this->isThrottled($request, (int) ($config['throttle'] ?? 0));
    }

    /**
     * Check the request path against the configured ignore patterns.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $patterns
     * @return bool
     */
    protected function isIgnored(Request $request, array $patterns)
    {
        $path = $request->path();

        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $path) || Str::is('/'.ltrim($pattern, '/'), '/'.$path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether an alert for this URL has been sent recently, and if
     * not, record that we are about to send one.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $minutes
     * @return bool
     */
    protected function isThrottled(Request $request, $minutes)
    {
        if ($minutes <= 0) {
            return false;
        }

        $key = 'page-not-found-email-alert:'.md5($request->fullUrl());

        if (Cache::has($key)) {
            return true;
        }

        Cache::put($key, true, $this->throttleTtl($minutes));

        return false;
    }

    /**
     * Build the cache TTL value, accommodating both the older (minutes) and
     * newer (DateInterval/seconds) Cache::put signatures across Laravel
     * versions.
     *
     * @param  int  $minutes
     * @return \DateInterval|int
     */
    protected function throttleTtl($minutes)
    {
        if (class_exists(\DateInterval::class)) {
            return new \DateInterval('PT'.$minutes.'M');
        }

        return $minutes * 60;
    }

    /**
     * Send the 404 alert email. Mail failures are logged but never allowed to
     * break the underlying response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function sendAlert(Request $request)
    {
        $config = $this->config();

        $data = [
            'url'        => $request->fullUrl(),
            'method'     => $request->method(),
            'referer'    => $request->headers->get('referer'),
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp'  => date('Y-m-d H:i:s'),
        ];

        try {
            Mail::to($config['to'])->send(new PageNotFound($data, $config));
        } catch (\Throwable $e) {
            Log::error('Failed to send 404 email alert: '.$e->getMessage(), [
                'exception' => $e,
                'url'       => $data['url'],
            ]);
        }
    }

    /**
     * Resolve the package configuration.
     *
     * @return array
     */
    protected function config()
    {
        return (array) config('page-not-found-email-alert', []);
    }
}

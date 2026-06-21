<?php

namespace Jeylabs\PageNotFoundEmailAlert\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Jeylabs\PageNotFoundEmailAlert\Models\RequestLog;

/**
 * Persists a recorded "not so great" request off the request lifecycle. When a
 * real queue connection is configured the database write happens on a worker;
 * with the "sync" connection it runs inline (same as before).
 */
class RecordBadRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * The request log attributes to persist.
     *
     * @var array
     */
    public $attributes;

    /**
     * @param  array  $attributes
     */
    public function __construct(array $attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * Write the record. Failures are logged and swallowed so a recording
     * problem can never escalate (it is not worth retrying a 404 log entry).
     *
     * @return void
     */
    public function handle()
    {
        try {
            if (! RequestLog::tableExists()) {
                return;
            }

            RequestLog::create($this->attributes);
        } catch (\Throwable $e) {
            Log::error('Failed to record bad request (queued): '.$e->getMessage(), [
                'exception' => $e,
                'url'       => $this->attributes['url'] ?? null,
                'status'    => $this->attributes['status_code'] ?? null,
            ]);
        }
    }
}

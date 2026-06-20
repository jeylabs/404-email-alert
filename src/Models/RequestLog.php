<?php

namespace Jeylabs\PageNotFoundEmailAlert\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single recorded "not so great" request (any 4xx/5xx response).
 *
 * @property int         $id
 * @property int         $status_code
 * @property string      $method
 * @property string      $url
 * @property string      $path
 * @property string|null $referer
 * @property string|null $ip
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class RequestLog extends Model
{
    /**
     * The log is insert-only; there is no "updated_at" column.
     */
    const UPDATED_AT = null;

    /**
     * All attributes are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * Attribute casts.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status_code' => 'integer',
        'created_at'  => 'datetime',
    ];

    /**
     * Resolve the table name from configuration so it stays in sync with the
     * published migration.
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table
            ?? config('page-not-found-email-alert.record.table', 'page_not_found_request_logs');
    }

    /**
     * Scope the query to records created within the given window (inclusive).
     */
    public function scopeBetween($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }
}

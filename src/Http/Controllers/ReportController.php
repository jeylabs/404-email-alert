<?php

namespace Jeylabs\PageNotFoundEmailAlert\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Jeylabs\PageNotFoundEmailAlert\Http\Middleware\EnsureAllowedGoogleUser;
use Jeylabs\PageNotFoundEmailAlert\Models\RequestLog;
use Jeylabs\PageNotFoundEmailAlert\Reporting\ReportBuilder;

class ReportController
{
    /**
     * @var \Jeylabs\PageNotFoundEmailAlert\Reporting\ReportBuilder
     */
    protected $builder;

    public function __construct(ReportBuilder $builder)
    {
        $this->builder = $builder;
    }

    /**
     * Render the HTML dashboard.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\View\View
     */
    public function index(Request $request)
    {
        return view('page-not-found-email-alert::dashboard', [
            'report'    => $this->report($request),
            'hours'     => $this->hours($request),
            'authEmail' => $request->hasSession()
                ? $request->session()->get(EnsureAllowedGoogleUser::SESSION_KEY)
                : null,
        ]);
    }

    /**
     * Drill-down: a paginated, filterable list of individual recorded requests.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\View\View
     */
    public function requests(Request $request)
    {
        [$start, $end] = $this->builder->window(
            $this->hours($request),
            null,
            (array) config('page-not-found-email-alert.report', [])
        );

        $filters = [
            'path'   => (string) $request->query('path', ''),
            'search' => (string) $request->query('search', ''),
            'status' => (string) $request->query('status', ''),
            'bot'    => (string) $request->query('bot', ''),
            'hours'  => $this->hours($request),
        ];

        $query = RequestLog::query()
            ->between($start, $end)
            ->orderByDesc('created_at');

        if ($filters['path'] !== '') {
            $query->where('path', $filters['path']);
        }

        if ($filters['search'] !== '') {
            $query->where('path', 'like', '%'.$filters['search'].'%');
        }

        if ($filters['status'] !== '') {
            $query->where('status_code', (int) $filters['status']);
        }

        if ($filters['bot'] !== '') {
            $query->where('is_bot', (bool) (int) $filters['bot']);
        }

        $rows = $query->paginate(50)->withQueryString();

        return view('page-not-found-email-alert::requests', [
            'rows'      => $rows,
            'filters'   => $filters,
            'window'    => ['from' => $start->toDateTimeString(), 'to' => $end->toDateTimeString()],
            'authEmail' => $request->hasSession()
                ? $request->session()->get(EnsureAllowedGoogleUser::SESSION_KEY)
                : null,
        ]);
    }

    /**
     * Return the aggregated report as JSON.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function data(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->report($request),
        ]);
    }

    /**
     * Compile the report for the requested (or configured) window.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function report(Request $request)
    {
        $reportConfig = (array) config('page-not-found-email-alert.report', []);

        [$start, $end] = $this->builder->window($this->hours($request), null, $reportConfig);

        return $this->builder->build($start, $end, (int) ($reportConfig['limit'] ?? 20));
    }

    /**
     * Resolve the window size (in hours) from the request, clamped to a sane
     * range, defaulting to the configured reporting period.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return int
     */
    protected function hours(Request $request)
    {
        $default = (int) config('page-not-found-email-alert.report.period_hours', 24);
        $hours = (int) $request->query('hours', $default);

        return max(1, min($hours, 24 * 365));
    }
}

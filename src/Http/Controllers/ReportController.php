<?php

namespace Jeylabs\PageNotFoundEmailAlert\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Jeylabs\PageNotFoundEmailAlert\Http\Middleware\EnsureAllowedGoogleUser;
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

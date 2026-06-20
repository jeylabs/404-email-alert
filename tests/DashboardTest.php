<?php

namespace Jeylabs\PageNotFoundEmailAlert\Tests;

use Illuminate\Support\Carbon;
use Jeylabs\PageNotFoundEmailAlert\Models\RequestLog;

class DashboardTest extends TestCase
{
    /**
     * Enable the dashboard and API (off by default) before the provider boots
     * so their routes get registered for these tests.
     */
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('page-not-found-email-alert.dashboard.enabled', true);
        $app['config']->set('page-not-found-email-alert.dashboard.middleware', []);
        $app['config']->set('page-not-found-email-alert.dashboard.path', 'page-not-found');

        $app['config']->set('page-not-found-email-alert.api.enabled', true);
        $app['config']->set('page-not-found-email-alert.api.middleware', []);
        $app['config']->set('page-not-found-email-alert.api.path', 'api/page-not-found');

        // Exercise the raw dashboard/API here; access control has its own test.
        $app['config']->set('page-not-found-email-alert.auth.enabled', false);
    }

    protected function seedLogs(): void
    {
        foreach ([404, 404, 500] as $i => $status) {
            RequestLog::create([
                'status_code' => $status,
                'method'      => 'GET',
                'url'         => 'https://example.com/p'.$i,
                'path'        => 'p'.$i,
                'ip'          => '1.2.3.4',
                'user_agent'  => 'phpunit',
                'created_at'  => Carbon::now()->subMinutes(5),
            ]);
        }
    }

    public function test_dashboard_renders_html()
    {
        $this->seedLogs();

        $this->get('/page-not-found')
            ->assertOk()
            ->assertSee('Not So Great Requests')
            ->assertSee('Client errors');
    }

    public function test_api_returns_json_aggregates()
    {
        $this->seedLogs();

        $this->getJson('/api/page-not-found')
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.client_errors', 2)
            ->assertJsonPath('data.server_errors', 1);
    }

    public function test_api_respects_the_hours_query_parameter()
    {
        RequestLog::create([
            'status_code' => 404,
            'method'      => 'GET',
            'url'         => 'https://example.com/old',
            'path'        => 'old',
            'ip'          => '9.9.9.9',
            'user_agent'  => 'phpunit',
            'created_at'  => Carbon::now()->subDays(5),
        ]);

        $this->getJson('/api/page-not-found?hours=24')
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_routes_are_registered_when_enabled()
    {
        $routes = $this->app['router']->getRoutes();

        $this->assertTrue($routes->hasNamedRoute('page-not-found.dashboard'));
        $this->assertTrue($routes->hasNamedRoute('page-not-found.api'));
    }
}

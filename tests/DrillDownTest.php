<?php

namespace Jeylabs\PageNotFoundEmailAlert\Tests;

use Illuminate\Support\Carbon;
use Jeylabs\PageNotFoundEmailAlert\Models\RequestLog;

class DrillDownTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('page-not-found-email-alert.dashboard.enabled', true);
        $app['config']->set('page-not-found-email-alert.dashboard.middleware', []);
        $app['config']->set('page-not-found-email-alert.auth.enabled', false);
    }

    protected function record(array $attributes): void
    {
        RequestLog::create(array_merge([
            'status_code' => 404,
            'method'      => 'GET',
            'url'         => 'https://example.com/x',
            'path'        => 'x',
            'ip'          => '1.2.3.4',
            'user_agent'  => 'phpunit',
            'is_bot'      => false,
            'created_at'  => Carbon::now()->subMinutes(2),
        ], $attributes));
    }

    public function test_it_lists_recorded_requests()
    {
        $this->record(['path' => 'alpha']);
        $this->record(['path' => 'beta', 'status_code' => 500]);

        $this->get('/page-not-found/requests')
            ->assertOk()
            ->assertSee('alpha')
            ->assertSee('beta');
    }

    public function test_it_filters_by_exact_path()
    {
        $this->record(['path' => 'alpha']);
        $this->record(['path' => 'beta']);

        $response = $this->get('/page-not-found/requests?path=alpha')->assertOk();

        $response->assertSee('alpha');
        $response->assertDontSee('beta');
    }

    public function test_it_filters_by_status_and_bot()
    {
        $this->record(['path' => 'human-404', 'is_bot' => false, 'status_code' => 404]);
        $this->record(['path' => 'bot-500', 'is_bot' => true, 'status_code' => 500]);

        $this->get('/page-not-found/requests?status=500')
            ->assertOk()->assertSee('bot-500')->assertDontSee('human-404');

        $this->get('/page-not-found/requests?bot=1')
            ->assertOk()->assertSee('bot-500')->assertDontSee('human-404');
    }

    public function test_it_searches_paths()
    {
        $this->record(['path' => 'wp-admin/login']);
        $this->record(['path' => 'account/settings']);

        $this->get('/page-not-found/requests?search=wp-admin')
            ->assertOk()->assertSee('wp-admin/login')->assertDontSee('account/settings');
    }
}

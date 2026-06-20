<?php

namespace Jeylabs\PageNotFoundEmailAlert\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use Jeylabs\PageNotFoundEmailAlert\PageNotFoundEmailAlertServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * Register the package service provider.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            PageNotFoundEmailAlertServiceProvider::class,
        ];
    }

    /**
     * Define a sensible default configuration for the tests.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('page-not-found-email-alert.enabled', true);
        $app['config']->set('page-not-found-email-alert.to', ['admin@example.com']);
        $app['config']->set('page-not-found-email-alert.throttle', 0);
        $app['config']->set('page-not-found-email-alert.ignore', ['favicon.ico']);
        $app['config']->set('page-not-found-email-alert.record.enabled', true);
    }
}

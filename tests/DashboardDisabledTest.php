<?php

namespace Jeylabs\PageNotFoundEmailAlert\Tests;

class DashboardDisabledTest extends TestCase
{
    public function test_dashboard_and_api_routes_are_not_registered_by_default()
    {
        $routes = $this->app['router']->getRoutes();

        $this->assertFalse($routes->hasNamedRoute('page-not-found.dashboard'));
        $this->assertFalse($routes->hasNamedRoute('page-not-found.api'));
    }
}

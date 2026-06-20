<?php

namespace Jeylabs\PageNotFoundEmailAlert\Tests;

use Illuminate\Console\Scheduling\Schedule;

class ScheduleTest extends TestCase
{
    public function test_the_report_command_is_scheduled_by_default()
    {
        $schedule = $this->app->make(Schedule::class);

        $matches = collect($schedule->events())->filter(
            fn ($event) => str_contains((string) $event->command, 'page-not-found:report')
        );

        $this->assertCount(1, $matches);
        $this->assertStringContainsString('--prune', (string) $matches->first()->command);
    }
}

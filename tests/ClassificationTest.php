<?php

namespace Jeylabs\PageNotFoundEmailAlert\Tests;

use Illuminate\Support\Carbon;
use Jeylabs\PageNotFoundEmailAlert\Models\RequestLog;
use Jeylabs\PageNotFoundEmailAlert\Reporting\ReportBuilder;
use Jeylabs\PageNotFoundEmailAlert\Support\UserAgentClassifier;

class ClassificationTest extends TestCase
{
    public function test_classifier_detects_bots_and_humans()
    {
        $this->assertTrue(UserAgentClassifier::isBot('Mozilla/5.0 (compatible; Googlebot/2.1)'));
        $this->assertTrue(UserAgentClassifier::isBot('curl/8.4.0'));
        $this->assertTrue(UserAgentClassifier::isBot('sqlmap/1.7'));
        $this->assertTrue(UserAgentClassifier::isBot(''));   // blank UA → automated
        $this->assertTrue(UserAgentClassifier::isBot(null));

        $this->assertFalse(UserAgentClassifier::isBot(
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36'
        ));
    }

    public function test_classifier_honours_configured_extra_signatures()
    {
        config()->set('page-not-found-email-alert.record.bot_user_agents', ['acme-monitor']);

        $this->assertTrue(UserAgentClassifier::isBot('ACME-Monitor/1.0'));
    }

    public function test_recording_stores_bot_and_referer_classification()
    {
        $this->withHeaders([
            'User-Agent' => 'curl/8.4.0',
            'referer'    => 'http://localhost/some-internal-page',
        ])->get('/missing-page')->assertNotFound();

        $log = RequestLog::first();

        $this->assertTrue($log->is_bot);
        $this->assertTrue($log->referer_internal); // referer host matches request host
    }

    public function test_external_referer_is_classified_as_external()
    {
        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 Chrome/120 Safari/537.36',
            'referer'    => 'https://some-other-site.example/page',
        ])->get('/missing-page')->assertNotFound();

        $log = RequestLog::first();

        $this->assertFalse($log->is_bot);
        $this->assertFalse($log->referer_internal);
    }

    public function test_report_aggregates_traffic_and_referers()
    {
        $rows = [
            ['is_bot' => true,  'referer_internal' => null, 'referer' => null],
            ['is_bot' => false, 'referer_internal' => true, 'referer' => 'http://localhost/a'],
            ['is_bot' => false, 'referer_internal' => true, 'referer' => 'http://localhost/a'],
            ['is_bot' => false, 'referer_internal' => false, 'referer' => 'https://ext.example/x'],
        ];

        foreach ($rows as $row) {
            RequestLog::create(array_merge([
                'status_code' => 404,
                'method'      => 'GET',
                'url'         => 'https://example.com/x',
                'path'        => 'x',
                'ip'          => '1.2.3.4',
                'user_agent'  => 'phpunit',
                'created_at'  => Carbon::now()->subMinutes(2),
            ], $row));
        }

        $report = app(ReportBuilder::class)->build(Carbon::now()->subHours(24), Carbon::now());

        $this->assertSame(1, $report['traffic']['bots']);
        $this->assertSame(3, $report['traffic']['humans']);
        $this->assertSame(2, $report['referers']['internal']);
        $this->assertSame(1, $report['referers']['external']);
        $this->assertSame(1, $report['referers']['direct']);

        $this->assertSame('http://localhost/a', $report['top_referers'][0]['referer']);
        $this->assertSame(2, $report['top_referers'][0]['count']);
        $this->assertTrue($report['top_referers'][0]['internal']);
    }
}

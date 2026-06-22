<?php

namespace Jeylabs\PageNotFoundEmailAlert\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Jeylabs\PageNotFoundEmailAlert\Notifications\PageNotFoundAlertNotification;
use Jeylabs\PageNotFoundEmailAlert\Notifications\Notifier;

class MultiChannelNotificationTest extends TestCase
{
    protected function notification(): PageNotFoundAlertNotification
    {
        return new PageNotFoundAlertNotification([
            'url'        => 'https://example.com/missing',
            'method'     => 'GET',
            'referer'    => null,
            'ip'         => '1.2.3.4',
            'user_agent' => 'phpunit',
            'timestamp'  => '2026-06-22 10:00:00',
        ], (array) config('page-not-found-email-alert'));
    }

    public function test_it_only_uses_mail_by_default()
    {
        Notification::fake();

        Notifier::send($this->notification(), ['admin@example.com']);

        Notification::assertSentOnDemand(
            PageNotFoundAlertNotification::class,
            fn ($notification, $channels) => $channels === ['mail']
        );
    }

    public function test_enabled_chat_channels_are_added()
    {
        config()->set('page-not-found-email-alert.channels.slack', [
            'enabled' => true, 'webhook_url' => 'https://hooks.slack.test/abc',
        ]);
        config()->set('page-not-found-email-alert.channels.discord', [
            'enabled' => true, 'webhook_url' => 'https://discord.test/webhook',
        ]);

        Notification::fake();

        Notifier::send($this->notification(), ['admin@example.com']);

        Notification::assertSentOnDemand(
            PageNotFoundAlertNotification::class,
            function ($notification, $channels) {
                return in_array('mail', $channels)
                    && in_array(\Jeylabs\PageNotFoundEmailAlert\Notifications\Channels\SlackWebhookChannel::class, $channels)
                    && in_array(\Jeylabs\PageNotFoundEmailAlert\Notifications\Channels\DiscordWebhookChannel::class, $channels);
            }
        );
    }

    public function test_a_chat_channel_without_a_url_is_skipped()
    {
        config()->set('page-not-found-email-alert.channels.slack', ['enabled' => true, 'webhook_url' => null]);

        Notification::fake();

        Notifier::send($this->notification(), ['admin@example.com']);

        Notification::assertSentOnDemand(
            PageNotFoundAlertNotification::class,
            fn ($notification, $channels) => $channels === ['mail']
        );
    }

    public function test_slack_channel_posts_a_formatted_payload()
    {
        config()->set('page-not-found-email-alert.channels.mail.enabled', false);
        config()->set('page-not-found-email-alert.channels.slack', [
            'enabled' => true, 'webhook_url' => 'https://hooks.slack.test/abc',
        ]);

        Http::fake(['hooks.slack.test/*' => Http::response('ok')]);

        Notifier::send($this->notification(), []);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://hooks.slack.test/abc'
                && $request['text'] === '404 Page Not Found'
                && $request['attachments'][0]['color'] === '#d97706'
                && collect($request['attachments'][0]['fields'])->contains('value', 'https://example.com/missing');
        });
    }

    public function test_discord_channel_posts_an_embed()
    {
        config()->set('page-not-found-email-alert.channels.mail.enabled', false);
        config()->set('page-not-found-email-alert.channels.discord', [
            'enabled' => true, 'webhook_url' => 'https://discord.test/webhook',
        ]);

        Http::fake(['discord.test/*' => Http::response('', 204)]);

        Notifier::send($this->notification(), []);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://discord.test/webhook'
                && $request['embeds'][0]['title'] === '404 Page Not Found'
                && is_int($request['embeds'][0]['color']);
        });
    }

    public function test_teams_channel_posts_a_message_card()
    {
        config()->set('page-not-found-email-alert.channels.mail.enabled', false);
        config()->set('page-not-found-email-alert.channels.teams', [
            'enabled' => true, 'webhook_url' => 'https://teams.test/webhook',
        ]);

        Http::fake(['teams.test/*' => Http::response('1')]);

        Notifier::send($this->notification(), []);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://teams.test/webhook'
                && $request['@type'] === 'MessageCard'
                && $request['title'] === '404 Page Not Found';
        });
    }

    public function test_generic_webhook_posts_the_raw_payload()
    {
        config()->set('page-not-found-email-alert.channels.mail.enabled', false);
        config()->set('page-not-found-email-alert.channels.webhook', [
            'enabled' => true, 'url' => 'https://hooks.example.test/ingest',
        ]);

        Http::fake(['hooks.example.test/*' => Http::response('ok')]);

        Notifier::send($this->notification(), []);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://hooks.example.test/ingest'
                && $request['event'] === 'page-not-found.notification'
                && $request['title'] === '404 Page Not Found'
                && $request['level'] === 'warning';
        });
    }
}

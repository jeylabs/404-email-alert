<?php

namespace Jeylabs\PageNotFoundEmailAlert\Notifications;

use Illuminate\Notifications\Notification;
use Jeylabs\PageNotFoundEmailAlert\Notifications\Channels\DiscordWebhookChannel;
use Jeylabs\PageNotFoundEmailAlert\Notifications\Channels\GenericWebhookChannel;
use Jeylabs\PageNotFoundEmailAlert\Notifications\Channels\SlackWebhookChannel;
use Jeylabs\PageNotFoundEmailAlert\Notifications\Channels\TeamsWebhookChannel;

/**
 * Base notification that routes to whichever channels are enabled and
 * configured: the built-in "mail" channel plus the chat webhook channels.
 */
abstract class ChannelNotification extends Notification
{
    /**
     * Map of config keys to their webhook channel implementation.
     */
    const WEBHOOK_CHANNELS = [
        'slack'   => SlackWebhookChannel::class,
        'discord' => DiscordWebhookChannel::class,
        'teams'   => TeamsWebhookChannel::class,
        'webhook' => GenericWebhookChannel::class,
    ];

    /**
     * Determine the delivery channels for the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        $config = (array) config('page-not-found-email-alert.channels', []);
        $channels = [];

        if (($config['mail']['enabled'] ?? true) && ! empty($notifiable->routeNotificationFor('mail'))) {
            $channels[] = 'mail';
        }

        foreach (static::WEBHOOK_CHANNELS as $key => $class) {
            $channel = (array) ($config[$key] ?? []);
            $url = $channel['webhook_url'] ?? $channel['url'] ?? null;

            if (($channel['enabled'] ?? false) && ! empty($url)) {
                $channels[] = $class;
            }
        }

        return $channels;
    }

    /**
     * Build the chat representation used by the webhook channels.
     *
     * @return \Jeylabs\PageNotFoundEmailAlert\Notifications\ChatMessage
     */
    abstract public function toChatMessage(): ChatMessage;

    /**
     * Resolve the dashboard URL when the dashboard route is registered.
     *
     * @return string|null
     */
    protected function dashboardUrl()
    {
        try {
            if (\Illuminate\Support\Facades\Route::has('page-not-found.dashboard')) {
                return route('page-not-found.dashboard');
            }
        } catch (\Throwable $e) {
            // Routing not available (e.g. console without the dashboard enabled).
        }

        return null;
    }
}

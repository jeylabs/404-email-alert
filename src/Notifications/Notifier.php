<?php

namespace Jeylabs\PageNotFoundEmailAlert\Notifications;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Dispatches package notifications to the configured channels (mail + chat
 * webhooks) using an on-demand notifiable, and reports whether any channel is
 * currently active.
 */
class Notifier
{
    /**
     * Send a notification, routing mail to the given recipients. Chat channels
     * pick up their endpoints from configuration.
     *
     * @param  \Jeylabs\PageNotFoundEmailAlert\Notifications\ChannelNotification  $notification
     * @param  array  $mailRecipients
     * @return void
     */
    public static function send(ChannelNotification $notification, array $mailRecipients = [])
    {
        try {
            Notification::route('mail', array_values(array_filter($mailRecipients)))
                ->notify($notification);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch page-not-found notification: '.$e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    /**
     * Whether at least one delivery channel is enabled and configured, given
     * the available mail recipients.
     *
     * @param  array  $mailRecipients
     * @return bool
     */
    public static function hasActiveChannels(array $mailRecipients = [])
    {
        $config = (array) config('page-not-found-email-alert.channels', []);

        if (($config['mail']['enabled'] ?? true) && ! empty(array_filter($mailRecipients))) {
            return true;
        }

        foreach (array_keys(ChannelNotification::WEBHOOK_CHANNELS) as $key) {
            $channel = (array) ($config[$key] ?? []);
            $url = $channel['webhook_url'] ?? $channel['url'] ?? null;

            if (($channel['enabled'] ?? false) && ! empty($url)) {
                return true;
            }
        }

        return false;
    }
}

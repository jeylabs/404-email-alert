<?php

namespace Jeylabs\PageNotFoundEmailAlert\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Base for the chat webhook channels. Reads its endpoint from the package
 * config and POSTs a provider-specific payload built from the notification's
 * ChatMessage. Delivery failures are logged, never thrown.
 */
abstract class WebhookChannel
{
    /**
     * The config key under "channels" for this provider.
     *
     * @return string
     */
    abstract protected function key(): string;

    /**
     * Format the chat message into the provider's payload.
     *
     * @param  \Jeylabs\PageNotFoundEmailAlert\Notifications\ChatMessage  $message
     * @return array
     */
    abstract protected function payload($message): array;

    /**
     * Deliver the notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        if (! method_exists($notification, 'toChatMessage')) {
            return;
        }

        $url = $this->url();

        if (empty($url)) {
            return;
        }

        try {
            Http::timeout(5)->post($url, $this->payload($notification->toChatMessage()));
        } catch (\Throwable $e) {
            Log::warning('Failed to deliver '.$this->key().' notification: '.$e->getMessage());
        }
    }

    /**
     * The configured webhook URL for this provider.
     *
     * @return string|null
     */
    protected function url()
    {
        $config = (array) config('page-not-found-email-alert.channels.'.$this->key(), []);

        return $config['webhook_url'] ?? $config['url'] ?? null;
    }
}

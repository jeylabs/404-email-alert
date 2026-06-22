<?php

namespace Jeylabs\PageNotFoundEmailAlert\Notifications\Channels;

class GenericWebhookChannel extends WebhookChannel
{
    protected function key(): string
    {
        return 'webhook';
    }

    protected function payload($message): array
    {
        return array_merge(
            ['event' => 'page-not-found.notification'],
            $message->toArray()
        );
    }
}

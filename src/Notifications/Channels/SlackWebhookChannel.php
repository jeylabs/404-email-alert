<?php

namespace Jeylabs\PageNotFoundEmailAlert\Notifications\Channels;

class SlackWebhookChannel extends WebhookChannel
{
    protected function key(): string
    {
        return 'slack';
    }

    protected function payload($message): array
    {
        $fields = array_map(fn ($field) => [
            'title' => $field['label'],
            'value' => $field['value'],
            'short' => true,
        ], $message->fields);

        return [
            'text'        => $message->title,
            'attachments' => [array_filter([
                'color'      => $message->color(),
                'text'       => $message->summary,
                'title_link' => $message->url,
                'fields'     => $fields,
            ])],
        ];
    }
}

<?php

namespace Jeylabs\PageNotFoundEmailAlert\Notifications\Channels;

class DiscordWebhookChannel extends WebhookChannel
{
    protected function key(): string
    {
        return 'discord';
    }

    protected function payload($message): array
    {
        $fields = array_map(fn ($field) => [
            'name'   => $field['label'],
            'value'  => $field['value'],
            'inline' => true,
        ], $message->fields);

        return [
            'embeds' => [array_filter([
                'title'       => $message->title,
                'description' => $message->summary,
                'color'       => $message->colorInt(),
                'url'         => $message->url,
                'fields'      => $fields,
            ], fn ($value) => $value !== null && $value !== [])],
        ];
    }
}

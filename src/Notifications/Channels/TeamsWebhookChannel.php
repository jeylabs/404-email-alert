<?php

namespace Jeylabs\PageNotFoundEmailAlert\Notifications\Channels;

class TeamsWebhookChannel extends WebhookChannel
{
    protected function key(): string
    {
        return 'teams';
    }

    protected function payload($message): array
    {
        $facts = array_map(fn ($field) => [
            'name'  => $field['label'],
            'value' => $field['value'],
        ], $message->fields);

        return array_filter([
            '@type'      => 'MessageCard',
            '@context'   => 'http://schema.org/extensions',
            'themeColor' => ltrim($message->color(), '#'),
            'summary'    => $message->title,
            'title'      => $message->title,
            'text'       => $message->summary,
            'sections'   => [['facts' => $facts]],
            'potentialAction' => $message->url ? [[
                '@type'   => 'OpenUri',
                'name'    => 'View dashboard',
                'targets' => [['os' => 'default', 'uri' => $message->url]],
            ]] : null,
        ], fn ($value) => $value !== null);
    }
}

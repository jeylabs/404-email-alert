<?php

namespace Jeylabs\PageNotFoundEmailAlert\Notifications;

/**
 * A provider-agnostic chat message. Each webhook channel formats it into the
 * shape Slack / Discord / Teams / a generic endpoint expects.
 */
class ChatMessage
{
    /** @var string */
    public $title;

    /** @var string|null */
    public $summary = null;

    /** @var array<int, array{label: string, value: string}> */
    public $fields = [];

    /** @var string  info|warning|error */
    public $level = 'info';

    /** @var string|null */
    public $url = null;

    public function __construct(string $title)
    {
        $this->title = $title;
    }

    public static function make(string $title): self
    {
        return new self($title);
    }

    public function summary(?string $summary): self
    {
        $this->summary = $summary;

        return $this;
    }

    public function level(string $level): self
    {
        $this->level = $level;

        return $this;
    }

    public function url(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function field(string $label, $value): self
    {
        $this->fields[] = ['label' => $label, 'value' => (string) ($value === '' ? '—' : $value)];

        return $this;
    }

    /**
     * Hex colour for the message level.
     */
    public function color(): string
    {
        return match ($this->level) {
            'error'   => '#dc2626',
            'warning' => '#d97706',
            default   => '#2563eb',
        };
    }

    /**
     * Integer colour (for Discord embeds).
     */
    public function colorInt(): int
    {
        return (int) hexdec(ltrim($this->color(), '#'));
    }

    public function toArray(): array
    {
        return [
            'title'   => $this->title,
            'summary' => $this->summary,
            'level'   => $this->level,
            'url'     => $this->url,
            'fields'  => $this->fields,
        ];
    }
}

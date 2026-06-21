<?php

namespace Jeylabs\PageNotFoundEmailAlert\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ThresholdAlert extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The breached rule payload.
     *
     * @var array
     */
    public $alert;

    /**
     * Package configuration.
     *
     * @var array
     */
    public $config;

    /**
     * @param  array  $alert
     * @param  array  $config
     */
    public function __construct(array $alert, array $config = [])
    {
        $this->alert = $alert;
        $this->config = $config;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $from = $this->config['from'] ?? [];

        return new Envelope(
            from: ! empty($from['address'])
                ? new Address($from['address'], $from['name'] ?? null)
                : null,
            subject: $this->config['alerts']['subject'] ?? 'Error spike detected',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'page-not-found-email-alert::threshold-alert',
            with: ['alert' => $this->alert],
        );
    }
}

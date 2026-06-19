<?php

namespace Jeylabs\PageNotFoundEmailAlert\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PageNotFound extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Details of the 404 request.
     *
     * @var array
     */
    public $data;

    /**
     * Package configuration.
     *
     * @var array
     */
    public $config;

    /**
     * Create a new message instance.
     *
     * @param  array  $data
     * @param  array  $config
     */
    public function __construct(array $data, array $config = [])
    {
        $this->data = $data;
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
            subject: $this->config['subject'] ?? '404 Page Not Found Alert',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'page-not-found-email-alert::email',
            with: ['data' => $this->data],
        );
    }
}

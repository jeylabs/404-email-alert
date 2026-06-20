<?php

namespace Jeylabs\PageNotFoundEmailAlert\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RequestReport extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The compiled report payload.
     *
     * @var array
     */
    public $report;

    /**
     * Package configuration.
     *
     * @var array
     */
    public $config;

    /**
     * Create a new message instance.
     *
     * @param  array  $report
     * @param  array  $config
     */
    public function __construct(array $report, array $config = [])
    {
        $this->report = $report;
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
            subject: $this->config['report']['subject']
                ?? 'Not So Great Requests Report',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'page-not-found-email-alert::report',
            with: ['report' => $this->report],
        );
    }
}

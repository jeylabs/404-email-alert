<?php

namespace Jeylabs\PageNotFoundEmailAlert\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
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
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->subject($this->config['subject'] ?? '404 Page Not Found Alert')
             ->view('page-not-found-email-alert::email')
             ->with('data', $this->data);

        $from = $this->config['from'] ?? [];

        if (! empty($from['address'])) {
            $this->from($from['address'], $from['name'] ?? null);
        }

        return $this;
    }
}

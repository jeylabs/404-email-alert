<?php

namespace Jeylabs\PageNotFoundEmailAlert\Notifications;

use Jeylabs\PageNotFoundEmailAlert\Mail\PageNotFound;

class PageNotFoundAlertNotification extends ChannelNotification
{
    /** @var array */
    public $data;

    /** @var array */
    public $config;

    public function __construct(array $data, array $config = [])
    {
        $this->data = $data;
        $this->config = $config;
    }

    /**
     * The mail channel reuses the existing Mailable (and its Blade view).
     *
     * @param  mixed  $notifiable
     * @return \Jeylabs\PageNotFoundEmailAlert\Mail\PageNotFound
     */
    public function toMail($notifiable)
    {
        return (new PageNotFound($this->data, $this->config))
            ->to($notifiable->routeNotificationFor('mail'));
    }

    public function toChatMessage(): ChatMessage
    {
        return ChatMessage::make('404 Page Not Found')
            ->level('warning')
            ->summary('A visitor reached a URL that does not exist on your application.')
            ->field('URL', $this->data['url'] ?? '')
            ->field('Method', $this->data['method'] ?? '')
            ->field('IP', $this->data['ip'] ?? '')
            ->field('Referer', $this->data['referer'] ?? '')
            ->field('User agent', $this->data['user_agent'] ?? '');
    }
}

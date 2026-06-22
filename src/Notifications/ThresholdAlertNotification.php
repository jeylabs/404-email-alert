<?php

namespace Jeylabs\PageNotFoundEmailAlert\Notifications;

use Jeylabs\PageNotFoundEmailAlert\Mail\ThresholdAlert;

class ThresholdAlertNotification extends ChannelNotification
{
    /** @var array */
    public $alert;

    /** @var array */
    public $config;

    public function __construct(array $alert, array $config = [])
    {
        $this->alert = $alert;
        $this->config = $config;
    }

    /**
     * @param  mixed  $notifiable
     * @return \Jeylabs\PageNotFoundEmailAlert\Mail\ThresholdAlert
     */
    public function toMail($notifiable)
    {
        return (new ThresholdAlert($this->alert, $this->config))
            ->to($notifiable->routeNotificationFor('mail'));
    }

    public function toChatMessage(): ChatMessage
    {
        return ChatMessage::make('⚠️ Error spike detected')
            ->level('error')
            ->url($this->dashboardUrl())
            ->summary(sprintf('The rule "%s" was breached.', $this->alert['name'] ?? 'rule'))
            ->field('Observed', ($this->alert['count'] ?? 0).' in '.($this->alert['window'] ?? 0).' min')
            ->field('Threshold', $this->alert['threshold'] ?? 0)
            ->field('Since', $this->alert['since'] ?? '');
    }
}

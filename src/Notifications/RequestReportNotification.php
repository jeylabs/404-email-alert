<?php

namespace Jeylabs\PageNotFoundEmailAlert\Notifications;

use Jeylabs\PageNotFoundEmailAlert\Mail\RequestReport;

class RequestReportNotification extends ChannelNotification
{
    /** @var array */
    public $report;

    /** @var array */
    public $config;

    public function __construct(array $report, array $config = [])
    {
        $this->report = $report;
        $this->config = $config;
    }

    /**
     * @param  mixed  $notifiable
     * @return \Jeylabs\PageNotFoundEmailAlert\Mail\RequestReport
     */
    public function toMail($notifiable)
    {
        return (new RequestReport($this->report, $this->config))
            ->to($notifiable->routeNotificationFor('mail'));
    }

    public function toChatMessage(): ChatMessage
    {
        $message = ChatMessage::make($this->config['report']['subject'] ?? 'Not So Great Requests Report')
            ->level('info')
            ->url($this->dashboardUrl())
            ->summary(sprintf(
                '%s failed requests from %s to %s.',
                number_format($this->report['total'] ?? 0),
                $this->report['from'] ?? '',
                $this->report['to'] ?? ''
            ))
            ->field('Total', number_format($this->report['total'] ?? 0))
            ->field('Client (4xx)', number_format($this->report['client_errors'] ?? 0))
            ->field('Server (5xx)', number_format($this->report['server_errors'] ?? 0));

        if (! empty($this->report['top_paths'])) {
            $top = collect($this->report['top_paths'])
                ->take(3)
                ->map(fn ($path) => $path['path'].' ('.$path['count'].')')
                ->implode(', ');

            $message->field('Top paths', $top);
        }

        return $message;
    }
}

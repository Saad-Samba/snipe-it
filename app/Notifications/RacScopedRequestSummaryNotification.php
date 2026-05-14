<?php

namespace App\Notifications;

use App\Helpers\Helper;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

class RacScopedRequestSummaryNotification extends Notification
{
    public function __construct(
        private readonly array $summary
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $projectName = $this->summary['project_name'] ?: trans('general.project');

        return (new MailMessage)->markdown('notifications.markdown.rac-request-summary', [
            'requester' => $this->summary['requester'],
            'submitted_at' => Helper::getFormattedDateObject($this->summary['submitted_at'], 'datetime', false),
            'project_name' => $this->summary['project_name'],
            'lines' => $this->summary['lines'],
        ])
            ->subject('👀 '.trans('mail.rac_request_scope_match_subject', ['project' => $projectName]))
            ->withSymfonyMessage(function (Email $message) {
                $message->getHeaders()->addTextHeader(
                    'X-System-Sender', 'Snipe-IT'
                );
            });
    }

    public function lines(): array
    {
        return $this->summary['lines'];
    }

    public function projectName(): ?string
    {
        return $this->summary['project_name'] ?? null;
    }
}

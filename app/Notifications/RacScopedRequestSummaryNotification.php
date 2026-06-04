<?php

namespace App\Notifications;

use App\Helpers\Helper;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

class RacScopedRequestSummaryNotification extends Notification
{
    public function __construct(
        private readonly array $summary,
        private readonly bool $isReminder = false
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $projectName = $this->summary['project_name'] ?: trans('general.project');
        $replyToAddress = config('mail.reply_to.address');
        $replyToName = config('mail.reply_to.name');

        $message = (new MailMessage)->markdown('notifications.markdown.rac-request-summary', [
            'requester' => $this->summary['requester'],
            'submitted_at' => Helper::getFormattedDateObject($this->summary['submitted_at'], 'datetime', false),
            'project_name' => $this->summary['project_name'],
            'lines' => $this->summary['lines'],
            'review_url' => $this->reviewUrl(),
            'is_reminder' => $this->isReminder,
            'review_label' => $this->isReminder
                ? trans('mail.rac_request_scope_match_reminder_cta')
                : trans('mail.rac_request_scope_match_cta'),
        ])
            ->subject(trans(
                $this->isReminder
                    ? 'mail.rac_request_scope_match_reminder_subject'
                    : 'mail.rac_request_scope_match_subject',
                ['project' => $projectName]
            ))
            ->withSymfonyMessage(function (Email $message) {
                $message->getHeaders()->addTextHeader(
                    'X-System-Sender', 'Snipe-IT'
                );
            });

        if ($replyToAddress) {
            $message->replyTo($replyToAddress, $replyToName);
        }

        return $message;
    }

    public function lines(): array
    {
        return $this->summary['lines'];
    }

    public function projectName(): ?string
    {
        return $this->summary['project_name'] ?? null;
    }

    public function reviewUrl(): ?string
    {
        return $this->summary['lines'][0]['request_detail_url']
            ?? $this->summary['lines'][0]['project_requests_url']
            ?? null;
    }

    public function isReminder(): bool
    {
        return $this->isReminder;
    }
}

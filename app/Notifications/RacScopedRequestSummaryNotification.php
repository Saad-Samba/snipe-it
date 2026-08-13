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
        private readonly bool $isReminder = false,
        private readonly bool $isUpdate = false
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
            'is_update' => $this->isUpdate,
            'review_label' => trans($this->messageKey('cta')),
        ])
            ->subject(trans($this->messageKey('subject'), ['project' => $projectName]))
            ->withSymfonyMessage(function (Email $message) {
                $message->getHeaders()->addTextHeader(
                    'X-System-Sender', 'LEAMS'
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

    public function isUpdate(): bool
    {
        return $this->isUpdate;
    }

    private function messageKey(string $suffix): string
    {
        if ($this->isReminder) {
            return 'mail.rac_request_scope_match_reminder_'.$suffix;
        }

        if ($this->isUpdate) {
            return 'mail.rac_request_scope_match_update_'.$suffix;
        }

        return 'mail.rac_request_scope_match_'.$suffix;
    }
}

<?php

namespace App\Notifications;

use App\Helpers\Helper;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
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
            'bulk_allocate_url' => $this->bulkAllocateUrlFor($notifiable),
            'review_url' => $this->reviewUrl(),
        ])
            ->subject(trans('mail.rac_request_scope_match_subject', ['project' => $projectName]))
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

    public function requestIds(): array
    {
        return collect($this->summary['lines'] ?? [])
            ->pluck('request_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function bulkAllocateUrlFor($notifiable): ?string
    {
        $requestIds = $this->requestIds();

        if (empty($requestIds) || ! $notifiable?->id) {
            return null;
        }

        return URL::signedRoute('requests.coordinator.allocate-all', [
            'coordinator' => $notifiable->id,
            'requests' => implode(',', $requestIds),
        ]);
    }

    public function reviewUrl(): ?string
    {
        return $this->summary['lines'][0]['request_detail_url']
            ?? $this->summary['lines'][0]['project_requests_url']
            ?? null;
    }
}

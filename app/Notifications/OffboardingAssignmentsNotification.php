<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

class OffboardingAssignmentsNotification extends Notification
{
    public function __construct(
        private readonly array $lines,
        private readonly string $intendedRecipient,
        private readonly bool $testMode = false
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $userCount = collect($this->lines)->pluck('user_id')->unique()->count();
        $subject = trans_choice(
            'mail.offboarding_assignments_subject',
            $userCount,
            ['count' => $userCount]
        );
        if ($this->testMode) {
            $subject = '[TEST] '.$subject;
        }

        return (new MailMessage)
            ->markdown('notifications.markdown.offboarding-assignments', [
                'lines' => $this->lines,
                'intended_recipient' => $this->intendedRecipient,
                'test_mode' => $this->testMode,
                'review_url' => route('users.index'),
            ])
            ->subject($subject)
            ->withSymfonyMessage(function (Email $message) {
                $message->getHeaders()->addTextHeader('X-System-Sender', 'LEAMS');
            });
    }

    public function lines(): array
    {
        return $this->lines;
    }

    public function intendedRecipient(): string
    {
        return $this->intendedRecipient;
    }

    public function isTestMode(): bool
    {
        return $this->testMode;
    }
}

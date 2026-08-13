<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

class UnroutedRacRequestNotification extends Notification
{
    public function __construct(private readonly array $lines)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->markdown('notifications.markdown.unrouted-rac-requests', [
                'lines' => $this->lines,
                'review_url' => $this->reviewUrl(),
            ])
            ->subject(trans_choice('mail.unrouted_rac_request_subject', count($this->lines), [
                'count' => count($this->lines),
            ]))
            ->withSymfonyMessage(function (Email $message) {
                $message->getHeaders()->addTextHeader('X-System-Sender', 'LEAMS');
            });

        if ($replyToAddress = config('mail.reply_to.address')) {
            $message->replyTo($replyToAddress, config('mail.reply_to.name'));
        }

        return $message;
    }

    public function lines(): array
    {
        return $this->lines;
    }

    public function reviewUrl(): ?string
    {
        return $this->lines[0]['review_url'] ?? null;
    }
}

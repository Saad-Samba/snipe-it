<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

class RacRequestRoutingRemovedNotification extends Notification
{
    public function __construct(
        private readonly string $modelName,
        private readonly ?string $projectName = null
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $projectName = $this->projectName ?: trans('general.project');
        $replyToAddress = config('mail.reply_to.address');
        $replyToName = config('mail.reply_to.name');

        $message = (new MailMessage)
            ->subject(trans('mail.rac_request_routing_removed_subject', ['project' => $projectName]))
            ->line(trans('mail.rac_request_routing_removed_intro', [
                'model' => $this->modelName,
                'project' => $projectName,
            ]))
            ->line(trans('mail.rac_request_routing_removed_action'))
            ->withSymfonyMessage(function (Email $message) {
                $message->getHeaders()->addTextHeader('X-System-Sender', 'Snipe-IT');
            });

        if ($replyToAddress) {
            $message->replyTo($replyToAddress, $replyToName);
        }

        return $message;
    }

    public function modelName(): string
    {
        return $this->modelName;
    }

    public function projectName(): ?string
    {
        return $this->projectName;
    }
}

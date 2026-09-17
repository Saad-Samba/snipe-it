<?php

namespace App\Notifications;

use App\Models\CheckoutRequest;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class RequestAlternativeFollowUpNotification extends Notification
{
    public function __construct(
        private readonly Collection $checkoutRequests,
        private readonly Collection $afms
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $requests = $this->checkoutRequests->each->loadMissing([
            'requestedItem',
            'project',
            'company',
            'requestedDiscipline',
        ]);
        $firstRequest = $requests->first();
        $subject = $requests->count() === 1
            ? 'Alternative model follow-up for request #'.$firstRequest->id
            : 'Alternative model follow-up for '.$requests->count().' requested models';

        $message = (new MailMessage)
            ->subject($subject)
            ->markdown('notifications.markdown.request-alternative-follow-up', [
                'requests' => $requests,
                'afms' => $this->afms,
                'requestsUrl' => route('requests.index'),
            ]);

        $this->afms->each(
            fn (User $afm) => $message->cc($afm->email, $afm->display_name)
        );

        return $message;
    }

    public function checkoutRequest(): CheckoutRequest
    {
        return $this->checkoutRequests->first();
    }

    public function checkoutRequests(): Collection
    {
        return $this->checkoutRequests;
    }
}

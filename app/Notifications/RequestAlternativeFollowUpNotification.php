<?php

namespace App\Notifications;

use App\Models\CheckoutRequest;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RequestAlternativeFollowUpNotification extends Notification
{
    public function __construct(
        private readonly CheckoutRequest $checkoutRequest,
        private readonly ?User $afm = null
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $request = $this->checkoutRequest->loadMissing([
            'requestedItem',
            'project',
            'company',
            'requestedDiscipline',
        ]);

        $message = (new MailMessage)
            ->subject('Alternative model follow-up for request #'.$request->id)
            ->markdown('notifications.markdown.request-alternative-follow-up', [
                'request' => $request,
                'afm' => $this->afm,
                'fulfilledQuantity' => $request->allocatedQuantity(),
                'remainingQuantity' => $request->remainingAllocationQuantity(),
                'requestsUrl' => route('requests.index'),
            ]);

        if ($this->afm) {
            $message->cc($this->afm->email, $this->afm->display_name);
        }

        return $message;
    }

    public function checkoutRequest(): CheckoutRequest
    {
        return $this->checkoutRequest;
    }
}

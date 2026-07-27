<?php

namespace App\Notifications;

use App\Models\CheckoutRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AfmRequestReviewNotification extends Notification
{
    public function __construct(private readonly CheckoutRequest $checkoutRequest)
    {
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
            'user',
        ]);

        return (new MailMessage)
            ->subject('AFM review required for request #'.$request->id)
            ->markdown('notifications.markdown.afm-request-review', [
                'request' => $request,
                'remainingQuantity' => $request->remainingAllocationQuantity(),
                'reviewUrl' => route('hardware.index', [
                    'request_id' => $request->id,
                    'request_bucket' => 'reusable_now',
                ]),
            ]);
    }

    public function checkoutRequest(): CheckoutRequest
    {
        return $this->checkoutRequest;
    }
}

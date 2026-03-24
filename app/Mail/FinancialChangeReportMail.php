<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class FinancialChangeReportMail extends BaseMailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Collection $statusEvents,
        public Collection $companyEvents,
        public ?Company $company = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: 'Financial asset change report',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'notifications.markdown.financial-change-report',
            with: [
                'user' => $this->user,
                'company' => $this->company,
                'statusEvents' => $this->statusEvents,
                'companyEvents' => $this->companyEvents,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

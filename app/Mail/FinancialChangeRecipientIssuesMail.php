<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class FinancialChangeRecipientIssuesMail extends BaseMailable
{
    use Queueable, SerializesModels;

    public function __construct(public Collection $issues)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: 'Financial report recipient issues',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'notifications.markdown.financial-change-recipient-issues',
            with: [
                'issues' => $this->issues,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\User;
use App\Support\FinancialChangeCsvExport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
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
                'statusEventCount' => $this->statusEvents->count(),
                'companyEventCount' => $this->companyEvents->count(),
                'totalEventCount' => $this->statusEvents->count() + $this->companyEvents->count(),
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->csvExport()->toString(), $this->csvFilename())
                ->withMime('text/csv'),
        ];
    }

    protected function csvFilename(): string
    {
        $date = now()->format('Y-m-d');
        $companySlug = str($this->company?->name ?? 'company')
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-');

        return "financial-change-report-{$companySlug}-{$date}.csv";
    }

    protected function csvExport(): FinancialChangeCsvExport
    {
        return new FinancialChangeCsvExport(
            $this->statusEvents,
            $this->companyEvents,
            $this->company,
        );
    }
}

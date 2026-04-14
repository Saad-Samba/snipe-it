<?php

namespace App\Support;

use App\Models\Company;
use Illuminate\Support\Collection;
use League\Csv\Writer;
use SplTempFileObject;

class FinancialChangeCsvExport
{
    public function __construct(
        protected Collection $statusEvents,
        protected Collection $companyEvents,
        protected ?Company $company = null,
    ) {
    }

    public function toString(): string
    {
        $csv = Writer::createFromFileObject(new SplTempFileObject());

        $csv->insertOne([
            'event_type',
            'asset_id',
            'previous_status',
            'new_status',
            'previous_company',
            'new_company',
            'effective_at',
            'changed_by',
        ]);

        foreach ($this->statusEvents as $event) {
            $csv->insertOne([
                'status_change',
                $event->asset_id,
                $event->previousStatus?->name ?? 'Unassigned',
                $event->newStatus?->name ?? 'Unassigned',
                '',
                '',
                $event->effective_at?->format('Y-m-d H:i:s'),
                $event->changedBy?->display_name ?? $event->changedBy?->username ?? 'System',
            ]);
        }

        foreach ($this->companyEvents as $event) {
            $csv->insertOne([
                'company_change',
                $event->asset_id,
                '',
                '',
                $event->previousCompany?->name ?? 'Unassigned',
                $event->newCompany?->name ?? 'Unassigned',
                $event->effective_at?->format('Y-m-d H:i:s'),
                $event->changedBy?->display_name ?? $event->changedBy?->username ?? 'System',
            ]);
        }

        return $csv->toString();
    }
}

<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\FinancialChangeEvent;
use App\Models\Statuslabel;
use Carbon\Carbon;

class FinancialChangeRecorder
{
    public function stagePendingEvents(Asset $asset): array
    {
        $original = $asset->getRawOriginal();
        $pendingEvents = [];

        $oldStatusId = $this->normalizeNullableInt($original['status_id'] ?? null);
        $newStatusId = $this->normalizeNullableInt($asset->status_id);
        $oldCompanyId = $this->normalizeNullableInt($original['company_id'] ?? null);
        $newCompanyId = $this->normalizeNullableInt($asset->company_id);

        if ($oldStatusId !== $newStatusId && $this->isFinanceRelevantStatusTransition($oldStatusId, $newStatusId)) {
            $pendingEvents[] = [
                'asset_id' => $asset->id,
                'event_type' => 'status_change',
                'company_id' => $newCompanyId,
                'previous_status_id' => $oldStatusId,
                'new_status_id' => $newStatusId,
                'previous_company_id' => null,
                'new_company_id' => null,
                'effective_at' => $this->resolveEffectiveAt($asset),
                'changed_by' => auth()->id(),
            ];
        }

        if ($oldCompanyId !== $newCompanyId) {
            $pendingEvents[] = [
                'asset_id' => $asset->id,
                'event_type' => 'company_change',
                'company_id' => null,
                'previous_status_id' => null,
                'new_status_id' => null,
                'previous_company_id' => $oldCompanyId,
                'new_company_id' => $newCompanyId,
                'effective_at' => $this->resolveEffectiveAt($asset),
                'changed_by' => auth()->id(),
            ];
        }

        return $pendingEvents;
    }

    public function persistPendingEvents(Asset $asset): void
    {
        foreach ($asset->pendingFinancialChangeEvents as $pendingEvent) {
            FinancialChangeEvent::create($pendingEvent);
        }

        $asset->pendingFinancialChangeEvents = [];
    }

    protected function isFinanceRelevantStatusTransition(?int $oldStatusId, ?int $newStatusId): bool
    {
        $statusIds = array_filter([$oldStatusId, $newStatusId]);

        if (empty($statusIds)) {
            return false;
        }

        return Statuslabel::whereIn('id', $statusIds)
            ->where('finance_relevant', 1)
            ->exists();
    }

    protected function resolveEffectiveAt(Asset $asset): Carbon
    {
        if (! empty($asset->financialChangeEffectiveAt)) {
            return Carbon::parse($asset->financialChangeEffectiveAt);
        }

        return Carbon::now();
    }

    protected function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return (int) $value;
    }
}

<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use App\Models\Company;
use App\Models\FinancialChangeEvent;
use App\Models\Statuslabel;
use App\Models\User;
use Tests\TestCase;

class FinancialChangeEventCaptureTest extends TestCase
{
    public function testRelevantStatusTransitionCreatesFinancialChangeEvent()
    {
        $oldStatus = Statuslabel::factory()->create(['finance_relevant' => 0]);
        $newStatus = Statuslabel::factory()->create(['finance_relevant' => 1]);
        $asset = Asset::factory()->create(['status_id' => $oldStatus->id]);
        $actor = User::factory()->superuser()->create();

        $this->actingAs($actor)->put(route('hardware.update', $asset), [
            'status_id' => $newStatus->id,
            'model_id' => $asset->model_id,
            'asset_tags' => $asset->asset_tag,
        ]);

        $this->assertDatabaseHas('financial_change_events', [
            'asset_id' => $asset->id,
            'event_type' => 'status_change',
            'previous_status_id' => $oldStatus->id,
            'new_status_id' => $newStatus->id,
            'changed_by' => $actor->id,
        ]);
    }

    public function testNonRelevantStatusTransitionDoesNotCreateFinancialChangeEvent()
    {
        $oldStatus = Statuslabel::factory()->create(['finance_relevant' => 0]);
        $newStatus = Statuslabel::factory()->create(['finance_relevant' => 0]);
        $asset = Asset::factory()->create(['status_id' => $oldStatus->id]);

        $this->actingAs(User::factory()->superuser()->create())->put(route('hardware.update', $asset), [
            'status_id' => $newStatus->id,
            'model_id' => $asset->model_id,
            'asset_tags' => $asset->asset_tag,
        ]);

        $this->assertDatabaseCount('financial_change_events', 0);
    }

    public function testCompanyChangeCreatesFinancialChangeEvent()
    {
        $oldCompany = Company::factory()->create();
        $newCompany = Company::factory()->create();
        $asset = Asset::factory()->create(['company_id' => $oldCompany->id]);
        $actor = User::factory()->superuser()->editAssets()->create();

        $this->actingAs($actor)->put(route('hardware.update', $asset), [
            'status_id' => $asset->status_id,
            'model_id' => $asset->model_id,
            'asset_tags' => $asset->asset_tag,
            'company_id' => $newCompany->id,
        ]);

        $this->assertDatabaseHas('financial_change_events', [
            'asset_id' => $asset->id,
            'event_type' => 'company_change',
            'previous_company_id' => $oldCompany->id,
            'new_company_id' => $newCompany->id,
            'changed_by' => $actor->id,
        ]);
    }

    public function testCheckinUsesProvidedEffectiveDateForFinancialEvent()
    {
        $oldStatus = Statuslabel::factory()->create(['finance_relevant' => 0]);
        $newStatus = Statuslabel::factory()->create(['finance_relevant' => 1]);
        $asset = Asset::factory()->assignedToUser()->create(['status_id' => $oldStatus->id]);
        $checkinDate = '2026-03-01';

        $this->actingAs(User::factory()->checkinAssets()->create())
            ->post(route('hardware.checkin.store', [$asset]), [
                'status_id' => $newStatus->id,
                'checkin_at' => $checkinDate,
            ]);

        $event = FinancialChangeEvent::first();

        $this->assertNotNull($event);
        $this->assertSame('status_change', $event->event_type);
        $this->assertSame($checkinDate, $event->effective_at->format('Y-m-d'));
    }
}

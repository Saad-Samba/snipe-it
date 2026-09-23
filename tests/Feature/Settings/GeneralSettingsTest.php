<?php

namespace Tests\Feature\Settings;

use App\Models\Setting;
use App\Models\Statuslabel;
use App\Models\User;
use Tests\TestCase;

class GeneralSettingsTest extends TestCase
{
    public function test_rfq_reservation_status_selector_only_lists_deployable_statuses(): void
    {
        $deployableStatus = Statuslabel::factory()->readyToDeploy()->create([
            'name' => 'Reserved for RFQ',
        ]);
        $undeployableStatus = Statuslabel::factory()->create([
            'name' => 'Unavailable for RFQ',
        ]);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('settings.general.index'))
            ->assertOk()
            ->assertSee('RFQ Reservation Status')
            ->assertSee($deployableStatus->name)
            ->assertDontSee($undeployableStatus->name);
    }

    public function test_superuser_can_configure_the_rfq_reservation_status(): void
    {
        $status = Statuslabel::factory()->readyToDeploy()->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->post(route('settings.general.save'), [
                'rfq_reserved_statuslabel_id' => $status->id,
                'thumbnail_max_h' => 50,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.index'));

        $this->assertSame($status->id, Setting::query()->firstOrFail()->rfq_reserved_statuslabel_id);
    }

    public function test_rfq_reservation_status_must_be_deployable(): void
    {
        $status = Statuslabel::factory()->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->from(route('settings.general.index'))
            ->post(route('settings.general.save'), [
                'rfq_reserved_statuslabel_id' => $status->id,
            ])
            ->assertSessionHasErrors('rfq_reserved_statuslabel_id')
            ->assertRedirect(route('settings.general.index'));

        $this->assertNull(Setting::query()->firstOrFail()->rfq_reserved_statuslabel_id);
    }

    public function test_superuser_can_clear_the_rfq_reservation_status(): void
    {
        $status = Statuslabel::factory()->readyToDeploy()->create();
        $setting = Setting::query()->firstOrFail();
        $setting->rfq_reserved_statuslabel_id = $status->id;
        $setting->save();
        Setting::$_cache = null;

        $this->actingAs(User::factory()->superuser()->create())
            ->post(route('settings.general.save'), [
                'rfq_reserved_statuslabel_id' => '',
                'thumbnail_max_h' => 50,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.index'));

        $this->assertNull($setting->fresh()->rfq_reserved_statuslabel_id);
    }
}

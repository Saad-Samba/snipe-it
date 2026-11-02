<?php

namespace Tests\Feature\Checkouts\General;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\License;
use App\Models\Statuslabel;
use App\Models\User;
use Tests\TestCase;

class CategoryAcceptanceDisabledTest extends TestCase
{
    private User $actor;
    private User $assignedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actor = User::factory()->superuser()->create();
        $this->assignedUser = User::factory()->create();
    }

    public function test_accessory_checkout_does_not_create_acceptance_for_legacy_category_flags(): void
    {
        $accessory = Accessory::factory()->create();
        $accessory->category->update([
            'require_acceptance' => true,
            'alert_on_response' => true,
        ]);

        $this->actingAs($this->actor)
            ->post(route('accessories.checkout.store', $accessory), [
                'checkout_to_type' => 'user',
                'status_id' => (string) Statuslabel::factory()->readyToDeploy()->create()->id,
                'assigned_user' => $this->assignedUser->id,
                'checkout_qty' => 1,
            ]);

        $this->assertDatabaseCount('checkout_acceptances', 0);
    }

    public function test_asset_checkout_does_not_create_acceptance_for_legacy_category_flags(): void
    {
        $asset = Asset::factory()->create();
        $asset->model->category->update([
            'require_acceptance' => true,
            'alert_on_response' => true,
        ]);

        $this->actingAs($this->actor)
            ->post(route('hardware.checkout.store', $asset), [
                'checkout_to_type' => 'user',
                'status_id' => (string) Statuslabel::factory()->readyToDeploy()->create()->id,
                'assigned_user' => $this->assignedUser->id,
            ]);

        $this->assertDatabaseCount('checkout_acceptances', 0);
    }

    public function test_license_checkout_does_not_create_acceptance_for_legacy_category_flags(): void
    {
        $license = License::factory()->create();
        $license->category->update([
            'require_acceptance' => true,
            'alert_on_response' => true,
        ]);

        $this->actingAs($this->actor)
            ->post("/licenses/{$license->id}/checkout/", [
                'checkout_to_type' => 'user',
                'assigned_to' => $this->assignedUser->id,
            ]);

        $this->assertDatabaseCount('checkout_acceptances', 0);
    }
}

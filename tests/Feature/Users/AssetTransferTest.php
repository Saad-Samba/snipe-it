<?php

namespace Tests\Feature\Users;

use App\Events\CheckoutableCheckedIn;
use App\Events\CheckoutableCheckedOut;
use App\Models\Asset;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AssetTransferTest extends TestCase
{
    public function test_admin_can_transfer_assets_within_company_when_fmcs_enabled(): void
    {
        Event::fake([CheckoutableCheckedIn::class, CheckoutableCheckedOut::class]);
        $this->settings->enableMultipleFullCompanySupport();

        $company = Company::factory()->create();

        $admin = User::factory()->admin()->for($company)->create();
        $fromUser = User::factory()->for($company)->create();
        $targetUser = User::factory()->for($company)->create();

        $asset = Asset::factory()->for($company)->assignedToUser($fromUser)->create();

        $this->actingAs($admin)
            ->post(route('users.transfer.assets', $fromUser), [
                'bulk_actions' => 'transfer',
                'ids' => [$asset->id],
                'transfer_target_user_id' => $targetUser->id,
            ])
            ->assertRedirect(route('users.show', $fromUser))
            ->assertSessionHas('success');

        $this->assertEquals($targetUser->id, $asset->fresh()->assigned_to);

        Event::assertDispatched(CheckoutableCheckedIn::class);
        Event::assertDispatched(CheckoutableCheckedOut::class);
    }

    public function test_admin_cannot_transfer_outside_company_when_fmcs_enabled(): void
    {
        Event::fake([CheckoutableCheckedIn::class, CheckoutableCheckedOut::class]);
        $this->settings->enableMultipleFullCompanySupport();

        $adminCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $admin = User::factory()->admin()->for($adminCompany)->create();
        $fromUser = User::factory()->for($adminCompany)->create();
        $targetUser = User::factory()->for($otherCompany)->create();

        $asset = Asset::factory()->for($adminCompany)->assignedToUser($fromUser)->create();

        $this->actingAs($admin)
            ->post(route('users.transfer.assets', $fromUser), [
                'bulk_actions' => 'transfer',
                'ids' => [$asset->id],
                'transfer_target_user_id' => $targetUser->id,
            ])
            ->assertRedirect(route('users.show', $fromUser))
            ->assertSessionHas('error');

        $this->assertEquals($fromUser->id, $asset->fresh()->assigned_to);

        Event::assertNotDispatched(CheckoutableCheckedIn::class);
        Event::assertNotDispatched(CheckoutableCheckedOut::class);
    }

    public function test_admin_can_transfer_all_assets_flag(): void
    {
        Event::fake([CheckoutableCheckedIn::class, CheckoutableCheckedOut::class]);

        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $fromUser = User::factory()->for($company)->create();
        $targetUser = User::factory()->for($company)->create();

        $assets = Asset::factory()->count(2)->for($company)->create()->each(function ($asset) use ($fromUser) {
            $asset->assigned_to = $fromUser->id;
            $asset->assigned_type = User::class;
            $asset->save();
        });

        $this->actingAs($admin)
            ->post(route('users.transfer.assets.all', $fromUser), [
                'transfer_all' => 1,
                'transfer_target_user_id' => $targetUser->id,
            ])
            ->assertRedirect(route('users.show', $fromUser))
            ->assertSessionHas('success');

        foreach ($assets as $asset) {
            $this->assertEquals($targetUser->id, $asset->fresh()->assigned_to);
        }

        Event::assertDispatched(CheckoutableCheckedIn::class);
        Event::assertDispatched(CheckoutableCheckedOut::class);
    }

    public function test_non_admin_cannot_transfer_assets(): void
    {
        $fromUser = User::factory()->create();
        $targetUser = User::factory()->create();
        $asset = Asset::factory()->assignedToUser($fromUser)->create();

        $this->actingAs(User::factory()->create())
            ->post(route('users.transfer.assets', $fromUser), [
                'bulk_actions' => 'transfer',
                'ids' => [$asset->id],
                'transfer_target_user_id' => $targetUser->id,
            ])
            ->assertForbidden();
    }
}

<?php

namespace Tests\Feature\RegionalAssetCoordinators\Ui;

use App\Models\User;
use Tests\TestCase;

class IndexRegionalAssetCoordinatorsTest extends TestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        User::factory()->create();

        $this->get(route('account.regional-asset-coordinators.index'))
            ->assertRedirect(route('login'));
    }

    public function test_any_authenticated_user_can_view_the_directory(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('account.regional-asset-coordinators.index'))
            ->assertOk()
            ->assertSeeText(trans('general.regional_asset_coordinators'))
            ->assertSee('Recently released feature')
            ->assertSee(route('api.regional-asset-coordinators.index'));
    }
}

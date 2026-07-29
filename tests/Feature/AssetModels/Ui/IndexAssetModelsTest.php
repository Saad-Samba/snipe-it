<?php

namespace Tests\Feature\AssetModels\Ui;

use App\Models\User;
use Tests\TestCase;

class IndexAssetModelsTest extends TestCase
{
    public function test_models_index_does_not_include_a_company_column()
    {
        $user = User::factory()->requestAssetModels()->viewAssetModels()->create();

        $this->actingAs($user)
            ->get(route('models.index'))
            ->assertOk()
            ->assertDontSee('&quot;field&quot;:&quot;company&quot;', false);
    }

    public function test_models_index_hides_total_needed_column_without_request_permission()
    {
        $user = User::factory()->viewAssetModels()->create();

        $this->actingAs($user)
            ->get(route('models.index'))
            ->assertOk()
            ->assertDontSee('&quot;field&quot;:&quot;request&quot;', false);
    }

    public function test_models_index_shows_total_needed_column_with_request_permission()
    {
        $user = User::factory()->requestAssetModels()->viewAssetModels()->create();

        $this->actingAs($user)
            ->get(route('models.index'))
            ->assertOk()
            ->assertSee('&quot;field&quot;:&quot;request&quot;', false)
            ->assertSee('&quot;title&quot;:&quot;Total Needed&quot;', false);
    }

    public function test_models_index_shows_reference_price_column_by_default()
    {
        $user = User::factory()->viewAssetModels()->create();

        $this->actingAs($user)
            ->get(route('models.index'))
            ->assertOk()
            ->assertSee('&quot;field&quot;:&quot;reference_price&quot;', false)
            ->assertSee('&quot;title&quot;:&quot;Reference Price&quot;', false)
            ->assertSee('&quot;visible&quot;:true', false);
    }
}

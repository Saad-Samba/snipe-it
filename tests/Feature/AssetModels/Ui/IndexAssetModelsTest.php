<?php

namespace Tests\Feature\AssetModels\Ui;

use App\Models\User;
use Tests\TestCase;

class IndexAssetModelsTest extends TestCase
{
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

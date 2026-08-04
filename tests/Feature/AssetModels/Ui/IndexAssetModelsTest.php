<?php

namespace Tests\Feature\AssetModels\Ui;

use App\Models\AssetModel;
use App\Models\Category;
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

    public function test_models_index_does_not_show_minimum_quantity_column(): void
    {
        $this->actingAs(User::factory()->viewAssetModels()->create())
            ->get(route('models.index'))
            ->assertOk()
            ->assertDontSee('&quot;field&quot;:&quot;min_amt&quot;', false)
            ->assertDontSee(trans('mail.min_QTY'));
    }

    public function test_available_models_view_explains_filters_and_links_reusable_assets_to_the_model(): void
    {
        $category = Category::factory()->forAssets()->create([
            'name' => 'Communication',
        ]);
        AssetModel::factory()->create([
            'category_id' => $category->id,
        ]);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('models.index', [
                'category_id' => $category->id,
                'available_models' => 1,
            ]))
            ->assertOk()
            ->assertSeeText('Available Models — Communication')
            ->assertSeeText('Filtered view')
            ->assertSeeText('Category: Communication')
            ->assertSeeText('Availability: Reusable')
            ->assertSeeText('Back to Communication')
            ->assertSeeText('Clear all filters')
            ->assertSee('&quot;title&quot;:&quot;Reusable Assets&quot;', false)
            ->assertSee('modelReusableAssetsFormatter', false)
            ->assertSee('?model_id=', false)
            ->assertSee('&reusable_assets=1', false);
    }
}

<?php

namespace Tests\Feature\Categories\Api;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class IndexCategoriesTest extends TestCase
{

    public function testViewingCategoryIndexRequiresPermission()
    {
        $this->actingAsForApi(User::factory()->create())
            ->getJson(route('api.categories.index'))
            ->assertForbidden();
    }

    public function testCategoryIndexReturnsExpectedSearchResults()
    {
        Category::factory()->count(10)->create();
        $category = Category::factory()->count(1)->forAssets()->create(['name' => 'My Test Category'])->first();
        $model = AssetModel::factory()->create(['category_id' => $category->id]);
        $deployableStatus = Statuslabel::factory()->rtd()->create(['name' => 'Search Test Ready']);
        $archivedStatus = Statuslabel::factory()->archived()->create(['name' => 'Search Test Archived']);
        $pendingStatus = Statuslabel::factory()->create(['name' => 'Search Test Pending', 'deployable' => 1, 'pending' => 1, 'archived' => 0]);

        Asset::factory()->count(2)
            ->for($model, 'model')
            ->for($deployableStatus, 'assetstatus')
            ->create();

        Asset::factory()
            ->for($model, 'model')
            ->for($archivedStatus, 'assetstatus')
            ->create();

        Asset::factory()
            ->for($model, 'model')
            ->for($deployableStatus, 'assetstatus')
            ->create(['assigned_to' => User::factory()->create()->id, 'assigned_type' => User::class]);

        Asset::factory()
            ->for($model, 'model')
            ->for($pendingStatus, 'assetstatus')
            ->create();

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(
                route('api.categories.index', [
                    'search' => 'My Test Category',
                    'sort' => 'name',
                    'order' => 'asc',
                    'offset' => '0',
                    'limit' => '20',
                ]))
            ->assertOk()
            ->assertJsonStructure([
                'total',
                'rows',
            ])
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('total', 1)
                ->where('rows.0.id', $category->id)
                ->where('rows.0.reusable_assets_count', 2)
                ->where('rows.0.category_type_raw', 'asset')
                ->etc());

    }

    public function testCategoryIndexReturnsZeroReusableAssetsCountForNonAssetCategories()
    {
        $category = Category::factory()->forLicenses()->create(['name' => 'No Reuse License Category']);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(
                route('api.categories.index', [
                    'search' => 'No Reuse License Category',
                    'sort' => 'name',
                    'order' => 'asc',
                    'offset' => '0',
                    'limit' => '20',
                ]))
            ->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('total', 1)
                ->where('rows.0.id', $category->id)
                ->where('rows.0.category_type_raw', 'license')
                ->where('rows.0.reusable_assets_count', 0)
                ->etc());
    }

    public function testCategoryIndexReturnsExpectedCategories()
    {
        $this->markTestIncomplete('Not sure why the category factory is generating one more than expected here.');
        Category::factory()->count(3)->create();

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(
                route('api.categories.index', [
                    'sort' => 'id',
                    'order' => 'asc',
                    'offset' => '0',
                    'limit' => '20',
                ]))
            ->assertOk()
            ->assertJsonStructure([
                'total',
                'rows',
            ])
            ->assertJson([
                'total' => 3,
            ]);

    }

}

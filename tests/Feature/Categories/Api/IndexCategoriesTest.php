<?php

namespace Tests\Feature\Categories\Api;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\Setting;
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

    public function testCategoryManagerCanViewOnlyManagedCategoriesWithoutGlobalCategoriesViewPermission()
    {
        $manager = User::factory()->create();
        $managedCategory = Category::factory()->forAssets()->create([
            'name' => 'Managed Category',
            'manager_id' => $manager->id,
        ]);
        Category::factory()->forAssets()->create([
            'name' => 'Unmanaged Category',
        ]);

        $this->actingAsForApi($manager)
            ->getJson(route('api.categories.index'))
            ->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('total', 1)
                ->where('rows.0.id', $managedCategory->id)
                ->missing('rows.1')
                ->etc());
    }

    public function testAssignedAfmWithFormerGlobalViewPermissionCanViewOnlyManagedCategories()
    {
        $afm = User::factory()->create([
            'permissions' => json_encode(['categories.view' => 1]),
        ]);

        $managedCategory = Category::factory()->forAssets()->create([
            'manager_id' => $afm->id,
        ]);
        Category::factory()->forAssets()->create();

        $this->actingAsForApi($afm)
            ->getJson(route('api.categories.index'))
            ->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('total', 1)
                ->where('rows.0.id', $managedCategory->id)
                ->missing('rows.1')
                ->etc());
    }

    public function testAfmCategoryInventoryCountsSpanCompaniesUnderFmcs()
    {
        $settings = Setting::getSettings();
        $settings->full_multiple_companies_support = 1;
        $settings->save();
        Setting::$_cache = $settings->fresh();

        $afm = User::factory()->create(['company_id' => null]);
        $category = Category::factory()->forAssets()->create([
            'name' => 'Communication',
            'manager_id' => $afm->id,
        ]);
        $modelA = AssetModel::factory()->create(['category_id' => $category->id]);
        $modelB = AssetModel::factory()->create(['category_id' => $category->id]);
        $ready = Statuslabel::factory()->readyToDeploy()->create();

        foreach ([$modelA, $modelB] as $model) {
            Asset::factory()->create([
                'model_id' => $model->id,
                'status_id' => $ready->id,
                'company_id' => Company::factory()->create()->id,
                'requestable' => true,
            ]);
        }

        $this->actingAsForApi($afm)
            ->getJson(route('api.categories.index', ['name' => 'Communication']))
            ->assertOk()
            ->assertJsonPath('rows.0.available_models_count', 2)
            ->assertJsonPath('rows.0.assets_count', 2)
            ->assertJsonPath('rows.0.reusable_assets_count', 2);
    }

    public function testUnassignedGlobalViewerIsNotTreatedAsAfm()
    {
        $viewer = User::factory()->create([
            'permissions' => json_encode(['categories.view' => 1]),
        ]);
        Category::factory()->forAssets()->count(2)->create();
        $expectedCategoryCount = Category::count();

        $this->actingAsForApi($viewer)
            ->getJson(route('api.categories.index'))
            ->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('total', $expectedCategoryCount)
                ->has('rows', $expectedCategoryCount)
                ->etc());
    }

    public function testExplicitCategoriesViewDenyBlocksManagerFallbackAccess()
    {
        $manager = User::factory()->create([
            'permissions' => json_encode(['categories.view' => -1]),
        ]);

        Category::factory()->forAssets()->create([
            'manager_id' => $manager->id,
        ]);

        $this->actingAsForApi($manager)
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
                ->where('rows.0.available_models_count', 1)
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

    public function testCategoryIndexCanFilterByManager()
    {
        $manager = User::factory()->create();
        $otherManager = User::factory()->create();
        $managedCategory = Category::factory()->forAssets()->create([
            'name' => 'Managed Category',
            'manager_id' => $manager->id,
        ]);
        $otherCategory = Category::factory()->forAssets()->create([
            'name' => 'Other Managed Category',
            'manager_id' => $otherManager->id,
        ]);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(
                route('api.categories.index', [
                    'manager_id' => $manager->id,
                    'sort' => 'name',
                    'order' => 'asc',
                    'offset' => '0',
                    'limit' => '20',
                ]))
            ->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('total', 1)
                ->where('rows.0.id', $managedCategory->id)
                ->where('rows.0.manager.id', $manager->id)
                ->missing('rows.1')
                ->etc());

        $this->assertNotEquals($managedCategory->id, $otherCategory->id);
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

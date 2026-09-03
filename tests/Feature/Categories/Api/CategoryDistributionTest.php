<?php

namespace Tests\Feature\Categories\Api;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\Discipline;
use App\Models\Setting;
use App\Models\Statuslabel;
use App\Models\User;
use Tests\TestCase;

class CategoryDistributionTest extends TestCase
{
    public function testDistributionRequiresCategoryViewAccess(): void
    {
        $this->actingAsForApi(User::factory()->create())
            ->getJson(route('api.categories.distribution'))
            ->assertForbidden();
    }

    public function testDistributionGroupsVisibleAssetsBySiteAndUsesPortfolioPercentages(): void
    {
        $admin = User::factory()->superuser()->create();
        $siteA = Company::factory()->create(['name' => 'Kronach']);
        $siteB = Company::factory()->create(['name' => 'Rabat']);
        $categoryA = Category::factory()->forAssets()->create(['name' => 'Power & Loads']);
        $categoryB = Category::factory()->forAssets()->create(['name' => 'Software Tools']);
        $modelA = AssetModel::factory()->create(['category_id' => $categoryA->id]);
        $modelB = AssetModel::factory()->create(['category_id' => $categoryB->id]);
        $ready = Statuslabel::factory()->readyToDeploy()->create();
        $archived = Statuslabel::factory()->archived()->create();

        Asset::factory()->count(2)->create([
            'model_id' => $modelA->id,
            'status_id' => $ready->id,
            'company_id' => $siteA->id,
        ]);
        Asset::factory()->create([
            'model_id' => $modelA->id,
            'status_id' => $ready->id,
            'company_id' => $siteB->id,
        ]);
        Asset::factory()->create([
            'model_id' => $modelA->id,
            'status_id' => $ready->id,
            'company_id' => null,
        ]);
        Asset::factory()->create([
            'model_id' => $modelB->id,
            'status_id' => $ready->id,
            'company_id' => $siteB->id,
        ]);
        Asset::factory()->create([
            'model_id' => $modelB->id,
            'status_id' => $archived->id,
            'company_id' => $siteB->id,
        ]);

        $response = $this->actingAsForApi($admin)
            ->getJson(route('api.categories.distribution', ['dimension' => 'site']))
            ->assertOk()
            ->assertJsonPath('dimension', 'site')
            ->assertJsonPath('total_assets', 5)
            ->assertJsonPath('category_count', 2);

        $categories = collect($response->json('categories'))->keyBy('id');
        $this->assertSame(4, $categories[$categoryA->id]['asset_count']);
        $this->assertSame(80, $categories[$categoryA->id]['percentage']);
        $this->assertSame(1, $categories[$categoryB->id]['asset_count']);
        $this->assertSame(20, $categories[$categoryB->id]['percentage']);

        $children = collect($categories[$categoryA->id]['children'])->keyBy('name');
        $this->assertSame(2, $children['Kronach']['asset_count']);
        $this->assertSame(40, $children['Kronach']['percentage']);
        $this->assertSame(1, $children['Undefined']['asset_count']);
        $this->assertNull($children['Undefined']['assets_url']);
    }

    public function testDistributionCanGroupByDiscipline(): void
    {
        $admin = User::factory()->superuser()->create();
        $discipline = Discipline::create([
            'name' => 'Validation',
            'created_by' => $admin->id,
        ]);
        $category = Category::factory()->forAssets()->create(['name' => 'Power & Loads']);
        $model = AssetModel::factory()->create(['category_id' => $category->id]);
        $ready = Statuslabel::factory()->readyToDeploy()->create();

        Asset::factory()->count(2)->create([
            'model_id' => $model->id,
            'status_id' => $ready->id,
            'discipline_id' => $discipline->id,
        ]);

        $this->actingAsForApi($admin)
            ->getJson(route('api.categories.distribution', ['dimension' => 'discipline']))
            ->assertOk()
            ->assertJsonPath('dimension', 'discipline')
            ->assertJsonPath('total_assets', 2)
            ->assertJsonPath('categories.0.children.0.name', 'Validation')
            ->assertJsonPath('categories.0.children.0.asset_count', 2)
            ->assertJsonPath('categories.0.children.0.percentage', 100);
    }

    public function testAfmDistributionSpansCompaniesButOnlyIncludesManagedCategories(): void
    {
        $settings = Setting::getSettings();
        $settings->full_multiple_companies_support = 1;
        $settings->save();
        Setting::$_cache = $settings->fresh();

        $afm = User::factory()->create(['company_id' => null]);
        $managedCategory = Category::factory()->forAssets()->create([
            'manager_id' => $afm->id,
        ]);
        $otherCategory = Category::factory()->forAssets()->create();
        $managedModel = AssetModel::factory()->create(['category_id' => $managedCategory->id]);
        $otherModel = AssetModel::factory()->create(['category_id' => $otherCategory->id]);
        $ready = Statuslabel::factory()->readyToDeploy()->create();

        foreach ([Company::factory()->create(), Company::factory()->create()] as $site) {
            Asset::factory()->create([
                'model_id' => $managedModel->id,
                'status_id' => $ready->id,
                'company_id' => $site->id,
            ]);
        }
        Asset::factory()->create([
            'model_id' => $otherModel->id,
            'status_id' => $ready->id,
        ]);

        $this->actingAsForApi($afm)
            ->getJson(route('api.categories.distribution'))
            ->assertOk()
            ->assertJsonPath('total_assets', 2)
            ->assertJsonPath('category_count', 1)
            ->assertJsonPath('categories.0.id', $managedCategory->id)
            ->assertJsonPath('categories.0.asset_count', 2);
    }
}

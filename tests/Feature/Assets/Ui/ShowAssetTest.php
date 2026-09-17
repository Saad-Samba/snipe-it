<?php

namespace Tests\Feature\Assets\Ui;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

class ShowAssetTest extends TestCase
{
    public function testPageForAssetWithMissingModelStillRenders()
    {
        $asset = Asset::factory()->create();

        $asset->model_id = null;
        $asset->forceSave();

        $asset->refresh();

        $this->assertNull($asset->fresh()->model_id, 'This test needs model_id to be null to be helpful.');

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('hardware.show', $asset))
            ->assertOk();
    }

    public function testPageShowsObsoleteIndicatorWhenAssetModelIsObsolete()
    {
        $asset = Asset::factory()->create([
            'model_id' => \App\Models\AssetModel::factory()->create(['obsolete' => true])->id,
        ]);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('hardware.show', $asset))
            ->assertOk()
            ->assertSeeText(trans('admin/models/general.obsolete_indicator'));
    }

    public function testAssetFamilyManagerCanViewManagedAssetAcrossCompaniesButNotUnmanagedAsset()
    {
        $this->settings->disableMultipleFullCompanySupport();

        $company = Company::factory()->create();
        $afm = User::factory()->create(['company_id' => null]);

        $managedCategory = Category::factory()->assetLaptopCategory()->create([
            'manager_id' => $afm->id,
        ]);
        $unmanagedCategory = Category::factory()->assetLaptopCategory()->create();

        $managedModel = AssetModel::factory()->create(['category_id' => $managedCategory->id]);
        $unmanagedModel = AssetModel::factory()->create(['category_id' => $unmanagedCategory->id]);

        $createAsset = fn (AssetModel $model) => Asset::withoutEvents(
            fn () => Asset::forceCreate(Asset::factory()->raw([
                'company_id' => $company->id,
                'model_id' => $model->id,
            ]))
        );

        $managedAsset = $createAsset($managedModel);
        $unmanagedAsset = $createAsset($unmanagedModel);

        $this->settings->enableMultipleFullCompanySupport();

        $this->actingAs($afm)
            ->get(route('hardware.show', $managedAsset))
            ->assertOk();

        $this->actingAs($afm)
            ->get(route('hardware.show', $unmanagedAsset))
            ->assertRedirect(route('hardware.index'));
    }
}

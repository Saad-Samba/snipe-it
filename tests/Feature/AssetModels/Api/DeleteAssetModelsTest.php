<?php

namespace Tests\Feature\AssetModels\Api;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\User;
use Tests\Concerns\TestsPermissionsRequirement;
use Tests\TestCase;

class DeleteAssetModelsTest extends TestCase implements TestsPermissionsRequirement
{
    public function testRequiresPermission()
    {
        $assetModel = AssetModel::factory()->create();

        $this->actingAsForApi(User::factory()->create())
            ->deleteJson(route('api.models.destroy', $assetModel))
            ->assertForbidden();

        $this->assertNotSoftDeleted($assetModel);
    }

    public function testCannotDeleteAssetModelThatStillHasAssociatedAssets()
    {
        $afm = User::factory()->deleteAssetModels()->create();
        $managedCategory = Category::factory()->forAssets()->create([
            'manager_id' => $afm->id,
        ]);
        $assetModel = Asset::factory()->create([
            'model_id' => AssetModel::factory()->create([
                'category_id' => $managedCategory->id,
            ])->id,
        ])->model;

        $this->actingAsForApi($afm)
            ->deleteJson(route('api.models.destroy', $assetModel))
            ->assertStatusMessageIs('error');

        $this->assertNotSoftDeleted($assetModel);
    }

    public function testCanDeleteAssetModel()
    {
        $afm = User::factory()->deleteAssetModels()->create();
        $managedCategory = Category::factory()->forAssets()->create([
            'manager_id' => $afm->id,
        ]);
        $assetModel = AssetModel::factory()->create([
            'category_id' => $managedCategory->id,
        ]);

        $this->actingAsForApi($afm)
            ->deleteJson(route('api.models.destroy', $assetModel))
            ->assertStatusMessageIs('success');

        $this->assertSoftDeleted($assetModel);
    }

    public function testAfmCannotDeleteAssetModelOutsideManagedCategory()
    {
        $afm = User::factory()->deleteAssetModels()->create();
        Category::factory()->forAssets()->create([
            'manager_id' => $afm->id,
        ]);
        $assetModel = AssetModel::factory()->create();

        $this->actingAsForApi($afm)
            ->deleteJson(route('api.models.destroy', $assetModel))
            ->assertForbidden();

        $this->assertNotSoftDeleted($assetModel);
    }
}

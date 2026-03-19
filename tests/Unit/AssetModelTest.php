<?php
namespace Tests\Unit;

use App\Models\Asset;
use App\Models\Category;
use App\Models\AssetModel;
use App\Models\CustomFieldset;
use Tests\TestCase;

class AssetModelTest extends TestCase
{
    public function testAnAssetModelContainsAssets()
    {
        $category = Category::factory()->create([
            'category_type' => 'asset'
            ]);
        $model = AssetModel::factory()->create([
            'category_id' => $category->id,
        ]);
    
        $asset = Asset::factory()->create([
                    'model_id' => $model->id
                ]);
        $this->assertEquals(1, $model->assets()->count());
    }

    public function test_asset_model_inherits_category_fieldset_when_no_explicit_fieldset()
    {
        $fieldset = CustomFieldset::factory()->create();
        $category = Category::factory()->create([
            'category_type' => 'asset',
            'fieldset_id' => $fieldset->id,
        ]);

        $model = AssetModel::factory()->create([
            'category_id' => $category->id,
            'fieldset_id' => null,
        ]);

        $this->assertEquals($fieldset->id, $model->fieldset?->id);
    }

    public function test_explicit_asset_model_fieldset_overrides_category_fieldset()
    {
        $categoryFieldset = CustomFieldset::factory()->create();
        $modelFieldset = CustomFieldset::factory()->create();
        $category = Category::factory()->create([
            'category_type' => 'asset',
            'fieldset_id' => $categoryFieldset->id,
        ]);

        $model = AssetModel::factory()->create([
            'category_id' => $category->id,
            'fieldset_id' => $modelFieldset->id,
        ]);

        $this->assertEquals($modelFieldset->id, $model->fieldset?->id);
    }
}

<?php

namespace Tests\Feature\Livewire;

use App\Livewire\CustomFieldSetDefaultValuesForModel;
use App\Models\Category;
use App\Models\CustomField;
use App\Models\CustomFieldset;
use Livewire\Livewire;
use Tests\TestCase;

class CustomFieldSetDefaultValuesForModelTest extends TestCase
{
    public function testComponentUsesSelectedCategoryFieldsetWhenModelDoesNotExist()
    {
        $fieldset = CustomFieldset::factory()->create(['name' => 'Category Default Fieldset']);
        $category = Category::factory()->forAssets()->create([
            'fieldset_id' => $fieldset->id,
        ]);

        Livewire::test(CustomFieldSetDefaultValuesForModel::class)
            ->set('category_id', $category->id)
            ->assertSet('inheritedFieldset.id', $fieldset->id)
            ->assertSee('Category Default Fieldset');
    }

    public function testComponentShowsInheritedFieldsetDefaultsWithoutOverrideControlsWhenOverridesAreDisabled()
    {
        $categoryFieldset = CustomFieldset::factory()->create(['name' => 'Governed Category Fieldset']);
        $categoryFieldset->fields()->attach(CustomField::factory()->create(), ['order' => 1, 'required' => false]);
        $modelFieldset = CustomFieldset::factory()->create(['name' => 'Hidden Model Override']);
        $category = Category::factory()->forAssets()->create([
            'fieldset_id' => $categoryFieldset->id,
        ]);

        $model = \App\Models\AssetModel::factory()->create([
            'category_id' => $category->id,
            'fieldset_id' => $modelFieldset->id,
        ]);

        Livewire::test(CustomFieldSetDefaultValuesForModel::class, ['model_id' => $model->id])
            ->assertSee('Governed Category Fieldset')
            ->assertDontSee('Hidden Model Override')
            ->assertDontSeeHtml('name="fieldset_id"')
            ->assertSeeHtml('name="add_default_values"');
    }
}

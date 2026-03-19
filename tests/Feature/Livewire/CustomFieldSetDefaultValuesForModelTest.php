<?php

namespace Tests\Feature\Livewire;

use App\Livewire\CustomFieldSetDefaultValuesForModel;
use App\Models\Category;
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
}

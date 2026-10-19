<?php

namespace Tests\Feature\Categories\Ui;

use App\Models\Category;
use App\Models\CustomField;
use App\Models\CustomFieldset;
use App\Models\User;
use Tests\TestCase;

class ShowCategoryTest extends TestCase
{
    public function testPageRenders()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('categories.show', Category::factory()->create()))
            ->assertOk();
    }

    public function testModelsTabAddsAColumnForEachCategorySpecificSpecification()
    {
        $fieldset = CustomFieldset::factory()->create(['name' => 'Power Specifications']);
        $voltage = CustomField::factory()->create(['name' => 'Output Voltage']);
        $fieldset->fields()->attach($voltage->id, ['order' => 1, 'required' => false]);
        $category = Category::factory()->forAssets()->create([
            'name' => 'Power Supplies',
            'fieldset_id' => $fieldset->id,
        ]);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('categories.show', $category))
            ->assertOk()
            ->assertSee('This table includes one column for each technical specification configured for the category.')
            ->assertSee('Output Voltage')
            ->assertSee('specification_'.$voltage->id, false)
            ->assertDontSee('data-detail-view="true"', false);
    }
}

<?php

namespace Tests\Feature\Categories\Ui;

use App\Models\Category;
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

    public function testModelsTabExposesExpandableCategorySpecificSpecifications()
    {
        $fieldset = CustomFieldset::factory()->create(['name' => 'Power Specifications']);
        $category = Category::factory()->forAssets()->create([
            'name' => 'Power Supplies',
            'fieldset_id' => $fieldset->id,
        ]);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('categories.show', $category))
            ->assertOk()
            ->assertSee('Expand a model row to review its category-specific technical specifications.')
            ->assertSee('data-detail-view="true"', false)
            ->assertSee('categoryModelSpecificationsDetailFormatter', false)
            ->assertSee('Technical attribute')
            ->assertSee('Model specification');
    }
}

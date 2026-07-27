<?php

namespace Tests\Feature\Categories\Ui;

use App\Models\AssetModel;
use App\Models\Category;
use App\Models\CustomFieldset;
use App\Models\User;
use Tests\TestCase;

class CreateCategoriesTest extends TestCase
{
    public function testPermissionRequiredToCreateCategories()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('categories.store'), [
                'name' => 'Test Category',
                'category_type' => 'asset',
            ])
            ->assertForbidden();
    }

    public function testPageRenders()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('categories.create'))
            ->assertOk();
    }

    public function testAfmCannotOpenOrSubmitCategoryCreation()
    {
        $afm = User::factory()->create([
            'permissions' => json_encode(['categories.create' => 1]),
        ]);
        Category::factory()->forAssets()->create([
            'manager_id' => $afm->id,
        ]);

        $this->actingAs($afm)
            ->get(route('categories.create'))
            ->assertForbidden();

        $this->actingAs($afm)
            ->post(route('categories.store'), [
                'name' => 'Self Assigned Category',
                'category_type' => 'asset',
                'manager_id' => $afm->id,
            ])
            ->assertForbidden();
    }

    public function testUserCanCreateCategories()
    {
        $this->assertFalse(Category::where('name', 'Test Category')->exists());
        $fieldset = CustomFieldset::factory()->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->post(route('categories.store'), [
                'name' => 'Test Category',
                'category_type' => 'asset',
                'fieldset_id' => $fieldset->id,
                'eula_text' => 'Sample text',
                'require_acceptance' => '1',
                'notes' => 'My Note',
            ])
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', [
            'name' => 'Test Category',
            'category_type' => 'asset',
            'fieldset_id' => $fieldset->id,
            'eula_text' => 'Sample text',
            'notes' => 'My Note',
            'require_acceptance' => 1,
            'alert_on_response' => 0,
        ]);
    }

    public function testUserCannotCreateCategoriesWithInvalidType()
    {
        $this->assertFalse(Category::where('name', 'Test Category')->exists());

        $this->actingAs(User::factory()->superuser()->create())
            ->from(route('categories.create'))
            ->post(route('categories.store'), [
                'name' => 'Test Category',
                'category_type' => 'invalid',
            ])
            ->assertRedirect(route('categories.create'));

        $this->assertFalse(Category::where('name', 'Test Category')->exists());
    }

}

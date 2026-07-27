<?php

namespace Tests\Feature\Categories\Api;

use App\Models\Category;
use App\Models\CustomFieldset;
use App\Models\User;
use Tests\TestCase;

class UpdateCategoriesTest extends TestCase
{
    public function test_requires_permission_to_update_category()
    {
        $category = Category::factory()->create();

        $this->actingAsForApi(User::factory()->create())
            ->patchJson(route('api.categories.update', $category))
            ->assertForbidden();
    }

    public function test_can_update_category()
    {
        $category = Category::factory()->forAssets()->create([
            'name' => 'Test Category',
            'require_acceptance' => false,
            'alert_on_response' => false,
        ]);
        $fieldset = CustomFieldset::factory()->create();

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->patchJson(route('api.categories.update', $category), [
                'name' => 'Test Category Edited',
                'fieldset_id' => $fieldset->id,
                'notes' => 'Test Note Edited',
                'require_acceptance' => true,
                'alert_on_response' => true,
            ])
            ->assertOk()
            ->assertStatusMessageIs('success')
            ->assertStatus(200);

        $category->refresh();
        $this->assertEquals('Test Category Edited', $category->name, 'Name was not updated');
        $this->assertEquals('Test Note Edited', $category->notes, 'Note was not updated');
        $this->assertEquals($fieldset->id, $category->fieldset_id, 'Fieldset was not updated');
        $this->assertEquals(1, $category->require_acceptance, 'Require acceptance was not updated');
        $this->assertTrue($category->alert_on_response, 'Alert on response was not updated');
    }

    public function testCanUpdateCategoryViaPatchWithoutCategoryType()
    {
        $category = Category::factory()->create();

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->patchJson(route('api.categories.update', $category), [
                'name' => 'Test Category',
                'eula_text' => 'Test EULA',
                'notes' => 'Test Note',
            ])
            ->assertOk()
            ->assertStatusMessageIs('success')
            ->assertStatus(200)
            ->json();

        //dd($response);
        $category->refresh();
        $this->assertEquals('Test Category', $category->name, 'Name was not updated');
        $this->assertEquals('Test EULA', $category->eula_text, 'EULA was not updated');
        $this->assertEquals('Test Note', $category->notes, 'Note was not updated');

    }

    public function testCannotUpdateCategoryViaPatchWithCategoryType()
    {
        $category = Category::factory()->create();

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->patchJson(route('api.categories.update', $category), [
                'name' => 'Test Category',
                'eula_text' => 'Test EULA',
                'category_type' => 'accessory',
                'note' => 'Test Note',
            ])
            ->assertOk()
            ->assertStatusMessageIs('error')
            ->assertStatus(200)
            ->json();
        
        $category->refresh();
        $this->assertNotEquals('Test Category', $category->name, 'Name was not updated');
        $this->assertNotEquals('Test EULA', $category->eula_text, 'EULA was not updated');
        $this->assertNotEquals('Test Note', $category->notes, 'Note was not updated');
        $this->assertNotEquals('accessory', $category->category_type, 'EULA was not updated');

    }

    public function testCategoryManagerCanUpdateManagedCategoryWithoutChangingOwnership()
    {
        $manager = User::factory()->create();
        $otherManager = User::factory()->create();
        $category = Category::factory()->forAssets()->create([
            'name' => 'Managed Category',
            'manager_id' => $manager->id,
        ]);

        $this->actingAsForApi($manager)
            ->patchJson(route('api.categories.update', $category), [
                'name' => 'Managed Category Updated',
                'manager_id' => $otherManager->id,
            ])
            ->assertOk()
            ->assertStatusMessageIs('success');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Managed Category Updated',
            'manager_id' => $manager->id,
        ]);
    }

    public function testCategoryManagerCannotUpdateUnmanagedCategory()
    {
        $manager = User::factory()->create();
        Category::factory()->forAssets()->create([
            'manager_id' => $manager->id,
        ]);
        $unmanagedCategory = Category::factory()->forAssets()->create();

        $this->actingAsForApi($manager)
            ->patchJson(route('api.categories.update', $unmanagedCategory), [
                'name' => 'Should Not Change',
            ])
            ->assertForbidden();
    }
}

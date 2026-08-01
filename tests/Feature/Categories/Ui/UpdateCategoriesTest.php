<?php

namespace Tests\Feature\Categories\Ui;

use App\Models\Category;
use App\Models\Asset;
use App\Models\CustomFieldset;
use App\Models\User;
use Tests\TestCase;

class UpdateCategoriesTest extends TestCase
{
    public function testPermissionRequiredToStoreCategory()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('categories.store'), [
                'name' => 'Test Category',
                'category_type' => 'asset'
            ])
            ->assertStatus(403)
            ->assertForbidden();
    }

    public function testPageRenders()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('categories.edit', Category::factory()->create()))
            ->assertOk();
    }

    public function testUserCanCreateCategories()
    {
        $manager = User::factory()->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->post(route('categories.store'), [
                'name' => 'Test Category',
                'category_type' => 'asset',
                'manager_id' => $manager->id,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', [
            'name' => 'Test Category',
            'manager_id' => $manager->id,
        ]);
    }

    public function testUserCanEditAssetCategory()
    {
        $category = Category::factory()->forAssets()->create([
            'name' => 'Test Category',
            'require_acceptance' => false,
            'alert_on_response' => false,
        ]);
        $fieldset = CustomFieldset::factory()->create();
        $manager = User::factory()->create();

        $this->assertTrue(Category::where('name', 'Test Category')->exists());

        $response = $this->actingAs(User::factory()->superuser()->create())
            ->put(route('categories.update', $category), [
                'name' => 'Test Category Edited',
                'fieldset_id' => $fieldset->id,
                'manager_id' => $manager->id,
                'notes' => 'Test Note Edited',
                'require_acceptance' => '1',
                'alert_on_response' => '1',
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('categories.index'));

        $this->followRedirects($response)->assertSee('Success');

        $this->assertDatabaseHas('categories', [
            'name' => 'Test Category Edited',
            'fieldset_id' => $fieldset->id,
            'manager_id' => $manager->id,
            'notes' => 'Test Note Edited',
            'require_acceptance' => 0,
            'alert_on_response' => 0,
        ]);
    }

    public function testUserCanChangeCategoryTypeIfNoAssetsAssociated()
    {
        $category = Category::factory()->forAssets()->create(['name' => 'Test Category']);
        $this->assertTrue(Category::where('name', 'Test Category')->exists());

        $response = $this->actingAs(User::factory()->superuser()->create())
            ->from(route('categories.edit', $category->id))
            ->put(route('categories.update', $category), [
                'name' => 'Test Category Edited',
                'category_type' => 'accessory',
                'notes' => 'Test Note Edited',
            ])
            ->assertSessionHasNoErrors()
            ->assertStatus(302)
            ->assertRedirect(route('categories.index'));

        $this->followRedirects($response)->assertSee('Success');
        $this->assertTrue(Category::where('name', 'Test Category Edited')->where('notes', 'Test Note Edited')->exists());

    }

    public function testUserCannotChangeCategoryTypeIfAssetsAreAssociated()
    {
        Asset::factory()->count(5)->laptopMbp()->create();
        $category = Category::where('name', 'Laptops')->first();

        $response = $this->actingAs(User::factory()->superuser()->create())
            ->from(route('categories.edit', $category))
            ->put(route('categories.update', $category), [
                'name' => 'Test Category Edited',
                'category_type' => 'accessory',
                'notes' => 'Test Note Edited',
            ])
            ->assertSessionHasErrors(['category_type'])
            ->assertInvalid(['category_type'])
            ->assertStatus(302)
            ->assertRedirect(route('categories.edit', $category));

        $this->followRedirects($response)->assertSee(trans('general.error'));
        $this->assertFalse(Category::where('name', 'Test Category Edited')->where('notes', 'Test Note Edited')->exists());

    }

    public function testCategoryManagerEditFormShowsOwnershipAsReadOnly()
    {
        $manager = User::factory()->create([
            'first_name' => 'Assigned',
            'last_name' => 'AFM',
        ]);
        $category = Category::factory()->forAssets()->create([
            'manager_id' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->get(route('categories.edit', $category))
            ->assertOk()
            ->assertSee('Category Manager', false)
            ->assertSee('Assigned AFM', false)
            ->assertDontSee('category_manager_select', false);
    }

    public function testCategoryManagerCannotChangeManagedCategoryOwnership()
    {
        $manager = User::factory()->create();
        $otherManager = User::factory()->create();
        $category = Category::factory()->forAssets()->create([
            'name' => 'Managed Category',
            'manager_id' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->put(route('categories.update', $category), [
                'name' => 'Managed Category Updated',
                'category_type' => 'asset',
                'manager_id' => $otherManager->id,
            ])
            ->assertRedirect(route('categories.index'))
            ->assertSessionHasNoErrors();

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

        $this->actingAs($manager)
            ->put(route('categories.update', $unmanagedCategory), [
                'name' => 'Should Not Change',
                'category_type' => 'asset',
            ])
            ->assertForbidden();
    }
}

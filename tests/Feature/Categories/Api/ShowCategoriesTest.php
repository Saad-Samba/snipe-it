<?php

namespace Tests\Feature\Categories\Api;

use App\Models\Category;
use App\Models\User;
use Tests\TestCase;

class ShowCategoriesTest extends TestCase
{
    public function testManagerCanViewManagedCategoryWithoutGlobalCategoriesViewPermission()
    {
        $manager = User::factory()->create();
        $category = Category::factory()->forAssets()->create([
            'manager_id' => $manager->id,
        ]);

        $this->actingAsForApi($manager)
            ->getJson(route('api.categories.show', $category))
            ->assertOk()
            ->assertJson([
                'id' => $category->id,
            ]);
    }

    public function testManagerCannotViewUnmanagedCategoryWithoutGlobalCategoriesViewPermission()
    {
        $manager = User::factory()->create();
        $category = Category::factory()->forAssets()->create();

        $this->actingAsForApi($manager)
            ->getJson(route('api.categories.show', $category))
            ->assertForbidden();
    }
}

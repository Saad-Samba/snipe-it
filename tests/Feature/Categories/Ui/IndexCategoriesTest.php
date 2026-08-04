<?php

namespace Tests\Feature\Categories\Ui;

use App\Models\Category;
use App\Models\User;
use Tests\TestCase;

class IndexCategoriesTest extends TestCase
{
    public function testPermissionRequiredToViewCategoryList()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('categories.index'))
            ->assertForbidden();
    }

    public function testUserCanListCategories()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('categories.index'))
            ->assertOk()
            ->assertSee('Category Manager', false)
            ->assertSee('Reusable Inventory', false)
            ->assertDontSee('&quot;title&quot;:&quot;Available Models&quot;', false)
            ->assertDontSee('&quot;title&quot;:&quot;Available Assets&quot;', false)
            ->assertDontSee('&quot;title&quot;:&quot;Send Email&quot;', false)
            ->assertSee('categoryReusableInventoryFormatter', false)
            ->assertDontSee('My Categories', false);
    }

    public function testCategoryManagerCanOpenCategoryListWithoutGlobalCategoriesViewPermission()
    {
        $manager = User::factory()->create();
        Category::factory()->forAssets()->create([
            'manager_id' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->get(route('categories.index'))
            ->assertOk()
            ->assertDontSee('My Categories', false);
    }
}

<?php

namespace Tests\Feature\Categories\Ui;

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
            ->assertSee('Available Models', false)
            ->assertSee('Available Assets', false)
            ->assertSee('My Categories', false);
    }
}

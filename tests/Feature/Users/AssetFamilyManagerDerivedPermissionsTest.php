<?php

namespace Tests\Feature\Users;

use App\Models\Category;
use App\Models\User;
use Tests\TestCase;

class AssetFamilyManagerDerivedPermissionsTest extends TestCase
{
    public function testAssetFamilyAssignmentDerivesAfmPermissionsAndRevokesThemWithTheAssignment()
    {
        $user = User::factory()->create();

        $this->assertFalse($user->isAssetFamilyManager());
        $this->assertFalse($user->hasAccess('models.view'));

        $category = Category::factory()->forAssets()->create([
            'manager_id' => $user->id,
        ]);

        $this->assertTrue($user->isAssetFamilyManager());

        foreach ([
            'reports.view',
            'assets.view',
            'users.view',
            'models.view',
            'models.create',
            'models.edit',
            'models.request',
            'categories.view',
            'categories.edit',
            'customfields.view',
            'customfields.create',
            'customfields.edit',
            'manufacturers.view',
            'manufacturers.create',
            'manufacturers.edit',
            'suppliers.view',
            'suppliers.create',
            'suppliers.edit',
            'depreciations.view',
            'depreciations.create',
            'depreciations.edit',
            'locations.view',
            'companies.view',
            'departments.view',
            'statuslabels.view',
        ] as $permission) {
            $this->assertTrue($user->hasAccess($permission), $permission.' should be derived from the assignment.');
        }

        $this->assertFalse($user->hasAccess('categories.create'));
        $this->assertFalse($user->hasAccess('categories.delete'));
        $this->assertFalse($user->hasAccess('models.delete'));

        $category->update(['manager_id' => null]);

        $this->assertFalse($user->isAssetFamilyManager());
        $this->assertFalse($user->hasAccess('models.view'));
    }

    public function testExplicitPermissionDenialOverridesAfmDerivedPermission()
    {
        $user = User::factory()->create([
            'permissions' => json_encode(['models.view' => -1]),
        ]);
        Category::factory()->forAssets()->create([
            'manager_id' => $user->id,
        ]);

        $this->assertTrue($user->isAssetFamilyManager());
        $this->assertFalse($user->hasAccess('models.view'));
    }
}

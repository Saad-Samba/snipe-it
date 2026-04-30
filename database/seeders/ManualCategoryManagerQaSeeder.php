<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class ManualCategoryManagerQaSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::withoutGlobalScopes()->updateOrCreate(
            ['username' => 'qa-category-admin'],
            [
                'first_name' => 'QA',
                'last_name' => 'Category Admin',
                'display_name' => 'QA Category Admin',
                'email' => 'qa-category-admin@example.com',
                'activated' => 1,
                'company_id' => null,
                'locale' => 'en-US',
                'permissions' => json_encode(['superuser' => '1']),
                'password' => bcrypt('password'),
                'notes' => 'Deterministic QA admin for category manager validation.',
                'created_by' => 1,
            ]
        );

        $alphaManager = User::withoutGlobalScopes()->updateOrCreate(
            ['username' => 'qa-category-alpha'],
            [
                'first_name' => 'Alpha',
                'last_name' => 'Manager',
                'display_name' => 'Alpha Manager',
                'email' => 'qa-category-alpha@example.com',
                'activated' => 1,
                'company_id' => null,
                'locale' => 'en-US',
                'permissions' => json_encode(['categories.view' => '1']),
                'password' => bcrypt('password'),
                'notes' => 'Deterministic QA AFM-style user for My Categories validation.',
                'created_by' => $admin->id,
            ]
        );

        $betaManager = User::withoutGlobalScopes()->updateOrCreate(
            ['username' => 'qa-category-beta'],
            [
                'first_name' => 'Beta',
                'last_name' => 'Manager',
                'display_name' => 'Beta Manager',
                'email' => 'qa-category-beta@example.com',
                'activated' => 1,
                'company_id' => null,
                'locale' => 'en-US',
                'permissions' => json_encode(['categories.view' => '1']),
                'password' => bcrypt('password'),
                'notes' => 'Deterministic QA AFM-style user for manager filter validation.',
                'created_by' => $admin->id,
            ]
        );

        $this->upsertCategory(
            name: 'QA Category Family Alpha',
            type: 'asset',
            managerId: $alphaManager->id,
            createdBy: $admin->id,
            notes: 'Managed by Alpha Manager. Included in My Categories for qa-category-alpha.'
        );

        $this->upsertCategory(
            name: 'QA Category Family Bravo',
            type: 'asset',
            managerId: $alphaManager->id,
            createdBy: $admin->id,
            notes: 'Second category managed by Alpha Manager to validate multi-category ownership.'
        );

        $this->upsertCategory(
            name: 'QA Category Family Delta',
            type: 'consumable',
            managerId: $betaManager->id,
            createdBy: $admin->id,
            notes: 'Managed by Beta Manager. Used to confirm other managers remain visible globally.'
        );

        $this->upsertCategory(
            name: 'QA Category Family Unassigned',
            type: 'license',
            managerId: null,
            createdBy: $admin->id,
            notes: 'No category manager assigned. Control record for QA.'
        );

        $this->command?->info('Manual category manager QA dataset is ready.');
        $this->command?->line('Admin: qa-category-admin / password');
        $this->command?->line('AFM Alpha: qa-category-alpha / password');
        $this->command?->line('AFM Beta: qa-category-beta / password');
        $this->command?->line('Alpha manager categories: QA Category Family Alpha, QA Category Family Bravo');
        $this->command?->line('Beta manager category: QA Category Family Delta');
        $this->command?->line('Unassigned control: QA Category Family Unassigned');
    }

    private function upsertCategory(
        string $name,
        string $type,
        ?int $managerId,
        int $createdBy,
        string $notes
    ): Category {
        return Category::withoutGlobalScopes()->updateOrCreate(
            ['name' => $name, 'category_type' => $type],
            [
                'created_by' => $createdBy,
                'manager_id' => $managerId,
                'checkin_email' => 0,
                'require_acceptance' => 0,
                'use_default_eula' => 0,
                'notes' => $notes,
            ]
        );
    }
}

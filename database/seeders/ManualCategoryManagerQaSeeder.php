<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\Statuslabel;
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
                'permissions' => json_encode([
                    'categories.view' => '1',
                    'models.view' => '1',
                    'assets.view' => '1',
                ]),
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
                'permissions' => json_encode([
                    'categories.view' => '1',
                    'models.view' => '1',
                    'assets.view' => '1',
                ]),
                'password' => bcrypt('password'),
                'notes' => 'Deterministic QA AFM-style user for manager filter validation.',
                'created_by' => $admin->id,
            ]
        );

        $qaCompany = Company::withoutGlobalScopes()->firstOrCreate(
            ['name' => 'QA Category Manager Company'],
            ['created_by' => $admin->id]
        );

        $readyStatus = Statuslabel::withoutGlobalScopes()->firstOrCreate(
            ['name' => 'QA Category Manager Ready'],
            [
                'deployable' => 1,
                'pending' => 0,
                'archived' => 0,
                'default_label' => 0,
                'created_by' => $admin->id,
            ]
        );

        $archivedStatus = Statuslabel::withoutGlobalScopes()->firstOrCreate(
            ['name' => 'QA Category Manager Archived'],
            [
                'deployable' => 0,
                'pending' => 0,
                'archived' => 1,
                'default_label' => 0,
                'created_by' => $admin->id,
            ]
        );

        $undeployableStatus = Statuslabel::withoutGlobalScopes()->firstOrCreate(
            ['name' => 'QA Category Manager Undeployable'],
            [
                'deployable' => 0,
                'pending' => 0,
                'archived' => 0,
                'default_label' => 0,
                'created_by' => $admin->id,
            ]
        );

        $alphaCategory = $this->upsertCategory(
            name: 'QA Category Family Alpha',
            type: 'asset',
            managerId: $alphaManager->id,
            createdBy: $admin->id,
            notes: 'Managed by Alpha Manager. Included in My Categories for qa-category-alpha.'
        );

        $bravoCategory = $this->upsertCategory(
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

        $alphaModelA = $this->upsertModel(
            name: 'QA Alpha Model A',
            categoryId: $alphaCategory->id,
            createdBy: $admin->id
        );

        $alphaModelB = $this->upsertModel(
            name: 'QA Alpha Model B',
            categoryId: $alphaCategory->id,
            createdBy: $admin->id
        );

        $bravoModelA = $this->upsertModel(
            name: 'QA Bravo Model A',
            categoryId: $bravoCategory->id,
            createdBy: $admin->id
        );

        $this->upsertAsset(
            assetTag: 'QA-CAT-ALPHA-001',
            name: 'QA Alpha RTD 1',
            modelId: $alphaModelA->id,
            statusId: $readyStatus->id,
            companyId: $qaCompany->id,
            createdBy: $admin->id
        );

        $this->upsertAsset(
            assetTag: 'QA-CAT-ALPHA-002',
            name: 'QA Alpha RTD 2',
            modelId: $alphaModelA->id,
            statusId: $readyStatus->id,
            companyId: $qaCompany->id,
            createdBy: $admin->id
        );

        $this->upsertAsset(
            assetTag: 'QA-CAT-ALPHA-003',
            name: 'QA Alpha RTD 3',
            modelId: $alphaModelB->id,
            statusId: $readyStatus->id,
            companyId: $qaCompany->id,
            createdBy: $admin->id
        );

        $this->upsertAsset(
            assetTag: 'QA-CAT-ALPHA-ASSIGNED-001',
            name: 'QA Alpha Assigned',
            modelId: $alphaModelB->id,
            statusId: $readyStatus->id,
            companyId: $qaCompany->id,
            createdBy: $admin->id,
            assignedTo: $betaManager->id,
            assignedType: User::class
        );

        $this->upsertAsset(
            assetTag: 'QA-CAT-BRAVO-ARCH-001',
            name: 'QA Bravo Archived',
            modelId: $bravoModelA->id,
            statusId: $archivedStatus->id,
            companyId: $qaCompany->id,
            createdBy: $admin->id
        );

        $this->upsertAsset(
            assetTag: 'QA-CAT-BRAVO-UND-001',
            name: 'QA Bravo Undeployable',
            modelId: $bravoModelA->id,
            statusId: $undeployableStatus->id,
            companyId: $qaCompany->id,
            createdBy: $admin->id
        );

        $this->command?->info('Manual category manager QA dataset is ready.');
        $this->command?->line('Admin: qa-category-admin / password');
        $this->command?->line('AFM Alpha: qa-category-alpha / password');
        $this->command?->line('AFM Beta: qa-category-beta / password');
        $this->command?->line('Alpha manager categories: QA Category Family Alpha, QA Category Family Bravo');
        $this->command?->line('Beta manager category: QA Category Family Delta');
        $this->command?->line('Unassigned control: QA Category Family Unassigned');
        $this->command?->line('Expected Alpha counts: 2 available models, 3 remaining assets');
        $this->command?->line('Expected Bravo counts: 0 available models, 0 remaining assets');
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

    private function upsertModel(string $name, int $categoryId, int $createdBy): AssetModel
    {
        return AssetModel::withoutGlobalScopes()->updateOrCreate(
            ['name' => $name],
            [
                'category_id' => $categoryId,
                'created_by' => $createdBy,
                'require_serial' => 0,
                'notes' => 'Deterministic model for category manager QA.',
            ]
        );
    }

    private function upsertAsset(
        string $assetTag,
        string $name,
        int $modelId,
        int $statusId,
        ?int $companyId,
        int $createdBy,
        ?int $assignedTo = null,
        ?string $assignedType = null
    ): Asset {
        $asset = Asset::withoutGlobalScopes()->firstOrNew(['asset_tag' => $assetTag]);
        $asset->name = $name;
        $asset->asset_tag = $assetTag;
        $asset->model_id = $modelId;
        $asset->status_id = $statusId;
        $asset->company_id = $companyId;
        $asset->created_by = $createdBy;
        $asset->assigned_to = $assignedTo;
        $asset->assigned_type = $assignedType;
        $asset->requestable = 0;
        $asset->notes = 'Deterministic asset for category manager QA.';
        $asset->save();

        return $asset;
    }
}

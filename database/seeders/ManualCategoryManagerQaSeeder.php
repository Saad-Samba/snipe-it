<?php

namespace Database\Seeders;

use App\Actions\CheckoutRequests\ResolveCheckoutRequestCoordinatorsAction;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\CheckoutRequest;
use App\Models\Company;
use App\Models\Discipline;
use App\Models\RegionalAssetCoordinatorAssignment;
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
                    'assets.view.requestable' => '1',
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
                    'assets.view.requestable' => '1',
                ]),
                'password' => bcrypt('password'),
                'notes' => 'Deterministic QA AFM-style user for manager filter validation.',
                'created_by' => $admin->id,
            ]
        );

        $casablancaCoordinator = User::withoutGlobalScopes()->updateOrCreate(
            ['username' => 'qa-category-coordinator-casa'],
            [
                'first_name' => 'Casablanca',
                'last_name' => 'Coordinator',
                'display_name' => 'Casablanca Coordinator',
                'email' => 'qa-category-coordinator-casa@example.com',
                'activated' => 1,
                'company_id' => null,
                'locale' => 'en-US',
                'permissions' => json_encode([
                    'categories.view' => '1',
                    'models.view' => '1',
                    'assets.view' => '1',
                    'assets.view.requestable' => '1',
                ]),
                'password' => bcrypt('password'),
                'notes' => 'Deterministic RAC for Casablanca-site discipline routing QA.',
                'created_by' => $admin->id,
            ]
        );

        $rabatCoordinator = User::withoutGlobalScopes()->updateOrCreate(
            ['username' => 'qa-category-coordinator-rabat'],
            [
                'first_name' => 'Rabat',
                'last_name' => 'Coordinator',
                'display_name' => 'Rabat Coordinator',
                'email' => 'qa-category-coordinator-rabat@example.com',
                'activated' => 1,
                'company_id' => null,
                'locale' => 'en-US',
                'permissions' => json_encode([
                    'categories.view' => '1',
                    'models.view' => '1',
                    'assets.view' => '1',
                    'assets.view.requestable' => '1',
                ]),
                'password' => bcrypt('password'),
                'notes' => 'Deterministic RAC for Rabat-site discipline routing QA.',
                'created_by' => $admin->id,
            ]
        );

        $casablancaCompany = Company::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'QA Casablanca Site'],
            ['created_by' => $admin->id]
        );

        $rabatCompany = Company::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'QA Rabat Site'],
            ['created_by' => $admin->id]
        );

        $powerDiscipline = Discipline::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'QA Power Discipline'],
            [
                'notes' => 'Discipline used to resolve RAC candidates from company stock.',
                'created_by' => $admin->id,
            ]
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
            companyId: $casablancaCompany->id,
            disciplineId: $powerDiscipline->id,
            createdBy: $admin->id,
            requestable: true
        );

        $this->upsertAsset(
            assetTag: 'QA-CAT-ALPHA-002',
            name: 'QA Alpha RTD 2',
            modelId: $alphaModelA->id,
            statusId: $readyStatus->id,
            companyId: $casablancaCompany->id,
            disciplineId: $powerDiscipline->id,
            createdBy: $admin->id,
            requestable: true
        );

        $this->upsertAsset(
            assetTag: 'QA-CAT-ALPHA-003',
            name: 'QA Alpha RTD 3',
            modelId: $alphaModelB->id,
            statusId: $readyStatus->id,
            companyId: $rabatCompany->id,
            disciplineId: $powerDiscipline->id,
            createdBy: $admin->id,
            requestable: true
        );

        $this->upsertAsset(
            assetTag: 'QA-CAT-ALPHA-ASSIGNED-001',
            name: 'QA Alpha Assigned',
            modelId: $alphaModelB->id,
            statusId: $readyStatus->id,
            companyId: $rabatCompany->id,
            disciplineId: $powerDiscipline->id,
            createdBy: $admin->id,
            assignedTo: $betaManager->id,
            assignedType: User::class,
            requestable: false
        );

        $this->upsertAsset(
            assetTag: 'QA-CAT-BRAVO-ARCH-001',
            name: 'QA Bravo Archived',
            modelId: $bravoModelA->id,
            statusId: $archivedStatus->id,
            companyId: $casablancaCompany->id,
            disciplineId: $powerDiscipline->id,
            createdBy: $admin->id,
            requestable: false
        );

        $this->upsertAsset(
            assetTag: 'QA-CAT-BRAVO-UND-001',
            name: 'QA Bravo Undeployable',
            modelId: $bravoModelA->id,
            statusId: $undeployableStatus->id,
            companyId: $rabatCompany->id,
            disciplineId: $powerDiscipline->id,
            createdBy: $admin->id,
            requestable: false
        );

        RegionalAssetCoordinatorAssignment::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $casablancaCompany->id, 'discipline_id' => $powerDiscipline->id],
            ['user_id' => $casablancaCoordinator->id, 'created_by' => $admin->id]
        );

        RegionalAssetCoordinatorAssignment::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $rabatCompany->id, 'discipline_id' => $powerDiscipline->id],
            ['user_id' => $rabatCoordinator->id, 'created_by' => $admin->id]
        );

        $alphaRequest = CheckoutRequest::withoutGlobalScopes()->updateOrCreate(
            [
                'user_id' => $alphaManager->id,
                'requestable_id' => $alphaModelA->id,
                'requestable_type' => AssetModel::class,
                'canceled_at' => null,
            ],
            [
                'quantity' => 2,
                'fulfilled_at' => null,
            ]
        );

        $betaRequest = CheckoutRequest::withoutGlobalScopes()->updateOrCreate(
            [
                'user_id' => $betaManager->id,
                'requestable_id' => $alphaModelB->id,
                'requestable_type' => AssetModel::class,
                'canceled_at' => null,
            ],
            [
                'quantity' => 1,
                'fulfilled_at' => null,
            ]
        );

        ResolveCheckoutRequestCoordinatorsAction::run($alphaRequest);
        ResolveCheckoutRequestCoordinatorsAction::run($betaRequest);

        $this->command?->info('Manual category manager QA dataset is ready.');
        $this->command?->line('Admin: qa-category-admin / password');
        $this->command?->line('AFM Alpha: qa-category-alpha / password');
        $this->command?->line('AFM Beta: qa-category-beta / password');
        $this->command?->line('RAC Casablanca: qa-category-coordinator-casa / password');
        $this->command?->line('RAC Rabat: qa-category-coordinator-rabat / password');
        $this->command?->line('Alpha manager categories: QA Category Family Alpha, QA Category Family Bravo');
        $this->command?->line('Beta manager category: QA Category Family Delta');
        $this->command?->line('Unassigned control: QA Category Family Unassigned');
        $this->command?->line('Expected Alpha counts: 2 available models, 3 remaining assets');
        $this->command?->line('Expected Bravo counts: 0 available models, 0 remaining assets');
        $this->command?->line('AFM request demo models: QA Alpha Model A, QA Alpha Model B');
        $this->command?->line('Routing companies: QA Casablanca Site, QA Rabat Site');
        $this->command?->line('Routing discipline on eligible assets: QA Power Discipline');
        $this->command?->line('Coordinator queue: 2 preseeded model requests resolved from eligible asset company + discipline');
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
        ?int $disciplineId,
        int $createdBy,
        ?int $assignedTo = null,
        ?string $assignedType = null,
        bool $requestable = false
    ): Asset {
        $asset = Asset::withoutGlobalScopes()->firstOrNew(['asset_tag' => $assetTag]);
        $asset->name = $name;
        $asset->asset_tag = $assetTag;
        $asset->model_id = $modelId;
        $asset->status_id = $statusId;
        $asset->company_id = $companyId;
        $asset->discipline_id = $disciplineId;
        $asset->created_by = $createdBy;
        $asset->assigned_to = $assignedTo;
        $asset->assigned_type = $assignedType;
        $asset->requestable = $requestable;
        $asset->notes = 'Deterministic asset for category manager QA.';
        $asset->save();

        return $asset;
    }
}

<?php

namespace Database\Seeders;

use App\Actions\CheckoutRequests\ResolveCheckoutRequestCoordinatorsAction;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\CheckoutRequest;
use App\Models\Company;
use App\Models\CustomField;
use App\Models\CustomFieldset;
use App\Models\Discipline;
use App\Models\Location;
use App\Models\Project;
use App\Models\RegionalAssetCoordinatorAssignment;
use App\Models\Setting;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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
                'permissions' => json_encode([]),
                'password' => bcrypt('password'),
                'notes' => 'Deterministic assignment-derived AFM for category scope validation.',
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
                'permissions' => json_encode([]),
                'password' => bcrypt('password'),
                'notes' => 'Deterministic assignment-derived AFM for manager scope validation.',
                'created_by' => $admin->id,
            ]
        );

        $eplRequester = User::withoutGlobalScopes()->updateOrCreate(
            ['username' => 'qa-epl-requester'],
            [
                'first_name' => 'QA',
                'last_name' => 'EPL Requester',
                'display_name' => 'QA EPL Requester',
                'email' => 'qa-epl-requester@example.com',
                'activated' => 1,
                'company_id' => null,
                'locale' => 'en-US',
                'permissions' => json_encode([
                    'models.request' => '1',
                ]),
                'password' => bcrypt('password'),
                'notes' => 'Deterministic EPL requester for reusable inventory request workflow QA.',
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
                    'assets.checkout' => '1',
                    'assets.checkin' => '1',
                    'assets.edit' => '1',
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
                    'assets.checkout' => '1',
                    'assets.checkin' => '1',
                    'assets.edit' => '1',
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

        $casablancaLocation = Location::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'QA Casablanca Reuse Store'],
            [
                'company_id' => $casablancaCompany->id,
                'city' => 'Casablanca',
                'country' => 'Morocco',
                'created_by' => $admin->id,
                'notes' => 'Source location for the deterministic FMCS transfer walkthrough.',
            ]
        );

        $rabatLocation = Location::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'QA Rabat Receiving Store'],
            [
                'company_id' => $rabatCompany->id,
                'city' => 'Rabat',
                'country' => 'Morocco',
                'created_by' => $admin->id,
                'notes' => 'Destination location for the deterministic FMCS transfer walkthrough.',
            ]
        );

        $eplRequester->forceFill([
            'company_id' => $rabatCompany->id,
            'location_id' => $rabatLocation->id,
        ])->save();
        $casablancaCoordinator->forceFill([
            'company_id' => $casablancaCompany->id,
            'location_id' => $casablancaLocation->id,
        ])->save();
        $rabatCoordinator->forceFill([
            'company_id' => $rabatCompany->id,
            'location_id' => $rabatLocation->id,
        ])->save();

        if ($settings = Setting::getSettings()) {
            $settings->forceFill([
                'full_multiple_companies_support' => 1,
                'scope_locations_fmcs' => 1,
            ])->save();
            Setting::$_cache = $settings->fresh();
        }

        $alphaProject = Project::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'QA Alpha Project'],
            ['created_by' => $eplRequester->id]
        );

        $betaProject = Project::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'QA Beta Project'],
            ['created_by' => $eplRequester->id]
        );

        $crossSiteProject = Project::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'QA Cross-Site Project'],
            ['created_by' => $eplRequester->id]
        );

        $powerDisciplineId = DB::table('disciplines')->where('name', 'QA Power Discipline')->value('id');

        if (!$powerDisciplineId) {
            $powerDisciplineId = DB::table('disciplines')->insertGetId([
                'name' => 'QA Power Discipline',
                'notes' => 'Discipline used to resolve RAC candidates from company stock.',
                'created_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $powerDiscipline = Discipline::withoutGlobalScopes()->findOrFail($powerDisciplineId);

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

        Statuslabel::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'In Transfer'],
            [
                'deployable' => 0,
                'pending' => 1,
                'archived' => 0,
                'default_label' => 0,
                'created_by' => $admin->id,
                'notes' => 'Asset is moving between company scopes for a request-linked site transfer.',
            ]
        );

        $alphaFieldset = CustomFieldset::updateOrCreate(
            ['name' => 'QA Alpha Technical Details'],
            ['created_by' => $admin->id]
        );
        $operatingVoltageField = CustomField::updateOrCreate(
            ['name' => 'QA Operating Voltage'],
            [
                'element' => 'text',
                'format' => '',
                'help_text' => 'Nominal operating voltage inherited from the category fieldset.',
                'created_by' => $admin->id,
            ]
        );
        $alphaFieldset->fields()->syncWithoutDetaching([
            $operatingVoltageField->id => ['order' => 1, 'required' => false],
        ]);

        $alphaCategory = $this->upsertCategory(
            name: 'QA Category Family Alpha',
            type: 'asset',
            managerId: $alphaManager->id,
            createdBy: $admin->id,
            notes: 'Managed by Alpha Manager and visible in the AFM assigned-family scope.',
            fieldsetId: $alphaFieldset->id
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
        $alphaModelA->defaultValues()->syncWithoutDetaching([
            $operatingVoltageField->id => ['default_value' => '24 VDC'],
        ]);

        $alphaModelB = $this->upsertModel(
            name: 'QA Alpha Model B',
            categoryId: $alphaCategory->id,
            createdBy: $admin->id
        );

        $crossSiteModel = $this->upsertModel(
            name: 'QA Cross-Site Model',
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
            assetTag: 'QA-CAT-CROSS-001',
            name: 'QA Cross-Site Casa RTD 1',
            modelId: $crossSiteModel->id,
            statusId: $readyStatus->id,
            companyId: $casablancaCompany->id,
            disciplineId: $powerDiscipline->id,
            createdBy: $admin->id,
            requestable: true,
            locationId: $casablancaLocation->id,
            defaultLocationId: $casablancaLocation->id
        );

        $this->upsertAsset(
            assetTag: 'QA-CAT-CROSS-002',
            name: 'QA Cross-Site Rabat RTD 1',
            modelId: $crossSiteModel->id,
            statusId: $readyStatus->id,
            companyId: $rabatCompany->id,
            disciplineId: $powerDiscipline->id,
            createdBy: $admin->id,
            requestable: true,
            locationId: $rabatLocation->id,
            defaultLocationId: $rabatLocation->id
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
                'requestable_id' => $alphaModelA->id,
                'requestable_type' => AssetModel::class,
                'project_id' => $alphaProject->id,
                'canceled_at' => null,
            ],
            [
                'user_id' => $eplRequester->id,
                'quantity' => 2,
                'company_id' => $rabatCompany->id,
                'requested_discipline_id' => $powerDiscipline->id,
                'needed_by_date' => now()->addDays(30)->toDateString(),
                'fulfilled_at' => null,
            ]
        );

        $betaRequest = CheckoutRequest::withoutGlobalScopes()->updateOrCreate(
            [
                'requestable_id' => $alphaModelB->id,
                'requestable_type' => AssetModel::class,
                'project_id' => $betaProject->id,
                'canceled_at' => null,
            ],
            [
                'user_id' => $eplRequester->id,
                'quantity' => 1,
                'company_id' => $rabatCompany->id,
                'requested_discipline_id' => $powerDiscipline->id,
                'needed_by_date' => now()->addDays(30)->toDateString(),
                'fulfilled_at' => null,
            ]
        );

        $crossSiteRequest = CheckoutRequest::withoutGlobalScopes()->updateOrCreate(
            [
                'requestable_id' => $crossSiteModel->id,
                'requestable_type' => AssetModel::class,
                'project_id' => $crossSiteProject->id,
                'canceled_at' => null,
            ],
            [
                'user_id' => $eplRequester->id,
                'quantity' => 1,
                'company_id' => $rabatCompany->id,
                'requested_discipline_id' => $powerDiscipline->id,
                'needed_by_date' => now()->addDays(30)->toDateString(),
                'status' => CheckoutRequest::STATUS_PENDING,
                'fulfilled_at' => null,
                'note' => 'Deterministic cross-company transfer walkthrough: Casablanca source to Rabat destination.',
            ]
        );

        foreach ([$alphaRequest, $betaRequest, $crossSiteRequest] as $request) {
            $request->allocatedAssets()->detach();
            $request->coordinatorTargets()->delete();
            $request->status = CheckoutRequest::STATUS_PENDING;
            $request->fulfilled_at = null;
            $request->alternative_follow_up_notified_at = null;
            $request->rac_routing_alerted_at = null;
            $request->save();
        }

        ResolveCheckoutRequestCoordinatorsAction::run($alphaRequest);
        ResolveCheckoutRequestCoordinatorsAction::run($betaRequest);
        ResolveCheckoutRequestCoordinatorsAction::run($crossSiteRequest);

        $this->command?->info('Manual category manager QA dataset is ready.');
        $this->command?->line('Admin: qa-category-admin / password');
        $this->command?->line('AFM Alpha: qa-category-alpha / password');
        $this->command?->line('AFM Beta: qa-category-beta / password');
        $this->command?->line('EPL requester: qa-epl-requester / password');
        $this->command?->line('RAC Casablanca: qa-category-coordinator-casa / password');
        $this->command?->line('RAC Rabat: qa-category-coordinator-rabat / password');
        $this->command?->line('Alpha manager categories: QA Category Family Alpha, QA Category Family Bravo');
        $this->command?->line('Beta manager category: QA Category Family Delta');
        $this->command?->line('Unassigned control: QA Category Family Unassigned');
        $this->command?->line('Expected Alpha counts: 2 available models, 3 remaining assets');
        $this->command?->line('Expected Bravo counts: 0 available models, 0 remaining assets');
        $this->command?->line('EPL request demo models: QA Alpha Model A, QA Alpha Model B');
        $this->command?->line('Inherited fieldset demo: QA Alpha Technical Details on QA Category Family Alpha');
        $this->command?->line('Cross-site request demo model: QA Cross-Site Model');
        $this->command?->line('Cross-site request ID: #'.$crossSiteRequest->id);
        $this->command?->line('Cross-site source asset: QA-CAT-CROSS-001 (QA Casablanca Site)');
        $this->command?->line('Cross-site destination/control asset: QA-CAT-CROSS-002 (QA Rabat Site)');
        $this->command?->line('Transfer status: In Transfer (pending/non-deployable)');
        $this->command?->line('FMCS and FMCS location scoping: enabled');
        $this->command?->line('Source location: QA Casablanca Reuse Store');
        $this->command?->line('Destination location: QA Rabat Receiving Store');
        $this->command?->line('Seeded request projects: QA Alpha Project, QA Beta Project, QA Cross-Site Project');
        $this->command?->line('Routing companies: QA Casablanca Site, QA Rabat Site');
        $this->command?->line('Routing discipline on eligible assets: QA Power Discipline');
        $this->command?->line('Notifications target the routed RACs, while the EPL tracks booked counts through the request project.');
    }

    private function upsertCategory(
        string $name,
        string $type,
        ?int $managerId,
        int $createdBy,
        string $notes,
        ?int $fieldsetId = null
    ): Category {
        return Category::withoutGlobalScopes()->updateOrCreate(
            ['name' => $name, 'category_type' => $type],
            [
                'created_by' => $createdBy,
                'manager_id' => $managerId,
                'fieldset_id' => $fieldsetId,
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
        bool $requestable = false,
        ?int $locationId = null,
        ?int $defaultLocationId = null
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
        $asset->location_id = $locationId;
        $asset->rtd_location_id = $defaultLocationId;
        $asset->requestable = $requestable;
        $asset->notes = 'Deterministic asset for category manager QA.';
        $asset->save();

        return $asset;
    }
}

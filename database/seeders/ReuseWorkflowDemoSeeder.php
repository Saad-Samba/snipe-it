<?php

namespace Database\Seeders;

use App\Actions\CheckoutRequests\EstimateAssetModelReuseAction;
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
use App\Models\Manufacturer;
use App\Models\Project;
use App\Models\RegionalAssetCoordinatorAssignment;
use App\Models\Setting;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ReuseWorkflowDemoSeeder extends Seeder
{
    private const PASSWORD = 'password';

    private const ASSET_TAGS = [
        'DEMO-RF-VN-001',
        'DEMO-RF-VN-002',
        'DEMO-RF-VN-003',
        'DEMO-RF-VN-004',
        'DEMO-RF-PCAN-001',
        'DEMO-RF-SCOPE-001',
        'DEMO-RF-SCOPE-002',
        'DEMO-RF-KEYSCOPE-001',
        'DEMO-RF-JLINK-001',
        'DEMO-RF-TRACE32-001',
        'DEMO-RF-PSU-001',
        'DEMO-RF-PSU-002',
        'DEMO-RF-EAPSU-001',
    ];

    public function run(): void
    {
        $users = $this->seedUsers();
        $admin = $users['admin'];

        $statuses = $this->seedStatuses($admin);
        $this->seedSettings($statuses['reserved']);

        $companies = $this->seedCompanies($admin);
        $locations = $this->seedLocations($companies, $admin);
        $disciplines = $this->seedDisciplines($admin);

        $this->assignUserScopes($users, $companies, $locations);

        $fieldsets = $this->seedFieldsets($admin);
        $categories = $this->seedCategories($users, $fieldsets, $admin);
        $manufacturers = $this->seedManufacturers();
        $models = $this->seedModels($categories, $manufacturers, $admin);

        $projects = $this->seedProjects($users['epl']);
        $this->seedRacAssignments($users, $companies, $disciplines, $admin);
        $this->seedAssets(
            $users,
            $companies,
            $locations,
            $disciplines,
            $statuses,
            $models,
            $admin
        );
        $this->seedPreparedTransferRequest($users, $companies, $projects, $models);
        $this->assertDemoAssetsExist();
        $this->assertAfmCategoryCoverage($categories);

        $this->printManifest();
    }

    private function seedUsers(): array
    {
        $admin = $this->upsertUser(
            username: 'demo-GSA',
            firstName: 'Demo',
            lastName: 'GSA',
            email: 'demo-gsa@example.com',
            permissions: ['superuser' => '1'],
            createdBy: null,
            legacyUsername: 'demo-reuse-admin'
        );

        return [
            'admin' => $admin,
            'epl' => $this->upsertUser(
                'demo-EPM',
                'Demo',
                'EPM',
                'demo-epm@example.com',
                ['models.request' => '1'],
                $admin->id,
                'demo-reuse-epl'
            ),
            'afm_network' => $this->upsertUser(
                'demo-AFM-COMMUNICATION',
                'Demo',
                'AFM Communication',
                'demo-afm-communication@example.com',
                $this->afmPermissionOverrides(),
                $admin->id,
                'demo-reuse-afm-network'
            ),
            'afm_lab' => $this->upsertUser(
                'demo-AFM-LAB',
                'Demo',
                'AFM Lab',
                'demo-afm-lab@example.com',
                $this->afmPermissionOverrides(),
                $admin->id,
                'demo-reuse-afm-lab'
            ),
            'rac_casablanca' => $this->upsertUser(
                'demo-RAC-CASABLANCA',
                'Demo',
                'RAC Casablanca',
                'demo-rac-casablanca@example.com',
                $this->racPermissions(),
                $admin->id,
                'demo-reuse-rac-casablanca'
            ),
            'rac_rabat' => $this->upsertUser(
                'demo-RAC-RABAT',
                'Demo',
                'RAC Rabat',
                'demo-rac-rabat@example.com',
                $this->racPermissions(),
                $admin->id,
                'demo-reuse-rac-rabat'
            ),
            'engineer' => $this->upsertUser(
                'demo-REQUESTOR',
                'Demo',
                'Requestor',
                'demo-requestor@example.com',
                ['assets.view.requestable' => '1'],
                $admin->id,
                'demo-reuse-engineer'
            ),
        ];
    }

    private function upsertUser(
        string $username,
        string $firstName,
        string $lastName,
        string $email,
        array $permissions,
        ?int $createdBy,
        ?string $legacyUsername = null
    ): User {
        $user = User::withoutGlobalScopes()->where('username', $username)->first()
            ?? ($legacyUsername
                ? User::withoutGlobalScopes()->where('username', $legacyUsername)->first()
                : null)
            ?? new User();

        $user->forceFill([
            'username' => $username,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => $username,
            'email' => $email,
            'activated' => 1,
            'company_id' => null,
            'location_id' => null,
            'locale' => 'en-US',
            'permissions' => json_encode($permissions),
            'password' => Hash::make(self::PASSWORD),
            'notes' => 'Isolated environment account created by ReuseWorkflowDemoSeeder.',
            'created_by' => $createdBy,
        ]);

        if (! $user->save()) {
            throw new \RuntimeException(sprintf(
                'Unable to seed demo user %s: %s',
                $username,
                implode('; ', $user->getErrors()->all())
            ));
        }

        return $user;
    }

    private function racPermissions(): array
    {
        return [
            'reports.view' => '1',
            'assets.view' => '1',
            'assets.edit' => '1',
            'assets.checkin' => '1',
            'assets.checkout' => '1',
            'assets.audit' => '1',
            'users.view' => '1',
            'models.view' => '1',
            'categories.view' => '1',
            'departments.view' => '1',
            'statuslabels.view' => '1',
            'customfields.view' => '1',
            'locations.view' => '1',
        ];
    }

    private function afmPermissionOverrides(): array
    {
        // Category-manager assignment derives the least-privilege AFM
        // permission set. Keep explicit denials for sensitive capabilities
        // that are deliberately outside the role.
        return [
            'users.view' => '-1',
            'depreciations.view' => '-1',
            'depreciations.create' => '-1',
            'depreciations.edit' => '-1',
        ];
    }

    private function seedStatuses(User $admin): array
    {
        return [
            'ready' => $this->upsertStatus('Ready to Deploy', true, false, false, $admin),
            'transfer' => $this->upsertStatus('In Transfer', false, true, false, $admin),
            'reserved' => $this->upsertStatus('Reserved for RFQ', false, true, false, $admin),
            'archived' => $this->upsertStatus('Archived', false, false, true, $admin),
            'repair' => $this->upsertStatus('Out for Repair', false, false, false, $admin),
        ];
    }

    private function upsertStatus(
        string $name,
        bool $deployable,
        bool $pending,
        bool $archived,
        User $admin
    ): Statuslabel {
        return Statuslabel::withoutGlobalScopes()->updateOrCreate(
            ['name' => $name],
            [
                'deployable' => $deployable,
                'pending' => $pending,
                'archived' => $archived,
                'default_label' => $name === 'Ready to Deploy',
                'created_by' => $admin->id,
                'notes' => 'Status prepared for the reuse-first demo.',
            ]
        );
    }

    private function seedSettings(Statuslabel $reservedStatus): void
    {
        $settings = Setting::query()->firstOrNew();
        $settings->forceFill([
            'site_name' => 'LEAMS Reuse Workflow Demo',
            'per_page' => 25,
            'auto_increment_assets' => 0,
            'alert_email' => '',
            'alerts_enabled' => 0,
            'brand' => 2,
            'locale' => 'en-US',
            'default_currency' => 'EUR',
            'full_multiple_companies_support' => 1,
            'scope_locations_fmcs' => 1,
            'rfq_reserved_statuslabel_id' => $reservedStatus->id,
        ]);
        $settings->save();
        Setting::$_cache = $settings->fresh();
    }

    private function seedCompanies(User $admin): array
    {
        return [
            'casablanca' => $this->upsertCompany('LEAR Electronics Casablanca', $admin),
            'rabat' => $this->upsertCompany('LEAR Electronics Rabat', $admin),
            'tangier' => $this->upsertCompany('LEAR Electronics Tangier', $admin),
        ];
    }

    private function upsertCompany(string $name, User $admin): Company
    {
        return Company::withoutGlobalScopes()->updateOrCreate(
            ['name' => $name],
            ['created_by' => $admin->id]
        );
    }

    private function seedLocations(array $companies, User $admin): array
    {
        return [
            'casablanca' => $this->upsertLocation(
                'Casablanca Electronics Reuse Store',
                'Casablanca',
                $companies['casablanca'],
                $admin
            ),
            'rabat' => $this->upsertLocation(
                'Rabat Electronics Lab',
                'Rabat',
                $companies['rabat'],
                $admin
            ),
            'tangier' => $this->upsertLocation(
                'Tangier Validation Store',
                'Tangier',
                $companies['tangier'],
                $admin
            ),
        ];
    }

    private function upsertLocation(string $name, string $city, Company $company, User $admin): Location
    {
        return Location::withoutGlobalScopes()->updateOrCreate(
            ['name' => $name],
            [
                'city' => $city,
                'country' => 'Morocco',
                'company_id' => $company->id,
                'created_by' => $admin->id,
                'notes' => 'Location prepared for the reuse-first demo.',
            ]
        );
    }

    private function seedDisciplines(User $admin): array
    {
        return [
            'validation' => $this->upsertDiscipline('HARDWARE', 'Electronics Validation', $admin),
            'embedded' => $this->upsertDiscipline('SOFTWARE', 'Embedded Systems', $admin),
            'power' => $this->upsertDiscipline('SYSTEMS', 'Power Electronics', $admin),
        ];
    }

    private function upsertDiscipline(string $name, string $legacyName, User $admin): Discipline
    {
        $discipline = Discipline::withoutGlobalScopes()->where('name', $name)->first()
            ?? Discipline::withoutGlobalScopes()->where('name', $legacyName)->first()
            ?? new Discipline();

        $discipline->forceFill([
            'name' => $name,
            'notes' => 'Engineering discipline prepared for the reuse-first demo.',
            'created_by' => $admin->id,
        ]);

        if (! $discipline->save()) {
            throw new \RuntimeException(sprintf(
                'Unable to seed demo discipline %s: %s',
                $name,
                implode('; ', $discipline->getErrors()->all())
            ));
        }

        return $discipline;
    }

    private function assignUserScopes(array $users, array $companies, array $locations): void
    {
        $scopes = [
            'epl' => ['rabat', 'rabat'],
            'rac_casablanca' => ['casablanca', 'casablanca'],
            'rac_rabat' => ['rabat', 'rabat'],
            'engineer' => ['rabat', 'rabat'],
        ];

        foreach ($scopes as $userKey => [$companyKey, $locationKey]) {
            $users[$userKey]->forceFill([
                'company_id' => $companies[$companyKey]->id,
                'location_id' => $locations[$locationKey]->id,
            ])->save();
        }
    }

    private function seedFieldsets(User $admin): array
    {
        $network = CustomFieldset::updateOrCreate(
            ['name' => 'Industrial Network Equipment'],
            ['created_by' => $admin->id]
        );
        $automation = CustomFieldset::updateOrCreate(
            ['name' => 'Automation and Control Equipment'],
            ['created_by' => $admin->id]
        );

        $network->fields()->sync($this->fieldPivotIds([
            'Network Speed',
            'Port Count',
            'Media Type',
            'Industrial Protocol',
            'Management Type',
            'IP Rating',
            'Operating Voltage',
            'Firmware Version',
        ], $admin));

        $automation->fields()->sync($this->fieldPivotIds([
            'Device Type',
            'Control Platform',
            'Communication Protocol',
            'Digital I/O Count',
            'Analog I/O Count',
            'Safety Rated',
            'Safety Level',
            'Firmware Version',
            'Program Backup Location',
        ], $admin));

        return compact('network', 'automation');
    }

    private function fieldPivotIds(array $names, User $admin): array
    {
        $pivots = [];
        foreach ($names as $index => $name) {
            $field = CustomField::updateOrCreate(
                ['name' => $name],
                [
                    'element' => 'text',
                    'format' => '',
                    'help_text' => 'Governed technical attribute for the reuse workflow demo.',
                    'created_by' => $admin->id,
                ]
            );
            $pivots[$field->id] = ['order' => $index + 1, 'required' => false];
        }

        return $pivots;
    }

    private function seedCategories(array $users, array $fieldsets, User $admin): array
    {
        return [
            'network' => $this->upsertCategory(
                'Communication',
                $users['afm_network'],
                $fieldsets['network'],
                $admin
            ),
            'lab' => $this->upsertCategory(
                'Test and Measurement Equipment',
                $users['afm_lab'],
                null,
                $admin
            ),
            'embedded' => $this->upsertCategory(
                'Embedded Development Tools',
                $users['afm_network'],
                $fieldsets['automation'],
                $admin
            ),
            'power' => $this->upsertCategory(
                'Power Electronics Lab Equipment',
                $users['afm_lab'],
                $fieldsets['automation'],
                $admin
            ),
        ];
    }

    private function upsertCategory(
        string $name,
        User $manager,
        ?CustomFieldset $fieldset,
        User $admin
    ): Category {
        return Category::withoutGlobalScopes()->updateOrCreate(
            ['name' => $name, 'category_type' => 'asset'],
            [
                'manager_id' => $manager->id,
                'fieldset_id' => $fieldset?->id,
                'checkin_email' => 0,
                'require_acceptance' => 0,
                'use_default_eula' => 0,
                'created_by' => $admin->id,
                'notes' => 'LEAR Electronics asset family prepared for the reuse-first demo.',
            ]
        );
    }

    private function seedManufacturers(): array
    {
        return [
            'vector' => Manufacturer::updateOrCreate(['name' => 'Vector Informatik']),
            'tektronix' => Manufacturer::updateOrCreate(['name' => 'Tektronix']),
            'segger' => Manufacturer::updateOrCreate(['name' => 'SEGGER']),
            'keysight' => Manufacturer::updateOrCreate(['name' => 'Keysight Technologies']),
            'peak' => Manufacturer::updateOrCreate(['name' => 'PEAK-System Technik']),
            'lauterbach' => Manufacturer::updateOrCreate(['name' => 'Lauterbach']),
            'ea' => Manufacturer::updateOrCreate(['name' => 'EA Elektro-Automatik']),
        ];
    }

    private function seedModels(array $categories, array $manufacturers, User $admin): array
    {
        return [
            'network_interface' => $this->upsertModel(
                'Vector VN1630A CAN/LIN Interface',
                'VN1630A',
                1850.00,
                $categories['network'],
                $manufacturers['vector'],
                $admin
            ),
            'pcan_interface' => $this->upsertModel(
                'PEAK PCAN-USB FD Interface',
                'IPEH-004022',
                490.00,
                $categories['network'],
                $manufacturers['peak'],
                $admin
            ),
            'oscilloscope' => $this->upsertModel(
                'Tektronix MDO3024 Oscilloscope',
                'MDO3024',
                6900.00,
                $categories['lab'],
                $manufacturers['tektronix'],
                $admin
            ),
            'keysight_scope' => $this->upsertModel(
                'Keysight DSOX1204G Oscilloscope',
                'DSOX1204G',
                2400.00,
                $categories['lab'],
                $manufacturers['keysight'],
                $admin
            ),
            'debug_probe' => $this->upsertModel(
                'SEGGER J-Link PRO Debug Probe',
                'J-LINK-PRO',
                1250.00,
                $categories['embedded'],
                $manufacturers['segger'],
                $admin
            ),
            'trace32_probe' => $this->upsertModel(
                'Lauterbach TRACE32 Debug Probe',
                'LA-3500',
                4200.00,
                $categories['embedded'],
                $manufacturers['lauterbach'],
                $admin
            ),
            'power_supply' => $this->upsertModel(
                'Keysight E36313A DC Power Supply',
                'E36313A',
                3100.00,
                $categories['power'],
                $manufacturers['keysight'],
                $admin
            ),
            'ea_power_supply' => $this->upsertModel(
                'EA-PS 9080-60 DC Power Supply',
                'EA-PS-9080-60',
                3600.00,
                $categories['power'],
                $manufacturers['ea'],
                $admin
            ),
        ];
    }

    private function upsertModel(
        string $name,
        string $modelNumber,
        float $referencePrice,
        Category $category,
        Manufacturer $manufacturer,
        User $admin
    ): AssetModel {
        return AssetModel::withoutGlobalScopes()->updateOrCreate(
            ['name' => $name],
            [
                'model_number' => $modelNumber,
                'category_id' => $category->id,
                'manufacturer_id' => $manufacturer->id,
                'reference_price' => $referencePrice,
                'require_serial' => 1,
                'obsolete' => 0,
                'fieldset_id' => null,
                'created_by' => $admin->id,
                'notes' => 'Model prepared for the LEAR Electronics reuse-first demo.',
            ]
        );
    }

    private function seedProjects(User $epl): array
    {
        $live = Project::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'DEMO - Infotainment ECU Bench Expansion'],
            [
                'notes' => 'Primary live request project for the reuse-first demonstration.',
                'created_by' => $epl->id,
            ]
        );

        $transfer = Project::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'DEMO - Cross-Site Debug Bench Transfer'],
            [
                'notes' => 'Prepared cross-company transfer request for the reuse-first demonstration.',
                'created_by' => $epl->id,
            ]
        );

        $secondary = Project::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'DEMO - Power Electronics Validation Cell'],
            [
                'notes' => 'Secondary project for filters and follow-up demonstrations.',
                'created_by' => $epl->id,
            ]
        );

        return compact('live', 'transfer', 'secondary');
    }

    private function seedRacAssignments(array $users, array $companies, array $disciplines, User $admin): void
    {
        $assignments = [
            [$users['rac_casablanca'], $companies['casablanca'], $disciplines['validation']],
            [$users['rac_casablanca'], $companies['casablanca'], $disciplines['embedded']],
            [$users['rac_rabat'], $companies['rabat'], $disciplines['validation']],
            [$users['rac_rabat'], $companies['rabat'], $disciplines['power']],
        ];

        foreach ($assignments as [$coordinator, $company, $discipline]) {
            RegionalAssetCoordinatorAssignment::withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $company->id, 'discipline_id' => $discipline->id],
                ['user_id' => $coordinator->id, 'created_by' => $admin->id]
            );
        }

        // Intentionally do not assign Rabat / SOFTWARE. The demo
        // administrator can add demo-RAC-RABAT and reconcile the request.
        RegionalAssetCoordinatorAssignment::withoutGlobalScopes()
            ->where('company_id', $companies['rabat']->id)
            ->where('discipline_id', $disciplines['embedded']->id)
            ->delete();
    }

    private function seedAssets(
        array $users,
        array $companies,
        array $locations,
        array $disciplines,
        array $statuses,
        array $models,
        User $admin
    ): void {
        $this->upsertAsset('DEMO-RF-VN-001', 'VN1630A - Rabat Hardware', $models['network_interface'], $statuses['ready'], $companies['rabat'], $locations['rabat'], $disciplines['validation'], $admin);
        $this->upsertAsset('DEMO-RF-VN-002', 'VN1630A - Rabat Systems', $models['network_interface'], $statuses['ready'], $companies['rabat'], $locations['rabat'], $disciplines['power'], $admin);
        $this->upsertAsset('DEMO-RF-VN-003', 'VN1630A - Rabat Uncovered Software Scope', $models['network_interface'], $statuses['ready'], $companies['rabat'], $locations['rabat'], $disciplines['embedded'], $admin);
        $this->upsertAsset('DEMO-RF-VN-004', 'VN1630A - Due Back Before Need', $models['network_interface'], $statuses['ready'], $companies['rabat'], $locations['rabat'], $disciplines['validation'], $admin, $users['engineer'], '2026-09-15');
        $this->upsertAsset('DEMO-RF-PCAN-001', 'PCAN-USB FD - Casablanca Hardware', $models['pcan_interface'], $statuses['ready'], $companies['casablanca'], $locations['casablanca'], $disciplines['validation'], $admin);

        $this->upsertAsset('DEMO-RF-SCOPE-001', 'MDO3024 - Rabat Systems', $models['oscilloscope'], $statuses['ready'], $companies['rabat'], $locations['rabat'], $disciplines['power'], $admin);
        $this->upsertAsset('DEMO-RF-SCOPE-002', 'MDO3024 - Archived Control', $models['oscilloscope'], $statuses['archived'], $companies['rabat'], $locations['rabat'], $disciplines['power'], $admin);
        $this->upsertAsset('DEMO-RF-KEYSCOPE-001', 'DSOX1204G - Rabat Systems', $models['keysight_scope'], $statuses['ready'], $companies['rabat'], $locations['rabat'], $disciplines['power'], $admin);

        $this->upsertAsset('DEMO-RF-JLINK-001', 'J-Link PRO - Available', $models['debug_probe'], $statuses['ready'], $companies['casablanca'], $locations['casablanca'], $disciplines['embedded'], $admin);
        $this->upsertAsset('DEMO-RF-TRACE32-001', 'TRACE32 - Casablanca Software', $models['trace32_probe'], $statuses['ready'], $companies['casablanca'], $locations['casablanca'], $disciplines['embedded'], $admin);
        $this->upsertAsset('DEMO-RF-PSU-001', 'E36313A - Available', $models['power_supply'], $statuses['ready'], $companies['rabat'], $locations['rabat'], $disciplines['power'], $admin);
        $this->upsertAsset('DEMO-RF-PSU-002', 'E36313A - Out for Repair Control', $models['power_supply'], $statuses['repair'], $companies['rabat'], $locations['rabat'], $disciplines['power'], $admin);
        $this->upsertAsset('DEMO-RF-EAPSU-001', 'EA-PS 9080-60 - Rabat Systems', $models['ea_power_supply'], $statuses['ready'], $companies['rabat'], $locations['rabat'], $disciplines['power'], $admin);
    }

    private function seedPreparedTransferRequest(
        array $users,
        array $companies,
        array $projects,
        array $models
    ): void {
        $estimate = array_intersect_key(
            EstimateAssetModelReuseAction::run(
                $models['debug_probe'],
                1,
                '2026-09-30'
            ),
            array_flip([
                'reusable_quantity',
                'due_back_before_needed_by_quantity',
                'potentially_coverable_quantity',
                'procurement_shortfall',
                'estimated_savings',
                'reference_price_snapshot',
            ])
        );

        $request = CheckoutRequest::withoutGlobalScopes()->updateOrCreate(
            [
                'requestable_id' => $models['debug_probe']->id,
                'requestable_type' => AssetModel::class,
                'project_id' => $projects['transfer']->id,
                'user_id' => $users['epl']->id,
            ],
            array_merge($estimate, [
                'quantity' => 1,
                'company_id' => $companies['rabat']->id,
                'needed_by_date' => '2026-09-30',
                'status' => CheckoutRequest::STATUS_PENDING,
                'fulfilled_at' => null,
                'canceled_at' => null,
                'note' => 'Prepared Casablanca-to-Rabat transfer scenario.',
            ])
        );

        $request->allocatedAssets()->detach();
        $request->coordinatorTargets()->delete();
        $request->forceFill([
            'rac_routing_status' => null,
            'rac_unrouted_scopes' => null,
            'rac_routing_alerted_at' => null,
            'alternative_follow_up_notified_at' => null,
        ])->save();

        ResolveCheckoutRequestCoordinatorsAction::run($request, false);
    }

    private function upsertAsset(
        string $tag,
        string $name,
        AssetModel $model,
        Statuslabel $status,
        Company $company,
        Location $location,
        Discipline $discipline,
        User $admin,
        ?User $assignedTo = null,
        ?string $expectedCheckin = null
    ): Asset {
        $asset = Asset::withoutGlobalScopes()->firstOrNew(['asset_tag' => $tag]);
        $asset->forceFill([
            'asset_tag' => $tag,
            'serial' => str_replace('DEMO-RF-', 'SN-RF-', $tag),
            'name' => $name,
            'model_id' => $model->id,
            'status_id' => $status->id,
            'company_id' => $company->id,
            'location_id' => $location->id,
            'rtd_location_id' => $location->id,
            'discipline_id' => $discipline->id,
            'created_by' => $admin->id,
            'assigned_to' => $assignedTo?->id,
            'assigned_type' => $assignedTo ? User::class : null,
            'expected_checkin' => $expectedCheckin,
            'requestable' => 1,
            'notes' => 'Deterministic asset created by ReuseWorkflowDemoSeeder.',
        ]);
        if (! $asset->save()) {
            throw new \RuntimeException(sprintf(
                'Unable to seed demo asset %s: %s',
                $tag,
                implode('; ', $asset->getErrors()->all())
            ));
        }

        return $asset;
    }

    private function assertDemoAssetsExist(): void
    {
        $seededTags = Asset::withoutGlobalScopes()
            ->whereIn('asset_tag', self::ASSET_TAGS)
            ->pluck('asset_tag');
        $missingTags = collect(self::ASSET_TAGS)->diff($seededTags);

        if ($missingTags->isNotEmpty()) {
            throw new \RuntimeException(
                'Reuse workflow demo seeding is incomplete. Missing asset tags: '.$missingTags->implode(', ')
            );
        }
    }

    private function assertAfmCategoryCoverage(array $categories): void
    {
        foreach ($categories as $category) {
            $modelCount = AssetModel::withoutGlobalScopes()
                ->where('category_id', $category->id)
                ->count();
            $assetCount = Asset::withoutGlobalScopes()
                ->whereHas('model', fn ($query) => $query->where('category_id', $category->id))
                ->count();

            if ($modelCount < 2 || $assetCount < 2) {
                throw new \RuntimeException(sprintf(
                    'AFM demo category %s needs at least two models and two assets; found %d models and %d assets.',
                    $category->name,
                    $modelCount,
                    $assetCount
                ));
            }
        }
    }

    private function printManifest(): void
    {
        $this->command?->info('Reuse-first demo dataset is ready.');
        $this->command?->line('Run again safely with: php artisan db:seed --class=ReuseWorkflowDemoSeeder --force');
        $this->command?->newLine();
        $this->command?->line('Shared password: '.self::PASSWORD);
        $this->command?->line('Seeded assets: '.count(self::ASSET_TAGS).' (search Hardware for DEMO-RF-)');
        $this->command?->line('EPM: demo-EPM');
        $this->command?->line('AFM (Communication): demo-AFM-COMMUNICATION');
        $this->command?->line('AFM (Lab/Power): demo-AFM-LAB');
        $this->command?->line('Source RAC: demo-RAC-CASABLANCA');
        $this->command?->line('Receiving RAC: demo-RAC-RABAT');
        $this->command?->line('RAC used to close the intentional gap: demo-RAC-RABAT');
        $this->command?->line('Requestor: demo-REQUESTOR');
        $this->command?->line('GSA / Administrator: demo-GSA');
        $this->command?->newLine();
        $this->command?->line('Live request project: DEMO - Infotainment ECU Bench Expansion');
        $this->command?->line('Destination: LEAR Electronics Rabat');
        $this->command?->line('Needed by: 2026-09-30');
        $this->command?->line('Vector VN1630A CAN/LIN Interface: quantity 5 / HARDWARE');
        $this->command?->line('Tektronix MDO3024 Oscilloscope: quantity 1 / SYSTEMS');
        $this->command?->line('Intentional routing gap: LEAR Electronics Rabat / SOFTWARE');
        $this->command?->line('Prepared transfer: SEGGER J-Link PRO Debug Probe / DEMO - Cross-Site Debug Bench Transfer');
    }
}

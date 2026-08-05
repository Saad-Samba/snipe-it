<?php

namespace Database\Seeders;

use App\Models\Accessory;
use App\Models\AccessoryCheckout;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\Consumable;
use App\Models\Discipline;
use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\RegionalAssetCoordinatorAssignment;
use App\Models\Setting;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ManualOffboardingAlertsQaSeeder extends Seeder
{
    private const PASSWORD_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    public function run(): void
    {
        if (! Setting::query()->exists()) {
            $this->call(SettingsSeeder::class);
        }

        DB::transaction(function (): void {
            $admin = $this->upsertUser('qa-offboarding-admin', [
                'first_name' => 'QA',
                'last_name' => 'Offboarding Admin',
                'display_name' => 'QA Offboarding Admin',
                'email' => 'qa-offboarding-admin@example.com',
                'employee_num' => 'QA-OFF-ADMIN',
                'permissions' => json_encode(['superuser' => '1']),
                'company_id' => null,
                'created_by' => 1,
            ]);

            $alphaCompany = Company::withoutGlobalScopes()->updateOrCreate(
                ['name' => 'QA Offboarding Plant Alpha'],
                ['notes' => 'Deterministic company for offboarding-alert routing QA.', 'created_by' => $admin->id]
            );
            $betaCompany = Company::withoutGlobalScopes()->updateOrCreate(
                ['name' => 'QA Offboarding Plant Beta'],
                ['notes' => 'Company with multiple RAC disciplines for unresolved-routing QA.', 'created_by' => $admin->id]
            );

            $engineering = $this->upsertDiscipline('QA Offboarding Engineering', $admin->id);
            $manufacturing = $this->upsertDiscipline('QA Offboarding Manufacturing', $admin->id);

            $alphaRac = $this->upsertUser('qa-offboarding-rac-alpha', [
                'first_name' => 'Alpha',
                'last_name' => 'RAC',
                'display_name' => 'QA Offboarding Alpha RAC',
                'email' => 'qa-offboarding-rac-alpha@example.com',
                'employee_num' => 'QA-OFF-RAC-A',
                'company_id' => $alphaCompany->id,
                'created_by' => $admin->id,
            ]);
            $betaEngineeringRac = $this->upsertUser('qa-offboarding-rac-beta-eng', [
                'first_name' => 'Beta Engineering',
                'last_name' => 'RAC',
                'display_name' => 'QA Offboarding Beta Engineering RAC',
                'email' => 'qa-offboarding-rac-beta-eng@example.com',
                'employee_num' => 'QA-OFF-RAC-BE',
                'company_id' => $betaCompany->id,
                'created_by' => $admin->id,
            ]);
            $betaManufacturingRac = $this->upsertUser('qa-offboarding-rac-beta-mfg', [
                'first_name' => 'Beta Manufacturing',
                'last_name' => 'RAC',
                'display_name' => 'QA Offboarding Beta Manufacturing RAC',
                'email' => 'qa-offboarding-rac-beta-mfg@example.com',
                'employee_num' => 'QA-OFF-RAC-BM',
                'company_id' => $betaCompany->id,
                'created_by' => $admin->id,
            ]);

            $this->upsertRacAssignment($alphaRac, $alphaCompany, $engineering, $admin);
            $this->upsertRacAssignment($betaEngineeringRac, $betaCompany, $engineering, $admin);
            $this->upsertRacAssignment($betaManufacturingRac, $betaCompany, $manufacturing, $admin);

            $assetUser = $this->upsertUser('qa.offboarding.asset', [
                'first_name' => 'Asset',
                'last_name' => 'Leaver',
                'display_name' => 'QA Asset Leaver',
                'email' => 'qa.offboarding.asset@example.com',
                'employee_num' => 'QA-OFF-1001',
                'company_id' => $alphaCompany->id,
                'manager_id' => $admin->id,
                'created_by' => $admin->id,
            ]);
            $usernameUser = $this->upsertUser('qa.offboarding.username', [
                'first_name' => 'Username',
                'last_name' => 'Leaver',
                'display_name' => 'QA Username Leaver',
                'email' => 'qa.offboarding.username@example.com',
                'employee_num' => 'QA-OFF-2002',
                'company_id' => $alphaCompany->id,
                'manager_id' => $admin->id,
                'created_by' => $admin->id,
            ]);
            $emailUser = $this->upsertUser('qa.offboarding.email.actual', [
                'first_name' => 'Email',
                'last_name' => 'Leaver',
                'display_name' => 'QA Email Leaver',
                'email' => 'qa.offboarding.email@example.com',
                'employee_num' => 'QA-OFF-3003',
                'company_id' => $betaCompany->id,
                'manager_id' => $admin->id,
                'created_by' => $admin->id,
            ]);
            $this->upsertUser('qa.offboarding.clean', [
                'first_name' => 'Clean',
                'last_name' => 'Leaver',
                'display_name' => 'QA Clean Leaver',
                'email' => 'qa.offboarding.clean@example.com',
                'employee_num' => 'QA-OFF-4004',
                'company_id' => $alphaCompany->id,
                'manager_id' => $admin->id,
                'created_by' => $admin->id,
            ]);

            $assetCategory = $this->upsertCategory('QA Offboarding Computers', 'asset', $admin->id);
            $licenseCategory = $this->upsertCategory('QA Offboarding Software', 'license', $admin->id);
            $accessoryCategory = $this->upsertCategory('QA Offboarding Accessories', 'accessory', $admin->id);
            $consumableCategory = $this->upsertCategory('QA Offboarding Consumables', 'consumable', $admin->id);

            $readyStatus = Statuslabel::withoutGlobalScopes()->updateOrCreate(
                ['name' => 'QA Offboarding Deployed'],
                [
                    'deployable' => 1,
                    'pending' => 0,
                    'archived' => 0,
                    'default_label' => 0,
                    'created_by' => $admin->id,
                ]
            );
            $assetModel = AssetModel::withoutGlobalScopes()->updateOrCreate(
                ['name' => 'QA Offboarding Laptop'],
                [
                    'category_id' => $assetCategory->id,
                    'created_by' => $admin->id,
                    'require_serial' => 0,
                    'notes' => 'Deterministic model for offboarding alert QA.',
                ]
            );

            $this->upsertAssignedAsset(
                $assetUser,
                $assetModel,
                $readyStatus,
                $alphaCompany,
                $engineering,
                $admin
            );
            $this->upsertAssignedLicense(
                $assetUser,
                $licenseCategory,
                $alphaCompany,
                $engineering,
                $admin
            );
            $this->upsertAssignedAccessory(
                $usernameUser,
                $accessoryCategory,
                $alphaCompany,
                $admin
            );
            $this->upsertAssignedConsumable(
                $emailUser,
                $consumableCategory,
                $betaCompany,
                $admin
            );
        });

        $this->command?->info('Manual offboarding-alert QA dataset is ready.');
        $this->command?->line('Admin login: qa-offboarding-admin / password');
        $this->command?->line('Input CSV: database/seeders/fixtures/offboarding_alerts_disabled_accounts.csv');
        $this->command?->line('Expected: matched=4, not_found=1, ambiguous=1, skipped=2, obligations=4.');
        $this->command?->line('Expected routed obligations=3; Beta consumable has a multiple-RAC warning.');
    }

    private function upsertUser(string $username, array $attributes): User
    {
        $user = User::withoutGlobalScopes()->withTrashed()->firstOrNew(['username' => $username]);
        $user->fill($attributes);
        $user->password = self::PASSWORD_HASH;
        $user->activated = 1;
        $user->deleted_at = null;
        $user->locale = 'en-US';
        $user->save();

        return $user;
    }

    private function upsertDiscipline(string $name, int $createdBy): Discipline
    {
        $id = DB::table('disciplines')->where('name', $name)->value('id');
        if (! $id) {
            $id = DB::table('disciplines')->insertGetId([
                'name' => $name,
                'notes' => 'Deterministic discipline for offboarding alert QA.',
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return Discipline::withoutGlobalScopes()->findOrFail($id);
    }

    private function upsertRacAssignment(
        User $rac,
        Company $company,
        Discipline $discipline,
        User $admin
    ): void {
        RegionalAssetCoordinatorAssignment::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->id, 'discipline_id' => $discipline->id],
            ['user_id' => $rac->id, 'created_by' => $admin->id]
        );
    }

    private function upsertCategory(string $name, string $type, int $createdBy): Category
    {
        return Category::withoutGlobalScopes()->updateOrCreate(
            ['name' => $name, 'category_type' => $type],
            [
                'created_by' => $createdBy,
                'checkin_email' => 0,
                'require_acceptance' => 0,
                'use_default_eula' => 0,
                'notes' => 'Deterministic category for offboarding alert QA.',
            ]
        );
    }

    private function upsertAssignedAsset(
        User $user,
        AssetModel $model,
        Statuslabel $status,
        Company $company,
        Discipline $discipline,
        User $admin
    ): void {
        $asset = Asset::withoutGlobalScopes()->firstOrNew(['asset_tag' => 'QA-OFF-ASSET-001']);
        $asset->name = 'QA Offboarding Assigned Laptop';
        $asset->model_id = $model->id;
        $asset->status_id = $status->id;
        $asset->company_id = $company->id;
        $asset->discipline_id = $discipline->id;
        $asset->assigned_to = $user->id;
        $asset->assigned_type = User::class;
        $asset->created_by = $admin->id;
        $asset->requestable = 0;
        $asset->notes = 'Assigned to a deterministic disabled-account candidate.';
        $asset->deleted_at = null;
        $asset->save();
    }

    private function upsertAssignedLicense(
        User $user,
        Category $category,
        Company $company,
        Discipline $discipline,
        User $admin
    ): void {
        $license = License::withoutGlobalScopes()->firstOrNew([
            'name' => 'QA Offboarding Engineering Suite',
        ]);
        $license->category_id = $category->id;
        $license->company_id = $company->id;
        $license->discipline_id = $discipline->id;
        $license->seats = 1;
        $license->perpetual = true;
        $license->reassignable = true;
        $license->serial_number = 'QA-OFF-LICENSE-001';
        $license->created_by = $admin->id;
        $license->notes = 'Assigned to a deterministic disabled-account candidate.';
        $license->deleted_at = null;
        $license->save();

        $seat = LicenseSeat::withTrashed()->where('license_id', $license->id)->orderBy('id')->firstOrFail();
        $seat->assigned_to = $user->id;
        $seat->asset_id = null;
        $seat->deleted_at = null;
        $seat->notes = 'QA offboarding alert assignment';
        $seat->save();
    }

    private function upsertAssignedAccessory(
        User $user,
        Category $category,
        Company $company,
        User $admin
    ): void {
        $accessory = Accessory::withoutGlobalScopes()->firstOrNew([
            'name' => 'QA Offboarding USB Dock',
        ]);
        $accessory->category_id = $category->id;
        $accessory->company_id = $company->id;
        $accessory->qty = 5;
        $accessory->min_amt = 1;
        $accessory->created_by = $admin->id;
        $accessory->notes = 'Assigned accessory without a discipline; Alpha has one RAC.';
        $accessory->deleted_at = null;
        $accessory->save();

        $checkout = AccessoryCheckout::firstOrNew([
            'accessory_id' => $accessory->id,
            'assigned_to' => $user->id,
            'assigned_type' => User::class,
        ]);
        $checkout->created_by = $admin->id;
        $checkout->note = 'QA offboarding alert assignment';
        $checkout->save();
    }

    private function upsertAssignedConsumable(
        User $user,
        Category $category,
        Company $company,
        User $admin
    ): void {
        $consumable = Consumable::withoutGlobalScopes()->firstOrNew([
            'name' => 'QA Offboarding Security Token',
        ]);
        $consumable->category_id = $category->id;
        $consumable->company_id = $company->id;
        $consumable->qty = 5;
        $consumable->min_amt = 1;
        $consumable->item_no = 'QA-OFF-CONS-001';
        $consumable->created_by = $admin->id;
        $consumable->notes = 'No discipline; Beta has multiple RACs to force a routing warning.';
        $consumable->deleted_at = null;
        $consumable->save();

        DB::table('consumables_users')->updateOrInsert(
            ['consumable_id' => $consumable->id, 'assigned_to' => $user->id],
            [
                'created_by' => $admin->id,
                'note' => 'QA offboarding alert assignment',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}

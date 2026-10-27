<?php

namespace Database\Seeders;

use App\Actions\CheckoutRequests\EstimateAssetModelReuseAction;
use App\Actions\CheckoutRequests\ResolveCheckoutRequestCoordinatorsAction;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\CheckoutRequest;
use App\Models\Company;
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

/** Synthetic electronics-lab scenarios for a dedicated QA database. */
class LearAssignedCandidatesQaSeeder extends Seeder
{
    public function run(): void
    {
        $users = [];
        foreach ([
            'admin' => ['superuser' => '1'],
            'coordinator' => ['assets.view' => '1', 'assets.checkout' => '1', 'assets.checkin' => '1', 'assets.edit' => '1'],
            'requester' => ['assets.view' => '1', 'models.request' => '1'],
            'outsider' => ['assets.view' => '1'],
        ] as $role => $permissions) {
            $users[$role] = User::withoutGlobalScopes()->updateOrCreate(['username' => 'le-qa-'.$role], [
                'first_name' => 'QA', 'last_name' => ucfirst($role),
                'email' => 'le-qa-'.$role.'@example.test', 'activated' => 1,
                'password' => Hash::make('Lear-QA-2026!'), 'permissions' => json_encode($permissions),
                'locale' => 'en-US', 'company_id' => null,
            ]);
        }
        $admin = $users['admin'];
        $statuses = [];
        foreach ([
            'ready' => ['QA Operational', 1, 0, 0],
            'reserved' => ['QA Reserved for RFQ', 1, 0, 0],
            'pending' => ['QA Pending inspection', 0, 1, 0],
            'repair' => ['QA Under repair', 0, 0, 0],
            'archived' => ['QA Retired', 0, 0, 1],
        ] as $key => [$name, $deployable, $pending, $archived]) {
            $statuses[$key] = Statuslabel::updateOrCreate(['name' => $name], compact('deployable', 'pending', 'archived'));
        }
        $settings = Setting::firstOrNew();
        $settings->forceFill([
            'site_name' => 'Lear Electronics-inspired — Assigned Asset QA',
            'per_page' => 25, 'auto_increment_assets' => 0, 'locale' => 'en-US',
            'default_currency' => 'EUR', 'full_multiple_companies_support' => 0,
            'rfq_reserved_statuslabel_id' => $statuses['reserved']->id,
            'alerts_enabled' => 0, 'brand' => 1,
        ])->save();
        Setting::$_cache = $settings->fresh();

        $site = Company::updateOrCreate(['name' => 'QA Lear Electronics Rabat']);
        $otherSite = Company::updateOrCreate(['name' => 'QA Lear Electronics Valls']);
        $location = Location::updateOrCreate(['name' => 'QA Rabat Validation Lab'], ['company_id' => $site->id]);
        $discipline = Discipline::updateOrCreate(['name' => 'QA Electronics validation'], ['created_by' => $admin->id]);
        $project = Project::updateOrCreate(['name' => 'QA Next-generation Body Controller'], ['created_by' => $admin->id]);
        $otherProject = Project::updateOrCreate(['name' => 'QA Legacy Gateway Validation'], ['created_by' => $admin->id]);
        $category = Category::updateOrCreate(['name' => 'QA Automotive network interfaces'], ['category_type' => 'asset', 'manager_id' => $admin->id]);
        $manufacturer = Manufacturer::updateOrCreate(['name' => 'QA Vector-inspired']);
        $model = AssetModel::updateOrCreate(['name' => 'QA CAN-FD Validation Interface'], [
            'category_id' => $category->id, 'manufacturer_id' => $manufacturer->id,
            'model_number' => 'QA-CANFD-01', 'reference_price' => 1200,
        ]);
        $onlyAssignedModel = AssetModel::updateOrCreate(['name' => 'QA ECU Debug Probe'], [
            'category_id' => $category->id, 'manufacturer_id' => $manufacturer->id, 'reference_price' => 800,
        ]);
        AssetModel::updateOrCreate(['name' => 'QA Programmable DC Supply'], [
            'category_id' => $category->id, 'manufacturer_id' => $manufacturer->id,
            'model_number' => 'QA-PSU-60V', 'reference_price' => 1900,
        ]);
        RegionalAssetCoordinatorAssignment::updateOrCreate([
            'company_id' => $site->id, 'discipline_id' => $discipline->id,
        ], ['user_id' => $users['coordinator']->id, 'created_by' => $admin->id]);

        $neededBy = now()->addDays(14)->toDateString();
        $scenarios = [
            'AVAILABLE' => [null, null, 'ready', null, $site],
            'SAME' => [User::class, $users['requester']->id, 'ready', null, $site],
            'OTHER' => [User::class, $users['requester']->id, 'ready', null, $otherSite],
            'NO-PROJECT' => [User::class, $users['requester']->id, 'ready', null, $site],
            'LOCATION' => [Location::class, $location->id, 'ready', null, $site],
            'DUE' => [User::class, $users['requester']->id, 'ready', now()->addDays(7)->toDateString(), $site],
            'LATER' => [User::class, $users['requester']->id, 'ready', now()->addDays(30)->toDateString(), $site],
            'RESERVED' => [User::class, $users['requester']->id, 'reserved', null, $site],
            'REPAIR' => [User::class, $users['requester']->id, 'repair', null, $site],
            'PENDING' => [User::class, $users['requester']->id, 'pending', null, $site],
            'ARCHIVED' => [User::class, $users['requester']->id, 'archived', null, $site],
            'DELETED' => [User::class, $users['requester']->id, 'ready', null, $site],
        ];
        foreach ($scenarios as $tag => [$type, $assignee, $status, $return, $company]) {
            $asset = Asset::withoutGlobalScopes()->withTrashed()->firstOrNew(['asset_tag' => 'LE-QA-'.$tag]);
            $asset->forceFill([
                'name' => 'QA CAN-FD — '.$tag, 'model_id' => $model->id,
                'status_id' => $statuses[$status]->id, 'company_id' => $company->id,
                'discipline_id' => $discipline->id, 'location_id' => $location->id,
                'rtd_location_id' => $location->id, 'assigned_type' => $type, 'assigned_to' => $assignee,
                'project_id' => $tag === 'NO-PROJECT' ? null : ($tag === 'SAME' ? $project->id : $otherProject->id),
                'expected_checkin' => $return, 'created_by' => $admin->id,
                'deleted_at' => $tag === 'DELETED' ? now() : null,
            ]);
            if (! $asset->save()) {
                throw new \RuntimeException(implode('; ', $asset->getErrors()->all()));
            }
        }
        Asset::updateOrCreate(['asset_tag' => 'LE-QA-NESTED'], [
            'name' => 'QA CAN-FD — Asset checkout', 'model_id' => $model->id,
            'status_id' => $statuses['ready']->id, 'company_id' => $site->id,
            'discipline_id' => $discipline->id, 'rtd_location_id' => $location->id,
            'assigned_type' => Asset::class, 'assigned_to' => Asset::where('asset_tag', 'LE-QA-AVAILABLE')->firstOrFail()->id,
            'project_id' => $otherProject->id,
        ]);
        Asset::updateOrCreate(['asset_tag' => 'LE-QA-ONLY-ASSIGNED'], [
            'name' => 'QA ECU Probe — assigned only', 'model_id' => $onlyAssignedModel->id,
            'status_id' => $statuses['ready']->id, 'company_id' => $site->id,
            'discipline_id' => $discipline->id, 'rtd_location_id' => $location->id,
            'assigned_type' => User::class, 'assigned_to' => $users['requester']->id,
            'project_id' => $otherProject->id,
        ]);
        foreach ([$model, $onlyAssignedModel] as $requestedModel) {
            $request = CheckoutRequest::updateOrCreate([
                'user_id' => $users['requester']->id, 'requestable_type' => AssetModel::class,
                'requestable_id' => $requestedModel->id, 'project_id' => $project->id,
            ], array_merge(array_intersect_key(EstimateAssetModelReuseAction::run($requestedModel, 4, $neededBy), array_flip([
                'reusable_quantity', 'due_back_before_needed_by_quantity', 'potentially_coverable_quantity',
                'procurement_shortfall', 'estimated_savings', 'reference_price_snapshot',
            ])), [
                'quantity' => 4, 'needed_by_date' => $neededBy, 'company_id' => $site->id,
                'requested_discipline_id' => $discipline->id, 'status' => CheckoutRequest::STATUS_PENDING,
            ]));
            ResolveCheckoutRequestCoordinatorsAction::run($request, false);
            $this->command?->info($requestedModel->name.': request #'.$request->id);
        }
        $this->command?->info('Synthetic local QA users: le-qa-admin, le-qa-coordinator, le-qa-requester, le-qa-outsider. Password: Lear-QA-2026!');
    }
}

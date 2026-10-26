<?php

namespace Tests\Feature\Requests;

use App\Actions\CheckoutRequests\ResolveCheckoutRequestCoordinatorsAction;
use App\Actions\CheckoutRequests\StartCheckoutRequestTransferAction;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\CheckoutRequest;
use App\Models\Company;
use App\Models\Discipline;
use App\Models\Location;
use App\Models\Project;
use App\Models\RegionalAssetCoordinatorAssignment;
use App\Models\Setting;
use App\Models\Statuslabel;
use App\Models\User;
use App\Presenters\AssetPresenter;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AssignedCandidatesTest extends TestCase
{
    private function requestFor(User $coordinator, array $attributes = []): CheckoutRequest
    {
        $request = CheckoutRequest::factory()->forAssetModel()->create(array_merge([
            'project_id' => Project::factory(), 'needed_by_date' => '2026-11-15',
        ], $attributes));
        $request->coordinatorTargets()->create([
            'user_id' => $coordinator->id, 'company_id' => Company::factory()->create()->id,
            'discipline_id' => Discipline::firstOrCreate(['name' => 'Candidate review'], ['created_by' => $coordinator->id])->id,
        ]);

        return $request;
    }

    private function candidate(CheckoutRequest $request, array $attributes = []): Asset
    {
        return Asset::factory()->assignedToUser()->create(array_merge([
            'model_id' => $request->requestable_id,
            'status_id' => Statuslabel::factory()->rtd()->create()->id,
            'expected_checkin' => null,
        ], $attributes));
    }

    private function bucketUrl(?CheckoutRequest $request = null, array $query = []): string
    {
        return route('api.assets.index', array_merge([
            'request_id' => $request?->id, 'request_bucket' => 'assigned_candidates',
            'offset' => 0, 'limit' => 25,
        ], $query));
    }

    public function test_candidate_list_labels_all_project_relationships_and_checkout_types(): void
    {
        $coordinator = User::factory()->viewAssets()->create();
        $request = $this->requestFor($coordinator);
        $same = $this->candidate($request, ['project_id' => $request->project_id]);
        $other = $this->candidate($request, [
            'project_id' => Project::factory()->create()->id,
            'assigned_to' => Location::factory()->create()->id, 'assigned_type' => Location::class,
        ]);
        $none = $this->candidate($request, [
            'assigned_to' => $same->id, 'assigned_type' => Asset::class, 'project_id' => null,
        ]);

        $response = $this->actingAsForApi($coordinator)->getJson($this->bucketUrl($request))->assertOk()->assertJsonPath('total', 3);
        $rows = collect($response->json('rows'))->keyBy('id');
        $this->assertSame('Same project', $rows[$same->id]['project_relationship']);
        $this->assertSame('Other project', $rows[$other->id]['project_relationship']);
        $this->assertSame('No project', $rows[$none->id]['project_relationship']);
        foreach ($rows as $row) {
            $this->assertFalse($row['user_can_checkout']);
            $this->assertNotContains(true, $row['available_actions']);
        }
        $this->assertSame(3, $request->assignedCandidatesQuery($coordinator)->count());
    }

    public function test_due_back_dates_do_not_overlap_and_missing_needed_by_includes_all_dates(): void
    {
        $coordinator = User::factory()->viewAssets()->create();
        $request = $this->requestFor($coordinator);
        foreach (['2026-11-14', '2026-11-15', '2026-11-16', null] as $date) {
            $this->candidate($request, ['expected_checkin' => $date]);
        }
        $this->actingAsForApi($coordinator)->getJson($this->bucketUrl($request))->assertOk()->assertJsonPath('total', 2);
        $this->getJson($this->bucketUrl($request, ['request_bucket' => 'due_back']))->assertOk()->assertJsonPath('total', 2);
        $request->update(['needed_by_date' => null]);
        $this->getJson($this->bucketUrl($request))->assertOk()->assertJsonPath('total', 4);
    }

    public function test_ineligible_assets_are_excluded_even_when_deleted_status_is_requested(): void
    {
        $coordinator = User::factory()->viewAssets()->create();
        $request = $this->requestFor($coordinator);
        $eligible = $this->candidate($request);
        $this->candidate($request, ['model_id' => AssetModel::factory()->create()->id]);
        $this->candidate($request, ['assigned_to' => null, 'assigned_type' => null]);
        foreach ([[0, 1, 0], [0, 0, 1], [0, 0, 0]] as [$deployable, $pending, $archived]) {
            $this->candidate($request, ['status_id' => Statuslabel::factory()->create(compact('deployable', 'pending', 'archived'))->id]);
        }
        $reserved = Statuslabel::factory()->rtd()->create();
        $settings = Setting::getSettings();
        $settings->rfq_reserved_statuslabel_id = $reserved->id;
        $settings->save();
        Setting::$_cache = $settings->fresh();
        $this->candidate($request, ['status_id' => $reserved->id]);
        $this->candidate($request)->delete();
        $this->actingAsForApi($coordinator)->getJson($this->bucketUrl($request))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('rows.0.id', $eligible->id);
        $this->getJson($this->bucketUrl($request, ['status' => 'Deleted']))->assertOk()->assertJsonPath('total', 0);
    }

    public function test_request_ownership_or_unrelated_coordinator_assignment_does_not_grant_access(): void
    {
        $coordinator = User::factory()->viewAssets()->create();
        $requester = User::factory()->viewAssets()->create();
        $request = $this->requestFor($coordinator, ['user_id' => $requester->id]);
        $outsider = User::factory()->viewAssets()->create();
        foreach ([$requester, $outsider] as $user) {
            $this->actingAsForApi($user)->getJson($this->bucketUrl($request))->assertForbidden();
            $this->actingAs($user, 'web')->get(route('hardware.index', [
                'request_id' => $request->id, 'request_bucket' => 'assigned_candidates',
            ]))->assertForbidden();
        }
        $this->actingAsForApi(User::factory()->superuser()->create())->getJson($this->bucketUrl($request))->assertOk();
        $this->getJson($this->bucketUrl(null, ['request_id' => 99999999]))->assertNotFound();
        $this->getJson($this->bucketUrl())->assertNotFound();
    }

    public function test_coordinator_without_asset_permission_cannot_access_candidates_or_counts(): void
    {
        $coordinator = User::factory()->create();
        $request = $this->requestFor($coordinator);
        $this->candidate($request);
        $this->actingAsForApi($coordinator)->getJson($this->bucketUrl($request))->assertForbidden();
        $this->getJson(route('api.rac-requests.index'))->assertOk()
            ->assertJsonPath('rows.0.assigned_candidates_count', null)
            ->assertJsonPath('rows.0.assigned_candidates_url', null);
    }

    public function test_counts_and_lists_respect_full_company_visibility(): void
    {
        $settings = Setting::getSettings();
        $settings->full_multiple_companies_support = 1;
        $settings->save();
        Setting::$_cache = $settings->fresh();
        $company = Company::factory()->create();
        $coordinator = User::factory()->viewAssets()->create(['company_id' => $company->id]);
        $request = $this->requestFor($coordinator, ['company_id' => $company->id]);
        $visible = $this->candidate($request, ['company_id' => $company->id]);
        $this->candidate($request, ['company_id' => Company::factory()->create()->id]);
        $this->actingAsForApi($coordinator)->getJson($this->bucketUrl($request))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('rows.0.id', $visible->id);
        $this->getJson(route('api.rac-requests.index'))->assertOk()->assertJsonPath('rows.0.assigned_candidates_count', 1);
    }

    public function test_candidate_queries_respect_category_manager_visibility(): void
    {
        $settings = Setting::getSettings();
        $settings->full_multiple_companies_support = 1;
        $settings->save();
        Setting::$_cache = $settings->fresh();
        $manager = User::factory()->viewAssets()->create();
        $managedModel = AssetModel::factory()->create(['category_id' => Category::factory()->create(['manager_id' => $manager->id])->id]);
        $request = $this->requestFor($manager, ['requestable_id' => $managedModel->id]);
        $this->candidate($request);
        $other = $this->requestFor(User::factory()->create());
        $this->candidate($other);
        $this->assertSame(1, $request->assignedCandidatesQuery($manager)->count());
        $this->assertSame(0, $other->assignedCandidatesQuery($manager)->count());
    }

    public function test_assigned_page_is_read_only_and_existing_request_view_links_to_it(): void
    {
        $coordinator = User::factory()->viewAssets()->editAssets()->checkoutAssets()->checkinAssets()->create();
        $request = $this->requestFor($coordinator);
        $this->candidate($request);
        $url = route('hardware.index', ['request_id' => $request->id, 'request_bucket' => 'assigned_candidates']);
        $this->actingAs($coordinator)->get($url)->assertOk()->assertSee('Assigned — release required')
            ->assertSee('does not confirm availability')
            ->assertSee('No eligible assigned candidates match the current filters.')
            ->assertDontSee('id="assetsBulkEditToolbar"', false)
            ->assertDontSee('Mark no more reusable stock available');
        $this->get(route('hardware.index', ['request_id' => $request->id, 'request_bucket' => 'reusable_now']))
            ->assertOk()->assertSee($url);
        $columns = collect(json_decode(AssetPresenter::dataTableLayout(false, true), true))->keyBy('field');
        $this->assertFalse($columns->has('checkbox'));
        $this->assertFalse($columns->has('actions'));
        $this->assertFalse($columns->has('checkincheckout'));
        $this->assertTrue($columns['project']['visible']);
        $this->assertTrue($columns['expected_checkin']['visible']);
    }

    public function test_candidates_do_not_change_metrics_routing_or_asset_state(): void
    {
        Notification::fake();
        $coordinator = User::factory()->viewAssets()->create();
        $company = Company::factory()->create();
        $discipline = Discipline::create(['name' => 'Unrouted assigned', 'created_by' => $coordinator->id]);
        $request = CheckoutRequest::factory()->forAssetModel()->create(['company_id' => $company->id]);
        RegionalAssetCoordinatorAssignment::create([
            'company_id' => $company->id, 'discipline_id' => $discipline->id,
            'user_id' => $coordinator->id, 'created_by' => $coordinator->id,
        ]);
        $before = $request->liveRequestMetrics();
        $asset = $this->candidate($request, ['company_id' => $company->id, 'discipline_id' => $discipline->id]);
        $attributes = $asset->fresh()->getAttributes();
        $this->assertSame($before, $request->fresh()->liveRequestMetrics());
        ResolveCheckoutRequestCoordinatorsAction::run($request, false);
        $this->assertSame(0, $request->coordinatorTargets()->count());
        $this->assertSame(CheckoutRequest::RAC_ROUTING_NOT_REQUIRED, $request->fresh()->rac_routing_status);
        $admin = User::factory()->superuser()->create();
        $this->actingAsForApi($admin)->getJson($this->bucketUrl($request))->assertOk();
        $this->assertSame($attributes, $asset->fresh()->getAttributes());
        Notification::assertNothingSent();
    }

    public function test_candidate_list_supports_pagination(): void
    {
        $coordinator = User::factory()->viewAssets()->create();
        $request = $this->requestFor($coordinator);
        $first = $this->candidate($request, ['name' => 'Alpha candidate']);
        $second = $this->candidate($request, ['name' => 'Beta candidate']);
        $this->actingAsForApi($coordinator)->getJson($this->bucketUrl($request, [
            'limit' => 1, 'offset' => 1, 'sort' => 'name', 'order' => 'asc',
        ]))->assertOk()->assertJsonPath('total', 2)->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.id', $second->id);
    }

    public function test_candidate_list_supports_search_and_empty_results(): void
    {
        $coordinator = User::factory()->viewAssets()->create();
        $request = $this->requestFor($coordinator);
        $first = $this->candidate($request, ['name' => 'Alpha candidate']);
        $this->candidate($request, ['name' => 'Beta candidate']);
        $this->actingAsForApi($coordinator)->getJson($this->bucketUrl($request, ['search' => 'Alpha candidate']))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('rows.0.id', $first->id);
        $this->getJson($this->bucketUrl($request, ['search' => 'No such candidate']))
            ->assertOk()->assertJsonPath('total', 0)->assertJsonCount(0, 'rows');
    }

    public function test_demo_seeder_is_repeatable_and_keeps_assigned_only_request_unrouted(): void
    {
        $this->seed(\Database\Seeders\LearAssignedCandidatesQaSeeder::class);
        $this->seed(\Database\Seeders\LearAssignedCandidatesQaSeeder::class);
        $coordinator = User::where('username', 'le-qa-coordinator')->firstOrFail();
        $requests = CheckoutRequest::where('user_id', User::where('username', 'le-qa-requester')->firstOrFail()->id)->get();
        $this->assertCount(2, $requests);
        $main = $requests->firstWhere('requestable_id', AssetModel::where('name', 'QA CAN-FD Validation Interface')->firstOrFail()->id);
        $onlyAssigned = $requests->firstWhere('requestable_id', AssetModel::where('name', 'QA ECU Debug Probe')->firstOrFail()->id);
        $this->assertSame(6, $main->assignedCandidatesQuery($coordinator)->count());
        $this->assertSame(1, $main->coordinatorTargets()->count());
        $this->assertSame(0, $onlyAssigned->coordinatorTargets()->count());
        $this->assertSame(14, Asset::withTrashed()->where('asset_tag', 'like', 'LE-QA-%')->count());
    }

    public function test_transfer_still_rejects_assigned_candidates(): void
    {
        $settings = Setting::getSettings();
        $settings->full_multiple_companies_support = 1;
        $settings->save();
        Setting::$_cache = $settings->fresh();
        $admin = User::factory()->superuser()->create();
        $request = $this->requestFor($admin, ['company_id' => Company::factory()->create()->id]);
        $asset = $this->candidate($request, ['company_id' => Company::factory()->create()->id]);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The asset must be unassigned and reusable before a transfer can start.');
        StartCheckoutRequestTransferAction::run($request->id, $asset->id, $admin);
    }
}

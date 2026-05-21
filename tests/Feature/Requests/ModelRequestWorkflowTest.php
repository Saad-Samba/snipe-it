<?php

namespace Tests\Feature\Requests;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\CheckoutRequest;
use App\Models\Company;
use App\Models\Discipline;
use App\Models\Project;
use App\Models\RegionalAssetCoordinatorAssignment;
use App\Models\Setting;
use App\Models\Statuslabel;
use App\Models\User;
use App\Notifications\RacScopedRequestSummaryNotification;
use App\Notifications\RequestAssetNotification;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use ZipArchive;

class ModelRequestWorkflowTest extends TestCase
{
    public function test_model_request_persists_quantity_and_notifies_candidate_racs_from_asset_stock()
    {
        Notification::fake();

        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $disciplineA = Discipline::create(['name' => 'Electrical', 'created_by' => $requester->id]);
        $disciplineB = Discipline::create(['name' => 'Mechanical', 'created_by' => $requester->id]);
        $coordinatorA = User::factory()->create(['first_name' => 'Casablanca', 'last_name' => 'RAC']);
        $coordinatorB = User::factory()->create(['first_name' => 'Rabat', 'last_name' => 'RAC']);
        $companyA = Company::factory()->create(['name' => 'Casablanca Site']);
        $companyB = Company::factory()->create(['name' => 'Rabat Site']);
        $destinationCompany = Company::factory()->create(['name' => 'Receiving Site']);
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        $this->createEligibleAsset($model, $companyA->id, $disciplineA->id);
        $this->createEligibleAsset($model, $companyB->id, $disciplineB->id);

        RegionalAssetCoordinatorAssignment::create([
            'user_id' => $coordinatorA->id,
            'company_id' => $companyA->id,
            'discipline_id' => $disciplineA->id,
            'created_by' => $requester->id,
        ]);

        RegionalAssetCoordinatorAssignment::create([
            'user_id' => $coordinatorB->id,
            'company_id' => $companyB->id,
            'discipline_id' => $disciplineB->id,
            'created_by' => $requester->id,
        ]);

        $this->actingAs($requester)
            ->post(route('account/request-item', ['itemType' => 'asset_model', 'itemId' => $model->id]), [
                'request-quantity' => 2,
                'requested_discipline_id' => $disciplineA->id,
                'company_id' => $destinationCompany->id,
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-01',
            ])
            ->assertRedirect();

        $checkoutRequest = CheckoutRequest::query()
            ->where('user_id', $requester->id)
            ->where('requestable_id', $model->id)
            ->where('requestable_type', AssetModel::class)
            ->firstOrFail();

        $this->assertSame(2, $checkoutRequest->quantity);
        $this->assertSame($disciplineA->id, $checkoutRequest->requested_discipline_id);
        $this->assertSame($destinationCompany->id, $checkoutRequest->company_id);
        $this->assertSame('2026-06-01', optional($checkoutRequest->needed_by_date)->format('Y-m-d'));
        $this->assertSame('pending', $checkoutRequest->status);
        $this->assertSame(2, $checkoutRequest->reusable_quantity);
        $this->assertSame(0, $checkoutRequest->due_back_before_needed_by_quantity);
        $this->assertSame(2, $checkoutRequest->potentially_coverable_quantity);
        $this->assertSame(0, $checkoutRequest->procurement_shortfall);
        $this->assertSame((float) $model->reference_price * 2, (float) $checkoutRequest->estimated_savings);
        $this->assertSame((float) $model->reference_price, (float) $checkoutRequest->reference_price_snapshot);

        $this->assertDatabaseHas('checkout_request_coordinators', [
            'checkout_request_id' => $checkoutRequest->id,
            'user_id' => $coordinatorA->id,
            'company_id' => $companyA->id,
            'discipline_id' => $disciplineA->id,
        ]);

        $this->assertDatabaseHas('checkout_request_coordinators', [
            'checkout_request_id' => $checkoutRequest->id,
            'user_id' => $coordinatorB->id,
            'company_id' => $companyB->id,
            'discipline_id' => $disciplineB->id,
        ]);

        Notification::assertSentTo($coordinatorA, RacScopedRequestSummaryNotification::class, function ($notification) use ($project, $disciplineA) {
            return $notification->projectName() === $project->name
                && count($notification->lines()) === 1
                && $notification->lines()[0]['company_name'] === 'Receiving Site'
                && $notification->lines()[0]['discipline_name'] === $disciplineA->name
                && $notification->lines()[0]['reusable_quantity'] === 1;
        });
        Notification::assertSentTo($coordinatorB, RacScopedRequestSummaryNotification::class, function ($notification) use ($project, $disciplineA) {
            return $notification->projectName() === $project->name
                && count($notification->lines()) === 1
                && $notification->lines()[0]['company_name'] === 'Receiving Site'
                && $notification->lines()[0]['discipline_name'] === $disciplineA->name
                && $notification->lines()[0]['reusable_quantity'] === 1;
        });
    }

    public function test_model_request_requires_models_request_permission()
    {
        $requester = User::factory()->create();
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        $discipline = Discipline::create([
            'name' => 'Validation',
            'created_by' => $requester->id,
        ]);
        $this->createEligibleAsset($model, Company::factory()->create()->id, $discipline->id);
        $destinationCompany = Company::factory()->create();
        $destinationCompany = Company::factory()->create();
        $destinationCompany = Company::factory()->create();

        $this->actingAs($requester)
            ->post(route('account/request-item', ['itemType' => 'asset_model', 'itemId' => $model->id]), [
                'request-quantity' => 1,
                'requested_discipline_id' => $discipline->id,
                'company_id' => $destinationCompany->id,
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-01',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('checkout_requests', [
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
        ]);
    }

    public function test_model_request_requires_model_to_be_in_requesters_managed_category_scope()
    {
        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $project = Project::factory()->create();
        Category::factory()->forAssets()->create([
            'manager_id' => $requester->id,
        ]);
        $model = AssetModel::factory()->create([
            'category_id' => Category::factory()->forAssets()->create()->id,
        ]);

        $discipline = Discipline::create([
            'name' => 'Scoped Validation',
            'created_by' => $requester->id,
        ]);
        $this->createEligibleAsset($model, Company::factory()->create()->id, $discipline->id);
        $destinationCompany = Company::factory()->create();

        $this->actingAs($requester)
            ->post(route('account/request-item', ['itemType' => 'asset_model', 'itemId' => $model->id]), [
                'request-quantity' => 1,
                'requested_discipline_id' => $discipline->id,
                'company_id' => $destinationCompany->id,
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-01',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('checkout_requests', [
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
        ]);
    }

    public function test_submitted_requests_page_requires_models_request_permission()
    {
        $requester = User::factory()->create();

        $this->actingAs($requester)
            ->get(route('requests.index'))
            ->assertForbidden();
    }

    public function test_submitted_requests_api_requires_models_request_permission()
    {
        $requester = User::factory()->create();

        $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index'))
            ->assertForbidden();
    }

    public function test_requested_assets_api_returns_project_and_booked_metadata_for_requester()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $project = Project::factory()->create(['name' => 'Request Tracking Project']);
        $discipline = Discipline::create([
            'name' => 'API Discipline',
            'created_by' => $requester->id,
        ]);
        $destinationCompany = Company::factory()->create(['name' => 'API Destination']);
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'name' => 'QA Routing Model',
        ]);
        $this->createEligibleAsset($model, Company::factory()->create()->id, $discipline->id);

        $checkoutRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'quantity' => 2,
            'requested_discipline_id' => $discipline->id,
            'company_id' => $destinationCompany->id,
            'project_id' => $project->id,
            'reusable_quantity' => 1,
            'procurement_shortfall' => 1,
            'estimated_savings' => 499.99,
            'reference_price_snapshot' => 499.99,
        ]);

        $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index'))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.request_id', $checkoutRequest->id)
            ->assertJsonPath('rows.0.qty', 2)
            ->assertJsonPath('rows.0.requested_discipline_id', $checkoutRequest->requested_discipline_id)
            ->assertJsonPath('rows.0.requested_discipline', 'API Discipline')
            ->assertJsonPath('rows.0.company_id', $destinationCompany->id)
            ->assertJsonPath('rows.0.company', 'API Destination')
            ->assertJsonPath('rows.0.status', 'Pending')
            ->assertJsonPath('rows.0.project', 'Request Tracking Project')
            ->assertJsonPath('rows.0.reusable_quantity', 1)
            ->assertJsonPath('rows.0.due_back_before_needed_by_quantity', 0)
            ->assertJsonPath('rows.0.procurement_shortfall', 1)
            ->assertJsonPath('rows.0.estimated_savings', 499.99)
            ->assertJsonPath('rows.0.amount_to_buy', 499.99)
            ->assertJsonPath('rows.0.reference_price_snapshot_formatted', '499.99')
            ->assertJsonPath('rows.0.reserved_count', 0)
            ->assertJsonPath('rows.0.reserved_by_other_rfqs_count', 0)
            ->assertJsonPath('rows.0.project_requests_url', route('projects.show', ['project' => $project->id, 'tab' => 'requests']))
            ->assertJsonPath('rows.0.request_update_url', route('requests.update', $checkoutRequest))
            ->assertJsonPath('rows.0.request_cancel_url', route('requests.cancel', $checkoutRequest));
    }

    public function test_requested_assets_api_uses_live_reusable_quantity_for_same_model_across_disciplines()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $project = Project::factory()->create();
        $disciplineA = Discipline::create(['name' => 'Electrical', 'created_by' => $requester->id]);
        $disciplineB = Discipline::create(['name' => 'Mechanical', 'created_by' => $requester->id]);
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'reference_price' => 250,
        ]);

        $this->createEligibleAsset($model, Company::factory()->create()->id, $disciplineA->id);
        $this->createEligibleAsset($model, Company::factory()->create()->id, $disciplineB->id);
        $destinationCompanyA = Company::factory()->create();
        $destinationCompanyB = Company::factory()->create();

        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'requested_discipline_id' => $disciplineA->id,
            'company_id' => $destinationCompanyA->id,
            'project_id' => $project->id,
            'quantity' => 1,
            'reusable_quantity' => 99,
        ]);

        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'requested_discipline_id' => $disciplineB->id,
            'company_id' => $destinationCompanyB->id,
            'project_id' => $project->id,
            'quantity' => 1,
            'reusable_quantity' => 0,
        ]);

        $response = $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index', ['project_id' => $project->id]))
            ->assertOk()
            ->json('rows');

        $this->assertCount(2, $response);
        $this->assertSame([2, 2], collect($response)->pluck('reusable_quantity')->sort()->values()->all());
    }

    public function test_requested_assets_api_status_uses_discipline_aware_reserved_count()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $project = Project::factory()->create();
        $disciplineA = Discipline::create(['name' => 'Operations', 'created_by' => $requester->id]);
        $disciplineB = Discipline::create(['name' => 'Software', 'created_by' => $requester->id]);
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);
        $reservedStatus = Statuslabel::factory()->create([
            'name' => 'Reserved for RFQ',
            'deployable' => 1,
            'default_label' => 0,
        ]);
        $settings = Setting::getSettings();
        $settings->rfq_reserved_statuslabel_id = null;
        $settings->save();
        Setting::$_cache = $settings->fresh();

        Asset::factory()->create([
            'model_id' => $model->id,
            'company_id' => Company::factory()->create()->id,
            'discipline_id' => $disciplineA->id,
            'project_id' => $project->id,
            'status_id' => $reservedStatus->id,
            'requestable' => 1,
            'assigned_to' => User::factory()->create()->id,
            'assigned_type' => User::class,
        ]);

        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'requested_discipline_id' => $disciplineA->id,
            'company_id' => Company::factory()->create()->id,
            'project_id' => $project->id,
            'quantity' => 1,
        ]);

        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'requested_discipline_id' => $disciplineB->id,
            'company_id' => Company::factory()->create()->id,
            'project_id' => $project->id,
            'quantity' => 1,
        ]);

        $rows = $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index', ['project_id' => $project->id]))
            ->assertOk()
            ->json('rows');

        $indexedByDiscipline = collect($rows)->keyBy('requested_discipline');

        $this->assertSame('Fully allocated', $indexedByDiscipline['Operations']['status']);
        $this->assertSame(1, $indexedByDiscipline['Operations']['reserved_count']);
        $this->assertSame('Fully allocated', $indexedByDiscipline['Software']['status']);
        $this->assertSame(1, $indexedByDiscipline['Software']['reserved_count']);
    }

    public function test_requested_assets_api_can_filter_to_single_model()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $modelA = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'name' => 'Filtered Model',
        ]);
        $modelB = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'name' => 'Other Model',
        ]);

        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $modelA->id,
            'requestable_type' => AssetModel::class,
            'quantity' => 2,
        ]);

        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $modelB->id,
            'requestable_type' => AssetModel::class,
            'quantity' => 1,
        ]);

        $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index', ['model_id' => $modelA->id]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.name', 'Filtered Model');
    }

    public function test_requested_assets_api_can_filter_by_project_name_from_advanced_search()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $matchingProject = Project::factory()->create(['name' => 'Alpha Expansion']);
        $otherProject = Project::factory()->create(['name' => 'Beta Rollout']);
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'name' => 'Project Search Model',
        ]);

        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'project_id' => $matchingProject->id,
            'quantity' => 1,
        ]);

        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'project_id' => $otherProject->id,
            'quantity' => 1,
        ]);

        $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index', ['project' => 'Alpha Expansion']))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.project', 'Alpha Expansion');
    }

    public function test_requested_assets_api_can_filter_to_single_project_by_id()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $matchingProject = Project::factory()->create(['name' => 'Project One']);
        $otherProject = Project::factory()->create(['name' => 'Project Two']);
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'name' => 'Project Filter Model',
        ]);

        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'project_id' => $matchingProject->id,
            'quantity' => 1,
        ]);

        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'project_id' => $otherProject->id,
            'quantity' => 1,
        ]);

        $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index', ['project_id' => $matchingProject->id]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.project', 'Project One');
    }

    public function test_project_requests_tab_shows_request_review_for_requester()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $project = Project::factory()->create(['name' => 'Requests Tab Project']);
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'name' => 'Requests Tab Model',
        ]);

        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'project_id' => $project->id,
            'quantity' => 2,
        ]);

        $this->actingAs($requester)
            ->get(route('projects.show', ['project' => $project->id, 'tab' => 'requests']))
            ->assertOk()
            ->assertSee('Requests')
            ->assertSee('projectRequestsTable', false)
            ->assertSee('Quantity')
            ->assertSee('Reference Price')
            ->assertDontSee('Potentially Coverable');
    }

    public function test_requester_cannot_open_project_assets_tab_from_project_requests_view()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $project = Project::factory()->create(['name' => 'Restricted Project']);
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'project_id' => $project->id,
            'quantity' => 1,
        ]);

        $this->actingAs($requester)
            ->get(route('projects.show', ['project' => $project->id, 'tab' => 'assets']))
            ->assertForbidden();
    }

    public function test_submitted_requests_page_shows_reference_price_column()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'project_id' => $project->id,
            'reference_price_snapshot' => 1250.00,
        ]);

        $this->actingAs($requester)
            ->get(route('requests.index'))
            ->assertOk()
            ->assertSee('Reference Price');
    }

    public function test_requester_can_open_request_detail_in_hardware_view()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $company = Company::factory()->create(['name' => 'Casablanca Site']);
        $discipline = Discipline::create(['name' => 'Power', 'created_by' => $requester->id]);
        $project = Project::factory()->create();
        $reservedStatus = Statuslabel::factory()->create([
            'name' => 'Reserved for RFQ',
            'deployable' => 1,
            'default_label' => 0,
        ]);
        $settings = Setting::getSettings();
        $settings->rfq_reserved_statuslabel_id = $reservedStatus->id;
        $settings->save();
        Setting::$_cache = $settings->fresh();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'name' => 'Allocatable Model',
        ]);

        $request = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'status' => CheckoutRequest::STATUS_PENDING,
            'project_id' => $project->id,
            'requested_discipline_id' => $discipline->id,
        ]);

        Asset::factory()->create([
            'model_id' => $model->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
            'project_id' => $project->id,
            'status_id' => $reservedStatus->id,
            'requestable' => 1,
            'assigned_to' => $requester->id,
            'assigned_type' => User::class,
        ]);

        $this->actingAs($requester)
            ->get(route('hardware.index', [
                'request_id' => $request->id,
                'model_id' => $model->id,
                'project_id' => $project->id,
                'discipline_id' => $discipline->id,
                'status_id' => $reservedStatus->id,
            ]))
            ->assertOk()
            ->assertSee('Request #'.$request->id)
            ->assertSee('request_id='.$request->id, false)
            ->assertSee('model_id='.$model->id, false)
            ->assertSee('project_id='.$project->id, false)
            ->assertSee('discipline_id='.$discipline->id, false)
            ->assertSee('status_id='.$reservedStatus->id, false);
    }

    public function test_requested_assets_api_points_request_detail_url_to_unfiltered_request_review()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $project = Project::factory()->create();
        $discipline = Discipline::create(['name' => 'Reserved Detail', 'created_by' => $requester->id]);
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        $checkoutRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'project_id' => $project->id,
            'requested_discipline_id' => $discipline->id,
        ]);

        $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index'))
            ->assertOk()
            ->assertJsonPath('rows.0.request_detail_url', route('hardware.index', [
                'request_id' => $checkoutRequest->id,
                'model_id' => $model->id,
            ]));
    }

    public function test_requested_assets_api_exposes_bucket_specific_asset_review_urls()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $project = Project::factory()->create();
        $discipline = Discipline::create(['name' => 'Bucket Links', 'created_by' => $requester->id]);
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        $checkoutRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'project_id' => $project->id,
            'requested_discipline_id' => $discipline->id,
        ]);

        $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index'))
            ->assertOk()
            ->assertJsonPath('rows.0.reusable_now_url', route('hardware.index', [
                'request_id' => $checkoutRequest->id,
                'request_bucket' => 'reusable_now',
            ]))
            ->assertJsonPath('rows.0.due_back_url', route('hardware.index', [
                'request_id' => $checkoutRequest->id,
                'request_bucket' => 'due_back',
            ]))
            ->assertJsonPath('rows.0.reserved_assets_url', route('hardware.index', [
                'request_id' => $checkoutRequest->id,
                'request_bucket' => 'reserved',
            ]))
            ->assertJsonPath('rows.0.reserved_by_other_project_url', route('hardware.index', [
                'request_id' => $checkoutRequest->id,
                'request_bucket' => 'reserved_other_project',
            ]));
    }

    public function test_request_bucket_filters_return_the_expected_assets()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $company = Company::factory()->create();
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $discipline = Discipline::create(['name' => 'Bucket Filter', 'created_by' => $requester->id]);
        $otherDiscipline = Discipline::create(['name' => 'Other Bucket Filter', 'created_by' => $requester->id]);
        $reservedStatus = Statuslabel::factory()->create([
            'name' => 'Reserved for RFQ',
            'deployable' => 1,
            'default_label' => 0,
        ]);
        $settings = Setting::getSettings();
        $settings->rfq_reserved_statuslabel_id = $reservedStatus->id;
        $settings->save();
        Setting::$_cache = $settings->fresh();

        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        $checkoutRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'project_id' => $project->id,
            'requested_discipline_id' => $discipline->id,
            'needed_by_date' => '2026-06-20',
        ]);

        $reusableAsset = $this->createEligibleAsset($model, $company->id, $discipline->id);

        $dueBackAsset = $this->createEligibleAsset($model, $company->id, $otherDiscipline->id);
        $dueBackAsset->assigned_to = $requester->id;
        $dueBackAsset->assigned_type = User::class;
        $dueBackAsset->expected_checkin = '2026-06-15';
        $dueBackAsset->save();

        $reservedAsset = $this->createEligibleAsset($model, $company->id, $discipline->id);
        $reservedAsset->project_id = $project->id;
        $reservedAsset->discipline_id = $discipline->id;
        $reservedAsset->status_id = $reservedStatus->id;
        $reservedAsset->assigned_to = $requester->id;
        $reservedAsset->assigned_type = User::class;
        $reservedAsset->save();

        $reservedOtherProjectAsset = $this->createEligibleAsset($model, $company->id, $otherDiscipline->id);
        $reservedOtherProjectAsset->project_id = $otherProject->id;
        $reservedOtherProjectAsset->status_id = $reservedStatus->id;
        $reservedOtherProjectAsset->assigned_to = $requester->id;
        $reservedOtherProjectAsset->assigned_type = User::class;
        $reservedOtherProjectAsset->save();

        $this->actingAsForApi($requester)
            ->getJson(route('api.assets.index', [
                'request_id' => $checkoutRequest->id,
                'request_bucket' => 'reusable_now',
            ]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.id', $reusableAsset->id);

        $this->actingAsForApi($requester)
            ->getJson(route('api.assets.index', [
                'request_id' => $checkoutRequest->id,
                'request_bucket' => 'due_back',
            ]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.id', $dueBackAsset->id);

        $this->actingAsForApi($requester)
            ->getJson(route('api.assets.index', [
                'request_id' => $checkoutRequest->id,
                'request_bucket' => 'reserved',
            ]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.id', $reservedAsset->id);

        $this->actingAsForApi($requester)
            ->getJson(route('api.assets.index', [
                'request_id' => $checkoutRequest->id,
                'request_bucket' => 'reserved_other_project',
            ]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.id', $reservedOtherProjectAsset->id);
    }

    public function test_request_bucket_is_forwarded_by_the_hardware_review_page_table()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $project = Project::factory()->create();
        $discipline = Discipline::create(['name' => 'Forward Bucket', 'created_by' => $requester->id]);
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        $checkoutRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'project_id' => $project->id,
            'requested_discipline_id' => $discipline->id,
        ]);

        $this->actingAs($requester)
            ->get(route('hardware.index', [
                'request_id' => $checkoutRequest->id,
                'request_bucket' => 'reserved',
            ]))
            ->assertOk()
            ->assertSee('request-'.$checkoutRequest->id.'-reserved-assetsListingTable', false)
            ->assertSee('data-search-text=""', false)
            ->assertSee('request_bucket=reserved', false);
    }

    public function test_request_filtered_assets_api_keeps_showing_project_booked_assets_for_the_request()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $coordinator = User::factory()->viewAssets()->create();
        $company = Company::factory()->create(['name' => 'Casablanca Site']);
        $discipline = Discipline::create(['name' => 'Power', 'created_by' => $requester->id]);
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'name' => 'Request Workspace Model',
        ]);

        $request = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'project_id' => $project->id,
        ]);

        $asset = $this->createEligibleAsset($model, $company->id, $discipline->id);
        $asset->project_id = $project->id;
        $asset->save();
        $asset->checkOut($requester, $coordinator, now(), null, 'Allocated in request workspace');

        $this->actingAsForApi($requester)
            ->getJson(route('api.assets.index', [
                'request_id' => $request->id,
                'status' => 'RTD',
                'model_id' => $model->id,
            ]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.id', $asset->id)
            ->assertJsonPath('rows.0.assigned_to.id', $requester->id);
    }

    public function test_request_detail_hides_submit_controls_in_simplified_flow()
    {
        $requester = User::factory()->viewAssets()->create();
        $company = Company::factory()->create(['name' => 'Casablanca Site']);
        $discipline = Discipline::create(['name' => 'Power', 'created_by' => $requester->id]);
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'name' => 'Scoped Allocatable Model',
        ]);

        $request = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'status' => CheckoutRequest::STATUS_PENDING,
            'project_id' => $project->id,
        ]);

        $asset = $this->createEligibleAsset($model, $company->id, $discipline->id);

        $this->actingAs($requester)
            ->get(route('hardware.index', [
                'request_id' => $request->id,
                'status' => 'RTD',
                'model_id' => $model->id,
            ]))
            ->assertOk()
            ->assertSee('Request #'.$request->id)
            ->assertDontSee('>Submit<', false)
            ->assertSee('request_id='.$request->id, false)
            ->assertSee('model_id='.$model->id, false);
    }

    public function test_model_request_can_exceed_reusable_now_and_persists_shortfall()
    {
        Notification::fake();

        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'reference_price' => 250,
        ]);

        $discipline = Discipline::create([
            'name' => 'Validation',
            'created_by' => $requester->id,
        ]);
        $this->createEligibleAsset($model, Company::factory()->create()->id, $discipline->id);
        $destinationCompany = Company::factory()->create();

        $this->actingAs($requester)
            ->from(route('requestable-assets'))
            ->post(route('account/request-item', ['itemType' => 'asset_model', 'itemId' => $model->id]), [
                'request-action' => 'create',
                'request-quantity' => 2,
                'requested_discipline_id' => $discipline->id,
                'company_id' => $destinationCompany->id,
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-01',
            ])
            ->assertRedirect(route('requestable-assets'));

        $this->assertDatabaseHas('checkout_requests', [
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'quantity' => 2,
            'reusable_quantity' => 1,
            'due_back_before_needed_by_quantity' => 0,
            'potentially_coverable_quantity' => 1,
            'procurement_shortfall' => 1,
        ]);
    }

    public function test_model_request_update_reuses_existing_request_and_updates_quantity()
    {
        Notification::fake();

        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $discipline = Discipline::create(['name' => 'Power', 'created_by' => $requester->id]);
        $company = Company::factory()->create(['name' => 'Casablanca Site']);
        $coordinator = User::factory()->create(['first_name' => 'Casablanca', 'last_name' => 'RAC']);
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        $this->createEligibleAsset($model, $company->id, $discipline->id);
        $this->createEligibleAsset($model, $company->id, $discipline->id);

        RegionalAssetCoordinatorAssignment::create([
            'user_id' => $coordinator->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
            'created_by' => $requester->id,
        ]);

        $existingRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'quantity' => 1,
            'requested_discipline_id' => $discipline->id,
            'company_id' => $company->id,
            'project_id' => $project->id,
        ]);

        $existingRequest->coordinatorTargets()->create([
            'user_id' => $coordinator->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
        ]);

        $this->actingAs($requester)
            ->post(route('account/request-item', ['itemType' => 'asset_model', 'itemId' => $model->id]), [
                'request-action' => 'update',
                'request-quantity' => 5,
                'requested_discipline_id' => $discipline->id,
                'company_id' => $company->id,
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-15',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('checkout_requests', [
            'id' => $existingRequest->id,
            'quantity' => 5,
            'project_id' => $project->id,
            'reusable_quantity' => 2,
            'due_back_before_needed_by_quantity' => 0,
            'potentially_coverable_quantity' => 2,
            'procurement_shortfall' => 3,
            'estimated_savings' => number_format($model->reference_price * 2, 2, '.', ''),
        ]);

        $this->assertSame('2026-06-15', optional($existingRequest->fresh()->needed_by_date)->format('Y-m-d'));

        $this->assertSame(
            1,
            CheckoutRequest::query()
                ->where('user_id', $requester->id)
                ->where('requestable_id', $model->id)
                ->where('requestable_type', AssetModel::class)
                ->count()
        );
    }

    public function test_requester_can_modify_existing_submitted_request_by_exact_row()
    {
        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $project = Project::factory()->create();
        $updatedProject = Project::factory()->create();
        $company = Company::factory()->create();
        $updatedCompany = Company::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'reference_price' => 400,
        ]);

        $discipline = Discipline::create([
            'name' => 'Scoped Update',
            'created_by' => $requester->id,
        ]);
        $this->createEligibleAsset($model, Company::factory()->create()->id, $discipline->id);

        $request = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'requested_discipline_id' => $discipline->id,
            'company_id' => $company->id,
            'project_id' => $project->id,
            'quantity' => 1,
            'needed_by_date' => '2026-06-01',
        ]);

        $this->actingAs($requester)
            ->post(route('requests.update', $request), [
                'request-action' => 'update',
                'request-quantity' => 2,
                'requested_discipline_id' => $discipline->id,
                'company_id' => $updatedCompany->id,
                'project_id' => $updatedProject->id,
                'needed_by_date' => '2026-07-01',
            ])
            ->assertRedirect();

        $request->refresh();

        $this->assertSame(2, $request->quantity);
        $this->assertSame($updatedProject->id, $request->project_id);
        $this->assertSame($updatedCompany->id, $request->company_id);
        $this->assertSame('2026-07-01', optional($request->needed_by_date)->format('Y-m-d'));
        $this->assertSame(1, $request->reusable_quantity);
        $this->assertSame(1, $request->procurement_shortfall);
        $this->assertSame(400.0, (float) $request->estimated_savings);
    }

    public function test_model_request_requires_company()
    {
        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);
        $discipline = Discipline::create([
            'name' => 'Company Required',
            'created_by' => $requester->id,
        ]);
        $this->createEligibleAsset($model, Company::factory()->create()->id, $discipline->id);

        $this->actingAs($requester)
            ->from(route('requestable-assets'))
            ->post(route('account/request-item', ['itemType' => 'asset_model', 'itemId' => $model->id]), [
                'request-action' => 'create',
                'request-quantity' => 1,
                'requested_discipline_id' => $discipline->id,
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-01',
            ])
            ->assertSessionHasErrors('company_id');
    }

    public function test_model_request_duplicate_detection_is_scoped_by_destination_company()
    {
        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);
        $discipline = Discipline::create([
            'name' => 'Duplicate Scope',
            'created_by' => $requester->id,
        ]);
        $sourceCompany = Company::factory()->create();
        $destinationCompanyA = Company::factory()->create();
        $destinationCompanyB = Company::factory()->create();
        $this->createEligibleAsset($model, $sourceCompany->id, $discipline->id);

        $this->actingAs($requester)
            ->post(route('account/request-item', ['itemType' => 'asset_model', 'itemId' => $model->id]), [
                'request-action' => 'create',
                'request-quantity' => 1,
                'requested_discipline_id' => $discipline->id,
                'company_id' => $destinationCompanyA->id,
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-01',
            ])
            ->assertRedirect();

        $this->actingAs($requester)
            ->post(route('account/request-item', ['itemType' => 'asset_model', 'itemId' => $model->id]), [
                'request-action' => 'create',
                'request-quantity' => 1,
                'requested_discipline_id' => $discipline->id,
                'company_id' => $destinationCompanyB->id,
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-01',
            ])
            ->assertRedirect();

        $this->actingAs($requester)
            ->from(route('requestable-assets'))
            ->post(route('account/request-item', ['itemType' => 'asset_model', 'itemId' => $model->id]), [
                'request-action' => 'create',
                'request-quantity' => 1,
                'requested_discipline_id' => $discipline->id,
                'company_id' => $destinationCompanyA->id,
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-01',
            ])
            ->assertSessionHasErrors('project_id');

        $this->assertSame(
            2,
            CheckoutRequest::query()
                ->where('user_id', $requester->id)
                ->where('requestable_id', $model->id)
                ->where('requestable_type', AssetModel::class)
                ->whereNull('canceled_at')
                ->count()
        );
    }

    public function test_requester_can_cancel_submitted_request_without_hard_deleting_it()
    {
        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        $request = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'project_id' => $project->id,
            'quantity' => 1,
        ]);

        $this->actingAs($requester)
            ->post(route('requests.cancel', $request))
            ->assertRedirect();

        $request->refresh();

        $this->assertNotNull($request->canceled_at);
        $this->assertSame(CheckoutRequest::STATUS_CANCELED, $request->status);

        $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index'))
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_model_request_submission_without_reference_price_sets_zero_savings()
    {
        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'reference_price' => null,
        ]);

        $discipline = Discipline::create([
            'name' => 'Unpriced',
            'created_by' => $requester->id,
        ]);
        $this->createEligibleAsset($model, Company::factory()->create()->id, $discipline->id);
        $destinationCompany = Company::factory()->create();

        $this->actingAs($requester)
            ->from(route('requestable-assets'))
            ->post(route('account/request-item', ['itemType' => 'asset_model', 'itemId' => $model->id]), [
                'request-action' => 'create',
                'request-quantity' => 1,
                'requested_discipline_id' => $discipline->id,
                'company_id' => $destinationCompany->id,
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-01',
            ])
            ->assertRedirect(route('requestable-assets'));

        $this->assertDatabaseHas('checkout_requests', [
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'reusable_quantity' => 1,
            'due_back_before_needed_by_quantity' => 0,
            'potentially_coverable_quantity' => 1,
            'procurement_shortfall' => 0,
            'estimated_savings' => 0,
            'reference_price_snapshot' => null,
        ]);
    }

    public function test_requester_can_create_project_from_request_flow()
    {
        $requester = User::factory()->requestAssetModels()->create();

        $this->actingAs($requester)
            ->postJson(route('account.request-projects.store'), [
                'name' => 'Popup Project',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('payload.name', 'Popup Project');

        $this->assertDatabaseHas('projects', [
            'name' => 'Popup Project',
            'created_by' => $requester->id,
        ]);
    }

    public function test_model_request_estimate_endpoint_returns_snapshot()
    {
        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'reference_price' => 325.50,
        ]);

        $this->createEligibleAsset($model, Company::factory()->create()->id, Discipline::create([
            'name' => 'Estimator',
            'created_by' => $requester->id,
        ])->id);
        $destinationCompany = Company::factory()->create();

        $this->actingAs($requester)
            ->postJson(route('account.request-estimate', ['itemType' => 'asset_model', 'itemId' => $model->id]), [
                'request-quantity' => 3,
                'company_id' => $destinationCompany->id,
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-01',
            ])
            ->assertOk()
            ->assertJson([
                'requested_quantity' => 3,
                'reusable_now' => 1,
                'reusable_quantity' => 1,
                'due_back_before_needed_by_quantity' => 0,
                'potentially_coverable_by_needed_by' => 1,
                'procurement_shortfall' => 2,
                'estimated_savings' => 325.5,
                'reference_price_snapshot' => 325.5,
            ]);
    }

    public function test_bulk_model_request_uses_inline_quantities_with_shared_metadata()
    {
        Notification::fake();

        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $project = Project::factory()->create();
        $category = $this->managedAssetCategoryFor($requester);
        $modelA = AssetModel::factory()->create([
            'category_id' => $category->id,
            'reference_price' => 100,
        ]);
        $modelB = AssetModel::factory()->create([
            'category_id' => $category->id,
            'reference_price' => 200,
        ]);

        $this->createEligibleAsset($modelA, Company::factory()->create()->id, Discipline::create([
            'name' => 'Bulk A',
            'created_by' => $requester->id,
        ])->id);
        $this->createEligibleAsset($modelB, Company::factory()->create()->id, Discipline::create([
            'name' => 'Bulk B',
            'created_by' => $requester->id,
        ])->id);
        $destinationCompany = Company::factory()->create();

        $this->actingAs($requester)
            ->post(route('account.request-items-bulk'), [
                'company_id' => $destinationCompany->id,
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-20',
                'model_quantities' => [
                    $modelA->id => 1,
                    $modelB->id => 1,
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('checkout_requests', [
            'requestable_id' => $modelA->id,
            'requestable_type' => AssetModel::class,
            'project_id' => $project->id,
            'quantity' => 1,
            'potentially_coverable_quantity' => 1,
        ]);

        $this->assertDatabaseHas('checkout_requests', [
            'requestable_id' => $modelB->id,
            'requestable_type' => AssetModel::class,
            'project_id' => $project->id,
            'quantity' => 1,
            'potentially_coverable_quantity' => 1,
        ]);

        $this->assertSame(
            '2026-06-20',
            optional(
                CheckoutRequest::query()
                    ->where('user_id', $requester->id)
                    ->where('requestable_id', $modelA->id)
                    ->where('requestable_type', AssetModel::class)
                    ->first()
                    ?->needed_by_date
            )->format('Y-m-d')
        );

        $this->assertSame(
            '2026-06-20',
            optional(
                CheckoutRequest::query()
                    ->where('user_id', $requester->id)
                    ->where('requestable_id', $modelB->id)
                    ->where('requestable_type', AssetModel::class)
                    ->first()
                    ?->needed_by_date
            )->format('Y-m-d')
        );
    }

    public function test_request_cart_submit_creates_distinct_requests_for_same_model_across_disciplines()
    {
        Notification::fake();

        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'reference_price' => 100,
        ]);
        $disciplineA = Discipline::create(['name' => 'Electrical', 'created_by' => $requester->id]);
        $disciplineB = Discipline::create(['name' => 'Mechanical', 'created_by' => $requester->id]);
        $companyId = Company::factory()->create()->id;

        $this->createEligibleAsset($model, $companyId, $disciplineA->id);

        $this->actingAs($requester)
            ->postJson(route('account.request-cart.items.add'), [
                'lines' => [
                    [
                        'model_id' => $model->id,
                        'quantity' => 2,
                        'discipline_id' => $disciplineA->id,
                        'company_id' => $companyId,
                    ],
                    [
                        'model_id' => $model->id,
                        'quantity' => 1,
                        'discipline_id' => $disciplineB->id,
                        'company_id' => $companyId,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('cart_count', 2);

        $this->actingAs($requester)
            ->post(route('account.request-cart.submit'), [
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-20',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('checkout_requests', [
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'project_id' => $project->id,
            'requested_discipline_id' => $disciplineA->id,
            'company_id' => $companyId,
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('checkout_requests', [
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'project_id' => $project->id,
            'requested_discipline_id' => $disciplineB->id,
            'company_id' => $companyId,
            'quantity' => 1,
        ]);
    }

    public function test_request_cart_submit_creates_distinct_requests_for_same_model_and_discipline_across_companies()
    {
        Notification::fake();

        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'reference_price' => 100,
        ]);
        $discipline = Discipline::create(['name' => 'Shared Discipline', 'created_by' => $requester->id]);
        $companyAId = Company::factory()->create()->id;
        $companyBId = Company::factory()->create()->id;

        $this->createEligibleAsset($model, $companyAId, $discipline->id);
        $this->createEligibleAsset($model, $companyBId, $discipline->id);

        $this->actingAs($requester)
            ->postJson(route('account.request-cart.items.add'), [
                'lines' => [
                    [
                        'model_id' => $model->id,
                        'quantity' => 2,
                        'discipline_id' => $discipline->id,
                        'company_id' => $companyAId,
                    ],
                    [
                        'model_id' => $model->id,
                        'quantity' => 1,
                        'discipline_id' => $discipline->id,
                        'company_id' => $companyBId,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('cart_count', 2);

        $this->actingAs($requester)
            ->post(route('account.request-cart.submit'), [
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-20',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('checkout_requests', [
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'project_id' => $project->id,
            'requested_discipline_id' => $discipline->id,
            'company_id' => $companyAId,
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('checkout_requests', [
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'project_id' => $project->id,
            'requested_discipline_id' => $discipline->id,
            'company_id' => $companyBId,
            'quantity' => 1,
        ]);
    }

    public function test_request_cart_preview_discards_legacy_lines_missing_company()
    {
        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'reference_price' => 100,
        ]);
        $discipline = Discipline::create(['name' => 'Legacy Cart Discipline', 'created_by' => $requester->id]);

        $response = $this->actingAs($requester)
            ->withSession([
                'model_request_cart' => [
                    $model->id.':'.$discipline->id => [
                        'model_id' => $model->id,
                        'quantity' => 2,
                        'discipline_id' => $discipline->id,
                    ],
                ],
            ])
            ->postJson(route('account.request-cart.preview'), [
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-20',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('cart_count', 0)
            ->assertJsonPath('lines', []);

        $this->assertSame([], session('model_request_cart'));
    }

    public function test_request_cart_submit_includes_requested_date_for_rac_notifications()
    {
        Notification::fake();

        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'reference_price' => 100,
        ]);
        $discipline = Discipline::create(['name' => 'Cart Notify', 'created_by' => $requester->id]);
        $coordinator = User::factory()->create(['first_name' => 'Notify', 'last_name' => 'RAC']);
        $companyId = Company::factory()->create()->id;

        $this->createEligibleAsset($model, $companyId, $discipline->id);

        RegionalAssetCoordinatorAssignment::create([
            'user_id' => $coordinator->id,
            'company_id' => $companyId,
            'discipline_id' => $discipline->id,
            'created_by' => $requester->id,
        ]);

        $this->actingAs($requester)
            ->postJson(route('account.request-cart.items.add'), [
                'lines' => [
                    [
                        'model_id' => $model->id,
                        'quantity' => 2,
                        'discipline_id' => $discipline->id,
                        'company_id' => $companyId,
                    ],
                ],
            ])
            ->assertOk();

        $this->actingAs($requester)
            ->post(route('account.request-cart.submit'), [
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-20',
            ])
            ->assertRedirect();

        Notification::assertSentTo($coordinator, RacScopedRequestSummaryNotification::class, function ($notification) use ($project) {
            return $notification->projectName() === $project->name
                && count($notification->lines()) === 1
                && $notification->lines()[0]['requested_quantity'] === 2
                && $notification->lines()[0]['reusable_quantity'] === 1;
        });
    }

    public function test_request_cart_submit_batches_multiple_relevant_lines_into_one_rac_email()
    {
        Notification::fake();

        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $project = Project::factory()->create();
        $disciplineA = Discipline::create(['name' => 'Electrical', 'created_by' => $requester->id]);
        $disciplineB = Discipline::create(['name' => 'Mechanical', 'created_by' => $requester->id]);
        $coordinator = User::factory()->create(['first_name' => 'Batch', 'last_name' => 'RAC']);
        $companyId = Company::factory()->create()->id;
        $modelA = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);
        $modelB = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        $this->createEligibleAsset($modelA, $companyId, $disciplineA->id);
        $this->createEligibleAsset($modelB, $companyId, $disciplineB->id);

        RegionalAssetCoordinatorAssignment::create([
            'user_id' => $coordinator->id,
            'company_id' => $companyId,
            'discipline_id' => $disciplineA->id,
            'created_by' => $requester->id,
        ]);
        RegionalAssetCoordinatorAssignment::create([
            'user_id' => $coordinator->id,
            'company_id' => $companyId,
            'discipline_id' => $disciplineB->id,
            'created_by' => $requester->id,
        ]);

        $this->actingAs($requester)
            ->postJson(route('account.request-cart.items.add'), [
                'lines' => [
                    [
                        'model_id' => $modelA->id,
                        'quantity' => 2,
                        'discipline_id' => $disciplineA->id,
                        'company_id' => $companyId,
                    ],
                    [
                        'model_id' => $modelB->id,
                        'quantity' => 1,
                        'discipline_id' => $disciplineB->id,
                        'company_id' => $companyId,
                    ],
                ],
            ])
            ->assertOk();

        $this->actingAs($requester)
            ->post(route('account.request-cart.submit'), [
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-20',
            ])
            ->assertRedirect();

        $this->assertCount(1, Notification::sent($coordinator, RacScopedRequestSummaryNotification::class));
        Notification::assertSentTo($coordinator, RacScopedRequestSummaryNotification::class, function ($notification) use ($project) {
            return $notification->projectName() === $project->name
                && count($notification->lines()) === 2;
        });
    }

    public function test_due_back_only_matches_do_not_trigger_rac_email()
    {
        Notification::fake();

        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $project = Project::factory()->create();
        $discipline = Discipline::create(['name' => 'Due Back Only', 'created_by' => $requester->id]);
        $coordinator = User::factory()->create(['first_name' => 'DueBack', 'last_name' => 'RAC']);
        $companyId = Company::factory()->create()->id;
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        RegionalAssetCoordinatorAssignment::create([
            'user_id' => $coordinator->id,
            'company_id' => $companyId,
            'discipline_id' => $discipline->id,
            'created_by' => $requester->id,
        ]);

        $dueBackAsset = Asset::factory()->create([
            'model_id' => $model->id,
            'company_id' => $companyId,
            'discipline_id' => $discipline->id,
        ]);
        $dueBackAsset->checkOut($requester, $requester, now(), '2026-05-30', 'Due back');

        $this->actingAs($requester)
            ->post(route('account/request-item', ['itemType' => 'asset_model', 'itemId' => $model->id]), [
                'request-quantity' => 1,
                'requested_discipline_id' => $discipline->id,
                'company_id' => $companyId,
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-01',
            ])
            ->assertRedirect();

        Notification::assertNotSentTo($coordinator, RacScopedRequestSummaryNotification::class);
    }

    public function test_reserved_by_other_project_counts_reserved_assets_without_expected_checkin()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $discipline = Discipline::create(['name' => 'Reserved API', 'created_by' => $requester->id]);
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'name' => 'Reserved Tracking Model',
        ]);

        $checkoutRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'requested_discipline_id' => $discipline->id,
            'quantity' => 1,
            'project_id' => $project->id,
        ]);

        $reservedStatus = Statuslabel::factory()->create([
            'name' => 'Reserved for RFQ',
            'deployable' => 1,
            'default_label' => 0,
        ]);

        $settings = Setting::getSettings();
        $settings->rfq_reserved_statuslabel_id = $reservedStatus->id;
        $settings->save();
        Setting::$_cache = $settings->fresh();

        Asset::factory()->create([
            'model_id' => $model->id,
            'company_id' => Company::factory()->create()->id,
            'discipline_id' => $discipline->id,
            'project_id' => $otherProject->id,
            'status_id' => $reservedStatus->id,
            'requestable' => 1,
            'assigned_to' => User::factory()->create()->id,
            'assigned_type' => User::class,
            'expected_checkin' => null,
        ]);

        $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index'))
            ->assertOk()
            ->assertJsonPath('rows.0.reserved_by_other_rfqs_count', 1)
            ->assertJsonPath('rows.0.requested_discipline', 'Reserved API');
    }

    public function test_reserved_count_ignores_request_discipline_when_summarizing_project_stock()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $project = Project::factory()->create();
        $matchingDiscipline = Discipline::create(['name' => 'Matching Discipline', 'created_by' => $requester->id]);
        $otherDiscipline = Discipline::create(['name' => 'Other Discipline', 'created_by' => $requester->id]);
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'name' => 'Reserved Discipline Model',
        ]);
        $reservedStatus = Statuslabel::factory()->create([
            'name' => 'Reserved for RFQ',
            'deployable' => 1,
            'default_label' => 0,
        ]);

        $settings = Setting::getSettings();
        $settings->rfq_reserved_statuslabel_id = null;
        $settings->save();
        Setting::$_cache = $settings->fresh();

        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'requested_discipline_id' => $matchingDiscipline->id,
            'quantity' => 1,
            'project_id' => $project->id,
        ]);

        Asset::factory()->create([
            'model_id' => $model->id,
            'company_id' => Company::factory()->create()->id,
            'discipline_id' => $otherDiscipline->id,
            'project_id' => $project->id,
            'status_id' => $reservedStatus->id,
            'requestable' => 1,
            'assigned_to' => User::factory()->create()->id,
            'assigned_type' => User::class,
        ]);

        Asset::factory()->create([
            'model_id' => $model->id,
            'company_id' => Company::factory()->create()->id,
            'discipline_id' => $matchingDiscipline->id,
            'project_id' => $project->id,
            'status_id' => $reservedStatus->id,
            'requestable' => 1,
            'assigned_to' => User::factory()->create()->id,
            'assigned_type' => User::class,
        ]);

        $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index'))
            ->assertOk()
            ->assertJsonPath('rows.0.reserved_count', 2);
    }

    public function test_model_request_estimate_counts_due_back_assets_before_needed_by_date()
    {
        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'reference_price' => 100,
        ]);

        $disciplineId = Discipline::create([
            'name' => 'Due Back',
            'created_by' => $requester->id,
        ])->id;
        $companyId = Company::factory()->create()->id;

        $this->createEligibleAsset($model, $companyId, $disciplineId);
        Asset::factory()->create([
            'model_id' => $model->id,
            'company_id' => $companyId,
            'discipline_id' => $disciplineId,
            'status_id' => Statuslabel::factory()->rtd()->create()->id,
            'requestable' => 1,
            'assigned_to' => User::factory()->create()->id,
            'assigned_type' => User::class,
            'expected_checkin' => '2026-06-01',
        ]);

        $this->actingAs($requester)
            ->postJson(route('account.request-estimate', ['itemType' => 'asset_model', 'itemId' => $model->id]), [
                'request-quantity' => 3,
                'company_id' => $companyId,
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-01',
            ])
            ->assertOk()
            ->assertJson([
                'requested_quantity' => 3,
                'reusable_now' => 1,
                'due_back_before_needed_by_quantity' => 1,
                'potentially_coverable_by_needed_by' => 2,
                'procurement_shortfall' => 1,
                'estimated_savings' => 200.0,
            ]);
    }

    public function test_model_request_estimate_excludes_rfq_reserved_assets_from_due_back()
    {
        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'reference_price' => 100,
        ]);

        $disciplineId = Discipline::create([
            'name' => 'Reserved Due Back',
            'created_by' => $requester->id,
        ])->id;
        $companyId = Company::factory()->create()->id;
        $reservedStatus = Statuslabel::factory()->create([
            'name' => 'Reserved for RFQ',
            'deployable' => 1,
            'default_label' => 0,
        ]);

        $settings = Setting::getSettings();
        $settings->rfq_reserved_statuslabel_id = $reservedStatus->id;
        $settings->save();
        Setting::$_cache = $settings->fresh();

        $this->createEligibleAsset($model, $companyId, $disciplineId);

        Asset::factory()->create([
            'model_id' => $model->id,
            'company_id' => $companyId,
            'discipline_id' => $disciplineId,
            'project_id' => $otherProject->id,
            'status_id' => $reservedStatus->id,
            'requestable' => 1,
            'assigned_to' => User::factory()->create()->id,
            'assigned_type' => User::class,
            'expected_checkin' => '2026-06-01',
        ]);

        $this->actingAs($requester)
            ->postJson(route('account.request-estimate', ['itemType' => 'asset_model', 'itemId' => $model->id]), [
                'request-quantity' => 3,
                'company_id' => $companyId,
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-01',
            ])
            ->assertOk()
            ->assertJson([
                'requested_quantity' => 3,
                'reusable_now' => 1,
                'due_back_before_needed_by_quantity' => 0,
                'potentially_coverable_by_needed_by' => 1,
                'procurement_shortfall' => 2,
                'estimated_savings' => 100.0,
            ]);
    }

    public function test_authorized_requester_can_export_project_requests_into_reuse_analysis_workbook()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $project = Project::factory()->create(['name' => 'Reuse Export Project']);
        $electricalDiscipline = Discipline::create([
            'name' => 'Electrical',
            'created_by' => $requester->id,
        ]);
        $platformDiscipline = Discipline::create([
            'name' => 'Platform',
            'created_by' => $requester->id,
        ]);
        $company = Company::factory()->create();
        $reservedStatus = Statuslabel::factory()->create([
            'name' => 'Reserved for RFQ',
            'deployable' => 1,
            'default_label' => 0,
        ]);
        $settings = Setting::getSettings();
        $settings->rfq_reserved_statuslabel_id = $reservedStatus->id;
        $settings->save();
        Setting::$_cache = $settings->fresh();

        $electricalModel = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'name' => 'Alpha Meter',
            'reference_price' => 500,
        ]);
        $platformModel = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'name' => 'Canoe Run',
            'reference_price' => 1250,
        ]);

        $this->createEligibleAsset($electricalModel, $company->id, $electricalDiscipline->id);
        $this->createEligibleAsset($platformModel, $company->id, $platformDiscipline->id);

        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $platformModel->id,
            'requestable_type' => AssetModel::class,
            'requested_discipline_id' => $platformDiscipline->id,
            'project_id' => $project->id,
            'quantity' => 2,
            'reference_price_snapshot' => 1250,
        ]);

        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $electricalModel->id,
            'requestable_type' => AssetModel::class,
            'requested_discipline_id' => $electricalDiscipline->id,
            'project_id' => $project->id,
            'quantity' => 1,
            'reference_price_snapshot' => 500,
        ]);

        Asset::factory()->create([
            'model_id' => $platformModel->id,
            'company_id' => $company->id,
            'discipline_id' => $platformDiscipline->id,
            'project_id' => $project->id,
            'status_id' => $reservedStatus->id,
            'requestable' => 1,
            'assigned_to' => User::factory()->create()->id,
            'assigned_type' => User::class,
            'serial' => 'RFQ-PLAT-001',
        ]);

        $response = $this->actingAs($requester)
            ->get(route('projects.requests.export-reuse-analysis', $project));

        $response->assertOk();
        $response->assertDownload('project-reuse-export-project-reuse-analysis-'.date('Y-m-d').'.xlsx');

        $workbookPath = $response->baseResponse->getFile()->getPathname();

        $this->assertSame('Discipline', $this->workbookCellValue($workbookPath, 'B3'));
        $this->assertSame('Category', $this->workbookCellValue($workbookPath, 'C3'));
        $this->assertSame('Model', $this->workbookCellValue($workbookPath, 'D3'));
        $this->assertSame('Reference Price', $this->workbookCellValue($workbookPath, 'E3'));
        $this->assertSame('Quantity', $this->workbookCellValue($workbookPath, 'F3'));
        $this->assertSame('Transfer Cost', $this->workbookCellValue($workbookPath, 'H3'));
        $this->assertSame('Shortfall', $this->workbookCellValue($workbookPath, 'L3'));
        $this->assertSame('Reused Asset List (S/N)', $this->workbookCellValue($workbookPath, 'N3'));

        $this->assertSame('Electrical', $this->workbookCellValue($workbookPath, 'B4'));
        $this->assertSame($electricalModel->category->name, $this->workbookCellValue($workbookPath, 'C4'));
        $this->assertSame('Alpha Meter', $this->workbookCellValue($workbookPath, 'D4'));
        $this->assertSame('500', $this->workbookCellValue($workbookPath, 'E4'));
        $this->assertSame('1', $this->workbookCellValue($workbookPath, 'F4'));
        $this->assertSame('500', $this->workbookCellValue($workbookPath, 'G4'));

        $this->assertSame('Platform', $this->workbookCellValue($workbookPath, 'B5'));
        $this->assertSame($platformModel->category->name, $this->workbookCellValue($workbookPath, 'C5'));
        $this->assertSame('Canoe Run', $this->workbookCellValue($workbookPath, 'D5'));
        $this->assertSame('1250', $this->workbookCellValue($workbookPath, 'E5'));
        $this->assertSame('2', $this->workbookCellValue($workbookPath, 'F5'));
        $this->assertSame('2500', $this->workbookCellValue($workbookPath, 'G5'));
        $this->assertSame('1', $this->workbookCellValue($workbookPath, 'I5'));
        $this->assertSame('1250', $this->workbookCellValue($workbookPath, 'J5'));
        $this->assertSame('1250', $this->workbookCellValue($workbookPath, 'K5'));
        $this->assertSame('1', $this->workbookCellValue($workbookPath, 'L5'));
        $this->assertSame('1250', $this->workbookCellValue($workbookPath, 'M5'));
        $this->assertSame('RFQ-PLAT-001', $this->workbookCellValue($workbookPath, 'N5'));
        $this->assertNull($this->workbookCellValue($workbookPath, 'H5'));
        $this->assertFalse($this->workbookCellHasFormula($workbookPath, 'G5'));
        $this->assertFalse($this->workbookCellHasFormula($workbookPath, 'H5'));

        $this->assertSame('Total (USD)', $this->workbookCellValue($workbookPath, 'D6'));
        $this->assertSame('3000', $this->workbookCellValue($workbookPath, 'G6'));
        $this->assertSame('1750', $this->workbookCellValue($workbookPath, 'J6'));
        $this->assertSame('1750', $this->workbookCellValue($workbookPath, 'K6'));
        $this->assertSame('1250', $this->workbookCellValue($workbookPath, 'M6'));
    }

    public function test_project_requests_reuse_analysis_export_requires_project_request_access()
    {
        $requester = User::factory()->create();
        $project = Project::factory()->create();

        $this->actingAs($requester)
            ->get(route('projects.requests.export-reuse-analysis', $project))
            ->assertForbidden();
    }

    public function test_project_requests_tab_configures_reuse_analysis_toolbar_button()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $project = Project::factory()->create(['name' => 'Requests Export Toolbar']);
        $discipline = Discipline::create([
            'name' => 'Toolbar Discipline',
            'created_by' => $requester->id,
        ]);
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        $this->createEligibleAsset($model, Company::factory()->create()->id, $discipline->id);

        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'requested_discipline_id' => $discipline->id,
            'project_id' => $project->id,
            'quantity' => 1,
        ]);

        $this->actingAs($requester)
            ->get(route('projects.show', ['project' => $project->id, 'tab' => 'requests']))
            ->assertOk()
            ->assertSee('data-buttons="projectRequestButtons"', false)
            ->assertSee(route('projects.requests.export-reuse-analysis', $project), false);
    }

    private function createEligibleAsset(AssetModel $model, int $companyId, int $disciplineId): Asset
    {
        return Asset::factory()->create([
            'model_id' => $model->id,
            'company_id' => $companyId,
            'discipline_id' => $disciplineId,
            'status_id' => Statuslabel::factory()->rtd()->create()->id,
            'requestable' => 1,
            'assigned_to' => null,
            'assigned_type' => null,
        ]);
    }

    private function managedAssetCategoryFor(User $user): Category
    {
        return Category::factory()->forAssets()->create([
            'manager_id' => $user->id,
        ]);
    }

    private function workbookCellValue(string $workbookPath, string $cellReference): ?string
    {
        $zip = new ZipArchive;
        $zip->open($workbookPath);

        $sharedStrings = $this->sharedStringsLookup((string) $zip->getFromName('xl/sharedStrings.xml'));
        $sheetXPath = $this->worksheetXPath((string) $zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();

        $cell = $sheetXPath->query(sprintf('//spreadsheet:c[@r="%s"]', $cellReference))->item(0);
        if (! $cell) {
            return null;
        }

        $valueNode = $sheetXPath->query('./spreadsheet:v', $cell)->item(0);
        if (! $valueNode) {
            return null;
        }

        if ($cell->attributes?->getNamedItem('t')?->nodeValue === 's') {
            return $sharedStrings[(int) $valueNode->textContent] ?? null;
        }

        if (is_numeric($valueNode->textContent)) {
            return rtrim(rtrim(number_format((float) $valueNode->textContent, 10, '.', ''), '0'), '.');
        }

        return $valueNode->textContent;
    }

    private function workbookCellHasFormula(string $workbookPath, string $cellReference): bool
    {
        $zip = new ZipArchive;
        $zip->open($workbookPath);
        $sheetXPath = $this->worksheetXPath((string) $zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();

        return $sheetXPath->query(sprintf('//spreadsheet:c[@r="%s"]/spreadsheet:f', $cellReference))->length > 0;
    }

    private function worksheetXPath(string $worksheetXml): DOMXPath
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadXML($worksheetXml);

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('spreadsheet', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return $xpath;
    }

    private function sharedStringsLookup(string $sharedStringsXml): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadXML($sharedStringsXml);

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('spreadsheet', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $lookup = [];
        foreach ($xpath->query('/spreadsheet:sst/spreadsheet:si') as $index => $sharedStringNode) {
            $text = '';
            foreach ($xpath->query('.//spreadsheet:t', $sharedStringNode) as $textNode) {
                $text .= $textNode->textContent;
            }

            $lookup[$index] = $text;
        }

        return $lookup;
    }
}

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
use App\Models\Statuslabel;
use App\Models\User;
use App\Notifications\RequestAssetNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

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

        Notification::assertSentTo($coordinatorA, RequestAssetNotification::class);
        Notification::assertSentTo($coordinatorB, RequestAssetNotification::class);
    }

    public function test_model_request_requires_models_request_permission()
    {
        $requester = User::factory()->create();
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        $this->createEligibleAsset($model, Company::factory()->create()->id, Discipline::create([
            'name' => 'Validation',
            'created_by' => $requester->id,
        ])->id);

        $this->actingAs($requester)
            ->post(route('account/request-item', ['itemType' => 'asset_model', 'itemId' => $model->id]), [
                'request-quantity' => 1,
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

        $this->createEligibleAsset($model, Company::factory()->create()->id, Discipline::create([
            'name' => 'Scoped Validation',
            'created_by' => $requester->id,
        ])->id);

        $this->actingAs($requester)
            ->post(route('account/request-item', ['itemType' => 'asset_model', 'itemId' => $model->id]), [
                'request-quantity' => 1,
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
            ->get(route('account.requested'))
            ->assertForbidden();
    }

    public function test_submitted_requests_api_requires_models_request_permission()
    {
        $requester = User::factory()->create();

        $this->actingAsForApi($requester)
            ->getJson(route('api.assets.requested'))
            ->assertForbidden();
    }

    public function test_requested_assets_api_returns_project_and_booked_metadata_for_requester()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $project = Project::factory()->create(['name' => 'Request Tracking Project']);
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'name' => 'QA Routing Model',
        ]);

        $checkoutRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'quantity' => 2,
            'project_id' => $project->id,
            'reusable_quantity' => 1,
            'procurement_shortfall' => 1,
            'estimated_savings' => 499.99,
            'reference_price_snapshot' => 499.99,
        ]);

        $this->actingAsForApi($requester)
            ->getJson(route('api.assets.requested'))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.request_id', $checkoutRequest->id)
            ->assertJsonPath('rows.0.qty', 2)
            ->assertJsonPath('rows.0.status', 'Pending')
            ->assertJsonPath('rows.0.project', 'Request Tracking Project')
            ->assertJsonPath('rows.0.booked_count', 0)
            ->assertJsonPath('rows.0.reusable_quantity', 1)
            ->assertJsonPath('rows.0.due_back_before_needed_by_quantity', 0)
            ->assertJsonPath('rows.0.procurement_shortfall', 1)
            ->assertJsonPath('rows.0.estimated_savings', 499.99)
            ->assertJsonPath('rows.0.project_requests_url', route('projects.show', ['project' => $project->id, 'tab' => 'requests']))
            ->assertJsonPath('rows.0.request_update_url', route('account.request-row.update', $checkoutRequest))
            ->assertJsonPath('rows.0.request_cancel_url', route('account.request-row.cancel', $checkoutRequest));
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
            ->getJson(route('api.assets.requested', ['model_id' => $modelA->id]))
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
            ->getJson(route('api.assets.requested', ['project' => 'Alpha Expansion']))
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
            ->getJson(route('api.assets.requested', ['project_id' => $matchingProject->id]))
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
            ->assertDontSee('Potentially Coverable');
    }

    public function test_requester_can_open_request_detail_in_hardware_view()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $company = Company::factory()->create(['name' => 'Casablanca Site']);
        $discipline = Discipline::create(['name' => 'Power', 'created_by' => $requester->id]);
        $project = Project::factory()->create();
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
            ->assertSee('request_id='.$request->id, false)
            ->assertSee('model_id='.$model->id, false);
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

        $this->createEligibleAsset($model, Company::factory()->create()->id, Discipline::create([
            'name' => 'Validation',
            'created_by' => $requester->id,
        ])->id);

        $this->actingAs($requester)
            ->from(route('requestable-assets'))
            ->post(route('account/request-item', ['itemType' => 'asset_model', 'itemId' => $model->id]), [
                'request-action' => 'create',
                'request-quantity' => 2,
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
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-15',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('checkout_requests', [
            'id' => $existingRequest->id,
            'quantity' => 5,
            'project_id' => $project->id,
            'needed_by_date' => '2026-06-15',
            'reusable_quantity' => 2,
            'due_back_before_needed_by_quantity' => 0,
            'potentially_coverable_quantity' => 2,
            'procurement_shortfall' => 3,
            'estimated_savings' => number_format($model->reference_price * 2, 2, '.', ''),
        ]);

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
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'reference_price' => 400,
        ]);

        $this->createEligibleAsset($model, Company::factory()->create()->id, Discipline::create([
            'name' => 'Scoped Update',
            'created_by' => $requester->id,
        ])->id);

        $request = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'project_id' => $project->id,
            'quantity' => 1,
            'needed_by_date' => '2026-06-01',
        ]);

        $this->actingAs($requester)
            ->post(route('account.request-row.update', $request), [
                'request-action' => 'update',
                'request-quantity' => 2,
                'project_id' => $updatedProject->id,
                'needed_by_date' => '2026-07-01',
            ])
            ->assertRedirect();

        $request->refresh();

        $this->assertSame(2, $request->quantity);
        $this->assertSame($updatedProject->id, $request->project_id);
        $this->assertSame('2026-07-01', optional($request->needed_by_date)->format('Y-m-d'));
        $this->assertSame(1, $request->reusable_quantity);
        $this->assertSame(1, $request->procurement_shortfall);
        $this->assertSame(400.0, (float) $request->estimated_savings);
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
            ->post(route('account.request-row.cancel', $request))
            ->assertRedirect();

        $request->refresh();

        $this->assertNotNull($request->canceled_at);
        $this->assertSame(CheckoutRequest::STATUS_CANCELED, $request->status);

        $this->actingAsForApi($requester)
            ->getJson(route('api.assets.requested'))
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

        $this->createEligibleAsset($model, Company::factory()->create()->id, Discipline::create([
            'name' => 'Unpriced',
            'created_by' => $requester->id,
        ])->id);

        $this->actingAs($requester)
            ->from(route('requestable-assets'))
            ->post(route('account/request-item', ['itemType' => 'asset_model', 'itemId' => $model->id]), [
                'request-action' => 'create',
                'request-quantity' => 1,
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

        $this->actingAs($requester)
            ->postJson(route('account.request-estimate', ['itemType' => 'asset_model', 'itemId' => $model->id]), [
                'request-quantity' => 3,
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

        $this->actingAs($requester)
            ->post(route('account.request-items-bulk'), [
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
            'needed_by_date' => '2026-06-20',
            'quantity' => 1,
            'potentially_coverable_quantity' => 1,
        ]);

        $this->assertDatabaseHas('checkout_requests', [
            'requestable_id' => $modelB->id,
            'requestable_type' => AssetModel::class,
            'project_id' => $project->id,
            'needed_by_date' => '2026-06-20',
            'quantity' => 1,
            'potentially_coverable_quantity' => 1,
        ]);
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
}

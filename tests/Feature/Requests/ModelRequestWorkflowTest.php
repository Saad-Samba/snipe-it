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

        $requester = User::factory()->requestAssetModels()->create();
        $disciplineA = Discipline::create(['name' => 'Electrical', 'created_by' => $requester->id]);
        $disciplineB = Discipline::create(['name' => 'Mechanical', 'created_by' => $requester->id]);
        $coordinatorA = User::factory()->create(['first_name' => 'Casablanca', 'last_name' => 'RAC']);
        $coordinatorB = User::factory()->create(['first_name' => 'Rabat', 'last_name' => 'RAC']);
        $companyA = Company::factory()->create(['name' => 'Casablanca Site']);
        $companyB = Company::factory()->create(['name' => 'Rabat Site']);
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => Category::factory()->forAssets()->create()->id,
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
            ])
            ->assertRedirect();

        $checkoutRequest = CheckoutRequest::query()
            ->where('user_id', $requester->id)
            ->where('requestable_id', $model->id)
            ->where('requestable_type', AssetModel::class)
            ->firstOrFail();

        $this->assertSame(2, $checkoutRequest->quantity);
        $this->assertSame('pending', $checkoutRequest->status);

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
            'category_id' => Category::factory()->forAssets()->create()->id,
        ]);

        $this->createEligibleAsset($model, Company::factory()->create()->id, Discipline::create([
            'name' => 'Validation',
            'created_by' => $requester->id,
        ])->id);

        $this->actingAs($requester)
            ->post(route('account/request-item', ['itemType' => 'asset_model', 'itemId' => $model->id]), [
                'request-quantity' => 1,
                'project_id' => $project->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('checkout_requests', [
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
        ]);
    }

    public function test_requested_assets_api_returns_project_and_booked_metadata_for_requester()
    {
        $requester = User::factory()->viewAssets()->create();
        $project = Project::factory()->create(['name' => 'Request Tracking Project']);
        $model = AssetModel::factory()->create([
            'category_id' => Category::factory()->forAssets()->create()->id,
            'name' => 'QA Routing Model',
        ]);

        $checkoutRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'quantity' => 2,
            'project_id' => $project->id,
        ]);

        $this->actingAsForApi($requester)
            ->getJson(route('api.assets.requested'))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.request_id', $checkoutRequest->id)
            ->assertJsonPath('rows.0.qty', 2)
            ->assertJsonPath('rows.0.status', 'Pending')
            ->assertJsonPath('rows.0.project', 'Request Tracking Project')
            ->assertJsonPath('rows.0.booked_count', 0);
    }

    public function test_requested_assets_api_can_filter_to_single_model()
    {
        $requester = User::factory()->viewAssets()->create();
        $modelA = AssetModel::factory()->create([
            'category_id' => Category::factory()->forAssets()->create()->id,
            'name' => 'Filtered Model',
        ]);
        $modelB = AssetModel::factory()->create([
            'category_id' => Category::factory()->forAssets()->create()->id,
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

    public function test_requester_can_open_request_detail_in_hardware_view()
    {
        $requester = User::factory()->viewAssets()->create();
        $company = Company::factory()->create(['name' => 'Casablanca Site']);
        $discipline = Discipline::create(['name' => 'Power', 'created_by' => $requester->id]);
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => Category::factory()->forAssets()->create()->id,
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
        $requester = User::factory()->viewAssets()->create();
        $coordinator = User::factory()->viewAssets()->create();
        $company = Company::factory()->create(['name' => 'Casablanca Site']);
        $discipline = Discipline::create(['name' => 'Power', 'created_by' => $requester->id]);
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => Category::factory()->forAssets()->create()->id,
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

    public function test_request_filtered_assets_api_keeps_obsolete_model_assets_visible_and_flagged()
    {
        $requester = User::factory()->viewAssets()->create();
        $coordinator = User::factory()->viewAssets()->create();
        $company = Company::factory()->create(['name' => 'Casablanca Site']);
        $discipline = Discipline::create(['name' => 'Power', 'created_by' => $requester->id]);
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => Category::factory()->forAssets()->create()->id,
            'name' => 'Obsolete request workspace model',
            'obsolete' => true,
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
            ->assertJsonPath('rows.0.model.obsolete', true)
            ->assertJsonPath('rows.0.assigned_to.id', $requester->id);
    }

    public function test_request_detail_hides_submit_controls_in_simplified_flow()
    {
        $requester = User::factory()->viewAssets()->create();
        $company = Company::factory()->create(['name' => 'Casablanca Site']);
        $discipline = Discipline::create(['name' => 'Power', 'created_by' => $requester->id]);
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => Category::factory()->forAssets()->create()->id,
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
            ->assertDontSee('Submit')
            ->assertSee('request_id='.$request->id, false)
            ->assertSee('model_id='.$model->id, false);
    }

    public function test_model_request_cannot_exceed_remaining_stock()
    {
        $requester = User::factory()->requestAssetModels()->create();
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => Category::factory()->forAssets()->create()->id,
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
            ])
            ->assertRedirect(route('requestable-assets'))
            ->assertSessionHasErrors('request-quantity');

        $this->assertDatabaseMissing('checkout_requests', [
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'quantity' => 2,
        ]);
    }

    public function test_model_request_update_reuses_existing_request_and_updates_quantity()
    {
        Notification::fake();

        $requester = User::factory()->requestAssetModels()->create();
        $discipline = Discipline::create(['name' => 'Power', 'created_by' => $requester->id]);
        $company = Company::factory()->create(['name' => 'Casablanca Site']);
        $coordinator = User::factory()->create(['first_name' => 'Casablanca', 'last_name' => 'RAC']);
        $project = Project::factory()->create();
        $updatedProject = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => Category::factory()->forAssets()->create()->id,
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
                'request-quantity' => 2,
                'project_id' => $updatedProject->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('checkout_requests', [
            'id' => $existingRequest->id,
            'quantity' => 2,
            'project_id' => $updatedProject->id,
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
}

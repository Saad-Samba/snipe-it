<?php

namespace Tests\Feature\Requests;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\CheckoutRequest;
use App\Models\Company;
use App\Models\Discipline;
use App\Models\RegionalAssetCoordinatorAssignment;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class ModelRequestWorkflowTest extends TestCase
{
    public function test_model_request_persists_quantity_and_resolves_candidate_racs_from_asset_stock()
    {
        Notification::fake();

        $requester = User::factory()->viewRequestableAssets()->create();
        $disciplineA = Discipline::create(['name' => 'Electrical', 'created_by' => $requester->id]);
        $disciplineB = Discipline::create(['name' => 'Mechanical', 'created_by' => $requester->id]);
        $coordinatorA = User::factory()->create(['first_name' => 'Casablanca', 'last_name' => 'RAC']);
        $coordinatorB = User::factory()->create(['first_name' => 'Rabat', 'last_name' => 'RAC']);
        $companyA = Company::factory()->create(['name' => 'Casablanca Site']);
        $companyB = Company::factory()->create(['name' => 'Rabat Site']);
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
    }

    public function test_requested_assets_api_returns_candidate_routing_metadata_for_requester()
    {
        $requester = User::factory()->create();
        $coordinatorA = User::factory()->create(['first_name' => 'North', 'last_name' => 'RAC']);
        $coordinatorB = User::factory()->create(['first_name' => 'South', 'last_name' => 'RAC']);
        $companyA = Company::factory()->create(['name' => 'Tangier Site']);
        $companyB = Company::factory()->create(['name' => 'Agadir Site']);
        $disciplineA = Discipline::create(['name' => 'Power', 'created_by' => $requester->id]);
        $disciplineB = Discipline::create(['name' => 'Mechanical', 'created_by' => $requester->id]);
        $model = AssetModel::factory()->create([
            'category_id' => Category::factory()->forAssets()->create()->id,
            'name' => 'QA Routing Model',
        ]);

        $checkoutRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'quantity' => 2,
        ]);

        $checkoutRequest->coordinatorTargets()->createMany([
            [
                'user_id' => $coordinatorA->id,
                'company_id' => $companyA->id,
                'discipline_id' => $disciplineA->id,
            ],
            [
                'user_id' => $coordinatorB->id,
                'company_id' => $companyB->id,
                'discipline_id' => $disciplineB->id,
            ],
        ]);

        $this->actingAsForApi($requester)
            ->getJson(route('api.assets.requested'))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.request_id', $checkoutRequest->id)
            ->assertJsonPath('rows.0.qty', 2)
            ->assertJsonPath('rows.0.status', 'Pending')
            ->assertJsonPath('rows.0.candidate_companies', 'Tangier Site, Agadir Site')
            ->assertJsonPath('rows.0.candidate_coordinators', 'North RAC, South RAC');
    }

    public function test_requested_assets_api_can_filter_to_single_model()
    {
        $requester = User::factory()->create();
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

    public function test_requested_assets_api_can_filter_to_coordinator_queue()
    {
        $requester = User::factory()->create(['first_name' => 'Global', 'last_name' => 'AFM']);
        $discipline = Discipline::create(['name' => 'Power', 'created_by' => $requester->id]);
        $coordinator = User::factory()->create();
        $otherCoordinator = User::factory()->create();
        $company = Company::factory()->create(['name' => 'Marrakech Site']);

        $requestForCoordinator = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'quantity' => 4,
        ]);

        $requestForCoordinator->coordinatorTargets()->create([
            'user_id' => $coordinator->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
        ]);

        $requestForOtherCoordinator = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'quantity' => 1,
        ]);

        $requestForOtherCoordinator->coordinatorTargets()->create([
            'user_id' => $otherCoordinator->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
        ]);

        $this->actingAsForApi($coordinator)
            ->getJson(route('api.assets.requested', ['coordinator' => 1]))
            ->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('total', 1)
                ->where('rows.0.requested_by', $requester->display_name)
                ->where('rows.0.qty', 4)
                ->where('rows.0.status', 'Pending')
                ->etc());
    }

    public function test_coordinator_can_progress_request_statuses()
    {
        Carbon::setTestNow('2026-05-01 18:45:00');

        $requester = User::factory()->create();
        $coordinator = User::factory()->create();
        $company = Company::factory()->create();
        $discipline = Discipline::create(['name' => 'Power', 'created_by' => $requester->id]);

        $checkoutRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'status' => CheckoutRequest::STATUS_PENDING,
        ]);

        $checkoutRequest->coordinatorTargets()->create([
            'user_id' => $coordinator->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
        ]);

        $this->actingAs($coordinator)
            ->post(route('account.requests.status', $checkoutRequest), [
                'status' => CheckoutRequest::STATUS_UNDER_REVIEW,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('checkout_requests', [
            'id' => $checkoutRequest->id,
            'status' => CheckoutRequest::STATUS_UNDER_REVIEW,
        ]);

        $this->actingAs($coordinator)
            ->post(route('account.requests.status', $checkoutRequest), [
                'status' => CheckoutRequest::STATUS_APPROVED,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('checkout_requests', [
            'id' => $checkoutRequest->id,
            'status' => CheckoutRequest::STATUS_APPROVED,
        ]);

        $this->actingAs($coordinator)
            ->post(route('account.requests.status', $checkoutRequest), [
                'status' => CheckoutRequest::STATUS_FULFILLED,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('checkout_requests', [
            'id' => $checkoutRequest->id,
            'status' => CheckoutRequest::STATUS_FULFILLED,
        ]);

        $this->assertNotNull($checkoutRequest->fresh()->fulfilled_at);
        Carbon::setTestNow();
    }

    public function test_model_request_cannot_exceed_remaining_stock()
    {
        $requester = User::factory()->viewRequestableAssets()->create();
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

        $requester = User::factory()->viewRequestableAssets()->create();
        $discipline = Discipline::create(['name' => 'Power', 'created_by' => $requester->id]);
        $company = Company::factory()->create(['name' => 'Casablanca Site']);
        $coordinator = User::factory()->create(['first_name' => 'Casablanca', 'last_name' => 'RAC']);
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
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('checkout_requests', [
            'id' => $existingRequest->id,
            'quantity' => 2,
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

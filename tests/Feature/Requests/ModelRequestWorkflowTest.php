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
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ModelRequestWorkflowTest extends TestCase
{
    public function test_model_request_persists_discipline_and_resolves_candidate_racs()
    {
        Notification::fake();

        $requester = User::factory()->viewRequestableAssets()->create();
        $discipline = Discipline::create(['name' => 'Electrical', 'created_by' => $requester->id]);
        $coordinatorA = User::factory()->create(['first_name' => 'Casablanca', 'last_name' => 'RAC']);
        $coordinatorB = User::factory()->create(['first_name' => 'Rabat', 'last_name' => 'RAC']);
        $companyA = Company::factory()->create(['name' => 'Casablanca Site']);
        $companyB = Company::factory()->create(['name' => 'Rabat Site']);
        $model = AssetModel::factory()->create([
            'category_id' => Category::factory()->forAssets()->create()->id,
            'requestable' => 1,
        ]);

        $this->createEligibleAsset($model, $companyA->id);
        $this->createEligibleAsset($model, $companyB->id);

        RegionalAssetCoordinatorAssignment::create([
            'user_id' => $coordinatorA->id,
            'company_id' => $companyA->id,
            'discipline_id' => $discipline->id,
            'created_by' => $requester->id,
        ]);

        RegionalAssetCoordinatorAssignment::create([
            'user_id' => $coordinatorB->id,
            'company_id' => $companyB->id,
            'discipline_id' => $discipline->id,
            'created_by' => $requester->id,
        ]);

        $this->actingAs($requester)
            ->post(route('account/request-item', ['itemType' => 'asset_model', 'itemId' => $model->id]), [
                'request-quantity' => 3,
                'discipline_id' => $discipline->id,
                'note' => 'Need regional allocation',
            ])
            ->assertRedirect(route('requestable-assets'));

        $checkoutRequest = CheckoutRequest::query()
            ->where('user_id', $requester->id)
            ->where('requestable_id', $model->id)
            ->where('requestable_type', AssetModel::class)
            ->firstOrFail();

        $this->assertSame(3, $checkoutRequest->quantity);
        $this->assertSame($discipline->id, $checkoutRequest->requested_discipline_id);
        $this->assertSame('Need regional allocation', $checkoutRequest->note);

        $this->assertDatabaseHas('checkout_request_coordinators', [
            'checkout_request_id' => $checkoutRequest->id,
            'user_id' => $coordinatorA->id,
            'company_id' => $companyA->id,
            'discipline_id' => $discipline->id,
        ]);

        $this->assertDatabaseHas('checkout_request_coordinators', [
            'checkout_request_id' => $checkoutRequest->id,
            'user_id' => $coordinatorB->id,
            'company_id' => $companyB->id,
            'discipline_id' => $discipline->id,
        ]);
    }

    public function test_requested_assets_api_returns_discipline_and_candidate_routing_metadata_for_requester()
    {
        $requester = User::factory()->create();
        $discipline = Discipline::create(['name' => 'Mechanical', 'created_by' => $requester->id]);
        $coordinatorA = User::factory()->create(['first_name' => 'North', 'last_name' => 'RAC']);
        $coordinatorB = User::factory()->create(['first_name' => 'South', 'last_name' => 'RAC']);
        $companyA = Company::factory()->create(['name' => 'Tangier Site']);
        $companyB = Company::factory()->create(['name' => 'Agadir Site']);
        $model = AssetModel::factory()->create([
            'category_id' => Category::factory()->forAssets()->create()->id,
            'requestable' => 1,
            'name' => 'QA Routing Model',
        ]);

        $checkoutRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'quantity' => 2,
            'requested_discipline_id' => $discipline->id,
            'note' => 'Prioritize available stock',
        ]);

        $checkoutRequest->coordinatorTargets()->createMany([
            [
                'user_id' => $coordinatorA->id,
                'company_id' => $companyA->id,
                'discipline_id' => $discipline->id,
            ],
            [
                'user_id' => $coordinatorB->id,
                'company_id' => $companyB->id,
                'discipline_id' => $discipline->id,
            ],
        ]);

        $this->actingAsForApi($requester)
            ->getJson(route('api.assets.requested'))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.qty', 2)
            ->assertJsonPath('rows.0.discipline', 'Mechanical')
            ->assertJsonPath('rows.0.candidate_companies', 'Tangier Site, Agadir Site')
            ->assertJsonPath('rows.0.candidate_coordinators', 'North RAC, South RAC')
            ->assertJsonPath('rows.0.note', 'Prioritize available stock');
    }

    public function test_requested_assets_api_filters_to_candidate_rac_queue()
    {
        $requester = User::factory()->create(['first_name' => 'Global', 'last_name' => 'AFM']);
        $discipline = Discipline::create(['name' => 'Power', 'created_by' => $requester->id]);
        $coordinator = User::factory()->create();
        $otherCoordinator = User::factory()->create();
        $company = Company::factory()->create(['name' => 'Marrakech Site']);

        $requestForCoordinator = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requested_discipline_id' => $discipline->id,
            'quantity' => 4,
        ]);

        $requestForCoordinator->coordinatorTargets()->create([
            'user_id' => $coordinator->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
        ]);

        $requestForOtherCoordinator = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requested_discipline_id' => $discipline->id,
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
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.requested_by', $requester->display_name)
            ->assertJsonPath('rows.0.qty', 4);
    }

    private function createEligibleAsset(AssetModel $model, int $companyId): Asset
    {
        return Asset::factory()->create([
            'model_id' => $model->id,
            'company_id' => $companyId,
            'status_id' => Statuslabel::factory()->rtd()->create()->id,
            'requestable' => 1,
            'assigned_to' => null,
            'assigned_type' => null,
        ]);
    }
}

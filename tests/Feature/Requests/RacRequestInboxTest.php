<?php

namespace Tests\Feature\Requests;

use App\Http\Controllers\Api\ModelRequestsController as ApiModelRequestsController;
use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\Company;
use App\Models\Discipline;
use App\Models\Project;
use App\Models\RegionalAssetCoordinatorAssignment;
use App\Models\User;
use Tests\TestCase;

class RacRequestInboxTest extends TestCase
{
    public function test_only_racs_can_open_the_received_requests_inbox()
    {
        $ordinaryUser = User::factory()->create();

        $this->actingAs($ordinaryUser)
            ->get(route('rac-requests.index'))
            ->assertForbidden();

        $rac = User::factory()->create();
        $discipline = Discipline::create(['name' => 'Inbox Access', 'created_by' => $ordinaryUser->id]);
        $company = Company::factory()->create();

        RegionalAssetCoordinatorAssignment::create([
            'user_id' => $rac->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
            'created_by' => $ordinaryUser->id,
        ]);

        $this->actingAs($rac)
            ->get(route('rac-requests.index'))
            ->assertOk()
            ->assertSee('Received Requests')
            ->assertSee(route('api.rac-requests.index'), false)
            ->assertSee('Inventory Discipline(s)')
            ->assertSee('>Status</th>', false)
            ->assertDontSee('RAC Status');
    }

    public function test_received_requests_inbox_is_scoped_to_the_rac_and_combines_inventory_disciplines()
    {
        $requester = User::factory()->create();
        $rac = User::factory()->viewAssets()->create();
        $otherRac = User::factory()->viewAssets()->create();
        $electrical = Discipline::create(['name' => 'Electrical', 'created_by' => $requester->id]);
        $mechanical = Discipline::create(['name' => 'Mechanical', 'created_by' => $requester->id]);
        $destinationCompany = Company::factory()->create(['name' => 'Destination Site']);
        $sourceCompany = Company::factory()->create();
        $project = Project::factory()->create(['name' => 'Inbox Project']);
        $model = AssetModel::factory()->create(['name' => 'Inbox Model']);

        $request = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'company_id' => $destinationCompany->id,
            'project_id' => $project->id,
            'quantity' => 3,
            'needed_by_date' => '2026-09-01',
        ]);

        foreach ([$electrical, $mechanical] as $discipline) {
            $request->coordinatorTargets()->create([
                'user_id' => $rac->id,
                'company_id' => $sourceCompany->id,
                'discipline_id' => $discipline->id,
            ]);
        }
        $request->coordinatorTargets()->firstOrFail()->markInProgress();

        $otherRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'quantity' => 1,
        ]);
        $otherRequest->coordinatorTargets()->create([
            'user_id' => $otherRac->id,
            'company_id' => $sourceCompany->id,
            'discipline_id' => $electrical->id,
        ]);

        $this->actingAs($rac);
        $result = app(ApiModelRequestsController::class)->received();

        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame($request->id, $result['rows'][0]['request_id']);
        $this->assertSame('Inbox Model', $result['rows'][0]['name']);
        $this->assertSame('Inbox Project', $result['rows'][0]['project']);
        $this->assertSame('Destination Site', $result['rows'][0]['company']);
        $this->assertSame('Electrical, Mechanical', $result['rows'][0]['inventory_disciplines']);
        $this->assertSame('In progress', $result['rows'][0]['rac_status']);
        $this->assertSame(3, $result['rows'][0]['remaining_quantity']);
        $this->assertSame(
            route('hardware.index', ['request_id' => $request->id, 'request_bucket' => 'reusable_now']),
            $result['rows'][0]['request_detail_url']
        );
        $this->assertNotEmpty($result['rows'][0]['received_at']);
    }

    public function test_received_requests_status_says_no_more_stock_available_after_rac_finishes_review(): void
    {
        $requester = User::factory()->create();
        $rac = User::factory()->create();
        $discipline = Discipline::create(['name' => 'No More Stock', 'created_by' => $requester->id]);
        $company = Company::factory()->create();
        $request = CheckoutRequest::factory()->forAssetModel()->create();

        $target = $request->coordinatorTargets()->create([
            'user_id' => $rac->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
        ]);
        $target->markCompletedNoStock();

        $this->actingAs($rac);
        $result = app(ApiModelRequestsController::class)->received();

        $this->assertSame('No more stock available', $result['rows'][0]['rac_status']);
    }
}

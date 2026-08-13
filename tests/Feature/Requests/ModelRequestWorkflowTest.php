<?php

namespace Tests\Feature\Requests;

use App\Actions\CheckoutRequests\ResolveCheckoutRequestCoordinatorsAction;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\CheckoutRequest;
use App\Models\CheckoutRequestCoordinator;
use App\Models\Company;
use App\Models\Discipline;
use App\Models\Location;
use App\Models\Project;
use App\Models\RegionalAssetCoordinatorAssignment;
use App\Models\Setting;
use App\Models\Statuslabel;
use App\Models\User;
use App\Notifications\RacScopedRequestSummaryNotification;
use App\Notifications\RacRequestRoutingRemovedNotification;
use App\Notifications\RequestAssetNotification;
use App\Notifications\UnroutedRacRequestNotification;
use App\Notifications\RequestAlternativeFollowUpNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
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
        $this->assertDatabaseMissing('checkout_request_coordinators', [
            'checkout_request_id' => $checkoutRequest->id,
            'user_id' => $coordinatorA->id,
            'initial_notified_at' => null,
        ]);
        $this->assertDatabaseMissing('checkout_request_coordinators', [
            'checkout_request_id' => $checkoutRequest->id,
            'user_id' => $coordinatorB->id,
            'initial_notified_at' => null,
        ]);

        Notification::assertSentTo($coordinatorA, RacScopedRequestSummaryNotification::class, function ($notification) use ($project, $disciplineA) {
            return $notification->projectName() === $project->name
                && count($notification->lines()) === 1
                && $notification->lines()[0]['company_name'] === 'Receiving Site'
                && $notification->lines()[0]['discipline_name'] === $disciplineA->name
                && $notification->lines()[0]['inventory_discipline_names'] === [$disciplineA->name]
                && $notification->lines()[0]['reusable_quantity'] === 1;
        });
        Notification::assertSentTo($coordinatorB, RacScopedRequestSummaryNotification::class, function ($notification) use ($project, $disciplineA, $disciplineB) {
            return $notification->projectName() === $project->name
                && count($notification->lines()) === 1
                && $notification->lines()[0]['company_name'] === 'Receiving Site'
                && $notification->lines()[0]['discipline_name'] === $disciplineA->name
                && $notification->lines()[0]['inventory_discipline_names'] === [$disciplineB->name]
                && $notification->lines()[0]['reusable_quantity'] === 1;
        });
    }

    public function test_rac_email_groups_all_matched_inventory_disciplines_for_the_same_request()
    {
        Notification::fake();

        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $electrical = Discipline::create(['name' => 'Electrical', 'created_by' => $requester->id]);
        $mechanical = Discipline::create(['name' => 'Mechanical', 'created_by' => $requester->id]);
        $coordinator = User::factory()->create();
        $sourceCompany = Company::factory()->create();
        $destinationCompany = Company::factory()->create();
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        $this->createEligibleAsset($model, $sourceCompany->id, $electrical->id);
        $this->createEligibleAsset($model, $sourceCompany->id, $mechanical->id);

        foreach ([$electrical, $mechanical] as $discipline) {
            RegionalAssetCoordinatorAssignment::create([
                'user_id' => $coordinator->id,
                'company_id' => $sourceCompany->id,
                'discipline_id' => $discipline->id,
                'created_by' => $requester->id,
            ]);
        }

        $this->actingAs($requester)
            ->post(route('account/request-item', ['itemType' => 'asset_model', 'itemId' => $model->id]), [
                'request-quantity' => 2,
                'requested_discipline_id' => $electrical->id,
                'company_id' => $destinationCompany->id,
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-01',
            ])
            ->assertRedirect();

        Notification::assertSentTo($coordinator, RacScopedRequestSummaryNotification::class, function ($notification) {
            return count($notification->lines()) === 1
                && $notification->lines()[0]['inventory_discipline_names'] === ['Electrical', 'Mechanical'];
        });
    }

    public function test_model_request_marks_and_alerts_when_reusable_scope_has_no_rac()
    {
        Notification::fake();
        $this->settings->enableAlertEmail('asset-admin@example.com');

        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $discipline = Discipline::create([
            'name' => 'Uncovered Discipline',
            'created_by' => $requester->id,
        ]);
        $sourceCompany = Company::factory()->create(['name' => 'Uncovered Source']);
        $destinationCompany = Company::factory()->create(['name' => 'Destination']);
        $project = Project::factory()->create(['name' => 'Unrouted Project']);
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);
        $this->createEligibleAsset($model, $sourceCompany->id, $discipline->id);
        $this->createEligibleAsset($model, $sourceCompany->id, $discipline->id);

        $this->actingAs($requester)
            ->post(route('account/request-item', ['itemType' => 'asset_model', 'itemId' => $model->id]), [
                'request-quantity' => 1,
                'requested_discipline_id' => $discipline->id,
                'company_id' => $destinationCompany->id,
                'project_id' => $project->id,
                'needed_by_date' => '2026-08-01',
            ])
            ->assertRedirect();

        $checkoutRequest = CheckoutRequest::query()
            ->where('user_id', $requester->id)
            ->where('requestable_id', $model->id)
            ->firstOrFail();

        $this->assertSame(CheckoutRequest::RAC_ROUTING_UNROUTED, $checkoutRequest->rac_routing_status);
        $this->assertSame([
            [
                'company_id' => $sourceCompany->id,
                'company_name' => $sourceCompany->name,
                'discipline_id' => $discipline->id,
                'discipline_name' => $discipline->name,
                'reusable_quantity' => 2,
            ],
        ], $checkoutRequest->rac_unrouted_scopes);
        $this->assertNotNull($checkoutRequest->rac_routing_alerted_at);
        $this->assertCount(0, $checkoutRequest->coordinatorTargets);

        Notification::assertSentOnDemand(
            UnroutedRacRequestNotification::class,
            function (UnroutedRacRequestNotification $notification, array $channels, object $notifiable) use ($checkoutRequest, $sourceCompany) {
                return $notifiable->routes['mail'] === 'asset-admin@example.com'
                    && $notification->lines()[0]['request_id'] === $checkoutRequest->id
                    && $notification->lines()[0]['unrouted_scopes'][0]['company_name'] === $sourceCompany->name;
            }
        );
    }

    public function test_rerouting_preserves_existing_coordinator_resolution_and_does_not_repeat_gap_alert()
    {
        Notification::fake();
        $this->settings->enableAlertEmail('asset-admin@example.com');

        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $coveredDiscipline = Discipline::create(['name' => 'Covered', 'created_by' => $requester->id]);
        $uncoveredDiscipline = Discipline::create(['name' => 'Uncovered', 'created_by' => $requester->id]);
        $sourceCompany = Company::factory()->create();
        $destinationCompany = Company::factory()->create();
        $coordinator = User::factory()->create(['company_id' => $sourceCompany->id]);
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        $this->createEligibleAsset($model, $sourceCompany->id, $coveredDiscipline->id);
        $this->createEligibleAsset($model, $sourceCompany->id, $uncoveredDiscipline->id);
        RegionalAssetCoordinatorAssignment::create([
            'user_id' => $coordinator->id,
            'company_id' => $sourceCompany->id,
            'discipline_id' => $coveredDiscipline->id,
            'created_by' => $requester->id,
        ]);

        $this->actingAs($requester)
            ->post(route('account/request-item', ['itemType' => 'asset_model', 'itemId' => $model->id]), [
                'request-quantity' => 1,
                'requested_discipline_id' => $coveredDiscipline->id,
                'company_id' => $destinationCompany->id,
                'project_id' => $project->id,
                'needed_by_date' => '2026-08-01',
            ]);

        $checkoutRequest = CheckoutRequest::query()
            ->where('user_id', $requester->id)
            ->where('requestable_id', $model->id)
            ->firstOrFail();
        $target = $checkoutRequest->coordinatorTargets()->firstOrFail();
        $target->markCompletedNoStock();
        $alertedAt = $checkoutRequest->fresh()->rac_routing_alerted_at;
        $this->createEligibleAsset($model, $sourceCompany->id, $uncoveredDiscipline->id);

        $result = ResolveCheckoutRequestCoordinatorsAction::run($checkoutRequest->fresh());

        $this->assertSame(CheckoutRequest::RAC_ROUTING_PARTIALLY_ROUTED, $result->status);
        $this->assertFalse($result->shouldAlert);
        $this->assertSame(2, $result->unroutedScopes[0]['reusable_quantity']);
        $this->assertSame(
            CheckoutRequestCoordinator::RESOLUTION_COMPLETED_NO_STOCK,
            $checkoutRequest->coordinatorTargets()->firstOrFail()->resolution_status
        );
        $this->assertTrue($alertedAt->equalTo($checkoutRequest->fresh()->rac_routing_alerted_at));
    }

    public function test_scheduled_reconciliation_backfills_existing_pending_requests()
    {
        Notification::fake();
        $this->settings->enableAlertEmail('asset-admin@example.com');

        $requester = User::factory()->create();
        $discipline = Discipline::create(['name' => 'Backfill Gap', 'created_by' => $requester->id]);
        $sourceCompany = Company::factory()->create();
        $model = AssetModel::factory()->create();
        $this->createEligibleAsset($model, $sourceCompany->id, $discipline->id);

        $checkoutRequest = CheckoutRequest::factory()
            ->forAssetModel()
            ->create([
                'requestable_id' => $model->id,
                'user_id' => $requester->id,
                'status' => CheckoutRequest::STATUS_PENDING,
                'rac_routing_status' => null,
                'rac_unrouted_scopes' => null,
                'rac_routing_alerted_at' => null,
            ]);

        $this->artisan('snipeit:reconcile-rac-routing')
            ->expectsOutput('1 active requests reconciled; 1 unrouted requests included in administrator alerts.')
            ->assertSuccessful();

        $this->assertSame(
            CheckoutRequest::RAC_ROUTING_UNROUTED,
            $checkoutRequest->fresh()->rac_routing_status
        );
        $this->assertNotNull($checkoutRequest->fresh()->rac_routing_alerted_at);
        Notification::assertSentOnDemand(UnroutedRacRequestNotification::class);
    }

    public function test_category_assignment_does_not_grant_models_request_capability()
    {
        $requester = User::factory()->create();
        Category::factory()->forAssets()->create([
            'manager_id' => $requester->id,
        ]);

        $this->assertTrue($requester->isAssetFamilyManager());
        $this->assertFalse($requester->hasAccess('models.request'));
    }

    public function test_model_request_permission_allows_request_outside_managed_category_scope()
    {
        $requester = User::factory()->requestAssetModels()->create();
        $project = Project::factory()->create();
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
            ->assertRedirect();

        $this->assertDatabaseHas('checkout_requests', [
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

    public function test_submitted_requests_page_marks_the_breadcrumb_as_new()
    {
        $requester = User::factory()->requestAssetModels()->create();

        $this->actingAs($requester)
            ->get(route('requests.index'))
            ->assertOk()
            ->assertSeeText('Submitted Requests')
            ->assertSee('Recently released feature');
    }

    public function test_submitted_requests_api_requires_models_request_permission()
    {
        $requester = User::factory()->create();

        $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index'))
            ->assertForbidden();
    }

    public function test_submitted_requests_batch_api_groups_cart_lines_and_preserves_legacy_requests()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $project = Project::factory()->create(['name' => 'Grouped Submission Project']);
        $batchId = (string) Str::uuid();
        $modelA = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'name' => 'Grouped Model A',
        ]);
        $modelB = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'name' => 'Grouped Model B',
        ]);

        $firstRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $modelA->id,
            'project_id' => $project->id,
            'submission_batch_id' => $batchId,
            'quantity' => 2,
            'rac_routing_status' => CheckoutRequest::RAC_ROUTING_UNROUTED,
        ]);
        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $modelB->id,
            'project_id' => $project->id,
            'submission_batch_id' => $batchId,
            'quantity' => 3,
        ]);
        $legacyRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $modelA->id,
            'project_id' => $project->id,
            'submission_batch_id' => null,
            'quantity' => 1,
        ]);

        $rows = $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index', ['view' => 'batches']))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->json('rows');

        $batchRow = collect($rows)->firstWhere('submission_batch_id', $batchId);
        $legacyRow = collect($rows)->firstWhere('submission_batch_id', null);

        $this->assertSame('#'.$firstRequest->id, $batchRow['submission_reference']);
        $this->assertSame('Grouped Submission Project', $batchRow['project']);
        $this->assertNull($batchRow['project_requests_url']);
        $this->assertSame(2, $batchRow['models_count']);
        $this->assertSame(2, $batchRow['lines_count']);
        $this->assertSame(5, $batchRow['total_quantity']);
        $this->assertSame(0, $batchRow['allocated_quantity']);
        $this->assertSame(5, $batchRow['remaining_quantity']);
        $this->assertFalse($batchRow['review_complete']);
        $this->assertSame('In Progress', $batchRow['status']);
        $this->assertNull($batchRow['amount_to_buy']);
        $this->assertTrue($batchRow['has_rac_routing_gap']);
        $this->assertSame(route('requests.index', ['submission_batch_id' => $batchId]), $batchRow['details_url']);
        $this->assertSame(route('request-submissions.update', $firstRequest), $batchRow['submission_update_url']);
        $this->assertSame(route('request-submissions.cancel', $firstRequest), $batchRow['submission_cancel_url']);
        $this->assertSame('#'.$legacyRequest->id, $legacyRow['submission_reference']);
        $this->assertSame(route('requests.index', ['request_id' => $legacyRequest->id]), $legacyRow['details_url']);
    }

    public function test_submitted_requests_page_shows_batches_and_batch_detail_shows_model_lines()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $project = Project::factory()->create(['name' => 'Drilldown Project']);
        $batchId = (string) Str::uuid();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'name' => 'Drilldown Model',
        ]);

        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'project_id' => $project->id,
            'submission_batch_id' => $batchId,
            'quantity' => 2,
        ]);

        $this->actingAs($requester)
            ->get(route('requests.index'))
            ->assertOk()
            ->assertSee('userRequestSubmissions', false)
            ->assertSee('Each row is one cart submission')
            ->assertSee('view=batches', false)
            ->assertDontSee('data-field="models_count"', false)
            ->assertDontSee('data-field="rac_routing_status"', false)
            ->assertSee('requestRequesterStatusFormatter', false)
            ->assertSee('Coordinator assignment pending', false)
            ->assertSee('Review Status')
            ->assertSee('Allocated')
            ->assertSee('Remaining')
            ->assertSee('Pending review')
            ->assertDontSee('data-field="reusable_quantity"', false)
            ->assertDontSee('data-field="due_back_before_needed_by_quantity"', false)
            ->assertDontSee('data-field="estimated_savings"', false)
            ->assertDontSee('Reference Price');

        $this->actingAs($requester)
            ->get(route('requests.index', ['submission_batch_id' => $batchId]))
            ->assertOk()
            ->assertSee('Showing submission')
            ->assertSee('Drilldown Project')
            ->assertDontSee('Models in this submission')
            ->assertDontSee('Reuse planning fields')
            ->assertSee('userRequests', false)
            ->assertSee('data-show-footer="true"', false)
            ->assertSee('data-footer-formatter="requestPageTotalLabelFormatter"', false)
            ->assertSee('data-footer-formatter="qtySumFormatter"', false)
            ->assertSee('data-footer-formatter="sumFormatter"', false)
            ->assertDontSee('<strong>Models</strong>', false)
            ->assertDontSee('<strong>Model Lines</strong>', false)
            ->assertSee('Reference Price')
            ->assertSee('submission_batch_id='.$batchId, false);
    }

    public function test_completed_submission_reports_actual_allocation_outcome_and_pending_purchase_cost()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $coordinator = User::factory()->create();
        $project = Project::factory()->create();
        $company = Company::factory()->create();
        $discipline = Discipline::create(['name' => 'Completed Review Discipline', 'created_by' => $requester->id]);
        $batchId = (string) Str::uuid();
        $modelA = AssetModel::factory()->create();
        $modelB = AssetModel::factory()->create();

        $requestA = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $modelA->id,
            'project_id' => $project->id,
            'submission_batch_id' => $batchId,
            'quantity' => 2,
            'reference_price_snapshot' => 100,
            'rac_routing_status' => CheckoutRequest::RAC_ROUTING_ROUTED,
        ]);
        $requestB = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $modelB->id,
            'project_id' => $project->id,
            'submission_batch_id' => $batchId,
            'quantity' => 3,
            'reference_price_snapshot' => 200,
            'rac_routing_status' => CheckoutRequest::RAC_ROUTING_ROUTED,
        ]);

        foreach ([$requestA, $requestB] as $checkoutRequest) {
            $checkoutRequest->coordinatorTargets()->create([
                'user_id' => $coordinator->id,
                'company_id' => $company->id,
                'discipline_id' => $discipline->id,
                'resolution_status' => CheckoutRequestCoordinator::RESOLUTION_COMPLETED_NO_STOCK,
            ]);
        }

        $assetA = Asset::factory()->create(['model_id' => $modelA->id]);
        $assetB1 = Asset::factory()->create(['model_id' => $modelB->id]);
        $assetB2 = Asset::factory()->create(['model_id' => $modelB->id]);
        $requestA->allocatedAssets()->attach($assetA->id, ['allocated_by' => $coordinator->id, 'allocated_at' => now()]);
        $requestB->allocatedAssets()->attach([$assetB1->id, $assetB2->id], [
            'allocated_by' => $coordinator->id,
            'allocated_at' => now(),
        ]);

        $row = collect($this->actingAsForApi($requester)
            ->getJson(route('api.requests.index', ['view' => 'batches']))
            ->assertOk()
            ->json('rows'))
            ->firstWhere('submission_batch_id', $batchId);

        $this->assertSame('Review Complete', $row['status']);
        $this->assertSame('review_complete', $row['status_value']);
        $this->assertTrue($row['review_complete']);
        $this->assertSame(5, $row['total_quantity']);
        $this->assertSame(3, $row['allocated_quantity']);
        $this->assertSame(2, $row['remaining_quantity']);
        $this->assertEquals(300.0, $row['amount_to_buy']);
        $this->assertSame('300.00', $row['amount_to_buy_formatted']);
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
            'rac_routing_status' => CheckoutRequest::RAC_ROUTING_UNROUTED,
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
            ->assertJsonPath('rows.0.has_rac_routing_gap', true)
            ->assertJsonPath('rows.0.project', 'Request Tracking Project')
            ->assertJsonPath('rows.0.reusable_quantity', 1)
            ->assertJsonPath('rows.0.due_back_before_needed_by_quantity', 0)
            ->assertJsonPath('rows.0.procurement_shortfall', 1)
            ->assertJsonPath('rows.0.estimated_savings', 499.99)
            ->assertJsonPath('rows.0.amount_to_buy', 499.99)
            ->assertJsonPath('rows.0.category', $model->category->name)
            ->assertJsonPath('rows.0.total_need_cost', 999.98)
            ->assertJsonPath('rows.0.total_need_cost_formatted', '999.98')
            ->assertJsonPath('rows.0.reference_price_snapshot_formatted', '499.99')
            ->assertJsonPath('rows.0.reserved_count', 0)
            ->assertJsonPath('rows.0.reserved_by_other_rfqs_count', 0)
            ->assertJsonPath('rows.0.project_requests_url', null)
            ->assertJsonPath('rows.0.request_update_url', route('requests.update', $checkoutRequest))
            ->assertJsonPath('rows.0.request_cancel_url', null);
    }

    public function test_requested_assets_api_links_project_for_users_who_can_view_projects()
    {
        $requester = User::factory()->superuser()->requestAssetModels()->create();
        $project = Project::factory()->create(['name' => 'Visible Project']);
        $model = AssetModel::factory()->create();

        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'project_id' => $project->id,
        ]);

        $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index'))
            ->assertOk()
            ->assertJsonPath('rows.0.project', 'Visible Project')
            ->assertJsonPath('rows.0.project_requests_url', route('projects.show', ['project' => $project->id, 'tab' => 'requests']));
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
            ->assertSee('Reuse Summary')
            ->assertSee('Reuse planning fields')
            ->assertSee('Recently released feature')
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

    public function test_submission_detail_page_shows_reference_price_column()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $project = Project::factory()->create();
        $batchId = (string) Str::uuid();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'project_id' => $project->id,
            'submission_batch_id' => $batchId,
            'reference_price_snapshot' => 1250.00,
        ]);

        $this->actingAs($requester)
            ->get(route('requests.index', ['submission_batch_id' => $batchId]))
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

    public function test_requested_assets_api_points_request_detail_url_to_reusable_now_request_review()
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
                'request_bucket' => 'reusable_now',
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

    public function test_requester_without_asset_view_permission_gets_model_metrics_without_asset_links()
    {
        $requester = User::factory()->requestAssetModels()->create();
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create();
        $checkoutRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'project_id' => $project->id,
            'quantity' => 2,
        ]);

        $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index'))
            ->assertOk()
            ->assertJsonPath('rows.0.qty', 2)
            ->assertJsonPath('rows.0.model_show_url', null)
            ->assertJsonPath('rows.0.request_detail_url', null)
            ->assertJsonPath('rows.0.reusable_now_url', null)
            ->assertJsonPath('rows.0.due_back_url', null)
            ->assertJsonPath('rows.0.reserved_assets_url', null)
            ->assertJsonPath('rows.0.reserved_by_other_project_url', null)
            ->assertJsonPath('rows.0.request_update_url', route('requests.update', $checkoutRequest))
            ->assertJsonPath('rows.0.request_cancel_url', null);
    }

    public function test_requester_with_managed_model_view_permission_gets_model_link()
    {
        $requester = User::factory()->viewAssetModels()->requestAssetModels()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
        ]);

        $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index'))
            ->assertOk()
            ->assertJsonPath('rows.0.model_show_url', route('models.show', $model->id));
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

    public function test_reusable_now_bucket_prioritizes_same_company_assets_and_exposes_match_flag()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $matchingCompany = Company::factory()->create(['name' => 'Matching Center']);
        $fallbackCompany = Company::factory()->create(['name' => 'Fallback Center']);
        $project = Project::factory()->create();
        $discipline = Discipline::create(['name' => 'Reusable Match', 'created_by' => $requester->id]);
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        $fallbackAsset = $this->createEligibleAsset($model, $fallbackCompany->id, $discipline->id);
        $matchingAsset = $this->createEligibleAsset($model, $matchingCompany->id, $discipline->id);

        $checkoutRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'project_id' => $project->id,
            'requested_discipline_id' => $discipline->id,
            'company_id' => $matchingCompany->id,
            'needed_by_date' => '2026-06-20',
        ]);

        $this->actingAsForApi($requester)
            ->getJson(route('api.assets.index', [
                'request_id' => $checkoutRequest->id,
                'request_bucket' => 'reusable_now',
            ]))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('rows.0.id', $matchingAsset->id)
            ->assertJsonPath('rows.0.is_closest_match', true)
            ->assertJsonPath('rows.1.id', $fallbackAsset->id)
            ->assertJsonPath('rows.1.is_closest_match', false);
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

    public function test_reusable_now_request_review_page_shows_same_center_badge_for_matching_assets()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $project = Project::factory()->create();
        $matchingCompany = Company::factory()->create(['name' => 'Matching Center']);
        $fallbackCompany = Company::factory()->create(['name' => 'Fallback Center']);
        $discipline = Discipline::create(['name' => 'Review Match', 'created_by' => $requester->id]);
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        $matchingAsset = $this->createEligibleAsset($model, $matchingCompany->id, $discipline->id);
        $this->createEligibleAsset($model, $fallbackCompany->id, $discipline->id);

        $checkoutRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'project_id' => $project->id,
            'requested_discipline_id' => $discipline->id,
            'company_id' => $matchingCompany->id,
        ]);

        $this->actingAs($requester)
            ->get(route('hardware.index', [
                'request_id' => $checkoutRequest->id,
                'request_bucket' => 'reusable_now',
            ]))
            ->assertOk()
            ->assertSee('Same center');
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
        Notification::fake();
        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $coordinator = User::factory()->create();
        $project = Project::factory()->create();
        $updatedProject = Project::factory()->create();
        $company = Company::factory()->create();
        $updatedCompany = Company::factory()->create();
        $sourceCompany = Company::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'reference_price' => 400,
        ]);

        $discipline = Discipline::create([
            'name' => 'Scoped Update',
            'created_by' => $requester->id,
        ]);
        $updatedDiscipline = Discipline::create([
            'name' => 'Updated Destination',
            'created_by' => $requester->id,
        ]);
        $this->createEligibleAsset($model, $sourceCompany->id, $discipline->id);
        RegionalAssetCoordinatorAssignment::create([
            'user_id' => $coordinator->id,
            'company_id' => $sourceCompany->id,
            'discipline_id' => $discipline->id,
            'created_by' => $requester->id,
        ]);

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
        ResolveCheckoutRequestCoordinatorsAction::run($request, false);

        $this->actingAs($requester)
            ->post(route('requests.update', $request), [
                'request-action' => 'update',
                'request-quantity' => 2,
                'requested_discipline_id' => $updatedDiscipline->id,
                'company_id' => $updatedCompany->id,
                'project_id' => $updatedProject->id,
                'needed_by_date' => '2026-07-01',
            ])
            ->assertRedirect();

        $request->refresh();

        $this->assertSame(2, $request->quantity);
        $this->assertSame($project->id, $request->project_id);
        $this->assertSame($updatedDiscipline->id, $request->requested_discipline_id);
        $this->assertSame($updatedCompany->id, $request->company_id);
        $this->assertSame('2026-06-01', optional($request->needed_by_date)->format('Y-m-d'));
        $this->assertSame(1, $request->reusable_quantity);
        $this->assertSame(1, $request->procurement_shortfall);
        $this->assertSame(400.0, (float) $request->estimated_savings);
        Notification::assertSentTo($coordinator, RacScopedRequestSummaryNotification::class, function ($notification) use ($updatedCompany, $updatedDiscipline) {
            return $notification->isUpdate()
                && $notification->lines()[0]['requested_quantity'] === 2
                && $notification->lines()[0]['company_name'] === $updatedCompany->name
                && $notification->lines()[0]['discipline_name'] === $updatedDiscipline->name;
        });
    }

    public function test_edit_notifies_racs_added_or_removed_when_inventory_routing_changes()
    {
        Notification::fake();

        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $oldCoordinator = User::factory()->create();
        $newCoordinator = User::factory()->create();
        $project = Project::factory()->create();
        $destinationCompany = Company::factory()->create();
        $oldSourceCompany = Company::factory()->create();
        $newSourceCompany = Company::factory()->create();
        $discipline = Discipline::create([
            'name' => 'Routing Change',
            'created_by' => $requester->id,
        ]);
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);
        $oldAsset = $this->createEligibleAsset($model, $oldSourceCompany->id, $discipline->id);

        foreach ([
            [$oldCoordinator, $oldSourceCompany],
            [$newCoordinator, $newSourceCompany],
        ] as [$coordinator, $sourceCompany]) {
            RegionalAssetCoordinatorAssignment::create([
                'user_id' => $coordinator->id,
                'company_id' => $sourceCompany->id,
                'discipline_id' => $discipline->id,
                'created_by' => $requester->id,
            ]);
        }

        $checkoutRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'requested_discipline_id' => $discipline->id,
            'company_id' => $destinationCompany->id,
            'project_id' => $project->id,
            'quantity' => 1,
            'needed_by_date' => '2026-06-01',
        ]);
        ResolveCheckoutRequestCoordinatorsAction::run($checkoutRequest, false);
        $this->assertSame([$oldCoordinator->id], $checkoutRequest->coordinatorTargets()->pluck('user_id')->all());

        $oldAsset->delete();
        $this->createEligibleAsset($model, $newSourceCompany->id, $discipline->id);

        $this->actingAs($requester)
            ->post(route('requests.update', $checkoutRequest), [
                'request-action' => 'update',
                'request-quantity' => 2,
                'requested_discipline_id' => $discipline->id,
                'company_id' => $destinationCompany->id,
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-01',
            ])
            ->assertRedirect();

        Notification::assertSentTo($newCoordinator, RacScopedRequestSummaryNotification::class, fn ($notification) => $notification->isUpdate());
        Notification::assertSentTo($oldCoordinator, RacRequestRoutingRemovedNotification::class, function ($notification) use ($model, $project) {
            return $notification->modelName() === $model->name
                && $notification->projectName() === $project->name;
        });
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

    public function test_requester_updates_project_and_needed_by_date_for_entire_submission()
    {
        Notification::fake();
        $requester = User::factory()->requestAssetModels()->create();
        $originalProject = Project::factory()->create();
        $updatedProject = Project::factory()->create();
        $batchId = (string) Str::uuid();
        $firstRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'project_id' => $originalProject->id,
            'submission_batch_id' => $batchId,
            'needed_by_date' => '2026-06-01',
        ]);
        $secondRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'project_id' => $originalProject->id,
            'submission_batch_id' => $batchId,
            'needed_by_date' => '2026-06-01',
        ]);

        $this->actingAs($requester)
            ->post(route('request-submissions.update', $firstRequest), [
                'project_id' => $updatedProject->id,
                'needed_by_date' => '2026-08-15',
            ])
            ->assertRedirect();

        foreach ([$firstRequest->fresh(), $secondRequest->fresh()] as $updatedRequest) {
            $this->assertSame($updatedProject->id, $updatedRequest->project_id);
            $this->assertSame('2026-08-15', optional($updatedRequest->needed_by_date)->format('Y-m-d'));
        }
    }

    public function test_submission_context_and_cancel_actions_are_blocked_after_rac_processing_starts()
    {
        $requester = User::factory()->requestAssetModels()->create();
        $coordinator = User::factory()->create();
        $project = Project::factory()->create();
        $updatedProject = Project::factory()->create();
        $request = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'project_id' => $project->id,
            'needed_by_date' => '2026-06-01',
        ]);
        $request->coordinatorTargets()->create([
            'user_id' => $coordinator->id,
            'resolution_status' => CheckoutRequestCoordinator::RESOLUTION_IN_PROGRESS,
        ]);

        $this->actingAs($requester)
            ->from(route('requests.index'))
            ->post(route('request-submissions.update', $request), [
                'project_id' => $updatedProject->id,
                'needed_by_date' => '2026-08-15',
            ])
            ->assertRedirect(route('requests.index'))
            ->assertSessionHasErrors('submission');

        $this->actingAs($requester)
            ->from(route('requests.index'))
            ->post(route('request-submissions.cancel', $request))
            ->assertRedirect(route('requests.index'))
            ->assertSessionHasErrors('submission');

        $request->refresh();
        $this->assertSame($project->id, $request->project_id);
        $this->assertNull($request->canceled_at);
    }

    public function test_requester_can_cancel_entire_submission_without_hard_deleting_lines()
    {
        $requester = User::factory()->requestAssetModels()->create();
        $batchId = (string) Str::uuid();
        $firstRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'submission_batch_id' => $batchId,
        ]);
        $secondRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'submission_batch_id' => $batchId,
        ]);

        $this->actingAs($requester)
            ->post(route('request-submissions.cancel', $firstRequest))
            ->assertRedirect();

        foreach ([$firstRequest->fresh(), $secondRequest->fresh()] as $canceledRequest) {
            $this->assertNotNull($canceledRequest->canceled_at);
            $this->assertSame(CheckoutRequest::STATUS_CANCELED, $canceledRequest->status);
        }
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

        $submittedRequests = CheckoutRequest::query()
            ->where('user_id', $requester->id)
            ->whereIn('requestable_id', [$modelA->id, $modelB->id])
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $submittedRequests);
        $this->assertNotNull($submittedRequests->first()->submission_batch_id);
        $this->assertCount(1, $submittedRequests->pluck('submission_batch_id')->unique());
        $this->assertTrue(
            $submittedRequests->every(
                fn (CheckoutRequest $checkoutRequest) =>
                    $checkoutRequest->rac_routing_status === CheckoutRequest::RAC_ROUTING_UNROUTED
            )
        );
        Notification::assertNotSentTo(
            $requester,
            RequestAlternativeFollowUpNotification::class
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

        $submittedRequests = CheckoutRequest::query()
            ->where('user_id', $requester->id)
            ->where('project_id', $project->id)
            ->get();

        $this->assertCount(2, $submittedRequests);
        $this->assertNotNull($submittedRequests->first()->submission_batch_id);
        $this->assertCount(1, $submittedRequests->pluck('submission_batch_id')->unique());
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

    public function test_project_requests_tab_shows_category_and_total_need_cost_columns()
    {
        $requester = User::factory()->viewAssets()->requestAssetModels()->create();
        $project = Project::factory()->create(['name' => 'Requests Columns Project']);
        $discipline = Discipline::create([
            'name' => 'Requests Columns Discipline',
            'created_by' => $requester->id,
        ]);
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
            'reference_price' => 1200,
        ]);

        $this->createEligibleAsset($model, Company::factory()->create()->id, $discipline->id);

        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'requested_discipline_id' => $discipline->id,
            'project_id' => $project->id,
            'quantity' => 2,
            'reference_price_snapshot' => 1200,
        ]);

        $this->actingAs($requester)
            ->get(route('projects.show', ['project' => $project->id, 'tab' => 'requests']))
            ->assertOk()
            ->assertSee('data-field="category"', false)
            ->assertSee('data-field="total_need_cost"', false)
            ->assertSee('Category', false)
            ->assertSee('Total Need Cost', false);
    }

    public function test_rac_notification_points_reviewers_to_the_request_in_the_app()
    {
        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $coordinator = User::factory()->create(['first_name' => 'Mail', 'last_name' => 'RAC']);
        $project = Project::factory()->create();
        $discipline = Discipline::create(['name' => 'Mail Scope', 'created_by' => $requester->id]);
        $company = Company::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        $this->createEligibleAsset($model, $company->id, $discipline->id);

        RegionalAssetCoordinatorAssignment::create([
            'user_id' => $coordinator->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
            'created_by' => $requester->id,
        ]);

        $this->actingAs($requester)
            ->post(route('account/request-item', ['itemType' => 'asset_model', 'itemId' => $model->id]), [
                'request-action' => 'create',
                'request-quantity' => 1,
                'requested_discipline_id' => $discipline->id,
                'company_id' => $company->id,
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-20',
            ])
            ->assertRedirect();

        $request = CheckoutRequest::query()
            ->where('user_id', $requester->id)
            ->where('requestable_id', $model->id)
            ->firstOrFail();

        $notification = new RacScopedRequestSummaryNotification([
            'requester' => $requester,
            'submitted_at' => $request->created_at?->toDateTimeString() ?? now()->toDateTimeString(),
            'project_name' => $project->name,
            'lines' => [[
                'request_id' => $request->id,
                'model_name' => $model->name,
                'company_name' => $company->name,
                'inventory_discipline_names' => [$discipline->name],
                'requested_quantity' => 1,
                'reusable_quantity' => 1,
                'needed_by_date' => '2026-06-20',
                'model_show_url' => route('models.show', $model->id),
                'project_requests_url' => route('projects.show', ['project' => $project->id, 'tab' => 'requests']),
                'request_detail_url' => route('hardware.index', ['request_id' => $request->id, 'request_bucket' => 'reusable_now']),
            ]],
        ]);

        $renderedMail = $notification->toMail($coordinator)->render();
        $mailMessage = $notification->toMail($coordinator);

        $this->assertSame(route('hardware.index', ['request_id' => $request->id, 'request_bucket' => 'reusable_now']), $notification->reviewUrl());
        $this->assertStringNotContainsString('Allocate everything', $renderedMail);
        $this->assertStringContainsString('Inventory Discipline(s)', $renderedMail);
        $this->assertStringContainsString($discipline->name, $renderedMail);
        $this->assertStringContainsString('Review request in LEAMS', $renderedMail);
        $this->assertStringContainsString('Please open the request in LEAMS', $renderedMail);
        $this->assertSame('Action required: reusable request for '.$project->name, $mailMessage->subject);
    }

    public function test_candidate_rac_bulk_checkout_form_prefills_request_context()
    {
        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create([
            'first_name' => 'Harold',
            'last_name' => "O'Keefe",
        ]);
        $coordinator = User::factory()->viewAssets()->checkoutAssets()->create();
        $project = Project::factory()->create();
        $discipline = Discipline::create(['name' => 'Bulk Checkout Scope', 'created_by' => $requester->id]);
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
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        $request = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'requested_discipline_id' => $discipline->id,
            'company_id' => $company->id,
            'project_id' => $project->id,
            'quantity' => 2,
            'status' => CheckoutRequest::STATUS_PENDING,
            'needed_by_date' => '2026-07-15',
        ]);

        $request->coordinatorTargets()->create([
            'user_id' => $coordinator->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
        ]);

        $assetA = $this->createEligibleAsset($model, $company->id, $discipline->id);
        $assetB = $this->createEligibleAsset($model, $company->id, $discipline->id);

        $this->actingAs($coordinator)
            ->get(route('hardware.bulkcheckout.show', ['request_id' => $request->id]))
            ->assertOk()
            ->assertDontSee('Request context')
            ->assertSee($assetA->present()->fullName, false)
            ->assertSee($assetB->present()->fullName, false)
            ->assertSee($requester->present()->fullName)
            ->assertSee('value="'.now()->format('Y-m-d').'"', false)
            ->assertSee('value="2026-07-15"', false)
            ->assertSee($reservedStatus->name, false);
    }

    public function test_request_row_checkout_uses_request_aware_checkout_with_only_that_asset_selected()
    {
        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $coordinator = User::factory()->viewAssets()->checkoutAssets()->create();
        $project = Project::factory()->create();
        $discipline = Discipline::create(['name' => 'Single Checkout Scope', 'created_by' => $requester->id]);
        $company = Company::factory()->create();
        $reservedStatus = Statuslabel::factory()->readyToDeploy()->create([
            'name' => 'Reserved for RFQ',
        ]);
        $settings = Setting::getSettings();
        $settings->rfq_reserved_statuslabel_id = $reservedStatus->id;
        $settings->save();
        Setting::$_cache = $settings->fresh();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);
        $request = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'requested_discipline_id' => $discipline->id,
            'company_id' => $company->id,
            'project_id' => $project->id,
            'quantity' => 2,
            'needed_by_date' => '2026-07-15',
        ]);
        $request->coordinatorTargets()->create([
            'user_id' => $coordinator->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
        ]);
        $selectedAsset = $this->createEligibleAsset($model, $company->id, $discipline->id);
        $otherAsset = $this->createEligibleAsset($model, $company->id, $discipline->id);

        $response = $this->actingAs($coordinator)
            ->get(route('hardware.checkout.create', [
                'asset' => $selectedAsset->id,
                'request_id' => $request->id,
            ]))
            ->assertRedirect(route('hardware.bulkcheckout.show', [
                'request_id' => $request->id,
            ]));

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee($selectedAsset->present()->fullName, false)
            ->assertDontSee($otherAsset->present()->fullName, false)
            ->assertSee($requester->present()->fullName)
            ->assertSee('value="2026-07-15"', false)
            ->assertSee($reservedStatus->name, false);
    }

    public function test_candidate_rac_can_bulk_checkout_everything_from_request_review_flow()
    {
        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $coordinator = User::factory()->viewAssets()->checkoutAssets()->create();
        $project = Project::factory()->create();
        $discipline = Discipline::create(['name' => 'Allocate Scope', 'created_by' => $requester->id]);
        $company = Company::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        $request = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'requested_discipline_id' => $discipline->id,
            'company_id' => $company->id,
            'project_id' => $project->id,
            'quantity' => 2,
            'status' => CheckoutRequest::STATUS_PENDING,
        ]);

        $request->coordinatorTargets()->create([
            'user_id' => $coordinator->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
        ]);

        $assetA = $this->createEligibleAsset($model, $company->id, $discipline->id);
        $assetB = $this->createEligibleAsset($model, $company->id, $discipline->id);

        $this->actingAs($coordinator)
            ->get(route('hardware.index', ['request_id' => $request->id]))
            ->assertOk();

        $this->actingAs($coordinator)
            ->post(route('hardware.bulkcheckout.store'), [
                'request_id' => $request->id,
                'selected_assets' => [$assetA->id, $assetB->id],
                'checkout_to_type' => 'user',
                'assigned_user' => $requester->id,
                'project_id' => $project->id,
                'discipline_id' => $discipline->id,
                'expected_checkin' => now()->addWeek()->format('Y-m-d'),
            ])
            ->assertRedirect(route('hardware.index', ['request_id' => $request->id]));

        $request->refresh();

        $this->assertSame(CheckoutRequest::STATUS_FULLY_ALLOCATED, $request->status);
        $this->assertSame(2, $request->allocatedAssets()->count());
        $this->assertDatabaseHas('checkout_request_assets', [
            'checkout_request_id' => $request->id,
            'asset_id' => $assetA->id,
            'allocated_by' => $coordinator->id,
        ]);
        $this->assertDatabaseHas('checkout_request_assets', [
            'checkout_request_id' => $request->id,
            'asset_id' => $assetB->id,
            'allocated_by' => $coordinator->id,
        ]);
        $this->assertDatabaseHas('assets', [
            'id' => $assetA->id,
            'assigned_to' => $requester->id,
            'project_id' => $project->id,
            'discipline_id' => $discipline->id,
        ]);
        $this->assertDatabaseHas('assets', [
            'id' => $assetB->id,
            'assigned_to' => $requester->id,
            'project_id' => $project->id,
            'discipline_id' => $discipline->id,
        ]);
        $this->assertDatabaseHas('checkout_request_coordinators', [
            'checkout_request_id' => $request->id,
            'user_id' => $coordinator->id,
            'resolution_status' => CheckoutRequestCoordinator::RESOLUTION_COMPLETED,
        ]);
    }

    public function test_partial_allocation_marks_coordinator_target_in_progress_and_request_stays_actionable()
    {
        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $coordinator = User::factory()->viewAssets()->checkoutAssets()->create();
        $project = Project::factory()->create();
        $discipline = Discipline::create(['name' => 'Partial Allocate Scope', 'created_by' => $requester->id]);
        $company = Company::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        $request = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'requested_discipline_id' => $discipline->id,
            'company_id' => $company->id,
            'project_id' => $project->id,
            'quantity' => 2,
            'status' => CheckoutRequest::STATUS_PENDING,
        ]);

        $request->coordinatorTargets()->create([
            'user_id' => $coordinator->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
        ]);

        $assetA = $this->createEligibleAsset($model, $company->id, $discipline->id);

        $this->actingAs($coordinator)
            ->get(route('hardware.index', ['request_id' => $request->id]))
            ->assertOk();

        $this->actingAs($coordinator)
            ->post(route('hardware.bulkcheckout.store'), [
                'request_id' => $request->id,
                'selected_assets' => [$assetA->id],
                'checkout_to_type' => 'user',
                'assigned_user' => $requester->id,
                'project_id' => $project->id,
                'discipline_id' => $discipline->id,
                'expected_checkin' => now()->addWeek()->format('Y-m-d'),
            ])
            ->assertRedirect(route('hardware.index', ['request_id' => $request->id]));

        $request->refresh();
        $target = $request->coordinatorTargets()->where('user_id', $coordinator->id)->firstOrFail();

        $this->assertSame(CheckoutRequest::STATUS_PARTIALLY_ALLOCATED, $request->status);
        $this->assertSame(1, $request->remainingAllocationQuantity());
        $this->assertTrue($request->canBeProcessedBy($coordinator));
        $this->assertSame(CheckoutRequestCoordinator::RESOLUTION_IN_PROGRESS, $target->resolvedStatus());
        $this->assertNotNull($target->reviewed_at);
        $this->assertNotNull($target->last_action_at);
    }

    public function test_candidate_rac_can_mark_no_more_stock_available_from_request_review_flow()
    {
        $requester = User::factory()->requestAssetModels()->viewAssets()->viewAssetModels()->create();
        $coordinator = User::factory()->viewAssets()->create();
        $project = Project::factory()->create();
        $discipline = Discipline::create(['name' => 'No Stock Scope', 'created_by' => $requester->id]);
        $company = Company::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        $request = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'requested_discipline_id' => $discipline->id,
            'company_id' => $company->id,
            'project_id' => $project->id,
            'quantity' => 2,
            'status' => CheckoutRequest::STATUS_PENDING,
        ]);

        $request->coordinatorTargets()->create([
            'user_id' => $coordinator->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
        ]);

        $this->actingAs($coordinator)
            ->get(route('hardware.index', ['request_id' => $request->id, 'request_bucket' => 'reusable_now']))
            ->assertOk()
            ->assertSee('Mark no more reusable stock available');

        $this->actingAs($coordinator)
            ->post(route('hardware.requests.coordinator-resolution', $request), [
                'resolution_status' => CheckoutRequestCoordinator::RESOLUTION_COMPLETED_NO_STOCK,
                'request_bucket' => 'reusable_now',
            ])
            ->assertRedirect(route('hardware.index', ['request_id' => $request->id, 'request_bucket' => 'reusable_now']));

        $target = $request->fresh()->coordinatorTargets()->where('user_id', $coordinator->id)->firstOrFail();

        $this->assertSame(CheckoutRequestCoordinator::RESOLUTION_COMPLETED_NO_STOCK, $target->resolvedStatus());
        $this->assertNotNull($target->reviewed_at);
        $this->assertNotNull($target->last_action_at);
    }

    public function test_request_review_page_no_longer_shows_allocate_everything_button()
    {
        $requester = User::factory()->requestAssetModels()->viewAssets()->viewAssetModels()->create();
        $project = Project::factory()->create();
        $discipline = Discipline::create(['name' => 'Requester View', 'created_by' => $requester->id]);
        $company = Company::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $this->managedAssetCategoryFor($requester)->id,
        ]);

        $request = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'requested_discipline_id' => $discipline->id,
            'company_id' => $company->id,
            'project_id' => $project->id,
            'quantity' => 1,
        ]);

        $this->actingAs($requester)
            ->get(route('hardware.index', ['request_id' => $request->id]))
            ->assertOk()
            ->assertDontSee('Allocate everything');
    }

    public function test_source_rac_can_start_a_request_linked_cross_company_transfer_under_fmcs()
    {
        $settings = Setting::getSettings();
        $settings->full_multiple_companies_support = 1;
        $settings->save();
        Setting::$_cache = $settings->fresh();

        $sourceCompany = Company::factory()->create(['name' => 'Source Site']);
        $destinationCompany = Company::factory()->create(['name' => 'Destination Site']);
        $sourceLocation = Location::factory()->create(['company_id' => $sourceCompany->id]);
        $sourceReturnLocation = Location::factory()->create(['company_id' => $sourceCompany->id]);
        $requester = User::factory()->create(['company_id' => $destinationCompany->id]);
        $coordinator = User::factory()->viewAssets()->editAssets()->create(['company_id' => $sourceCompany->id]);
        $discipline = Discipline::create(['name' => 'Transfer Scope', 'created_by' => $coordinator->id]);
        $model = AssetModel::factory()->create();
        $inTransfer = Statuslabel::factory()->create([
            'name' => 'In Transfer',
            'deployable' => 0,
            'pending' => 1,
            'archived' => 0,
        ]);
        $asset = $this->createEligibleAsset($model, $sourceCompany->id, $discipline->id);
        $asset->forceFill([
            'location_id' => $sourceLocation->id,
            'rtd_location_id' => $sourceReturnLocation->id,
            'notes' => 'Existing note',
        ])->save();
        $checkoutRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'company_id' => $destinationCompany->id,
            'requested_discipline_id' => $discipline->id,
            'quantity' => 1,
            'status' => CheckoutRequest::STATUS_PENDING,
        ]);
        $checkoutRequest->coordinatorTargets()->create([
            'user_id' => $coordinator->id,
            'company_id' => $sourceCompany->id,
            'discipline_id' => $discipline->id,
        ]);

        $this->actingAs($coordinator, 'web')
            ->get(route('hardware.index', [
                'request_id' => $checkoutRequest->id,
                'request_bucket' => 'reusable_now',
            ]))
            ->assertOk();

        $this->actingAsForApi($coordinator)
            ->getJson(route('api.assets.index', [
                'request_id' => $checkoutRequest->id,
                'request_bucket' => 'reusable_now',
            ]))
            ->assertOk()
            ->assertJsonPath('rows.0.available_actions.start_transfer', true);

        $this->actingAs($coordinator, 'web')
            ->post(route('hardware.requests.start-transfer', [
                'assetId' => $asset->id,
                'checkoutRequestId' => $checkoutRequest->id,
            ]), ['request_bucket' => 'reusable_now'])
            ->assertRedirect(route('hardware.index', [
                'request_id' => $checkoutRequest->id,
                'request_bucket' => 'reusable_now',
            ]));

        $transferredAsset = Asset::withoutGlobalScopes()->findOrFail($asset->id);
        $checkoutRequest->refresh();

        $this->assertSame($inTransfer->id, $transferredAsset->status_id);
        $this->assertNull($transferredAsset->assigned_to);
        $this->assertSame($sourceCompany->id, $transferredAsset->company_id);
        $this->assertNull($transferredAsset->location_id);
        $this->assertSame($sourceReturnLocation->id, $transferredAsset->rtd_location_id);
        $this->assertStringContainsString('Existing note', $transferredAsset->notes);
        $this->assertStringContainsString('request #'.$checkoutRequest->id, $transferredAsset->notes);
        $this->assertStringContainsString($destinationCompany->name, $transferredAsset->notes);
        $this->assertSame(CheckoutRequest::STATUS_IN_TRANSFER, $checkoutRequest->status);
        $this->assertNull($checkoutRequest->fulfilled_at);
        $this->assertDatabaseHas('checkout_request_assets', [
            'checkout_request_id' => $checkoutRequest->id,
            'asset_id' => $asset->id,
            'allocated_by' => $coordinator->id,
            'transfer_source_company_id' => $sourceCompany->id,
            'transfer_destination_company_id' => $destinationCompany->id,
            'transfer_completed_at' => null,
        ]);
        $this->assertDatabaseHas('checkout_request_coordinators', [
            'checkout_request_id' => $checkoutRequest->id,
            'user_id' => $coordinator->id,
            'resolution_status' => CheckoutRequestCoordinator::RESOLUTION_COMPLETED,
        ]);
    }

    public function test_destination_checkout_completes_a_request_linked_transfer()
    {
        $settings = Setting::getSettings();
        $settings->full_multiple_companies_support = 1;
        $settings->save();
        Setting::$_cache = $settings->fresh();

        $sourceCompany = Company::factory()->create();
        $destinationCompany = Company::factory()->create();
        $requester = User::factory()->create(['company_id' => $destinationCompany->id]);
        $sourceCoordinator = User::factory()->viewAssets()->editAssets()->create(['company_id' => $sourceCompany->id]);
        $destinationCoordinator = User::factory()->viewAssets()->checkoutAssets()->create(['company_id' => $destinationCompany->id]);
        $discipline = Discipline::create(['name' => 'Destination Checkout Scope', 'created_by' => $sourceCoordinator->id]);
        $model = AssetModel::factory()->create();
        Statuslabel::factory()->create([
            'name' => 'In Transfer',
            'deployable' => 0,
            'pending' => 1,
            'archived' => 0,
        ]);
        $ready = Statuslabel::factory()->rtd()->create();
        $asset = $this->createEligibleAsset($model, $sourceCompany->id, $discipline->id);
        $checkoutRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'company_id' => $destinationCompany->id,
            'requested_discipline_id' => $discipline->id,
            'quantity' => 1,
        ]);
        $checkoutRequest->coordinatorTargets()->create([
            'user_id' => $sourceCoordinator->id,
            'company_id' => $sourceCompany->id,
            'discipline_id' => $discipline->id,
        ]);

        $this->actingAs($sourceCoordinator)->post(route('hardware.requests.start-transfer', [
            'assetId' => $asset->id,
            'checkoutRequestId' => $checkoutRequest->id,
        ]))->assertRedirect();

        Asset::withoutGlobalScopes()->findOrFail($asset->id)->forceFill([
            'company_id' => $destinationCompany->id,
            'location_id' => null,
            'rtd_location_id' => null,
            'status_id' => $ready->id,
        ])->save();

        $this->actingAs($destinationCoordinator)
            ->post(route('hardware.checkout.store', $asset->id), [
                'checkout_to_type' => 'user',
                'assigned_user' => $requester->id,
                'expected_checkin' => now()->addWeek()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $checkoutRequest->refresh();
        $this->assertSame(CheckoutRequest::STATUS_FULLY_ALLOCATED, $checkoutRequest->status);
        $this->assertNotNull($checkoutRequest->fulfilled_at);
        $this->assertDatabaseMissing('checkout_request_assets', [
            'checkout_request_id' => $checkoutRequest->id,
            'asset_id' => $asset->id,
            'transfer_completed_at' => null,
        ]);
    }

    public function test_rac_cannot_start_transfer_for_an_asset_outside_their_routed_inventory_scope()
    {
        $settings = Setting::getSettings();
        $settings->full_multiple_companies_support = 1;
        $settings->save();
        Setting::$_cache = $settings->fresh();

        $sourceCompany = Company::factory()->create();
        $destinationCompany = Company::factory()->create();
        $coordinator = User::factory()->viewAssets()->editAssets()->create(['company_id' => $sourceCompany->id]);
        $assetDiscipline = Discipline::create(['name' => 'Asset Transfer Scope', 'created_by' => $coordinator->id]);
        $otherDiscipline = Discipline::create(['name' => 'Other Transfer Scope', 'created_by' => $coordinator->id]);
        $model = AssetModel::factory()->create();
        Statuslabel::factory()->create([
            'name' => 'In Transfer',
            'deployable' => 0,
            'pending' => 1,
            'archived' => 0,
        ]);
        $asset = $this->createEligibleAsset($model, $sourceCompany->id, $assetDiscipline->id);
        $checkoutRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => User::factory()->create(['company_id' => $destinationCompany->id])->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'company_id' => $destinationCompany->id,
            'requested_discipline_id' => $assetDiscipline->id,
            'quantity' => 1,
        ]);
        $checkoutRequest->coordinatorTargets()->create([
            'user_id' => $coordinator->id,
            'company_id' => $sourceCompany->id,
            'discipline_id' => $otherDiscipline->id,
        ]);

        $this->actingAs($coordinator)
            ->post(route('hardware.requests.start-transfer', [
                'assetId' => $asset->id,
                'checkoutRequestId' => $checkoutRequest->id,
            ]))
            ->assertForbidden();

        $this->assertSame(CheckoutRequest::STATUS_PENDING, $checkoutRequest->fresh()->status);
        $this->assertDatabaseMissing('checkout_request_assets', [
            'checkout_request_id' => $checkoutRequest->id,
            'asset_id' => $asset->id,
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

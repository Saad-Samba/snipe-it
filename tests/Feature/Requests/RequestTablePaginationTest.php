<?php

namespace Tests\Feature\Requests;

use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\Company;
use App\Models\Discipline;
use App\Models\Project;
use App\Models\User;
use Tests\TestCase;

class RequestTablePaginationTest extends TestCase
{
    public function test_submitted_request_lines_are_sorted_and_paged_by_project_name(): void
    {
        $requester = User::factory()->requestAssetModels()->create();
        $alpha = Project::factory()->create(['name' => 'Alpha Project']);
        $zulu = Project::factory()->create(['name' => 'Zulu Project']);
        $alphaRequest = CheckoutRequest::factory()->forAssetModel()->create(['user_id' => $requester->id, 'project_id' => $alpha->id]);
        $zuluRequest = CheckoutRequest::factory()->forAssetModel()->create(['user_id' => $requester->id, 'project_id' => $zulu->id]);

        $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index', ['sort' => 'project', 'order' => 'asc', 'limit' => 1]))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.request_id', $alphaRequest->id);

        app()->forgetInstance('api_limit_value');
        app()->forgetInstance('api_offset_value');

        $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index', ['sort' => 'project', 'order' => 'asc', 'limit' => 1, 'offset' => 1]))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.request_id', $zuluRequest->id);

        app()->forgetInstance('api_limit_value');
        app()->forgetInstance('api_offset_value');

        $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index', ['sort' => 'request_id', 'order' => 'desc', 'search' => 'Alpha', 'limit' => 1]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.request_id', $alphaRequest->id);
    }

    public function test_submission_batches_page_whole_batches_and_search_without_losing_lines(): void
    {
        $requester = User::factory()->requestAssetModels()->create();
        $alpha = Project::factory()->create(['name' => 'Alpha Project']);
        $zulu = Project::factory()->create(['name' => 'Zulu Project']);
        $batchId = '00000000-0000-4000-8000-000000000111';
        $alphaRequests = CheckoutRequest::factory()->forAssetModel()->count(2)->create([
            'user_id' => $requester->id,
            'project_id' => $alpha->id,
            'submission_batch_id' => $batchId,
        ]);
        $zuluRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'project_id' => $zulu->id,
        ]);
        foreach ($alphaRequests as $alphaRequest) {
            $alphaRequest->forceFill(['updated_at' => '2026-11-10 10:00:00'])->save();
        }
        $zuluRequest->forceFill(['updated_at' => '2026-10-10 10:00:00'])->save();

        $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index', ['view' => 'batches', 'sort' => 'project', 'order' => 'asc', 'limit' => 1]))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.submission_batch_id', $batchId)
            ->assertJsonPath('rows.0.lines_count', 2);

        app()->forgetInstance('api_limit_value');
        app()->forgetInstance('api_offset_value');

        $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index', ['view' => 'batches', 'sort' => 'project', 'order' => 'asc', 'limit' => 1, 'offset' => 1]))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.project', 'Zulu Project');

        app()->forgetInstance('api_limit_value');
        app()->forgetInstance('api_offset_value');

        $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index', ['view' => 'batches', 'search' => 'Alpha', 'limit' => 1]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.lines_count', 2);

        app()->forgetInstance('api_limit_value');
        app()->forgetInstance('api_offset_value');

        $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index', ['view' => 'batches', 'sort' => 'submitted_at', 'order' => 'desc', 'limit' => 1]))
            ->assertOk()
            ->assertJsonPath('rows.0.project', 'Alpha Project');
    }

    public function test_received_requests_are_sorted_and_paged_by_project_name(): void
    {
        $requester = User::factory()->create();
        $rac = User::factory()->create();
        $company = Company::factory()->create();
        $discipline = Discipline::create(['name' => 'Pagination QA', 'created_by' => $requester->id]);
        $model = AssetModel::factory()->create();
        $alpha = Project::factory()->create(['name' => 'Alpha Project']);
        $zulu = Project::factory()->create(['name' => 'Zulu Project']);

        foreach ([$zulu, $alpha] as $project) {
            $request = CheckoutRequest::factory()->forAssetModel()->create([
                'user_id' => $requester->id,
                'requestable_id' => $model->id,
                'project_id' => $project->id,
            ]);
            $target = $request->coordinatorTargets()->create([
                'user_id' => $rac->id,
                'company_id' => $company->id,
                'discipline_id' => $discipline->id,
            ]);
            $target->forceFill(['created_at' => $project->id === $zulu->id ? '2026-10-10 10:00:00' : '2026-11-10 10:00:00'])->save();
        }

        $this->actingAsForApi($rac)
            ->getJson(route('api.rac-requests.index', ['sort' => 'project', 'order' => 'asc', 'limit' => 1]))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.project', 'Alpha Project');

        app()->forgetInstance('api_limit_value');
        app()->forgetInstance('api_offset_value');

        $this->actingAsForApi($rac)
            ->getJson(route('api.rac-requests.index', ['sort' => 'project', 'order' => 'asc', 'limit' => 1, 'offset' => 1]))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.project', 'Zulu Project');

        app()->forgetInstance('api_limit_value');
        app()->forgetInstance('api_offset_value');

        $this->actingAsForApi($rac)
            ->getJson(route('api.rac-requests.index', ['sort' => 'received_at', 'order' => 'desc', 'limit' => 1]))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('rows.0.project', 'Alpha Project');
    }

    public function test_project_request_drill_down_uses_server_pagination(): void
    {
        $viewer = User::factory()->superuser()->create();
        $requester = User::factory()->create();
        $project = Project::factory()->create();
        CheckoutRequest::factory()->forAssetModel()->create(['user_id' => $requester->id, 'project_id' => $project->id]);
        $newer = CheckoutRequest::factory()->forAssetModel()->create(['user_id' => $requester->id, 'project_id' => $project->id]);

        $this->actingAsForApi($viewer)
            ->getJson(route('api.requests.index', [
                'project_id' => $project->id,
                'project_overview' => 1,
                'sort' => 'request_id',
                'order' => 'desc',
                'limit' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.request_id', $newer->id);
    }
}

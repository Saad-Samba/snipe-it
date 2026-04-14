<?php

namespace Tests\Feature\StatusLabels\Api;

use App\Models\Statuslabel;
use App\Models\User;
use Tests\TestCase;

class UpdateStatusLabelsTest extends TestCase
{
    public function testApiStoresFinanceRelevantFlag()
    {
        $this->actingAsForApi(User::factory()->superuser()->create())
            ->postJson(route('api.statuslabels.store'), [
                'name' => 'Finance Relevant Status',
                'type' => 'undeployable',
                'finance_relevant' => 1,
            ])
            ->assertOk()
            ->assertStatusMessageIs('success');

        $this->assertDatabaseHas('status_labels', [
            'name' => 'Finance Relevant Status',
            'finance_relevant' => 1,
        ]);
    }

    public function testApiIncludesFinanceRelevantFlag()
    {
        $statusLabel = Statuslabel::factory()->create(['finance_relevant' => 1]);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.statuslabels.show', $statusLabel))
            ->assertOk()
            ->assertJsonPath('finance_relevant', true);
    }
}

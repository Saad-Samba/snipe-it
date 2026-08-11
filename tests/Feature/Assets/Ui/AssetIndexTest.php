<?php

namespace Tests\Feature\Assets\Ui;

use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Statuslabel;
use App\Models\User;
use Tests\TestCase;

class AssetIndexTest extends TestCase
{
    public function testPageRenders()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('hardware.index'))
            ->assertOk()
            ->assertSee('data-export-data-type="all"', false);
    }

    public function testSidebarGroupsStatusLabelsAndFlags()
    {
        $statusLabel = Statuslabel::factory()->readyToDeploy()->create([
            'name' => 'Ready to Deploy',
        ]);
        $statusLabel->show_in_nav = 1;
        $statusLabel->default_label = 1;
        $statusLabel->save();

        $response = $this->actingAs(User::factory()->superuser()->create())
            ->get(route('hardware.index'));

        $response
            ->assertOk()
            ->assertSeeText('Status Labels')
            ->assertSeeText('Flags')
            ->assertSee('assignment=assigned', false)
            ->assertSee('assignment=unassigned', false)
            ->assertDontSee('requestable-sidenav-option', false)
            ->assertDontSee('hardware?status=Requestable', false)
            ->assertDontSee(route('assets.requested'), false)
            ->assertDontSee('hardware?status=Pending', false)
            ->assertDontSee('hardware?status=Undeployable', false)
            ->assertDontSee('hardware?status=BYOD', false)
            ->assertDontSee('hardware?status=Archived', false);
    }

    public function testAssetIndexPropagatesStackedAssignmentAndObsoleteFiltersToApiUrl()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('hardware.index', ['assignment' => 'assigned', 'model_obsolete' => 1]))
            ->assertOk()
            ->assertSee(route('api.assets.index'), false)
            ->assertSee('assignment=assigned', false)
            ->assertSee('model_obsolete=1', false);
    }

    public function test_reusable_assets_view_explains_model_and_availability_filters(): void
    {
        $category = Category::factory()->forAssets()->create([
            'name' => 'Communication',
        ]);
        $model = AssetModel::factory()->create([
            'name' => 'Desk Phone',
            'category_id' => $category->id,
        ]);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('hardware.index', [
                'model_id' => $model->id,
                'reusable_assets' => 1,
            ]))
            ->assertOk()
            ->assertSeeText('Reusable Assets — Desk Phone')
            ->assertSeeText('Filtered view')
            ->assertSeeText('Model: Desk Phone')
            ->assertSeeText('Availability: Reusable')
            ->assertSeeText('Back to available models')
            ->assertSeeText('Clear all filters');
    }
}

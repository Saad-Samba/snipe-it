<?php

namespace Tests\Feature\Assets\Ui;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Company;
use App\Models\Location;
use App\Models\Statuslabel;
use App\Models\User;
use Tests\TestCase;

class AssetIndexTest extends TestCase
{
    public function testPageRenders()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('hardware.index'))
            ->assertOk();
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
            ->assertDontSeeText('Pending')
            ->assertDontSeeText('Un-deployable')
            ->assertDontSeeText('BYOD')
            ->assertDontSeeText('Archived');
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

    public function testAssetIndexSeedsServerBackedSelectFilterSourcesFromAssetBackedValues()
    {
        $usedCompany = Company::factory()->create(['name' => 'Used Company']);
        $usedLocation = Location::factory()->create(['name' => 'Used Location']);
        $usedStatus = Statuslabel::factory()->readyToDeploy()->create(['name' => 'Used Status']);
        $usedModel = AssetModel::factory()->create(['name' => 'Used Model']);
        Asset::factory()->create([
            'company_id' => $usedCompany->id,
            'location_id' => $usedLocation->id,
            'status_id' => $usedStatus->id,
            'model_id' => $usedModel->id,
        ]);

        AssetModel::factory()->create(['name' => 'Unused Model']);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('hardware.index'))
            ->assertOk()
            ->assertSee('data-filter-control-server-cascade="true"', false)
            ->assertSee('filterData', false)
            ->assertSee('json:{', false)
            ->assertSee('Used Model', false)
            ->assertSee('Used Company', false)
            ->assertSee('Used Location', false)
            ->assertSee('Used Status', false)
            ->assertDontSee('Unused Model', false)
            ->assertDontSee('data-filter-control-current-page-only', false);
    }
}

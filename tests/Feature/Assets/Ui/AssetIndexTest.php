<?php

namespace Tests\Feature\Assets\Ui;

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

    public function testSidebarGroupsStatusLabelsOperationalStateAndFlags()
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
            ->assertDontSee('assignment=assigned', false)
            ->assertDontSee('assignment=unassigned', false)
            ->assertDontSeeText('Operational State')
            ->assertDontSeeText('Pending')
            ->assertDontSeeText('Un-deployable')
            ->assertDontSeeText('BYOD')
            ->assertDontSeeText('Archived');
    }
}

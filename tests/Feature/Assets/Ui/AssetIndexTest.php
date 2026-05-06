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
        Statuslabel::factory()->readyToDeploy()->create([
            'name' => 'Ready to Deploy',
            'show_in_nav' => 1,
        ]);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('hardware.index'))
            ->assertOk()
            ->assertSeeText('Status Labels')
            ->assertSeeText('Operational State')
            ->assertSeeText('Flags')
            ->assertSeeText('Assigned / In Use')
            ->assertSeeText('Unassigned Ready to Deploy');
    }
}

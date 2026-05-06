<?php

namespace Tests\Feature\StatusLabels\Ui;

use App\Models\Statuslabel;
use App\Models\User;
use Tests\TestCase;

class ShowStatusLabelTest extends TestCase
{
    public function testPageRenders()
    {
        $statuslabel = Statuslabel::factory()->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('statuslabels.show', $statuslabel))
            ->assertOk()
            ->assertSee('status_id='.$statuslabel->id, false)
            ->assertSee('model_obsolete=1', false)
            ->assertSee('model_obsolete=0', false);
    }
}

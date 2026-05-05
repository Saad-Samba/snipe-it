<?php

namespace Tests\Feature\Assets\Ui;

use App\Models\Asset;
use App\Models\User;
use Tests\TestCase;

class ShowAssetTest extends TestCase
{
    public function testPageForAssetWithMissingModelStillRenders()
    {
        $asset = Asset::factory()->create();

        $asset->model_id = null;
        $asset->forceSave();

        $asset->refresh();

        $this->assertNull($asset->fresh()->model_id, 'This test needs model_id to be null to be helpful.');

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('hardware.show', $asset))
            ->assertOk();
    }

    public function testPageShowsObsoleteIndicatorWhenAssetModelIsObsolete()
    {
        $asset = Asset::factory()->create([
            'model_id' => \App\Models\AssetModel::factory()->create(['obsolete' => true])->id,
        ]);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('hardware.show', $asset))
            ->assertOk()
            ->assertSeeText(trans('admin/models/general.obsolete_indicator'));
    }
}

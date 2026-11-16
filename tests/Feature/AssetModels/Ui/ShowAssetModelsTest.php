<?php

namespace Tests\Feature\AssetModels\Ui;

use App\Models\AssetModel;
use App\Models\User;
use Tests\TestCase;

class ShowAssetModelsTest extends TestCase
{
    public function testPageRenders()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('models.show', AssetModel::factory()->create()))
            ->assertOk();
    }

    public function testPageShowsObsoleteState()
    {
        $model = AssetModel::factory()->create(['obsolete' => true, 'eol' => 36]);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('models.show', $model))
            ->assertOk()
            ->assertSeeText(trans('admin/models/table.obsolete'))
            ->assertDontSeeText(trans('general.eol'))
            ->assertSeeText(trans('general.yes'));
    }
}

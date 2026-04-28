<?php

namespace Tests\Feature\AssetModels\Ui;

use App\Models\AssetModel;
use App\Models\User;
use Tests\TestCase;

class BulkEditAssetModelsTest extends TestCase
{
    public function testUserCanBulkSetAssetModelsObsolete()
    {
        $models = AssetModel::factory()->count(2)->create(['obsolete' => false]);

        $this->actingAs(User::factory()->superuser()->create())
            ->post(route('models.bulkedit.store'), [
                'ids' => $models->pluck('id')->all(),
                'obsolete' => '1',
            ])
            ->assertRedirect(route('models.index'))
            ->assertSessionHasNoErrors();

        AssetModel::findMany($models->pluck('id'))->each(function (AssetModel $model) {
            $this->assertTrue($model->obsolete);
        });
    }

    public function testUserCanBulkClearAssetModelsObsolete()
    {
        $models = AssetModel::factory()->count(2)->create(['obsolete' => true]);

        $this->actingAs(User::factory()->superuser()->create())
            ->post(route('models.bulkedit.store'), [
                'ids' => $models->pluck('id')->all(),
                'obsolete' => '0',
            ])
            ->assertRedirect(route('models.index'))
            ->assertSessionHasNoErrors();

        AssetModel::findMany($models->pluck('id'))->each(function (AssetModel $model) {
            $this->assertFalse($model->obsolete);
        });
    }
}

<?php

namespace Tests\Feature\AssetModels\Ui;

use App\Models\AssetModel;
use App\Models\Category;
use App\Models\CustomFieldset;
use App\Models\User;
use Tests\TestCase;

class BulkEditAssetModelsTest extends TestCase
{
    public function testBulkEditPageDoesNotShowModelFieldsetControlWhileDisabled()
    {
        $models = AssetModel::factory()->count(2)->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->post(route('models.bulkedit.index'), [
                'ids' => $models->pluck('id')->all(),
                'bulk_actions' => 'edit',
            ])
            ->assertOk()
            ->assertDontSeeHtml('name="fieldset_id"');
    }

    public function testBulkEditRejectsModelFieldsetOverrideWhileDisabled()
    {
        $models = AssetModel::factory()->count(2)->create(['fieldset_id' => null]);

        $this->actingAs(User::factory()->superuser()->create())
            ->from(route('models.index'))
            ->post(route('models.bulkedit.store'), [
                'ids' => $models->pluck('id')->all(),
                'fieldset_id' => CustomFieldset::factory()->create()->id,
            ])
            ->assertRedirect(route('models.index'))
            ->assertSessionHasErrors(['fieldset_id']);

        AssetModel::findMany($models->pluck('id'))->each(function (AssetModel $model) {
            $this->assertNull($model->fieldset_id);
        });
    }

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

    public function testAfmCannotBulkEditUnmanagedModels()
    {
        $afm = User::factory()->create();
        $managedCategory = Category::factory()->forAssets()->create([
            'manager_id' => $afm->id,
        ]);
        $managedModel = AssetModel::factory()->create([
            'category_id' => $managedCategory->id,
            'obsolete' => false,
        ]);
        $unmanagedModel = AssetModel::factory()->create([
            'obsolete' => false,
        ]);

        $this->actingAs($afm)
            ->post(route('models.bulkedit.store'), [
                'ids' => [$managedModel->id, $unmanagedModel->id],
                'obsolete' => '1',
            ])
            ->assertForbidden();

        $this->assertFalse($managedModel->fresh()->obsolete);
        $this->assertFalse($unmanagedModel->fresh()->obsolete);
    }
}

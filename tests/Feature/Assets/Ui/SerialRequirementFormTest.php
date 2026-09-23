<?php

namespace Tests\Feature\Assets\Ui;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\User;
use Tests\TestCase;

class SerialRequirementFormTest extends TestCase
{
    public function testCreateFormUsesPreselectedModelsSerialRequirement(): void
    {
        $model = AssetModel::factory()->create(['require_serial' => 1]);

        $response = $this->actingAs(User::factory()->createAssets()->create())
            ->get(route('hardware.create', ['model_id' => $model->id]))
            ->assertOk()
            ->assertSeeInOrder(['id="model_select_id"', 'id="serials[1]"'], false)
            ->assertSee(trans('admin/hardware/form.serial_required_for_model'));

        $this->assertMatchesRegularExpression('/id="serials\[1\]"[^>]*\srequired/', $response->getContent());
    }

    public function testEditFormUsesOldModelInsteadOfSavedModelAfterValidationError(): void
    {
        $requiredModel = AssetModel::factory()->create(['require_serial' => 1]);
        $optionalModel = AssetModel::factory()->create(['require_serial' => 0]);
        $asset = Asset::factory()->create(['model_id' => $requiredModel->id]);

        $response = $this->actingAs(User::factory()->editAssets()->create())
            ->withSession(['_old_input' => ['model_id' => $optionalModel->id, 'serials' => [1 => 'KEPT-SERIAL']]])
            ->get(route('hardware.edit', $asset))
            ->assertOk()
            ->assertSeeInOrder(['id="model_select_id"', 'id="serials[1]"'], false)
            ->assertSee('KEPT-SERIAL');

        $this->assertDoesNotMatchRegularExpression('/id="serials\[1\]"[^>]*\srequired/', $response->getContent());
    }

    public function testCloneFormRetainsModelBasedSerialRequirement(): void
    {
        $model = AssetModel::factory()->create(['require_serial' => 1]);
        $asset = Asset::factory()->create(['model_id' => $model->id]);

        $response = $this->actingAs(User::factory()->createAssets()->create())
            ->get(route('clone/hardware', $asset))
            ->assertOk()
            ->assertSeeInOrder(['id="model_select_id"', 'id="serials[1]"'], false);

        $this->assertMatchesRegularExpression('/id="serials\[1\]"[^>]*\srequired/', $response->getContent());
    }

    public function testModelSelectlistExposesSerialRequirement(): void
    {
        foreach ([0, 1] as $required) {
            $model = AssetModel::factory()->create(['require_serial' => $required]);
            $response = $this->actingAsForApi(User::factory()->superuser()->create())
                ->getJson(route('api.models.selectlist', ['search' => $model->name]))
                ->assertOk();

            $entry = collect($response->json('results'))->firstWhere('id', $model->id);
            $this->assertNotNull($entry);
            $this->assertSame((bool) $required, $entry['require_serial']);
        }
    }
}

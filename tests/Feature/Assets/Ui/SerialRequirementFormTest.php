<?php

namespace Tests\Feature\Assets\Ui;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\User;
use Tests\TestCase;

class SerialRequirementFormTest extends TestCase
{
    public function testSerialInputIsOptionalEvenForPreviouslyRequiredModel(): void
    {
        $model = AssetModel::factory()->create(['require_serial' => 1]);
        $asset = Asset::factory()->create(['model_id' => $model->id]);
        $user = User::factory()->superuser()->create();

        foreach ([route('hardware.create', ['model_id' => $model->id]), route('hardware.edit', $asset), route('clone/hardware', $asset)] as $url) {
            $response = $this->actingAs($user)->get($url)->assertOk();
            $this->assertDoesNotMatchRegularExpression('/id="serials\[1\]"[^>]*\srequired/', $response->getContent());
            $response->assertDontSee('serial-requirement-help');
        }
    }
}

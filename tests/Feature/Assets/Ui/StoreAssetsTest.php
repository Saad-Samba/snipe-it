<?php

namespace Tests\Feature\Assets\Ui;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Company;
use App\Models\StatusLabel;
use App\Models\User;
use Tests\TestCase;

class StoreAssetsTest extends TestCase
{
    public function testPageRenders()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('hardware.create'))
            ->assertOk();
    }

    public function testAssetCanBeStoredWithSerialRequiredAndSerialProvided()
    {
        $user = User::factory()->superuser()->create();
        $this->actingAs($user);

        $model = AssetModel::factory()->create([
            'require_serial' => 1,
        ]);

        $response = $this->post(route('hardware.store'), [
            'company_id' => Company::factory()->create()->id,
            'model_id' => $model->id,
            'serials' => [1 => 'ABC123'],
            'asset_tags' =>[1 => '1234'],
            'status_id' => 1,
            // other required fields...
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success-unescaped');
        $this->assertNotEquals(
            trans('admin/hardware/form.serial_required'),
            session('error')
        );
        $this->assertDatabaseHas('assets', [
            'model_id' => $model->id,
            'serial' => 'ABC123',
            'asset_tag' => '1234',
        ]);


    }

    public function testAssetCannotBeStoredIfSerialRequiredAndMissing()
    {
        $user = User::factory()->superuser()->create();
        $this->actingAs($user);

        $model = AssetModel::factory()->create([
            'require_serial' => 1,
        ]);

        $response = $this->post(route('hardware.store'), [
            'model_id' => $model->id,
            'serials' => [], // ← serial missing
            'asset_tags' => [1 => '1234'],
            'status_id' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['serials.1']);

        $this->assertDatabaseMissing('assets', [
            'model_id' => $model->id,
            'asset_tag' => '1234',
        ]);

        $response->assertSessionMissing('success-unescaped');
    }

    public function testCompanyIsRequiredWhenStoringAsset()
    {
        $user = User::factory()->superuser()->create(['company_id' => null]);
        $this->actingAs($user);

        $response = $this->from(route('hardware.create'))->post(route('hardware.store'), [
            'model_id' => AssetModel::factory()->create()->id,
            'asset_tags' => [1 => '1234'],
            'status_id' => StatusLabel::factory()->create()->id,
        ]);

        $response->assertRedirect(route('hardware.create'));
        $response->assertSessionHasErrors([
            'company_id' => 'The company field is required.',
        ]);

        $this->assertDatabaseMissing('assets', [
            'asset_tag' => '1234',
        ]);
    }

    public function testCompanyIsRequiredWhenStoringAssetWithFmcsDisabledEvenIfUserHasACompany()
    {
        $user = User::factory()->superuser()->create();
        $this->actingAs($user);

        $response = $this->from(route('hardware.create'))->post(route('hardware.store'), [
            'model_id' => AssetModel::factory()->create()->id,
            'asset_tags' => [1 => '1235'],
            'status_id' => StatusLabel::factory()->create()->id,
        ]);

        $response->assertRedirect(route('hardware.create'));
        $response->assertSessionHasErrors([
            'company_id' => 'The company field is required.',
        ]);

        $this->assertDatabaseMissing('assets', [
            'asset_tag' => '1235',
        ]);
    }
}

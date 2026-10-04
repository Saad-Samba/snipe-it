<?php

namespace Tests\Feature\Assets\Ui;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Company;
use App\Models\License;
use App\Models\Location;
use App\Models\StatusLabel;
use App\Models\User;
use Tests\TestCase;

class StoreAssetsTest extends TestCase
{
    public function testPageRenders()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('hardware.create'))
            ->assertOk()
            ->assertSee('platform_dongle_license')
            ->assertSee('License entitlement');
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

    public function testPlatformDongleCanOnlyBeCreatedOneAtATimeWithALicense(): void
    {
        $user = User::factory()->superuser()->create();
        $model = AssetModel::factory()->create(['name' => 'SIEMENS DONGLE']);
        $license = License::factory()->create(['seats' => 1]);
        $location = Location::factory()->create();

        $response = $this->actingAs($user)->post(route('hardware.store'), [
            'company_id' => Company::factory()->create()->id,
            'model_id' => $model->id,
            'license_id' => $license->id,
            'name' => 'Ignore this name',
            'rtd_location_id' => $location->id,
            'serials' => [1 => 'DONGLE-SERIAL'],
            'asset_tags' => [1 => 'SIEMENS-001'],
            'status_id' => StatusLabel::factory()->readyToDeploy()->create()->id,
        ]);

        $response->assertRedirect();
        $asset = Asset::where('asset_tag', 'SIEMENS-001')->sole();
        $this->assertNull($asset->name);
        $this->assertNull($asset->location_id);
        $this->assertNull($asset->rtd_location_id);
        $this->assertDatabaseHas('license_seats', ['license_id' => $license->id, 'asset_id' => $asset->id]);
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
            'company_id' => 'The site field is required.',
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
            'company_id' => 'The site field is required.',
        ]);

        $this->assertDatabaseMissing('assets', [
            'asset_tag' => '1235',
        ]);
    }
}

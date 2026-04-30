<?php

namespace Tests\Feature\Assets\Ui;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Statuslabel;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ProvidesDataForFullMultipleCompanySupportTesting;
use Tests\TestCase;

class StoreAssetWithFullMultipleCompanySupportTest extends TestCase
{
    use ProvidesDataForFullMultipleCompanySupportTesting;

    #[DataProvider('dataForFullMultipleCompanySupportTesting')]
    public function testAdheresToFullMultipleCompaniesSupportScoping($data)
    {
        ['actor' => $actor, 'company_attempting_to_associate' => $company, 'assertions' => $assertions] = $data();

        $this->settings->enableMultipleFullCompanySupport();

        $response = $this->actingAs($actor)
            ->post(route('hardware.store'), [
                'asset_tags' => ['1' => '1234'],
                'model_id' => AssetModel::factory()->create()->id,
                'status_id' => Statuslabel::factory()->create()->id,
                'company_id' => $company->id,
            ]);

        if (is_null($actor->company_id) && (! $actor->isSuperUser())) {
            $response->assertSessionHasErrors([
                'company_id' => 'You cannot complete this action because your account is not assigned to a company.',
            ]);
            $this->assertDatabaseMissing('assets', [
                'asset_tag' => '1234',
            ]);

            return;
        }

        $asset = Asset::where('asset_tag', '1234')->sole();

        $assertions($asset);
    }

    public function testRejectsUserWithoutCompanyAssignment()
    {
        $this->settings->enableMultipleFullCompanySupport();

        $actor = User::factory()->createAssets()->create(['company_id' => null]);

        $response = $this->actingAs($actor)
            ->from(route('hardware.create'))
            ->post(route('hardware.store'), [
                'asset_tags' => ['1' => '1234'],
                'model_id' => AssetModel::factory()->create()->id,
                'status_id' => Statuslabel::factory()->create()->id,
            ]);

        $response->assertRedirect(route('hardware.create'));
        $response->assertSessionHasErrors([
            'company_id' => 'You cannot complete this action because your account is not assigned to a company.',
        ]);

        $this->assertDatabaseMissing('assets', [
            'asset_tag' => '1234',
        ]);
    }
}

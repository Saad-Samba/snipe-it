<?php

namespace Tests\Feature\Assets\Api;

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

    /**
     * @link https://github.com/grokability/snipe-it/issues/15654
     */
    #[DataProvider('dataForFullMultipleCompanySupportTesting')]
    public function testAdheresToFullMultipleCompaniesSupportScoping($data)
    {
        ['actor' => $actor, 'company_attempting_to_associate' => $company, 'assertions' => $assertions] = $data();

        $this->settings->enableMultipleFullCompanySupport();

        $response = $this->actingAsForApi($actor)
            ->postJson(route('api.assets.store'), [
                'asset_tag' => 'random_string',
                'company_id' => $company->id,
                'model_id' => AssetModel::factory()->create()->id,
                'status_id' => Statuslabel::factory()->readyToDeploy()->create()->id,
            ]);

        if (is_null($actor->company_id) && (! $actor->isSuperUser())) {
            $response
                ->assertStatusMessageIs('error')
                ->assertJsonPath('messages.company_id.0', 'You cannot complete this action because your account is not assigned to a company.');

            $this->assertDatabaseMissing('assets', [
                'asset_tag' => 'random_string',
            ]);

            return;
        }

        $asset = Asset::withoutGlobalScopes()->findOrFail($response['payload']['id']);

        $assertions($asset);
    }

    #[DataProvider('dataForFullMultipleCompanySupportTesting')]
    public function testHandlesCompanyIdBeingString($data)
    {
        ['actor' => $actor, 'company_attempting_to_associate' => $company, 'assertions' => $assertions] = $data();

        $this->settings->enableMultipleFullCompanySupport();

        $response = $this->actingAsForApi($actor)
            ->postJson(route('api.assets.store'), [
                'asset_tag' => 'random_string',
                'company_id' => (string) $company->id,
                'model_id' => AssetModel::factory()->create()->id,
                'status_id' => Statuslabel::factory()->readyToDeploy()->create()->id,
            ]);

        if (is_null($actor->company_id) && (! $actor->isSuperUser())) {
            $response
                ->assertStatusMessageIs('error')
                ->assertJsonPath('messages.company_id.0', 'You cannot complete this action because your account is not assigned to a company.');

            $this->assertDatabaseMissing('assets', [
                'asset_tag' => 'random_string',
            ]);

            return;
        }

        $asset = Asset::withoutGlobalScopes()->findOrFail($response['payload']['id']);

        $assertions($asset);
    }

    public function testRejectsUserWithoutCompanyAssignment()
    {
        $this->settings->enableMultipleFullCompanySupport();

        $actor = User::factory()->createAssets()->create(['company_id' => null]);

        $this->actingAsForApi($actor)
            ->postJson(route('api.assets.store'), [
                'asset_tag' => 'random_string',
                'model_id' => AssetModel::factory()->create()->id,
                'status_id' => Statuslabel::factory()->readyToDeploy()->create()->id,
            ])
            ->assertStatusMessageIs('error')
            ->assertJsonPath('messages.company_id.0', 'You cannot complete this action because your account is not assigned to a company.');

        $this->assertDatabaseMissing('assets', [
            'asset_tag' => 'random_string',
        ]);
    }

    public function testUsesActorsCompanyWhenCompanyIdIsOmitted()
    {
        $this->settings->enableMultipleFullCompanySupport();

        $actor = User::factory()->forCompany()->createAssets()->create();

        $response = $this->actingAsForApi($actor)
            ->postJson(route('api.assets.store'), [
                'asset_tag' => 'random_string',
                'model_id' => AssetModel::factory()->create()->id,
                'status_id' => Statuslabel::factory()->readyToDeploy()->create()->id,
            ])
            ->assertStatusMessageIs('success')
            ->json();

        $asset = Asset::withoutGlobalScopes()->findOrFail($response['payload']['id']);

        $this->assertEquals($actor->company_id, $asset->company_id);
    }
}

<?php

namespace Tests\Feature\Requests;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\Discipline;
use App\Models\Group;
use App\Models\Statuslabel;
use App\Models\User;
use Tests\TestCase;

class RequestableModelsPageTest extends TestCase
{
    public function test_requester_sees_a_model_only_catalogue_with_cart_actions(): void
    {
        $requester = User::factory()->requestAssetModels()->create();
        $category = Category::factory()->forAssets()->create(['name' => 'Communication']);
        $requestableModel = AssetModel::factory()->create([
            'name' => 'Industrial Ethernet Switch',
            'category_id' => $category->id,
            'reference_price' => 1500,
        ]);
        $unavailableModel = AssetModel::factory()->create([
            'name' => 'Unavailable Model',
            'category_id' => $category->id,
        ]);

        Asset::factory()->create([
            'model_id' => $requestableModel->id,
            'status_id' => Statuslabel::factory()->readyToDeploy(),
            'requestable' => true,
        ]);
        Company::factory()->create(['name' => 'Casablanca Site']);
        Discipline::create(['name' => 'Operations', 'created_by' => $requester->id]);

        $this->actingAs($requester)
            ->get(route('requestable-assets'))
            ->assertOk()
            ->assertSeeText('Request Models')
            ->assertDontSeeText('Enter the required quantity and destination scope')
            ->assertSeeText('Industrial Ethernet Switch')
            ->assertSeeText('Communication')
            ->assertSeeText('Reusable Assets')
            ->assertSeeText('Destination Site')
            ->assertSeeText('Add to Request')
            ->assertSeeText('Add Selected to Cart')
            ->assertSeeText('Request Cart')
            ->assertSee('Recently released feature')
            ->assertSee('Reuse Planning Totals')
            ->assertDontSee('Reuse Planning Totals <span class="label label-info"', false)
            ->assertDontSee('model-request-cart-project">'.trans('general.project').' <span class="label label-info"', false)
            ->assertDontSee('model-request-cart-needed-by-date">Needed By <span class="label label-info"', false)
            ->assertSeeText('Submitted Requests')
            ->assertSee('model-booking-quantity-'.$requestableModel->id, false)
            ->assertSee('model-booking-discipline-'.$requestableModel->id, false)
            ->assertSee('model-booking-company-'.$requestableModel->id, false)
            ->assertSee('data-click-to-select="false"', false)
            ->assertDontSee('add-model-to-request-cart-modal', false)
            ->assertDontSeeText('Unavailable Model')
            ->assertDontSee('href="#assets"', false)
            ->assertDontSee(route('api.assets.requestable'), false);
    }

    public function test_user_without_model_request_permission_cannot_open_request_catalogue(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('requestable-assets'))
            ->assertForbidden();
    }

    public function test_cross_company_catalogue_permission_shows_requestable_models_outside_the_epl_company_under_fmcs(): void
    {
        $this->settings->enableMultipleFullCompanySupport();

        $eplCompany = Company::factory()->create();
        $sourceCompany = Company::factory()->create();
        $epl = User::factory()->create([
            'company_id' => $eplCompany->id,
            'permissions' => json_encode([
                'models.request' => '1',
                'models.request.all_companies' => '1',
            ]),
        ]);
        $model = AssetModel::factory()->create([
            'name' => 'Cross-company reusable model',
            'category_id' => Category::factory()->forAssets()->create()->id,
        ]);

        Asset::factory()->create([
            'model_id' => $model->id,
            'company_id' => $sourceCompany->id,
            'status_id' => Statuslabel::factory()->readyToDeploy(),
            'requestable' => true,
        ]);

        $this->actingAs($epl)
            ->get(route('requestable-assets'))
            ->assertOk()
            ->assertSeeText('Cross-company reusable model');
    }

    public function test_regular_requester_remains_company_scoped_under_fmcs(): void
    {
        $this->settings->enableMultipleFullCompanySupport();

        $requesterCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $requester = User::factory()->requestAssetModels()->create([
            'company_id' => $requesterCompany->id,
        ]);
        $model = AssetModel::factory()->create([
            'name' => 'Other-company-only reusable model',
            'category_id' => Category::factory()->forAssets()->create()->id,
        ]);

        Asset::factory()->create([
            'model_id' => $model->id,
            'company_id' => $otherCompany->id,
            'status_id' => Statuslabel::factory()->readyToDeploy(),
            'requestable' => true,
        ]);

        $this->actingAs($requester)
            ->get(route('requestable-assets'))
            ->assertOk()
            ->assertDontSeeText('Other-company-only reusable model');
    }

    public function test_group_permission_shows_requestable_models_across_companies_under_fmcs(): void
    {
        $this->settings->enableMultipleFullCompanySupport();

        $eplCompany = Company::factory()->create();
        $sourceCompany = Company::factory()->create();
        $epl = User::factory()->create(['company_id' => $eplCompany->id]);
        $eplGroup = Group::factory()->create([
            'permissions' => json_encode([
                'models.request' => '1',
                'models.request.all_companies' => '1',
            ]),
        ]);
        $epl->groups()->attach($eplGroup);

        $model = AssetModel::factory()->create([
            'name' => 'Group-visible cross-company model',
            'category_id' => Category::factory()->forAssets()->create()->id,
        ]);
        Asset::factory()->create([
            'model_id' => $model->id,
            'company_id' => $sourceCompany->id,
            'status_id' => Statuslabel::factory()->readyToDeploy(),
            'requestable' => true,
        ]);

        $this->assertTrue($epl->hasAccess('models.request'));
        $this->assertTrue($epl->hasAccess('models.request.all_companies'));

        $this->actingAs($epl)
            ->get(route('requestable-assets'))
            ->assertOk()
            ->assertSeeText('Group-visible cross-company model');
    }
}

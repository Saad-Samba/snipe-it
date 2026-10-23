<?php

namespace Tests\Feature\Requests;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\CustomField;
use App\Models\CustomFieldset;
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
            ->assertSee('id="requestableModelsToolbar"', false)
            ->assertSee('id="requestableModelsCategoryFilter"', false)
            ->assertSee('id="requestableModelsDiscipline"', false)
            ->assertSee('id="requestableModelsCompany"', false)
            ->assertSeeText('Communication')
            ->assertSee('data-advanced-search="false"', false)
            ->assertSee('Model, specification, or Field: value', false)
            ->assertSeeText('Add to Cart')
            ->assertDontSee('requestableModelsBulkForm', false)
            ->assertDontSee('requestableModelsBulkAddButton', false)
            ->assertDontSee('data-checkbox="true"', false)
            ->assertSeeText('Request Cart')
            ->assertSee('Recently released feature')
            ->assertSee('Reuse Planning Totals')
            ->assertDontSee('Reuse Planning Totals <span class="label label-info"', false)
            ->assertDontSee('model-request-cart-project">'.trans('general.project').' <span class="label label-info"', false)
            ->assertDontSee('model-request-cart-needed-by-date">Needed By <span class="label label-info"', false)
            ->assertSeeText('Submitted Requests')
            ->assertSee('model-booking-quantity-'.$requestableModel->id, false)
            ->assertDontSee('model-booking-discipline-'.$requestableModel->id, false)
            ->assertDontSee('model-booking-company-'.$requestableModel->id, false)
            ->assertSee('data-click-to-select="false"', false)
            ->assertDontSee('add-model-to-request-cart-modal', false)
            ->assertSeeText('Unavailable Model')
            ->assertSee('<td>0</td>', false)
            ->assertDontSee('href="#assets"', false)
            ->assertDontSee(route('api.assets.requestable'), false);
    }

    public function test_user_without_model_request_permission_cannot_open_request_catalogue(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('requestable-assets', ['all' => 1]))
            ->assertForbidden();
    }

    public function test_catalogue_paginates_and_filters_models_on_the_server(): void
    {
        $requester = User::factory()->requestAssetModels()->create();
        $category = Category::factory()->forAssets()->create(['name' => 'Pagination QA']);

        foreach (range(1, 26) as $number) {
            AssetModel::factory()->create([
                'name' => sprintf('Paged catalogue model %02d', $number),
                'category_id' => $category->id,
            ]);
        }

        $this->actingAs($requester)
            ->get(route('requestable-assets'))
            ->assertOk()
            ->assertSeeText('Paged catalogue model 01')
            ->assertDontSeeText('Paged catalogue model 26')
            ->assertSee('page=2', false);

        $this->actingAs($requester)
            ->get(route('requestable-assets', ['page' => 2, 'search' => 'Paged catalogue model']))
            ->assertOk()
            ->assertSeeText('Paged catalogue model 26');
    }

    public function test_catalogue_displays_only_eligible_model_specifications(): void
    {
        $requester = User::factory()->requestAssetModels()->create();
        $fieldset = CustomFieldset::factory()->create();
        $category = Category::factory()->forAssets()->create([
            'name' => 'QA Communication',
            'fieldset_id' => $fieldset->id,
        ]);
        $networkSpeed = CustomField::factory()->create([
            'name' => 'QA Network Speed',
            'element' => 'listbox',
            'field_values' => "100 Mbps\n1 Gbps\n10 Gbps",
            'show_in_requestable_list' => true,
        ]);
        $portCount = CustomField::factory()->create([
            'name' => 'QA Port Count',
            'format' => 'NUMERIC',
            'show_in_requestable_list' => true,
        ]);
        $serialNumber = CustomField::factory()->create([
            'name' => 'QA Serial Number',
            'is_unique' => true,
            'show_in_requestable_list' => true,
        ]);
        $internalNote = CustomField::factory()->create([
            'name' => 'QA Internal Note',
            'show_in_requestable_list' => false,
        ]);
        $fieldset->fields()->attach([
            $networkSpeed->id => ['order' => 1, 'required' => false],
            $portCount->id => ['order' => 2, 'required' => false],
            $serialNumber->id => ['order' => 3, 'required' => false],
            $internalNote->id => ['order' => 4, 'required' => false],
        ]);
        $model = AssetModel::factory()->create([
            'name' => 'QA specification search model',
            'category_id' => $category->id,
        ]);
        $model->defaultValues()->attach($networkSpeed->id, ['default_value' => '1 Gbps']);
        $model->defaultValues()->attach($portCount->id, ['default_value' => '8']);
        Asset::factory()->create([
            'model_id' => $model->id,
            'status_id' => Statuslabel::factory()->readyToDeploy(),
            'requestable' => true,
        ]);

        $this->actingAs($requester)
            ->get(route('requestable-assets'))
            ->assertOk()
            ->assertSeeText('Technical specifications')
            ->assertSeeText('QA Network Speed')
            ->assertSeeText('1 Gbps')
            ->assertSeeText('QA Port Count')
            ->assertSee('data-field="specifications"', false)
            ->assertSee('name="search"', false)
            ->assertSee('Field: value', false)
            ->assertDontSeeText('QA Serial Number')
            ->assertDontSeeText('QA Internal Note');

        $this->actingAs($requester)
            ->get(route('requestable-assets', ['search' => 'QA Network Speed: 1 Gbps']))
            ->assertOk()
            ->assertSeeText('QA specification search model');
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
            ->get(route('requestable-assets', ['all' => 1]))
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
            ->get(route('requestable-assets', ['all' => 1]))
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
            ->get(route('requestable-assets', ['all' => 1]))
            ->assertOk()
            ->assertSeeText('Group-visible cross-company model');
    }
}

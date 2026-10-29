<?php

namespace Tests\Feature\Requests;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\CheckoutRequest;
use App\Models\CustomField;
use App\Models\CustomFieldset;
use App\Models\Project;
use Illuminate\Support\Facades\Notification;
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
            ->assertDontSeeText('Reusable Assets')
            ->assertDontSeeText('Reference Price')
            ->assertSeeText('Destination Site')
            ->assertSeeText('Add to Request')
            ->assertSeeText('Add Selected to Cart')
            ->assertSeeText('Request Cart')
            ->assertSee('Recently released feature')
            ->assertDontSee('Reuse Planning Totals')
            ->assertDontSee('model-request-cart-total-shortfall')
            ->assertDontSee('model-request-cart-total-savings')
            ->assertDontSee('model-request-cart-total-buy')
            ->assertDontSee('Reuse Planning Totals <span class="label label-info"', false)
            ->assertDontSee('model-request-cart-project">'.trans('general.project').' <span class="label label-info"', false)
            ->assertDontSee('model-request-cart-needed-by-date">Needed By <span class="label label-info"', false)
            ->assertSeeText('Submitted Requests')
            ->assertSee('model-booking-quantity-'.$requestableModel->id, false)
            ->assertSee('model-booking-discipline-'.$requestableModel->id, false)
            ->assertSee('model-booking-company-'.$requestableModel->id, false)
            ->assertSee('data-click-to-select="false"', false)
            ->assertDontSee('add-model-to-request-cart-modal', false)
            ->assertSeeText('Unavailable Model')
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

    public function test_request_catalogue_is_independent_of_source_inventory_company_under_fmcs(): void
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
            ->assertSeeText('Other-company-only reusable model');
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
    public function test_catalogue_includes_assigned_only_empty_and_obsolete_models_but_excludes_deleted_models(): void
    {
        $requester = User::factory()->requestAssetModels()->create();
        $assigned = AssetModel::factory()->create(['name' => 'Assigned-only model']);
        Asset::factory()->assignedToUser()->create(['model_id' => $assigned->id]);
        $empty = AssetModel::factory()->create(['name' => 'Empty inventory model']);
        $obsolete = AssetModel::factory()->create(['name' => 'Legacy model', 'obsolete' => true]);
        $deleted = AssetModel::factory()->create(['name' => 'Deleted model']);
        $deleted->delete();

        $this->actingAs($requester)->get(route('requestable-assets'))->assertOk()
            ->assertSeeText($assigned->name)->assertSeeText($empty->name)->assertSeeText($obsolete->name)
            ->assertSeeText('Obsolete')->assertDontSeeText($deleted->name);
    }

    public function test_catalogue_paginates_and_keeps_search_and_category_filters(): void
    {
        $requester = User::factory()->requestAssetModels()->create();
        $category = Category::factory()->forAssets()->create();
        AssetModel::factory()->count(27)->create(['category_id' => $category->id, 'name' => 'Demand model']);
        AssetModel::factory()->create(['name' => 'Unrelated model']);
        $query = ['search' => 'Demand model', 'category_id' => $category->id];
        $this->actingAs($requester)->get(route('requestable-assets', $query))->assertOk()
            ->assertViewHas('models', fn ($models) => $models->total() === 27 && $models->count() === 25)
            ->assertSee('search=Demand%20model', false)
            ->assertSee('data-pagination="false"', false)
            ->assertSee('data-search="true"', false)
            ->assertSee('data-server-search-url=', false)
            ->assertSee('data-toolbar="#requestableModelsToolbar"', false)
            ->assertDontSee('id="catalogue-search"', false)
            ->assertDontSeeText('Unrelated model');
        $this->get(route('requestable-assets', array_merge($query, ['page' => 2])))->assertOk()
            ->assertViewHas('models', fn ($models) => $models->count() === 2);
        $this->get(route('requestable-assets', ['search' => 'No such model']))->assertOk()
            ->assertSee('data-empty-message="No catalogue models match your search."', false);
    }

    public function test_requester_can_read_public_model_specifications_without_model_admin_permission(): void
    {
        $requester = User::factory()->requestAssetModels()->create();
        $fieldset = CustomFieldset::factory()->create();
        $visible = CustomField::factory()->create(['name' => 'Bus speed', 'show_in_requestable_list' => true, 'field_encrypted' => false]);
        $private = CustomField::factory()->create(['name' => 'Internal calibration secret', 'show_in_requestable_list' => false]);
        $encrypted = CustomField::factory()->create(['name' => 'Encrypted specification', 'show_in_requestable_list' => true, 'field_encrypted' => true]);
        foreach ([$visible, $private, $encrypted] as $order => $field) {
            $fieldset->fields()->attach($field, ['order' => $order + 1, 'required' => false]);
        }
        $model = AssetModel::factory()->create(['category_id' => Category::factory()->forAssets()->create(['fieldset_id' => $fieldset->id])->id]);
        $model->defaultValues()->attach($visible, ['default_value' => '5 Mbit/s']);
        $model->defaultValues()->attach($private, ['default_value' => 'Do not expose this value']);
        $model->defaultValues()->attach($encrypted, ['default_value' => 'Encrypted model value']);
        $this->actingAs($requester)->get(route('requestable-assets'))->assertOk()
            ->assertSeeText('View specifications')->assertSeeText('Bus speed')->assertSeeText('5 Mbit/s')
            ->assertDontSee('Internal calibration secret')->assertDontSee('Do not expose this value')
            ->assertDontSee('Encrypted specification')->assertDontSee('Encrypted model value');
    }

    public function test_zero_inventory_cart_preview_contains_demand_only_and_submission_keeps_estimates(): void
    {
        Notification::fake();
        $requester = User::factory()->requestAssetModels()->create();
        $company = Company::factory()->create();
        $discipline = Discipline::create(['name' => 'Demand intake', 'created_by' => $requester->id]);
        $project = Project::factory()->create();
        $model = AssetModel::factory()->create(['reference_price' => 1200]);
        $line = ['model_id' => $model->id, 'discipline_id' => $discipline->id, 'company_id' => $company->id, 'quantity' => 4];
        $this->actingAs($requester)->postJson(route('account.request-cart.items.add'), ['lines' => [$line]])->assertOk();
        $metadata = ['project_id' => $project->id, 'needed_by_date' => '2026-12-15'];
        $this->postJson(route('account.request-cart.preview'), $metadata)->assertOk()
            ->assertJsonPath('lines.0.quantity', 4)->assertJsonPath('totals.quantity', 4)
            ->assertJsonMissingPath('lines.0.reusable_quantity')->assertJsonMissingPath('lines.0.procurement_shortfall')
            ->assertJsonMissingPath('lines.0.estimated_savings')->assertJsonMissingPath('lines.0.amount_to_buy')
            ->assertJsonMissingPath('totals_formatted');
        $this->post(route('account.request-cart.submit'), $metadata)->assertRedirect();
        $request = CheckoutRequest::where('user_id', $requester->id)->where('requestable_id', $model->id)->firstOrFail();
        $this->assertSame(4, (int) $request->quantity);
        $this->assertSame($project->id, (int) $request->project_id);
        $this->assertSame($company->id, (int) $request->company_id);
        $this->assertSame($discipline->id, (int) $request->requested_discipline_id);
        $this->assertSame('2026-12-15', $request->needed_by_date->format('Y-m-d'));
        $this->assertSame(4, (int) $request->procurement_shortfall);
        $this->assertSame(0, $request->allocatedAssets()->count());
        $this->assertSame(0, Asset::where('model_id', $model->id)->count());
        $this->get(route('requests.index', ['request_id' => $request->id]))->assertOk()
            ->assertSee('data-field="procurement_shortfall"', false)->assertSee('sourcing estimates');
    }

}

<?php

namespace Tests\Feature\Requests;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\Discipline;
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
            ->assertSeeText('Industrial Ethernet Switch')
            ->assertSeeText('Communication')
            ->assertSeeText('Reusable Assets')
            ->assertSeeText('Add to Request')
            ->assertSeeText('Add Selected to Cart')
            ->assertSeeText('Request Cart')
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
}

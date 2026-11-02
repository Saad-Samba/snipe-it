<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Company;
use App\Models\Discipline;
use App\Models\Statuslabel;
use App\Models\User;
use Tests\TestCase;

class DisciplineValidationTest extends TestCase
{
    private function payload(): array
    {
        return [
            'asset_tag' => 'DISCIPLINE-TEST',
            'company_id' => Company::factory()->create()->id,
            'model_id' => AssetModel::factory()->create()->id,
            'status_id' => Statuslabel::factory()->readyToDeploy()->create()->id,
        ];
    }

    public function testUiRejectsMissingDisciplineBeforeCreatingAnyAssets(): void
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->from(route('hardware.create'))
            ->post(route('hardware.store'), $this->payload() + ['asset_tags' => [1 => 'DISC-A', 2 => 'DISC-B']])
            ->assertRedirect(route('hardware.create'))
            ->assertSessionHasErrors('discipline_id');

        $this->assertDatabaseCount('assets', 0);
    }

    public function testUiRejectsInvalidAndDeletedDiscipline(): void
    {
        $deleted = Discipline::create(['name' => 'Deleted']);
        $deleted->delete();
        $this->actingAs(User::factory()->superuser()->create());
        $payload = $this->payload() + ['asset_tags' => [1 => 'DISC-A']];
        foreach (['', 'bad', 999999, $deleted->id] as $id) {
            $this->post(route('hardware.store'), $payload + ['discipline_id' => $id])
                ->assertSessionHasErrors('discipline_id');
        }
        $this->assertDatabaseCount('assets', 0);
    }

    public function testUiStoresDisciplineOnEveryCreatedAsset(): void
    {
        $discipline = Discipline::create(['name' => 'Hardware']);
        $this->actingAs(User::factory()->superuser()->create())
            ->post(route('hardware.store'), $this->payload() + [
                'asset_tags' => [1 => 'DISC-A', 2 => 'DISC-B'],
                'discipline_id' => $discipline->id,
            ])->assertSessionHasNoErrors()->assertSessionHas('success-unescaped');

        foreach (['DISC-A', 'DISC-B'] as $tag) {
            $this->assertDatabaseHas('assets', ['asset_tag' => $tag, 'discipline_id' => $discipline->id]);
        }
    }

    public function testApiRejectsMissingEmptyInvalidAndDeletedDisciplines(): void
    {
        $deleted = Discipline::create(['name' => 'Deleted']);
        $deleted->delete();
        $payload = $this->payload();
        $this->actingAsForApi(User::factory()->superuser()->create());

        foreach ([[], ['discipline_id' => null], ['discipline_id' => ''], ['discipline_id' => 'bad'], ['discipline_id' => 999999], ['discipline_id' => $deleted->id]] as $invalid) {
            $this->postJson(route('api.assets.store'), $payload + $invalid)
                ->assertStatusMessageIs('error')
                ->assertJsonStructure(['messages' => ['discipline_id']]);
        }
        $this->assertDatabaseCount('assets', 0);
    }

    public function testApiCreatesWithDiscipline(): void
    {
        $discipline = Discipline::create(['name' => 'Hardware']);
        $this->actingAsForApi(User::factory()->superuser()->create())
            ->postJson(route('api.assets.store'), $this->payload() + ['discipline_id' => $discipline->id])
            ->assertStatusMessageIs('success');
        $this->assertDatabaseHas('assets', ['asset_tag' => 'DISCIPLINE-TEST', 'discipline_id' => $discipline->id]);
    }

    public function testLegacyBlankCanBeEditedByApiWithoutBackfill(): void
    {
        $asset = Asset::factory()->create(['discipline_id' => null]);
        $this->actingAsForApi(User::factory()->superuser()->create())
            ->patchJson(route('api.assets.update', $asset), ['name' => 'Edited legacy asset'])
            ->assertStatusMessageIs('success');
        $this->assertNull($asset->fresh()->discipline_id);
        $this->assertSame('Edited legacy asset', $asset->fresh()->name);
    }

    public function testLegacyBlankCanBeEditedByUiWithoutBackfill(): void
    {
        $asset = Asset::factory()->create(['discipline_id' => null]);
        $this->actingAs(User::factory()->superuser()->create())
            ->put(route('hardware.update', $asset), [
                'name' => 'Edited legacy asset',
                'model_id' => $asset->model_id,
                'status_id' => $asset->status_id,
                'asset_tags' => [1 => $asset->asset_tag],
                'discipline_id' => '',
            ])->assertSessionHasNoErrors();
        $this->assertNull($asset->fresh()->discipline_id);
        $this->assertSame('Edited legacy asset', $asset->fresh()->name);
    }

    public function testCreateAndCloneFormsRequireDisciplineWhileLegacyEditDoesNot(): void
    {
        $asset = Asset::factory()->create(['discipline_id' => null]);
        $this->actingAs(User::factory()->superuser()->create());
        foreach ([route('hardware.create'), route('clone/hardware', $asset)] as $url) {
            $html = $this->get($url)->assertOk()->assertSeeHtml('for="discipline_select"')->getContent();
            $this->assertMatchesRegularExpression('/id="discipline_select"[^>]*required aria-required="true"/', $html);
            $this->assertStringContainsString('<span class="sr-only">Required</span>', $html);
        }
        $html = $this->get(route('hardware.edit', $asset))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/id="discipline_select"[^>]*required/', $html);
    }

    public function testSharedDisciplineSelectorRemainsOptionalForOtherScreens(): void
    {
        $html = view('partials.forms.edit.discipline-select', [
            'fieldname' => 'discipline_id', 'translated_name' => 'Discipline',
            'errors' => new \Illuminate\Support\ViewErrorBag,
        ])->render();
        $this->assertDoesNotMatchRegularExpression('/id="discipline_select"[^>]*required/', $html);
    }
}

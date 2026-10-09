<?php

namespace Tests\Feature\Assets\Ui;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Company;
use App\Models\CustomField;
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

    public function test_asset_reads_technical_specifications_from_its_model(): void
    {
        $this->markIncompleteIfMySQL('Custom Fields tests do not work on MySQL');

        $field = CustomField::factory()->create(['name' => 'Nominal Voltage']);
        $model = AssetModel::factory()->hasMultipleCustomFields([$field])->create();
        $model->defaultValues()->attach($field->id, ['default_value' => '230 V']);

        $this->actingAs(User::factory()->superuser()->create())
            ->post(route('hardware.store'), [
                'company_id' => Company::factory()->create()->id,
                'model_id' => $model->id,
                'asset_tags' => [1 => 'MODEL-SPEC-001'],
                'status_id' => StatusLabel::factory()->create()->id,
                $field->db_column => '110 V', // Ignored: this belongs to the model.
            ])
            ->assertRedirect();

        $asset = Asset::where('asset_tag', 'MODEL-SPEC-001')->firstOrFail();
        $this->assertNull($asset->{$field->db_column});
        $this->assertSame('230 V', $asset->customFieldValue($field));

        $model->defaultValues()->updateExistingPivot($field->id, ['default_value' => '110 V']);
        $asset->unsetRelation('model');

        $this->assertSame('110 V', $asset->customFieldValue($field));
    }

    public function test_asset_creation_shows_model_custom_fields_as_read_only(): void
    {
        $this->markIncompleteIfMySQL('Custom Fields tests do not work on MySQL');

        $field = CustomField::factory()->create(['name' => 'Nominal Voltage']);
        $model = AssetModel::factory()->hasMultipleCustomFields([$field])->create();
        $model->defaultValues()->attach($field->id, ['default_value' => '230 V']);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('hardware.create', ['model_id' => $model->id]))
            ->assertOk()
            ->assertSeeText('230 V')
            ->assertSeeText('Set by the model.')
            ->assertDontSee('name="'.$field->db_column.'"', false);
    }

    public function test_blank_required_model_specification_does_not_block_asset_creation(): void
    {
        $this->markIncompleteIfMySQL('Custom Fields tests do not work on MySQL');

        $field = CustomField::factory()->create(['name' => 'Rated Power']);
        $model = AssetModel::factory()->hasMultipleCustomFields([$field])->create();
        $model->fieldset->fields()->updateExistingPivot($field->id, ['required' => true]);

        $this->actingAs(User::factory()->superuser()->create())
            ->post(route('hardware.store'), [
                'company_id' => Company::factory()->create()->id,
                'model_id' => $model->id,
                'asset_tags' => [1 => 'MODEL-SPEC-BLANK-001'],
                'status_id' => StatusLabel::factory()->create()->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('assets', [
            'asset_tag' => 'MODEL-SPEC-BLANK-001',
            $field->db_column => null,
        ]);
    }

    public function test_unique_custom_fields_remain_asset_owned(): void
    {
        $this->markIncompleteIfMySQL('Custom Fields tests do not work on MySQL');

        $field = CustomField::factory()->create([
            'name' => 'Factory Certificate',
            'is_unique' => true,
        ]);
        $model = AssetModel::factory()->hasMultipleCustomFields([$field])->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->post(route('hardware.store'), [
                'company_id' => Company::factory()->create()->id,
                'model_id' => $model->id,
                'asset_tags' => [1 => 'MODEL-SPEC-UNIQUE-001'],
                'status_id' => StatusLabel::factory()->create()->id,
                $field->db_column => 'FC-001',
            ])
            ->assertRedirect();

        $asset = Asset::where('asset_tag', 'MODEL-SPEC-UNIQUE-001')->firstOrFail();
        $this->assertSame('FC-001', $asset->customFieldValue($field));
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

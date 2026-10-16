<?php

namespace Tests\Feature\CustomFields\Ui;

use App\Models\CustomField;
use App\Models\CustomFieldset;
use App\Models\User;
use Tests\TestCase;

class CatalogueCustomFieldConfigurationTest extends TestCase
{
    public function test_unique_and_encrypted_options_cannot_be_configured(): void
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->post(route('fields.store'), [
                'name' => 'Restricted catalogue field',
                'element' => 'text',
                'field_encrypted' => 1,
                'is_unique' => 1,
            ])
            ->assertSessionHasErrors(['field_encrypted', 'is_unique']);

        $this->assertDatabaseMissing('custom_fields', [
            'name' => 'Restricted catalogue field',
        ]);
    }

    public function test_existing_unique_or_encrypted_fields_cannot_be_added_to_a_fieldset(): void
    {
        $field = CustomField::factory()->create(['is_unique' => true]);
        $fieldset = CustomFieldset::factory()->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->post(route('fieldsets.associate', $fieldset), ['field_id' => $field->id])
            ->assertSessionHasErrors('field_id');

        $this->assertDatabaseMissing('custom_field_custom_fieldsets', [
            'custom_field_id' => $field->id,
            'custom_fieldset_id' => $fieldset->id,
        ]);
    }
}

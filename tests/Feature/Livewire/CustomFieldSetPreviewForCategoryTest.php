<?php

namespace Tests\Feature\Livewire;

use App\Livewire\CustomFieldSetPreviewForCategory;
use App\Models\CustomField;
use App\Models\CustomFieldset;
use Livewire\Livewire;
use Tests\TestCase;

class CustomFieldSetPreviewForCategoryTest extends TestCase
{
    public function testItPreviewsTheSelectedFieldsetFieldsAndRequirements(): void
    {
        $fieldset = CustomFieldset::factory()->create(['name' => 'Industrial Network Equipment']);
        $requiredField = CustomField::factory()->create([
            'name' => 'Network Speed',
            'help_text' => 'Maximum supported network speed',
        ]);
        $optionalField = CustomField::factory()->create(['name' => 'Firmware Version']);

        $fieldset->fields()->attach($requiredField, ['required' => 1, 'order' => 1]);
        $fieldset->fields()->attach($optionalField, ['required' => 0, 'order' => 2]);

        Livewire::test(CustomFieldSetPreviewForCategory::class, ['fieldset_id' => $fieldset->id])
            ->assertSee('Industrial Network Equipment')
            ->assertSee('Network Speed')
            ->assertSee('Maximum supported network speed')
            ->assertSee('Required')
            ->assertSee('Firmware Version')
            ->assertSee('Optional')
            ->assertDontSeeHtml('name="default_values');
    }

    public function testItUpdatesThePreviewWhenTheFieldsetSelectionChanges(): void
    {
        $firstFieldset = CustomFieldset::factory()->create();
        $secondFieldset = CustomFieldset::factory()->create();
        $firstField = CustomField::factory()->create(['name' => 'First Preview Field']);
        $secondField = CustomField::factory()->create(['name' => 'Second Preview Field']);

        $firstFieldset->fields()->attach($firstField, ['required' => 0, 'order' => 1]);
        $secondFieldset->fields()->attach($secondField, ['required' => 0, 'order' => 1]);

        Livewire::test(CustomFieldSetPreviewForCategory::class, ['fieldset_id' => $firstFieldset->id])
            ->assertSee('First Preview Field')
            ->set('fieldset_id', $secondFieldset->id)
            ->assertDontSee('First Preview Field')
            ->assertSee('Second Preview Field')
            ->set('fieldset_id', null)
            ->assertDontSee('Second Preview Field');
    }
}

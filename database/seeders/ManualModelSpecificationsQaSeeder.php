<?php

namespace Database\Seeders;

use App\Models\AssetModel;
use App\Models\Category;
use App\Models\CustomField;
use App\Models\CustomFieldset;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ManualModelSpecificationsQaSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::withoutGlobalScopes()->firstOrNew(['username' => 'qa-model-specs']);
        $user->forceFill([
            'first_name' => 'QA',
            'last_name' => 'Model Specifications',
            'display_name' => 'QA Model Specifications',
            'email' => 'qa-model-specs@example.com',
            'activated' => 1,
            'locale' => 'en-US',
            'permissions' => json_encode(['superuser' => '1']),
            'password' => Hash::make('password'),
            'notes' => 'Local-only account for model technical-specification QA.',
        ]);
        $user->save();

        $fieldset = CustomFieldset::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'QA Power Supply Specifications'],
            ['notes' => 'Local-only fieldset for model technical-specification QA.']
        );

        $voltage = $this->upsertField('QA Rated Voltage', 'text');
        $connector = $this->upsertField('QA Connector Type', 'listbox', "IEC C13\r\nIEC C14\r\nUSB-C");
        $fieldset->fields()->syncWithoutDetaching([
            $voltage->id => ['required' => false, 'order' => 1],
            $connector->id => ['required' => false, 'order' => 2],
        ]);

        $category = Category::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'QA Bench Power Supplies', 'category_type' => 'asset'],
            [
                'fieldset_id' => $fieldset->id,
                'created_by' => $user->id,
                'notes' => 'Local-only category for model technical-specification QA.',
            ]
        );

        $model = AssetModel::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'QA Bench PSU 24V', 'category_id' => $category->id],
            [
                'created_by' => $user->id,
                'notes' => 'Local-only model for model technical-specification QA.',
                'require_serial' => false,
            ]
        );
        $model->defaultValues()->sync([
            $voltage->id => ['default_value' => '24 VDC'],
            $connector->id => ['default_value' => 'IEC C13'],
        ]);

        $this->command?->info('Model specifications QA data is ready.');
        $this->command?->line('User: qa-model-specs / password');
        $this->command?->line('Category: QA Bench Power Supplies');
        $this->command?->line('Model: QA Bench PSU 24V');
    }

    private function upsertField(string $name, string $element, ?string $values = null): CustomField
    {
        return CustomField::withoutGlobalScopes()->updateOrCreate(
            ['name' => $name],
            [
                'element' => $element,
                'format' => '',
                'field_values' => $values,
                'field_encrypted' => false,
                'help_text' => 'Local-only field for model technical-specification QA.',
            ]
        );
    }
}

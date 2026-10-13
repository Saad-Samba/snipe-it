<?php

namespace Database\Seeders;

use App\Models\AssetModel;
use App\Models\CustomField;
use App\Models\CustomFieldset;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Deterministic manual QA data for the category-first requestable catalogue.
 *
 * Run this seeder in a disposable environment only. It builds on the reuse
 * workflow demo so the demo requester already has requestable inventory.
 */
class RequestableModelSpecificationSearchSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ReuseWorkflowDemoSeeder::class);

        $admin = User::withoutGlobalScopes()->where('username', 'demo-GSA')->firstOrFail();
        $fieldset = CustomFieldset::withoutGlobalScopes()
            ->where('name', 'Industrial Network Equipment')
            ->firstOrFail();

        $fields = [
            'Network Speed' => [
                'element' => 'listbox',
                'format' => 'Network speed',
                'field_values' => "100 Mbps\n1 Gbps\n10 Gbps",
                'show_in_requestable_list' => true,
            ],
            'Port Count' => [
                'element' => 'text',
                'format' => 'numeric',
                'field_values' => null,
                'show_in_requestable_list' => true,
            ],
            'Media Type' => [
                'element' => 'listbox',
                'format' => 'Media type',
                'field_values' => "Copper\nFiber",
                'show_in_requestable_list' => true,
            ],
        ];

        $order = 1;
        foreach ($fields as $name => $attributes) {
            $field = CustomField::withoutGlobalScopes()->updateOrCreate(
                ['name' => $name],
                $attributes + [
                    'created_by' => $admin->id,
                    'help_text' => 'Visible technical filter for requestable catalogue QA.',
                    'field_encrypted' => false,
                    'is_unique' => false,
                ]
            );
            $fieldset->fields()->syncWithoutDetaching([
                $field->id => ['order' => $order++, 'required' => false],
            ]);
        }

        $automationFieldset = CustomFieldset::withoutGlobalScopes()
            ->where('name', 'Automation and Control Equipment')
            ->firstOrFail();
        $automationFields = [
            'Device Type' => [
                'element' => 'listbox',
                'format' => 'Device type',
                'field_values' => "Debug Probe\nPower Supply",
                'show_in_requestable_list' => true,
            ],
            'Communication Protocol' => [
                'element' => 'listbox',
                'format' => 'Communication protocol',
                'field_values' => "USB\nEthernet",
                'show_in_requestable_list' => true,
            ],
            'Digital I/O Count' => [
                'element' => 'text',
                'format' => 'numeric',
                'field_values' => null,
                'show_in_requestable_list' => true,
            ],
        ];
        $automationOrder = 1;
        foreach ($automationFields as $name => $attributes) {
            $field = CustomField::withoutGlobalScopes()->updateOrCreate(
                ['name' => $name],
                $attributes + [
                    'created_by' => $admin->id,
                    'help_text' => 'Visible technical filter for automation catalogue QA.',
                    'field_encrypted' => false,
                    'is_unique' => false,
                ]
            );
            $automationFieldset->fields()->syncWithoutDetaching([
                $field->id => ['order' => $automationOrder++, 'required' => false],
            ]);
        }

        // These are deliberately configured as visible in the administration
        // screen but must never appear in the requestable catalogue.
        foreach ([
            'QA Unique Serial Number' => ['is_unique' => true, 'field_encrypted' => false],
            'QA Encrypted Procurement Note' => ['is_unique' => false, 'field_encrypted' => true],
        ] as $name => $restrictions) {
            $field = CustomField::withoutGlobalScopes()->updateOrCreate(
                ['name' => $name],
                $restrictions + [
                    'element' => 'text',
                    'format' => '',
                    'show_in_requestable_list' => true,
                    'created_by' => $admin->id,
                    'help_text' => 'Negative QA control: must not be exposed in the catalogue.',
                ]
            );
            $fieldset->fields()->syncWithoutDetaching([
                $field->id => ['order' => $order++, 'required' => false],
            ]);
        }

        $values = [
            'Vector VN1630A CAN/LIN Interface' => [
                'Network Speed' => '1 Gbps',
                'Port Count' => '8',
                'Media Type' => 'Copper',
            ],
            'PEAK PCAN-USB FD Interface' => [
                'Network Speed' => '100 Mbps',
                'Port Count' => '2',
                'Media Type' => 'Copper',
            ],
            'SEGGER J-Link PRO Debug Probe' => [
                'Device Type' => 'Debug Probe',
                'Communication Protocol' => 'USB',
                'Digital I/O Count' => '0',
            ],
            'Lauterbach TRACE32 Debug Probe' => [
                'Device Type' => 'Debug Probe',
                'Communication Protocol' => 'Ethernet',
                'Digital I/O Count' => '16',
            ],
            'Keysight E36313A DC Power Supply' => [
                'Device Type' => 'Power Supply',
                'Communication Protocol' => 'USB',
                'Digital I/O Count' => '4',
            ],
        ];

        foreach ($values as $modelName => $specifications) {
            $model = AssetModel::withoutGlobalScopes()->where('name', $modelName)->firstOrFail();
            foreach ($specifications as $fieldName => $value) {
                $field = CustomField::withoutGlobalScopes()->where('name', $fieldName)->firstOrFail();
                $model->defaultValues()->syncWithoutDetaching([
                    $field->id => ['default_value' => $value],
                ]);
            }
        }

        $this->command?->info('Requestable catalogue QA data is ready. Log in as demo-EPL / password.');
    }
}

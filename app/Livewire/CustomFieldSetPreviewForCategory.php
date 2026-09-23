<?php

namespace App\Livewire;

use App\Models\CustomFieldset;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CustomFieldSetPreviewForCategory extends Component
{
    public $fieldset_id;

    public function mount($fieldset_id = null): void
    {
        $this->fieldset_id = $fieldset_id;
    }

    #[Computed]
    public function fieldset()
    {
        return CustomFieldset::with('fields')->find($this->fieldset_id);
    }

    public function render()
    {
        return view('livewire.custom-field-set-preview-for-category');
    }
}

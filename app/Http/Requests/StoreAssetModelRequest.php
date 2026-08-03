<?php

namespace App\Http\Requests;

use App\Models\AssetModel;
use App\Models\Category;
use Illuminate\Support\Facades\Gate;

class StoreAssetModelRequest extends ImageUploadRequest
{

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $model = $this->route('model');

        if ($model && ! $model instanceof AssetModel) {
            $model = AssetModel::find($model);
        }

        return $model
            ? Gate::allows('update', $model)
            : Gate::allows('create', AssetModel::class);
    }

    public function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if ($this->category_id) {
            if ($category = Category::find($this->category_id)) {
                $this->merge([
                    'category_type' => $category->category_type ?? null,
                ]);
            }
        }

    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = array_merge(
            ['category_type' => 'in:asset'],
            parent::rules(),
        );

        if (! config('leams.model_fieldset_overrides')) {
            $rules = array_merge($rules, [
                'fieldset_id' => 'prohibited',
                'custom_fieldset_id' => 'prohibited',
                'add_default_values' => 'prohibited',
                'default_values' => 'prohibited',
            ]);
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('category_id')) {
                return;
            }

            $user = $this->user();

            if (! $user || $user->isSuperUser() || $user->isAdmin()) {
                return;
            }

            $isManagedCategory = Category::whereKey($this->integer('category_id'))
                ->where('category_type', 'asset')
                ->where('manager_id', $user->id)
                ->exists();

            if (! $isManagedCategory) {
                $validator->errors()->add('category_id', 'Selected category is not managed by you.');
            }
        });
    }

    public function messages(): array
    {
        $messages = [
            'category_type.in' => trans('admin/models/message.invalid_category_type'),
            'fieldset_id.prohibited' => trans('admin/models/message.fieldset_override_disabled'),
            'custom_fieldset_id.prohibited' => trans('admin/models/message.fieldset_override_disabled'),
            'add_default_values.prohibited' => trans('admin/models/message.fieldset_override_disabled'),
            'default_values.prohibited' => trans('admin/models/message.fieldset_override_disabled'),
        ];
        return $messages;
    }

    public function response(array $errors)
    {
        return $this->redirector->back()->withInput()->withErrors($errors, $this->errorBag);
    }
}

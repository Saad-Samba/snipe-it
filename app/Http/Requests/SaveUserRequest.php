<?php

namespace App\Http\Requests;

use App\Models\RegionalAssetCoordinatorAssignment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use App\Rules\UserCannotSwitchCompaniesIfItemsAssigned;

class SaveUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    public function response(array $errors)
    {
        return $this->redirector->back()->withInput()->withErrors($errors, $this->errorBag);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'department_id' => 'nullable|integer|exists:departments,id',
            'manager_id' => 'nullable|exists:users,id',
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'rac_enabled' => ['nullable', 'boolean'],
            'rac_discipline_id' => ['nullable', 'integer', 'exists:disciplines,id,deleted_at,NULL'],
            'rac_discipline_ids' => ['nullable', 'array'],
            'rac_discipline_ids.*' => ['integer', 'distinct', 'exists:disciplines,id,deleted_at,NULL'],
        ];

        switch ($this->method()) {

            // Brand new user
            case 'POST':
                $rules['first_name'] = 'required|string|min:1';
                $rules['username'] = 'required_unless:ldap_import,1|string|min:1';
                if ($this->request->get('ldap_import') == false) {
                    $rules['password'] = Setting::passwordComplexityRulesSaving('store').'|confirmed';
                }
                break;

            // Save all fields
            case 'PUT':
                $rules['first_name'] = 'required|string|min:1';
                $rules['username'] = 'required_unless:ldap_import,1|string|min:1';
                $rules['password'] = Setting::passwordComplexityRulesSaving('update').'|confirmed';
                $rules['company_id'] = [new UserCannotSwitchCompaniesIfItemsAssigned()];
                break;

            // Save only what's passed
            case 'PATCH':
                $rules['password'] = Setting::passwordComplexityRulesSaving('update');
                $rules['company_id'] = [new UserCannotSwitchCompaniesIfItemsAssigned()];
                break;

            default:
                break;
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->shouldEnableRac()) {
                return;
            }

            $disciplineIds = $this->resolvedRacDisciplineIds();
            $companyId = $this->resolvedCompanyId();
            $disciplineErrorKey = $this->has('rac_discipline_ids')
                ? 'rac_discipline_ids'
                : 'rac_discipline_id';

            if (empty($disciplineIds)) {
                $validator->errors()->add($disciplineErrorKey, 'At least one discipline is required when the user acts as a RAC.');
            }

            if (! $companyId) {
                $validator->errors()->add('company_id', 'The company field is required when the user acts as a RAC.');
            }

            if (! empty($disciplineIds) && $companyId) {
                $conflictingAssignment = RegionalAssetCoordinatorAssignment::query()
                    ->with(['coordinator', 'company', 'discipline'])
                    ->where('company_id', $companyId)
                    ->whereIn('discipline_id', $disciplineIds)
                    ->when($this->route('user') instanceof User, function ($query) {
                        $query->where('user_id', '!=', $this->route('user')->id);
                    })
                    ->first();

                if ($conflictingAssignment) {
                    $assignee = $conflictingAssignment->coordinator?->display_name
                        ?: $conflictingAssignment->coordinator?->username
                        ?: 'another user';
                    $companyName = $conflictingAssignment->company?->name ?: 'this company';
                    $disciplineName = $conflictingAssignment->discipline?->name ?: 'this discipline';

                    $validator->errors()->add(
                        $disciplineErrorKey,
                        "A RAC is already assigned to {$companyName} / {$disciplineName}: {$assignee}."
                    );
                }
            }
        });
    }

    protected function shouldEnableRac(): bool
    {
        if ($this->has('rac_enabled')) {
            return (bool) $this->input('rac_enabled');
        }

        return $this->existingRacAssignments()->isNotEmpty();
    }

    protected function resolvedRacDisciplineIds(): array
    {
        if ($this->has('rac_discipline_ids')) {
            return collect($this->input('rac_discipline_ids', []))
                ->filter(fn ($disciplineId) => is_numeric($disciplineId) && (int) $disciplineId > 0)
                ->map(fn ($disciplineId) => (int) $disciplineId)
                ->unique()
                ->values()
                ->all();
        }

        if ($this->filled('rac_discipline_id')) {
            return [(int) $this->input('rac_discipline_id')];
        }

        return $this->existingRacAssignments()
            ->pluck('discipline_id')
            ->map(fn ($disciplineId) => (int) $disciplineId)
            ->all();
    }

    protected function resolvedCompanyId(): ?int
    {
        if ($this->filled('company_id')) {
            return (int) $this->input('company_id');
        }

        $routedUser = $this->route('user');

        if ($routedUser instanceof User) {
            return $routedUser->company_id;
        }

        return null;
    }

    protected function existingRacAssignments(): Collection
    {
        $routedUser = $this->route('user');

        if (! $routedUser instanceof User) {
            return collect();
        }

        return $routedUser->relationLoaded('racAssignments')
            ? $routedUser->racAssignments
            : $routedUser->racAssignments()->get();
    }
}

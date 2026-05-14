<?php

namespace App\Http\Requests;

use App\Models\RegionalAssetCoordinatorAssignment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
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

            $disciplineId = $this->resolvedRacDisciplineId();
            $companyId = $this->resolvedCompanyId();

            if (! $disciplineId) {
                $validator->errors()->add('rac_discipline_id', 'The discipline field is required when the user acts as a RAC.');
            }

            if (! $companyId) {
                $validator->errors()->add('company_id', 'The company field is required when the user acts as a RAC.');
            }

            if ($disciplineId && $companyId) {
                $conflictingAssignment = RegionalAssetCoordinatorAssignment::query()
                    ->with(['coordinator', 'company', 'discipline'])
                    ->where('company_id', $companyId)
                    ->where('discipline_id', $disciplineId)
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
                        'rac_discipline_id',
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

        return (bool) $this->existingRacAssignment();
    }

    protected function resolvedRacDisciplineId(): ?int
    {
        if ($this->filled('rac_discipline_id')) {
            return (int) $this->input('rac_discipline_id');
        }

        return $this->existingRacAssignment()?->discipline_id;
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

    protected function existingRacAssignment(): ?RegionalAssetCoordinatorAssignment
    {
        $routedUser = $this->route('user');

        if (! $routedUser instanceof User) {
            return null;
        }

        return $routedUser->relationLoaded('racAssignment')
            ? $routedUser->racAssignment
            : $routedUser->racAssignment()->first();
    }
}

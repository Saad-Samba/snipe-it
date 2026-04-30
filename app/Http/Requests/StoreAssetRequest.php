<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\MayContainCustomFields;
use App\Models\Asset;
use App\Models\Company;
use App\Models\Setting;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Facades\Gate;
use App\Rules\AssetCannotBeCheckedOutToNondeployableStatus;
use Illuminate\Validation\Validator;

class StoreAssetRequest extends ImageUploadRequest
{
    use MayContainCustomFields;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return Gate::allows('create', new Asset);
    }

    public function prepareForValidation(): void
    {
        parent::prepareForValidation(); // call ImageUploadRequest thing
        $this->parseLastAuditDate();
        $ownerId = $this->owner_id === '' ? null : $this->owner_id;

        $this->merge([
            'asset_tag' => $this->asset_tag ?? Asset::autoincrement_asset(),
            'company_id' => $this->resolveCompanyId($this->company_id),
            'owner_id' => $ownerId,
            'project_id' => $this->project_id === '' ? null : $this->project_id,
            'discipline_id' => $this->discipline_id === '' ? null : $this->discipline_id,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (Company::currentUserLacksCompanyAssignmentForFullMultipleCompanySupport()) {
                $validator->errors()->add('company_id', 'You cannot complete this action because your account is not assigned to a company.');

                return;
            }

            if (is_null($this->input('company_id'))) {
                $validator->errors()->add('company_id', 'The company field is required.');
            }
        });
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $modelRules = (new Asset)->getRules();

        if (Setting::getSettings()->digit_separator === '1.234,56' && is_string($this->input('purchase_cost'))) {
            // If purchase_cost was submitted as a string with a comma separator
            // then we need to ignore the normal numeric rules.
            // Since the original rules still live on the model they will be run
            // right before saving (and after purchase_cost has been
            // converted to a float via setPurchaseCostAttribute).
            $modelRules = $this->removeNumericRulesFromPurchaseCost($modelRules);
        }

        return array_merge(
            $modelRules,
            ['status_id' => [new AssetCannotBeCheckedOutToNondeployableStatus()]],
            parent::rules(),
        );
    }

    private function parseLastAuditDate(): void
    {
        if ($this->input('last_audit_date')) {
            try {
                $lastAuditDate = Carbon::parse($this->input('last_audit_date'));

                $this->merge([
                    'last_audit_date' => $lastAuditDate->startOfDay()->format('Y-m-d H:i:s'),
                ]);
            } catch (InvalidFormatException $e) {
                // we don't need to do anything here...
                // we'll keep the provided date in an
                // invalid format so validation picks it up later
            }
        }
    }

    private function removeNumericRulesFromPurchaseCost(array $rules): array
    {
        $purchaseCost = $rules['purchase_cost'];

        // If rule is in "|" format then turn it into an array
        if (is_string($purchaseCost)) {
            $purchaseCost = explode('|', $purchaseCost);
        }

        $rules['purchase_cost'] = array_filter($purchaseCost, function ($rule) {
            return $rule !== 'numeric' && $rule !== 'gte:0';
        });

        return $rules;
    }

    private function resolveCompanyId($companyId)
    {
        if (is_array($companyId)) {
            return $companyId;
        }

        if ((is_null($companyId) || ($companyId === '')) && auth()->user() && ! is_null(auth()->user()->company_id)) {
            return auth()->user()->company_id;
        }

        return Company::getIdForCurrentUser($companyId);
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferAssetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled in the controller.
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required_without:transfer_all', 'array'],
            'ids.*' => ['integer', 'exists:assets,id'],
            'transfer_all' => ['sometimes', 'boolean'],
            'transfer_target_user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}

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

    protected function getRedirectUrl(): string
    {
        $user = $this->route('user')
            ?? $this->route('userId')
            ?? $this->route('id');

        if (! $user && $this->route()) {
            $user = $this->route()->parameter('user')
                ?? $this->route()->parameter('userId')
                ?? $this->route()->parameter('id');
        }

        if (! $user) {
            $path = $this->path();
            if (preg_match('/^users\/(\d+)/', $path, $matches)) {
                $user = $matches[1];
            }
        }

        if ($user) {
            return route('users.show', $user);
        }

        return parent::getRedirectUrl();
    }
}

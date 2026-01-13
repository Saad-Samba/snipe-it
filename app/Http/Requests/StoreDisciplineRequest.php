<?php

namespace App\Http\Requests;

use App\Models\Discipline;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreDisciplineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', new Discipline) || Gate::allows('update', Discipline::class);
    }

    public function rules(): array
    {
        return (new Discipline)->getRules();
    }
}

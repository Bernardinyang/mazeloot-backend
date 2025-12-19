<?php

namespace App\Domains\Memora\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProofingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'maxRevisions' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'status' => ['sometimes', 'in:draft,active,completed'],
        ];
    }
}


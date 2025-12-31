<?php

namespace App\Domains\Memora\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class RecoverMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mediaIds' => ['required', 'array'],
            'mediaIds.*' => ['required', 'uuid'],
        ];
    }
}

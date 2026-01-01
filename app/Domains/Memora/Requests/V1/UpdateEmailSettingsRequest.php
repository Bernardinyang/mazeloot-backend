<?php

namespace App\Domains\Memora\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fromName' => ['nullable', 'string', 'max:255'],
            'fromAddress' => ['nullable', 'email', 'max:255'],
            'replyTo' => ['nullable', 'email', 'max:255'],
        ];
    }
}

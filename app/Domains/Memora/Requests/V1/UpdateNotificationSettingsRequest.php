<?php

namespace App\Domains\Memora\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notifications' => ['required', 'array'],
            'notifications.*' => ['boolean'],
        ];
    }
}

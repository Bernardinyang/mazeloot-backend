<?php

namespace App\Domains\Memora\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationChannelPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notify_email' => ['sometimes', 'boolean'],
            'notify_in_app' => ['sometimes', 'boolean'],
            'notify_whatsapp' => ['sometimes', 'boolean'],
            'whatsapp_number' => [
                'nullable',
                'required_if:notify_whatsapp,true',
                'string',
                'max:24',
                'regex:/^\+?[0-9\s\-\(\)]{10,24}$/',
            ],
        ];
    }
}

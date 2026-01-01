<?php

namespace App\Domains\Memora\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferenceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filenameDisplay' => ['nullable', 'in:show,hide'],
            'searchEngineVisibility' => ['nullable', 'in:homepage-only,all,none'],
            'sharpeningLevel' => ['nullable', 'in:optimal,low,medium,high'],
            'rawPhotoSupport' => ['nullable', 'boolean'],
            'termsOfService' => ['nullable', 'string'],
            'privacyPolicy' => ['nullable', 'string'],
            'enableCookieBanner' => ['nullable', 'boolean'],
            'language' => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'string', 'max:50'],
        ];
    }
}

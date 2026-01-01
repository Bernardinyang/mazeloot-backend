<?php

namespace App\Domains\Memora\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customDomain' => ['nullable', 'string', 'max:255'],
            'showMazelootBranding' => ['nullable', 'boolean'],
            'logoUuid' => ['nullable', 'uuid', 'exists:user_files,uuid'],
            'faviconUuid' => ['nullable', 'uuid', 'exists:user_files,uuid'],
        ];
    }
}

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
            'name' => ['nullable', 'string', 'max:255'],
            'supportEmail' => ['nullable', 'email', 'max:255'],
            'supportPhone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'addressStreet' => ['nullable', 'string', 'max:255'],
            'addressCity' => ['nullable', 'string', 'max:255'],
            'addressState' => ['nullable', 'string', 'max:255'],
            'addressZip' => ['nullable', 'string', 'max:50'],
            'addressCountry' => ['nullable', 'string', 'max:255'],
            'businessHours' => ['nullable', 'string'],
            'contactName' => ['nullable', 'string', 'max:255'],
            'taxVatId' => ['nullable', 'string', 'max:100'],
            'foundedYear' => ['nullable', 'integer', 'min:1800', 'max:' . date('Y')],
            'industry' => ['nullable', 'string', 'max:255'],
        ];
    }
}

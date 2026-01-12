<?php

namespace App\Domains\Memora\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSocialMediaPlatformRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $platformId = $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('memora_social_media_platforms', 'name')->ignore($platformId, 'uuid')],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('memora_social_media_platforms', 'slug')->ignore($platformId, 'uuid')],
            'icon' => ['nullable', 'string', 'max:255'],
            'baseUrl' => ['nullable', 'string', 'url', 'max:500'],
            'isActive' => ['nullable', 'boolean'],
            'order' => ['nullable', 'integer'],
        ];
    }
}

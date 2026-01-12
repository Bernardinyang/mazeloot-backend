<?php

namespace App\Domains\Memora\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreSocialMediaPlatformRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:memora_social_media_platforms,name'],
            'slug' => ['required', 'string', 'max:255', 'unique:memora_social_media_platforms,slug'],
            'icon' => ['nullable', 'string', 'max:255'],
            'baseUrl' => ['nullable', 'string', 'url', 'max:500'],
            'isActive' => ['nullable', 'boolean'],
            'order' => ['nullable', 'integer'],
        ];
    }
}

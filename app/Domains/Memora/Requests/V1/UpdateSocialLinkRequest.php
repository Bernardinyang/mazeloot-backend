<?php

namespace App\Domains\Memora\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSocialLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platformUuid' => ['sometimes', 'uuid', 'exists:social_media_platforms,uuid'],
            'url' => ['sometimes', 'string', 'url', 'max:500'],
            'isActive' => ['nullable', 'boolean'],
            'order' => ['nullable', 'integer'],
        ];
    }
}

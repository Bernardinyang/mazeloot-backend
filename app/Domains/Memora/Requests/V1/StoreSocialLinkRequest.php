<?php

namespace App\Domains\Memora\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreSocialLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platformUuid' => ['required', 'uuid', 'exists:social_media_platforms,uuid'],
            'url' => ['required', 'string', 'url', 'max:500'],
            'isActive' => ['nullable', 'boolean'],
            'order' => ['nullable', 'integer'],
        ];
    }
}

<?php

namespace App\Domains\Memora\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,active,archived'],
            'hasSelections' => ['nullable', 'boolean'],
            'hasProofing' => ['nullable', 'boolean'],
            'hasCollections' => ['nullable', 'boolean'],
            'parentId' => ['nullable', 'uuid'],
            'presetId' => ['nullable', 'uuid'],
            'watermarkId' => ['nullable', 'uuid'],
            'settings' => ['nullable', 'array'],
            'mediaSets' => ['nullable', 'array'],
            'mediaSets.*.name' => ['required_with:mediaSets', 'string', 'max:255'],
            'mediaSets.*.description' => ['nullable', 'string'],
            'mediaSets.*.order' => ['nullable', 'integer'],
        ];
    }
}


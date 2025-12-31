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
            'presetId' => ['nullable', 'uuid'],
            'watermarkId' => ['nullable', 'uuid'],
            'settings' => ['nullable', 'array'],
            'mediaSets' => ['nullable', 'array'],
            'mediaSets.*.name' => ['required_with:mediaSets', 'string', 'max:255'],
            'mediaSets.*.description' => ['nullable', 'string'],
            'mediaSets.*.order' => ['nullable', 'integer'],
            'color' => ['nullable', 'string', 'max:7'],
            // Phase settings
            'selectionSettings' => ['nullable', 'array'],
            'selectionSettings.name' => ['nullable', 'string', 'max:255'],
            'selectionSettings.description' => ['nullable', 'string'],
            'selectionSettings.selectionLimit' => ['nullable', 'integer', 'min:0'],
            'proofingSettings' => ['nullable', 'array'],
            'proofingSettings.name' => ['nullable', 'string', 'max:255'],
            'proofingSettings.maxRevisions' => ['nullable', 'integer', 'min:1', 'max:20'],
            'collectionSettings' => ['nullable', 'array'],
            'collectionSettings.name' => ['nullable', 'string', 'max:255'],
            'collectionSettings.description' => ['nullable', 'string'],
        ];
    }
}

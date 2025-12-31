<?php

namespace App\Domains\Memora\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:draft,active,archived'],
            'settings' => ['nullable', 'array'],
            'mediaSets' => ['nullable', 'array'],
            'color' => ['sometimes', 'string', 'max:7'],
            'presetId' => ['nullable', 'uuid'],
            'watermarkId' => ['nullable', 'uuid'],
            'eventDate' => ['nullable', 'date'],
            'hasSelections' => ['nullable', 'boolean'],
            'hasProofing' => ['nullable', 'boolean'],
            'hasCollections' => ['nullable', 'boolean'],
            // Phase settings
            'selectionSettings' => ['nullable', 'array'],
            'selectionSettings.name' => ['nullable', 'string', 'max:255'],
            'selectionSettings.description' => ['nullable', 'string'],
            'selectionSettings.selectionLimit' => ['nullable', 'integer', 'min:0'],
            'proofingSettings' => ['nullable', 'array'],
            'proofingSettings.name' => ['nullable', 'string', 'max:255'],
            'proofingSettings.description' => ['nullable', 'string', 'max:1000'],
            'proofingSettings.maxRevisions' => ['nullable', 'integer', 'min:1', 'max:20'],
            'collectionSettings' => ['nullable', 'array'],
            'collectionSettings.name' => ['nullable', 'string', 'max:255'],
            'collectionSettings.description' => ['nullable', 'string'],
        ];
    }
}

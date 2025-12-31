<?php

namespace App\Domains\Memora\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class MoveCopyMediaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled in the controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'media_uuids' => ['required', 'array', 'min:1'],
            'media_uuids.*' => ['required', 'string', 'uuid'],
            'target_set_uuid' => ['required', 'string', 'uuid'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'media_uuids.required' => 'At least one media item must be specified.',
            'media_uuids.array' => 'Media UUIDs must be an array.',
            'media_uuids.min' => 'At least one media item must be specified.',
            'media_uuids.*.required' => 'Each media UUID is required.',
            'media_uuids.*.uuid' => 'Each media UUID must be a valid UUID.',
            'target_set_uuid.required' => 'Target set UUID is required.',
            'target_set_uuid.uuid' => 'Target set UUID must be a valid UUID.',
        ];
    }
}

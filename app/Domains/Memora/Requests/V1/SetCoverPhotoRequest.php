<?php

namespace App\Domains\Memora\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class SetCoverPhotoRequest extends FormRequest
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
            'media_uuid' => ['required', 'string', 'uuid'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'media_uuid.required' => 'Media UUID is required.',
            'media_uuid.uuid' => 'Media UUID must be a valid UUID.',
        ];
    }
}


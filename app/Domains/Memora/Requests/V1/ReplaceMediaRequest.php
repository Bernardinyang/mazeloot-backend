<?php

namespace App\Domains\Memora\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceMediaRequest extends FormRequest
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
            'user_file_uuid' => [
                'required',
                'uuid',
                'exists:user_files,uuid',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'user_file_uuid.required' => 'User file UUID is required.',
            'user_file_uuid.uuid' => 'User file UUID must be a valid UUID.',
            'user_file_uuid.exists' => 'The specified user file does not exist.',
        ];
    }
}

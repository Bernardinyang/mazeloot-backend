<?php

namespace App\Domains\Memora\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class ApplyWatermarkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $userId = auth()->id();

        return [
            'watermarkUuid' => [
                'required',
                'string',
                'uuid',
                'exists:memora_watermarks,uuid,user_uuid,'.$userId,
            ],
            'previewStyle' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'watermarkUuid.required' => 'Watermark UUID is required',
            'watermarkUuid.uuid' => 'Watermark UUID must be a valid UUID',
            'watermarkUuid.exists' => 'The selected watermark does not exist',
        ];
    }
}

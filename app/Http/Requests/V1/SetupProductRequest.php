<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class SetupProductRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'domain' => [
                'nullable',
                'string',
                'min:3',
                'max:63',
                'regex:/^[a-z0-9-]+$/',
                'not_in:admin,api,www,mail,ftp,localhost,test,staging,dev,app',
            ],
            // Add other product-specific fields as needed
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'domain.regex' => 'Domain can only contain lowercase letters, numbers, and hyphens.',
            'domain.not_in' => 'This domain is reserved and cannot be used.',
        ];
    }
}

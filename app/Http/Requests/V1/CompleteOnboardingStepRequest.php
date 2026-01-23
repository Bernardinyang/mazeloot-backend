<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class CompleteOnboardingStepRequest extends FormRequest
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
        $rules = [
            'product_uuid' => ['required', 'uuid', 'exists:products,uuid'],
            'step' => ['required', 'string'],
            'token' => ['required', 'string'],
            'data' => ['required', 'array'],
        ];

        // For Memora domain step, validate domain format
        if ($this->input('step') === 'domain' && $this->input('product_uuid')) {
            $product = \App\Models\Product::where('uuid', $this->input('product_uuid'))->first();
            if ($product && $product->slug === 'memora') {
                $rules['data.domain'] = ['required', 'string', 'regex:/^[a-z0-9_-]{3,50}$/i'];
            }
        }

        return $rules;
    }
}

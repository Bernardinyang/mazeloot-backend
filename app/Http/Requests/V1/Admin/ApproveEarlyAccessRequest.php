<?php

namespace App\Http\Requests\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ApproveEarlyAccessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'discount_percentage' => 'sometimes|integer|min:0|max:100',
            'discount_rules' => 'sometimes|array',
            'feature_flags' => ['sometimes', 'array', function ($attribute, $value, $fail) {
                if (!is_array($value)) {
                    return;
                }
                $allowedFlags = config('early_access.allowed_features', []);
                $invalidFlags = array_diff($value, $allowedFlags);
                if (!empty($invalidFlags)) {
                    $fail("The following feature flags are not allowed: " . implode(', ', $invalidFlags));
                }
            }],
            'feature_flags.*' => 'string|max:255',
            'storage_multiplier' => 'sometimes|numeric|min:1.0',
            'priority_support' => 'sometimes|boolean',
            'exclusive_badge' => 'sometimes|boolean',
            'trial_extension_days' => 'sometimes|integer|min:0',
            'custom_branding_enabled' => 'sometimes|boolean',
            'release_version' => 'sometimes|string|max:255',
            'expires_at' => 'sometimes|nullable|date',
            'notes' => 'sometimes|string|max:1000',
        ];
    }
}

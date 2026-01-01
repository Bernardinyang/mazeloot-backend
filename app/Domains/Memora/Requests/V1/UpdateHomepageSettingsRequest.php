<?php

namespace App\Domains\Memora\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHomepageSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'max:255'],
            'biography' => ['nullable', 'string', 'max:200'],
            'info' => ['nullable', 'array'],
            'info.*' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('info') && !is_array($this->info)) {
            $this->merge([
                'info' => [],
            ]);
        }
    }
}

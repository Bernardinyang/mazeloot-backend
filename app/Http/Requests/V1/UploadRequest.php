<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxSize = config('upload.max_size', 10485760); // 10MB default
        $allowedTypes = config('upload.allowed_types', []);

        $fileRule = File::default()->max($maxSize / 1024); // Convert to KB

        // Add MIME type validation if configured
        if (!empty($allowedTypes)) {
            $fileRule->types($allowedTypes);
        }

        return [
            'file' => ['required_without:files', $fileRule],
            'files' => ['required_without:file', 'array'],
            'files.*' => ['required', $fileRule],
            'purpose' => ['nullable', 'string', 'max:255'],
            'path' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get validated data with additional options
     */
    public function getUploadOptions(): array
    {
        $options = [];

        if ($this->has('purpose')) {
            $options['purpose'] = $this->input('purpose');
        }

        if ($this->has('path')) {
            $options['path'] = $this->input('path');
        }

        // Add user and domain context if available
        if ($this->user()) {
            $options['userId'] = $this->user()->id;
        }

        // Extract domain from purpose or route
        if ($this->has('purpose')) {
            $options['domain'] = $this->input('purpose');
        }

        return $options;
    }
}


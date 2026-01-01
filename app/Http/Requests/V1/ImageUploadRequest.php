<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class ImageUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = config('upload.max_size', 10485760); // 10MB default
        $fileRule = [
            'required',
            'mimes:jpeg,jpg,png,webp,svg',
            'max:'.($maxSize / 1024), // Convert to KB
        ];

        return [
            // Support both 'file' (single) and 'files' (array) for compatibility
            'file' => ['required_without:files', ...$fileRule],
            'files' => ['required_without:file', 'array', 'min:1'],
            'files.*' => $fileRule,
            'context' => ['sometimes', 'string', 'max:255'],
            'visibility' => ['sometimes', 'string', 'in:public,private'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required_without' => 'An image file is required.',
            'files.required_without' => 'At least one image file is required.',
            'files.array' => 'Files must be provided as an array.',
            'file.mimes' => 'Images must be in JPEG, PNG, WebP, or SVG format.',
            'files.*.mimes' => 'Images must be in JPEG, PNG, WebP, or SVG format.',
            'file.max' => 'The image must not exceed the maximum file size.',
            'files.*.max' => 'Each image must not exceed the maximum file size.',
        ];
    }
}

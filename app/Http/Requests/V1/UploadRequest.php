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
        $maxSize = config('upload.max_size', 262144000); // 250MB default
        $purpose = $this->input('purpose');
        $isRawFile = $purpose && (str_contains(strtolower($purpose), 'raw') || str_contains(strtolower($purpose), 'rawfile'));

        // Build File rule with size constraint
        $fileRule = File::default()->max($maxSize / 1024); // Convert to KB

        // Use media/camera file types for raw files, standard types for others
        if ($isRawFile) {
            $rawFileTypes = config('raw_file_media_types', []);
            if (! empty($rawFileTypes)) {
                $fileRule = $fileRule->types($rawFileTypes);
            }
        } else {
            $allowedTypes = config('upload.allowed_types', []);
            if (! empty($allowedTypes)) {
                $fileRule = $fileRule->types($allowedTypes);
            }
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
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxSizeMB = number_format((config('upload.max_size', 262144000) / 1024 / 1024), 0);
        $purpose = $this->input('purpose');
        $isRawFile = $purpose && (str_contains(strtolower($purpose), 'raw') || str_contains(strtolower($purpose), 'rawfile'));
        $allowedTypes = $isRawFile ? config('raw_file_media_types', []) : config('upload.allowed_types', []);
        $allowedTypesStr = ! empty($allowedTypes) ? implode(', ', $allowedTypes) : ($isRawFile ? 'media or camera recorder file' : 'valid file');

        return [
            'file.required_without' => 'Please select a file to upload.',
            'file.max' => "The file must not exceed {$maxSizeMB}MB.",
            'file.types' => $isRawFile
                ? 'The file must be a valid media or camera recorder file type. Your file type is not allowed.'
                : "The file must be a valid {$allowedTypesStr} file. Your file type is not allowed.",
            'files.required_without' => 'Please select at least one file to upload.',
            'files.array' => 'Files must be provided as an array.',
            'files.*.required' => 'Each file is required.',
            'files.*.max' => "Each file must not exceed {$maxSizeMB}MB.",
            'files.*.types' => $isRawFile
                ? 'Each file must be a valid media or camera recorder file type.'
                : "Each file must be a valid {$allowedTypesStr} file.",
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

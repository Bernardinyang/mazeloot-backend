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
        
        // Get phase from request (can be in 'phase' field or extracted from 'purpose')
        $phase = $this->input('phase') ?? $this->extractPhaseFromPurpose($this->input('purpose'));
        
        // Get allowed types based on phase
        $allowedTypes = $this->getAllowedTypesForPhase($phase);

        // Build File rule with size and type constraints
        $fileRule = File::default()->max($maxSize / 1024); // Convert to KB

        // Add MIME type validation using File::types() method
        if (! empty($allowedTypes)) {
            $fileRule = $fileRule->types($allowedTypes);
        }

        return [
            'file' => ['required_without:files', $fileRule],
            'files' => ['required_without:file', 'array'],
            'files.*' => ['required', $fileRule],
            'purpose' => ['nullable', 'string', 'max:255'],
            'phase' => ['nullable', 'string', 'in:selection,proofing,collection,raw_files'],
            'path' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_\-\.\/]+$/'],
        ];
    }

    /**
     * Extract phase from purpose string
     * Purpose format examples: "memora:raw_files", "raw_files", "memora:selection"
     */
    protected function extractPhaseFromPurpose(?string $purpose): ?string
    {
        if (! $purpose) {
            return null;
        }

        // Check if purpose contains phase (e.g., "memora:raw_files" or just "raw_files")
        $phases = ['selection', 'proofing', 'collection', 'raw_files'];
        foreach ($phases as $phase) {
            if (str_contains($purpose, $phase)) {
                return $phase;
            }
        }

        return null;
    }

    /**
     * Get allowed file types for a specific phase
     */
    protected function getAllowedTypesForPhase(?string $phase): array
    {
        if ($phase) {
            $phaseTypes = config("upload.allowed_types_by_phase.{$phase}", null);
            if ($phaseTypes !== null) {
                return $phaseTypes;
            }
        }

        // Fallback to default allowed types
        return config('upload.allowed_types', []);
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxSizeMB = number_format((config('upload.max_size', 262144000) / 1024 / 1024), 0);
        
        // Get phase and allowed types for error message
        $phase = $this->input('phase') ?? $this->extractPhaseFromPurpose($this->input('purpose'));
        $allowedTypes = $this->getAllowedTypesForPhase($phase);
        $allowedTypesStr = ! empty($allowedTypes) 
            ? (count($allowedTypes) > 10 
                ? 'supported file type for this phase' 
                : implode(', ', array_slice($allowedTypes, 0, 10)).(count($allowedTypes) > 10 ? ' and more' : ''))
            : 'image or video files';

        return [
            'file.required_without' => 'Please select a file to upload.',
            'file.max' => "The file must not exceed {$maxSizeMB}MB.",
            'file.types' => "The file must be a valid {$allowedTypesStr}. Your file type is not allowed.",
            'files.required_without' => 'Please select at least one file to upload.',
            'files.array' => 'Files must be provided as an array.',
            'files.*.required' => 'Each file is required.',
            'files.*.max' => "Each file must not exceed {$maxSizeMB}MB.",
            'files.*.types' => "Each file must be a valid {$allowedTypesStr}.",
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

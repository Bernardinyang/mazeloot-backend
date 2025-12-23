<?php

namespace App\Domains\Memora\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RenameMediaRequest extends FormRequest
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
            'filename' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $mediaId = $this->route('mediaId');
            $filename = $this->input('filename');

            if (!$mediaId || !$filename) {
                return;
            }

            // Get the media and its file to check the original extension
            $media = \App\Domains\Memora\Models\MemoraMedia::where('uuid', $mediaId)
                ->with('file')
                ->first();

            if (!$media || !$media->file) {
                return;
            }

            $originalFilename = $media->file->filename;
            $originalExtension = pathinfo($originalFilename, PATHINFO_EXTENSION);
            $newExtension = pathinfo($filename, PATHINFO_EXTENSION);

            // If original file has an extension, the new filename must have the same extension
            if ($originalExtension && $newExtension !== $originalExtension) {
                $validator->errors()->add(
                    'filename',
                    "The filename extension must remain as '{$originalExtension}'. Please keep the original file extension."
                );
            }

            // If original file has no extension but new one does, that's also not allowed
            if (!$originalExtension && $newExtension) {
                $validator->errors()->add(
                    'filename',
                    'The filename cannot have an extension if the original file does not have one.'
                );
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'filename.required' => 'Filename is required.',
            'filename.string' => 'Filename must be a string.',
            'filename.max' => 'Filename must not exceed 255 characters.',
        ];
    }
}


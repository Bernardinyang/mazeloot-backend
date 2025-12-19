<?php

namespace App\Domains\Memora\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UploadMediaToSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'media' => ['required', 'array', 'min:1'],
            'media.*' => ['required', 'url', 'exists:user_files,url'],
        ];
    }

    public function messages(): array
    {
        $maxSizeMB = round(config('upload.max_size', 10485760) / (1024 * 1024), 2);

        return [

        ];
    }
}


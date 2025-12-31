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
            'user_file_uuid' => [
                'required',
                'uuid',
                'exists:user_files,uuid',
            ],
        ];
    }
}

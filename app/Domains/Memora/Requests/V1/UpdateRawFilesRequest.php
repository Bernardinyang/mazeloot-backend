<?php

namespace App\Domains\Memora\Requests\V1;

use App\Domains\Memora\Enums\SelectionStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRawFilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'status' => ['sometimes', Rule::enum(SelectionStatusEnum::class)],
            'color' => ['sometimes', 'string', 'max:7'],
            'cover_photo_url' => ['nullable', 'string', 'url'],
            'cover_focal_point' => ['sometimes', 'nullable', 'array'],
            'coverFocalPoint' => ['sometimes', 'nullable', 'array'],
            'password' => ['nullable', 'string', 'min:4', 'max:255'],
            'allowed_emails' => ['nullable', 'array'],
            'allowed_emails.*' => ['email', 'max:255'],
            'allowedEmails' => ['nullable', 'array'],
            'allowedEmails.*' => ['email', 'max:255'],
            'settings' => ['nullable', 'array'],
            'typographyDesign' => ['nullable', 'array'],
            'typographyDesign.fontFamily' => ['nullable', 'string', 'max:100'],
            'typographyDesign.fontStyle' => ['nullable', 'string', 'max:50'],
            'downloadPin' => ['nullable', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'downloadPinEnabled' => ['nullable', 'boolean'],
            'limitDownloads' => ['nullable', 'boolean'],
            'downloadLimit' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

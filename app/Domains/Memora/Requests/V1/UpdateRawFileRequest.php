<?php

namespace App\Domains\Memora\Requests\V1;

use App\Domains\Memora\Enums\RawFileStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRawFileRequest extends FormRequest
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
            'status' => ['sometimes', Rule::enum(RawFileStatusEnum::class)],
            'color' => ['sometimes', 'string', 'max:7'],
            'cover_photo_url' => ['nullable', 'string', 'url'],
            'cover_focal_point' => ['sometimes', 'nullable', 'array'],
            'coverFocalPoint' => ['sometimes', 'nullable', 'array'], // Frontend alias
            'password' => ['nullable', 'string', 'min:4', 'max:255'],
            'allowed_emails' => ['nullable', 'array'],
            'allowed_emails.*' => ['email', 'max:255'],
            'allowedEmails' => ['nullable', 'array'],
            'allowedEmails.*' => ['email', 'max:255'],
            'raw_file_limit' => ['nullable', 'integer', 'min:1'],
            'rawFileLimit' => ['nullable', 'integer', 'min:1'], // Frontend alias
            'auto_delete_date' => ['nullable', 'date'],
            'display_settings' => ['sometimes', 'array'],
            'display_settings.view_mode' => ['sometimes', 'string', 'in:grid,list'],
            'display_settings.grid_size' => ['sometimes', 'string', 'in:small,medium,large'],
            'display_settings.show_filename' => ['sometimes', 'boolean'],
            'display_settings.sort_order' => ['sometimes', 'string', 'in:uploaded-new-old,uploaded-old-new,name-a-z,name-z-a,date-taken-new-old,date-taken-old-new,random'],
            'typographyDesign' => ['nullable', 'array'],
            'typographyDesign.fontFamily' => ['nullable', 'string', 'max:100'],
            'typographyDesign.fontStyle' => ['nullable', 'string', 'max:50'],
            'galleryAssist' => ['sometimes', 'nullable', 'boolean'],
            'download' => ['sometimes', 'nullable', 'array'],
            'download.downloadEnabled' => ['sometimes', 'nullable', 'boolean'],
            'download.downloadPinEnabled' => ['sometimes', 'nullable', 'boolean'],
            'download.downloadPin' => ['nullable', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'downloadEnabled' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}

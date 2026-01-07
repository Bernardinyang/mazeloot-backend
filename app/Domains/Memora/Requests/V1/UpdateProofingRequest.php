<?php

namespace App\Domains\Memora\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProofingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'maxRevisions' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'status' => ['sometimes', 'in:draft,active,completed'],
            'color' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'allowedEmails' => ['sometimes', 'nullable', 'array'],
            'allowedEmails.*' => ['email', 'max:255'],
            'allowed_emails' => ['sometimes', 'nullable', 'array'],
            'allowed_emails.*' => ['email', 'max:255'],
            'primaryEmail' => ['sometimes', 'nullable', 'email', 'max:255'],
            'primary_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cover_photo_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'cover_focal_point' => ['sometimes', 'nullable', 'array'],
            'coverFocalPoint' => ['sometimes', 'nullable', 'array'], // Frontend alias
        ];
    }
}

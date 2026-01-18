<?php

namespace App\Domains\Memora\Requests\V1;

use App\Domains\Memora\Enums\RawFileStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRawFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'project_uuid' => ['nullable', 'uuid', 'exists:memora_projects,uuid'],
            'status' => ['nullable', Rule::enum(RawFileStatusEnum::class)],
            'color' => ['nullable', 'string', 'max:7'],
            'cover_photo_url' => ['nullable', 'string', 'url'],
            'password' => ['nullable', 'string', 'min:4', 'max:255'],
        ];
    }
}

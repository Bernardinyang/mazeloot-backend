<?php

namespace App\Domains\Memora\Requests\V1;

use App\Domains\Memora\Enums\SelectionStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(SelectionStatusEnum::class)],
            'color' => ['sometimes', 'string', 'max:7'],
            'cover_photo_url' => ['nullable', 'string', 'url'],
        ];
    }
}


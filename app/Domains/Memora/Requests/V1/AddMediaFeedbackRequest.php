<?php

namespace App\Domains\Memora\Requests\V1;

use App\Domains\Memora\Enums\MediaFeedbackTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddMediaFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(MediaFeedbackTypeEnum::class)],
            'content' => ['required', 'string'],
            'createdBy' => ['nullable', 'string', 'max:255'],
            'parentId' => ['nullable', 'uuid', 'exists:memora_media_feedback,uuid'],
            'timestamp' => ['nullable', 'numeric', 'min:0'],
            'mentions' => ['nullable', 'array'],
            'mentions.*' => ['email', 'max:255'], // Each mention must be a valid email
        ];
    }
}

<?php

namespace App\Domains\Memora\Requests\V1;

use App\Domains\Memora\Enums\BorderStyleEnum;
use App\Domains\Memora\Enums\FontStyleEnum;
use App\Domains\Memora\Enums\TextTransformEnum;
use App\Domains\Memora\Enums\WatermarkPositionEnum;
use App\Domains\Memora\Enums\WatermarkTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWatermarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(WatermarkTypeEnum::class)],

            'imageFileUuid' => [
                'required_if:type,image',
                'nullable',
                'uuid',
                'exists:user_files,uuid',
            ],

            'text' => [
                'required_if:type,text',
                'nullable',
                'string',
                'max:500',
            ],
            'fontFamily' => ['nullable', 'string', 'max:100'],
            'fontStyle' => ['nullable', Rule::enum(FontStyleEnum::class)],
            'fontColor' => ['nullable', 'string', 'max:50'],
            'backgroundColor' => ['nullable', 'string', 'max:50'],
            'lineHeight' => ['nullable', 'numeric', 'min:0.5', 'max:3'],
            'letterSpacing' => ['nullable', 'numeric', 'min:-5', 'max:10'],
            'padding' => ['nullable', 'integer', 'min:0', 'max:100'],
            'textTransform' => ['nullable', Rule::enum(TextTransformEnum::class)],
            'borderRadius' => ['nullable', 'integer', 'min:0', 'max:100'],
            'borderWidth' => ['nullable', 'integer', 'min:0', 'max:20'],
            'borderColor' => ['nullable', 'string', 'max:50'],
            'borderStyle' => ['nullable', Rule::enum(BorderStyleEnum::class)],

            'scale' => [
                'nullable',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) {
                    $type = $this->input('type');
                    $maxScale = 100; // Both image and text watermarks now use 1-100 scale
                    if ($value !== null && $value > $maxScale) {
                        $fail("The scale must not be greater than {$maxScale} for {$type} watermarks.");
                    }
                },
            ],
            'opacity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'position' => ['nullable', Rule::enum(WatermarkPositionEnum::class)],
        ];
    }
}


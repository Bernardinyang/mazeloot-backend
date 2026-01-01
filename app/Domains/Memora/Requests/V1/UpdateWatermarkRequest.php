<?php

namespace App\Domains\Memora\Requests\V1;

use App\Domains\Memora\Enums\BorderStyleEnum;
use App\Domains\Memora\Enums\FontStyleEnum;
use App\Domains\Memora\Enums\TextTransformEnum;
use App\Domains\Memora\Enums\WatermarkPositionEnum;
use App\Domains\Memora\Enums\WatermarkTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWatermarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::enum(WatermarkTypeEnum::class)],

            'imageFileUuid' => [
                'sometimes',
                'required_if:type,image',
                'nullable',
                'uuid',
                'exists:user_files,uuid',
            ],

            'text' => [
                'sometimes',
                'required_if:type,text',
                'nullable',
                'string',
                'max:500',
            ],
            'fontFamily' => ['sometimes', 'nullable', 'string', 'max:100'],
            'fontStyle' => ['sometimes', 'nullable', Rule::enum(FontStyleEnum::class)],
            'fontColor' => ['sometimes', 'nullable', 'string', 'max:50'],
            'backgroundColor' => ['sometimes', 'nullable', 'string', 'max:50'],
            'lineHeight' => ['sometimes', 'nullable', 'numeric', 'min:0.5', 'max:3'],
            'letterSpacing' => ['sometimes', 'nullable', 'numeric', 'min:-5', 'max:10'],
            'padding' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'textTransform' => ['sometimes', 'nullable', Rule::enum(TextTransformEnum::class)],
            'borderRadius' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'borderWidth' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:20'],
            'borderColor' => ['sometimes', 'nullable', 'string', 'max:50'],
            'borderStyle' => ['sometimes', 'nullable', Rule::enum(BorderStyleEnum::class)],

            // Common fields
            'scale' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) {
                    $type = $this->input('type');
                    if (! $type) {
                        $watermarkId = $this->route('id');
                        if ($watermarkId) {
                            $watermark = \App\Domains\Memora\Models\MemoraWatermark::find($watermarkId);
                            $type = $watermark?->type;
                        }
                    }
                    $maxScale = 100; // Both image and text watermarks now use 1-100 scale
                    if ($value !== null && $value > $maxScale) {
                        $typeLabel = $type ?? 'text';
                        $fail("The scale must not be greater than {$maxScale} for {$typeLabel} watermarks.");
                    }
                },
            ],
            'opacity' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'position' => ['sometimes', 'nullable', Rule::enum(WatermarkPositionEnum::class)],
        ];
    }
}

<?php

namespace App\Domains\Memora\Requests\V1;

use App\Domains\Memora\Enums\PhaseTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveMediaBetweenPhasesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mediaIds' => ['required', 'array'],
            'mediaIds.*' => ['uuid'],
            'fromPhase' => ['required', Rule::enum(PhaseTypeEnum::class)],
            'fromPhaseId' => ['required', 'uuid'],
            'toPhase' => ['required', Rule::enum(PhaseTypeEnum::class)],
            'toPhaseId' => ['required', 'uuid'],
        ];
    }
}


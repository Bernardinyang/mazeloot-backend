<?php

namespace App\Domains\Memora\Requests\V1;

use App\Domains\Memora\Models\MemoraMediaSet;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $selectionId = $this->route('selectionId');
        $setId = $this->route('id');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($selectionId, $setId) {
                    if (!$selectionId || !$value) {
                        return;
                    }

                    $trimmedName = trim($value);
                    $exists = MemoraMediaSet::where('selection_uuid', $selectionId)
                        ->where('uuid', '!=', $setId)
                        ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($trimmedName)])
                        ->exists();

                    if ($exists) {
                        $fail('A photo set with this name already exists. Please choose a different name.');
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'selection_limit' => ['nullable', 'integer', 'min:1', 'sometimes'],
            'selectionLimit' => ['nullable', 'integer', 'min:1', 'sometimes'], // Frontend alias
        ];
    }
}


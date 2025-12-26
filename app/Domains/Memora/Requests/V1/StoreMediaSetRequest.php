<?php

namespace App\Domains\Memora\Requests\V1;

use App\Domains\Memora\Models\MemoraMediaSet;
use Illuminate\Foundation\Http\FormRequest;

class StoreMediaSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $selectionId = $this->route('selectionId');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($selectionId) {
                    if (!$selectionId) {
                        return;
                    }

                    $trimmedName = trim($value);
                    $exists = MemoraMediaSet::where('selection_uuid', $selectionId)
                        ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($trimmedName)])
                        ->exists();

                    if ($exists) {
                        $fail('A photo set with this name already exists. Please choose a different name.');
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'selection_limit' => ['nullable', 'integer', 'min:1'],
            'selectionLimit' => ['nullable', 'integer', 'min:1'], // Frontend alias
        ];
    }
}


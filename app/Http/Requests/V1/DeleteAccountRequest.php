<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DeleteAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');
        if (is_string($code)) {
            $this->merge(['code' => trim($code)]);
        }
    }

    public function rules(): array
    {
        $user = $this->user();
        $hasPassword = $user && $user->password;

        return [
            'password' => [$hasPassword ? 'required' : 'nullable', 'string'],
            'code' => [$hasPassword ? 'nullable' : 'required', 'string', 'size:6'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            if (! $user) {
                return;
            }

            if ($user->password) {
                $password = $this->input('password');
                if ($password === null || $password === '' || ! Hash::check($password, $user->password)) {
                    throw ValidationException::withMessages([
                        'password' => ['The password is incorrect.'],
                    ]);
                }
            } else {
                $code = $this->input('code');
                $code = $code !== null ? trim((string) $code) : '';
                $key = 'account_deletion_code:'.$user->uuid;
                $stored = Cache::get($key);
                if ($code === '' || $stored === null || ! hash_equals($stored, $code)) {
                    throw ValidationException::withMessages([
                        'code' => ['The code is invalid or has expired. Request a new code.'],
                    ]);
                }
                Cache::forget($key);
            }
        });
    }
}

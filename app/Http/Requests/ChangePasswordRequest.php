<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PasswordValidation;
use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    use PasswordValidation;
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'new_password'     => $this->passwordRules(),
        ];
    }

    public function messages(): array
    {
        return array_merge(
            [
                'current_password.required' => 'رمز عبور فعلی الزامی است.',
                'current_password.string'   => 'رمز عبور فعلی باید به صورت متن باشد.',
            ],
            $this->passwordValidationMessages('new_password', 'رمز عبور جدید')
        );
    }
}

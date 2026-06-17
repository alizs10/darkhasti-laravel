<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PasswordValidation;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    use PasswordValidation;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
        ];
    }

    public function messages(): array
    {
        return array_merge(
            [
                'username.required' => 'نام کاربری الزامی است.',
                'username.string' => 'نام کاربری باید به صورت متن باشد.',
                'username.max' => 'نام کاربری نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',
                'username.unique' => 'این نام کاربری قبلاً ثبت شده است.',
            ],
            $this->passwordValidationMessages('password', 'رمز عبور')
        );
    }
}

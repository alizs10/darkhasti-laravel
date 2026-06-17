<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rules\Password;

trait PasswordValidation
{
    protected function passwordRules(): array
    {
        return [
            'required',
            'string',
            'confirmed',
            'min:8',
            'max:64',
            Password::min(8)->mixedCase()->numbers()->symbols(),
        ];
    }

    protected function passwordValidationMessages(string $field, string $label = 'رمز عبور'): array
    {
        return [
            "{$field}.required"  => "{$label} الزامی است.",
            "{$field}.string"    => "{$label} باید به صورت متن باشد.",
            "{$field}.confirmed" => "تأیید {$label} مطابقت ندارد.",
            "{$field}.min"       => "{$label} باید حداقل ۸ کاراکتر باشد.",
            "{$field}.max"       => "{$label} نمی‌تواند بیشتر از ۶۴ کاراکتر باشد.",
            "{$field}.mixed"     => "{$label} باید حداقل یک حرف بزرگ و یک حرف کوچک داشته باشد.",
            "{$field}.numbers"   => "{$label} باید حداقل یک عدد داشته باشد.",
            "{$field}.symbols"   => "{$label} باید حداقل یک کاراکتر ویژه داشته باشد.",
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'        => 'required|string|max:255',
            'description'  => 'required|string|max:10000',
            'temp_files'   => 'array|max:10',
            'temp_files.*' => 'integer|exists:temp_files,id',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'       => 'عنوان درخواست الزامی است.',
            'title.max'            => 'عنوان نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',
            'description.required' => 'توضیحات درخواست الزامی است.',
            'description.max'      => 'توضیحات نمی‌تواند بیشتر از ۱۰۰۰۰ کاراکتر باشد.',
            'temp_files.max'       => 'حداکثر ۱۰ فایل می‌توانید آپلود کنید.',
            'temp_files.*.exists'  => 'فایل موقت انتخاب شده معتبر نیست.',
        ];
    }
}
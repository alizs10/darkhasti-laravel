<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_id'   => 'required|exists:requests,id',
            'body'         => 'required|string|max:5000',
            'parent_id'    => 'nullable|exists:comments,id',
            'temp_files'   => 'array|max:10',
            'temp_files.*' => 'integer|exists:temp_files,id',
        ];
    }

    public function messages(): array
    {
        return [
            'body.required'        => 'متن کامنت الزامی است.',
            'body.max'             => 'متن کامنت نمی‌تواند بیشتر از ۵۰۰۰ کاراکتر باشد.',
            'request_id.exists'    => 'درخواست مورد نظر یافت نشد.',
            'parent_id.exists'     => 'کامنت والد معتبر نیست.',
            'temp_files.max'       => 'حداکثر ۱۰ فایل مجاز است.',
        ];
    }
}
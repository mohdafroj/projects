<?php

namespace App\Modules\Capture\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_hi' => ['required', 'string', 'max:255'],
            'role_title' => ['nullable', 'string', 'max:255'],
            'state_jur' => ['nullable', 'string', 'max:255'],
        ];
    }
}

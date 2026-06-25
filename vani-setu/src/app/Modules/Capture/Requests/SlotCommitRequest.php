<?php

namespace App\Modules\Capture\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SlotCommitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lang_role' => ['required', 'string', Rule::in(['en', 'hi', 'ta', 'ur', 'bn', 'mr'])],
        ];
    }
}

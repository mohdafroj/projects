<?php

namespace App\Modules\Chief\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChiefCommitRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'lang_side' => ['required', Rule::in(['en', 'hi'])],
        ];
    }
}

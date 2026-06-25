<?php

namespace App\Modules\Chief\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChiefReturnRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'to_stage' => ['required', Rule::in(['supervisor', 'reporter'])],
        ];
    }
}

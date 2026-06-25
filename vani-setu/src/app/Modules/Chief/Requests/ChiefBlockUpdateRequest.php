<?php

namespace App\Modules\Chief\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChiefBlockUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:20000'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}

<?php

namespace App\Modules\Capture\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BlockUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}

<?php

namespace App\Modules\Capture\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkflowForwardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string'],
        ];
    }
}

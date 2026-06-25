<?php

namespace App\Modules\Capture\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkflowReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10'],
        ];
    }
}

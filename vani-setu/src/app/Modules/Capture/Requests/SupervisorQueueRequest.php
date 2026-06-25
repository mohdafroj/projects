<?php

namespace App\Modules\Capture\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupervisorQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSupervisor() ?? false;
    }

    public function rules(): array
    {
        return [
            'lang' => ['nullable', 'string', Rule::in(['en', 'hi', 'ta', 'ur', 'bn', 'mr'])],
            'section' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

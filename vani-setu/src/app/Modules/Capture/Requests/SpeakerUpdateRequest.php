<?php

namespace App\Modules\Capture\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SpeakerUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required_without:custom_member_id', 'prohibits:custom_member_id', 'integer', 'exists:members,id'],
            'custom_member_id' => ['required_without:member_id', 'prohibits:member_id', 'integer', 'exists:member_customs,id'],
        ];
    }
}

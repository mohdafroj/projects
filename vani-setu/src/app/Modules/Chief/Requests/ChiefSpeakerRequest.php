<?php

namespace App\Modules\Chief\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChiefSpeakerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'member_id' => ['nullable', 'integer', 'exists:members,id', 'required_without:custom_member_id'],
            'custom_member_id' => ['nullable', 'integer', 'exists:member_customs,id', 'required_without:member_id'],
        ];
    }
}

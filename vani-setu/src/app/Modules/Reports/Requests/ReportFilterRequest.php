<?php

namespace App\Modules\Reports\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'workflow_stage' => ['nullable'],
            'workflow_stage.*' => ['string', Rule::in(['reporter', 'returned', 'supervisor', 'chief', 'js', 'sg', 'director', 'published'])],
            'user_id' => ['nullable'],
            'user_id.*' => ['integer', 'exists:users,id'],
            'section' => ['nullable'],
            'section.*' => ['string', 'max:128'],
            'content_type' => ['nullable', Rule::in(['all', 'original', 'ai', 'translated'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'format' => ['nullable', Rule::in(['csv', 'pdf'])],
            'name' => ['nullable', 'string', 'max:160'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'workflow_stage' => $this->listFilter($validated['workflow_stage'] ?? null),
            'user_id' => array_map('intval', $this->listFilter($validated['user_id'] ?? null)),
            'section' => $this->listFilter($validated['section'] ?? null),
            'content_type' => $validated['content_type'] ?? 'all',
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
        ];
    }

    /**
     * @return list<string>
     */
    private function listFilter(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_filter($value, fn ($item) => $item !== null && $item !== ''));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    }
}

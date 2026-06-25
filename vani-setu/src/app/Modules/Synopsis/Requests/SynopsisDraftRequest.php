<?php

namespace App\Modules\Synopsis\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SynopsisDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:50000'],
            'title' => ['nullable', 'string', 'max:255'],
            'version' => ['required', 'integer', 'min:1'],
            'attributions' => ['nullable', 'array', 'max:100'],
            'attributions.*.speaker_name' => ['required_with:attributions', 'string', 'max:255'],
            'attributions.*.constituency' => ['nullable', 'string', 'max:255'],
            'attributions.*.summary_text' => ['required_with:attributions', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('title')) {
            $title = trim((string) $this->input('title'));
            $payload['title'] = $title !== '' ? $title : null;
        }

        if ($this->has('body')) {
            $payload['body'] = trim((string) $this->input('body'));
        }

        if (is_array($this->input('attributions'))) {
            $payload['attributions'] = $this->normalisedAttributions($this->input('attributions'));
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $attributions = $this->input('attributions');

            if (is_array($attributions) && ! array_is_list($attributions)) {
                $validator->errors()->add('attributions', 'The attributions field must be a list of rows.');
            }
        });
    }

    private function normalisedAttributions(array $attributions): array
    {
        $normalised = [];

        foreach ($attributions as $key => $item) {
            if (! is_array($item)) {
                $normalised[$key] = $item;

                continue;
            }

            $constituency = trim((string) ($item['constituency'] ?? ''));
            $normalised[$key] = [
                ...$item,
                'speaker_name' => trim((string) ($item['speaker_name'] ?? '')),
                'constituency' => $constituency !== '' ? $constituency : null,
                'summary_text' => trim((string) ($item['summary_text'] ?? '')),
            ];
        }

        return $normalised;
    }
}

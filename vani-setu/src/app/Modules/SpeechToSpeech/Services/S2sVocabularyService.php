<?php

namespace App\Modules\SpeechToSpeech\Services;

use App\Modules\SpeechToSpeech\Models\S2sVocabularyRule;
use Illuminate\Support\Str;

class S2sVocabularyService
{
    /**
     * @return array{clean_text:string, matches:array<int, array<string, mixed>>}
     */
    public function apply(string $text, ?string $languageCode = null): array
    {
        $clean = $text;
        $matches = [];

        $rules = S2sVocabularyRule::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('language_code')->orWhere('language_code', $languageCode))
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            $pattern = $this->phrasePattern((string) $rule->source_phrase);
            if (! preg_match_all($pattern, $clean, $matchedPhrases)) {
                continue;
            }

            $replacement = match ($rule->rule_type) {
                'blocked', 'bad_word', 'shadow_word' => '[redacted]',
                'filler' => '',
                'phonetic', 'correction', 'replacement' => $rule->replacement_text ?: $rule->source_phrase,
                default => $rule->replacement_text ?: $rule->source_phrase,
            };

            $clean = preg_replace($pattern, $replacement, $clean) ?? $clean;
            if ($rule->rule_type === 'filler') {
                $clean = $this->normalizeFillerRemoval($clean);
            }

            $matches[] = [
                'rule_id' => $rule->id,
                'rule_type' => $rule->rule_type,
                'source_phrase' => $rule->source_phrase,
                'replacement_text' => $replacement,
                'phonetic_hint' => $rule->phonetic_hint,
                'matched_text' => $matchedPhrases[0][0] ?? $rule->source_phrase,
                'matched_texts' => array_values(array_unique($matchedPhrases[0] ?? [])),
                'match_count' => count($matchedPhrases[0] ?? []),
            ];
        }

        return [
            'clean_text' => Str::squish($clean),
            'matches' => $matches,
        ];
    }

    private function normalizeFillerRemoval(string $text): string
    {
        $text = preg_replace('/\s+([,.;:!?])/', '$1', $text) ?? $text;
        $text = preg_replace('/([,.;:!?]){2,}/', '$1', $text) ?? $text;
        $text = preg_replace('/(^|\s)[,.;:!?](?=\s|$)/', '$1', $text) ?? $text;

        return Str::squish($text);
    }

    private function phrasePattern(string $phrase): string
    {
        return '/(?<![\p{L}\p{N}_])'.preg_quote($phrase, '/').'(?![\p{L}\p{N}_])/ui';
    }
}

<?php

namespace App\Modules\Translator\Services;

use App\Modules\Translator\Models\TranslatorGlossary;

class TerminologyEnforcer
{
    public function enforce(string $text, string $languagePair): string
    {
        $terms = TranslatorGlossary::query()
            ->where('language_pair', $languagePair)
            ->whereNotNull('approved_at')
            ->orderByRaw('length(term_source) desc')
            ->get(['term_source', 'term_target']);

        foreach ($terms as $term) {
            $text = str_replace($term->term_source, $term->term_target, $text);
        }

        return $text;
    }
}

<?php

namespace App\Modules\Regional\Services;

class RegionalLanguageDetector
{
    /**
     * @return array{language:string, confidence:float, detector:string}
     */
    public function detect(string $text): array
    {
        $scripts = [
            'ta' => '/\p{Tamil}/u',
            'te' => '/\p{Telugu}/u',
            'kn' => '/\p{Kannada}/u',
            'ml' => '/\p{Malayalam}/u',
            'bn' => '/\p{Bengali}/u',
            'gu' => '/\p{Gujarati}/u',
            'pa' => '/\p{Gurmukhi}/u',
            'or' => '/\p{Oriya}/u',
        ];

        $length = max(1, mb_strlen(preg_replace('/\s+/u', '', $text) ?: $text));
        $best = ['language' => 'und', 'confidence' => 0.0, 'detector' => 'unicode-script'];

        foreach ($scripts as $language => $pattern) {
            preg_match_all($pattern, $text, $matches);
            $score = count($matches[0]) / $length;
            if ($score > $best['confidence']) {
                $best = ['language' => $language, 'confidence' => round(min(0.99, $score), 2), 'detector' => 'unicode-script'];
            }
        }

        if ($best['language'] === 'und' || $best['confidence'] < 0.2) {
            abort(422, 'Unable to detect a supported non-EN/HI Indian language.');
        }

        return $best;
    }
}

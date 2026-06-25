<?php

namespace App\Modules\SpeechToSpeech\Services\Recheck;

class TranscriptDriftAnalyzer
{
    public function __construct(
        private readonly float $driftThreshold = 0.15,
        private readonly float $correctionConfidence = 0.85,
    ) {}

    /**
     * Compare the live-pass transcript ($source) against the second-pass
     * transcript ($candidate) and decide whether the segment passes QA,
     * shows drift, or warrants automatic correction.
     *
     * Returns an array shaped like:
     *   [
     *     'state'      => 'passed'|'drift'|'corrected',
     *     'score'      => float in [0,1] — 1 == identical, 0 == fully divergent,
     *     'wer'        => float,
     *     'matched_tokens'   => int,
     *     'source_tokens'    => int,
     *     'candidate_tokens' => int,
     *     'corrected_text'   => ?string,  // present iff state === 'corrected'
     *   ]
     */
    public function compare(string $source, string $candidate, float $candidateConfidence = 1.0): array
    {
        $sourceTokens = $this->tokenize($source);
        $candidateTokens = $this->tokenize($candidate);

        if ($sourceTokens === [] && $candidateTokens === []) {
            return $this->result('passed', 1.0, 0.0, 0, 0, 0, null);
        }

        $denominator = max(count($sourceTokens), 1);
        $distance = $this->tokenLevenshtein($sourceTokens, $candidateTokens);
        $wer = $distance / $denominator;
        $score = max(0.0, 1.0 - $wer);

        $state = match (true) {
            $wer <= $this->driftThreshold => 'passed',
            $wer > $this->driftThreshold && $candidateConfidence >= $this->correctionConfidence => 'corrected',
            default => 'drift',
        };

        $corrected = $state === 'corrected' ? $this->normaliseSpacing($candidate) : null;
        $matched = max(0, count($sourceTokens) - $distance);

        return $this->result($state, $score, $wer, $matched, count($sourceTokens), count($candidateTokens), $corrected);
    }

    private function result(string $state, float $score, float $wer, int $matched, int $srcN, int $candN, ?string $corrected): array
    {
        return [
            'state' => $state,
            'score' => round($score, 4),
            'wer' => round($wer, 4),
            'matched_tokens' => $matched,
            'source_tokens' => $srcN,
            'candidate_tokens' => $candN,
            'corrected_text' => $corrected,
        ];
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $normalised = mb_strtolower($text, 'UTF-8');
        $stripped = preg_replace('/[\p{P}\p{S}]+/u', ' ', $normalised) ?? $normalised;
        $tokens = preg_split('/\s+/u', trim($stripped));
        if ($tokens === false) {
            return [];
        }
        return array_values(array_filter($tokens, fn ($t) => $t !== ''));
    }

    private function normaliseSpacing(string $text): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($text));
        return $collapsed ?? $text;
    }

    /**
     * Token-level Levenshtein distance (substitutions + insertions + deletions).
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private function tokenLevenshtein(array $a, array $b): int
    {
        $n = count($a);
        $m = count($b);
        if ($n === 0) {
            return $m;
        }
        if ($m === 0) {
            return $n;
        }

        $prev = range(0, $m);
        for ($i = 1; $i <= $n; $i++) {
            $curr = [$i];
            for ($j = 1; $j <= $m; $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $curr[$j] = min(
                    $prev[$j] + 1,
                    $curr[$j - 1] + 1,
                    $prev[$j - 1] + $cost,
                );
            }
            $prev = $curr;
        }
        return $prev[$m];
    }
}

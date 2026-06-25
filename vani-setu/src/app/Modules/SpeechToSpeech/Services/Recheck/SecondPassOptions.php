<?php

namespace App\Modules\SpeechToSpeech\Services\Recheck;

final class SecondPassOptions
{
    public function __construct(
        public readonly string $language = 'auto',
        public readonly string $glossaryPrompt = '',
        public readonly bool $includeNeighbours = false,
        public readonly float $temperature = 0.0,
    ) {}
}

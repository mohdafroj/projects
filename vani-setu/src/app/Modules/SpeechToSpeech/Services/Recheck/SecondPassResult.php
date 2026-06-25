<?php

namespace App\Modules\SpeechToSpeech\Services\Recheck;

final class SecondPassResult
{
    public function __construct(
        public readonly string $text,
        public readonly float $confidence,
        public readonly string $provider,
        public readonly string $model,
        public readonly array $rawMeta = [],
    ) {}

    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'confidence' => $this->confidence,
            'provider' => $this->provider,
            'model' => $this->model,
            'raw_meta' => $this->rawMeta,
        ];
    }
}

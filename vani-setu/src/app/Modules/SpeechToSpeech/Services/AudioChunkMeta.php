<?php

namespace App\Modules\SpeechToSpeech\Services;

final class AudioChunkMeta
{
    public function __construct(
        public readonly string $mime,
        public readonly int $bytes,
        public readonly string $sha256,
        public readonly ?int $durationMs,
        public readonly ?int $sampleRate,
        public readonly ?int $channels,
        public readonly ?int $bitsPerSample,
        public readonly string $codec,
    ) {}

    public function toArray(): array
    {
        return [
            'mime' => $this->mime,
            'bytes' => $this->bytes,
            'sha256' => $this->sha256,
            'duration_ms' => $this->durationMs,
            'sample_rate' => $this->sampleRate,
            'channels' => $this->channels,
            'bits_per_sample' => $this->bitsPerSample,
            'codec' => $this->codec,
        ];
    }
}

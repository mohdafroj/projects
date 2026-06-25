<?php

namespace App\Modules\SpeechToSpeech\Services\Recheck;

use App\Modules\SpeechToSpeech\Models\S2sSegment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Second-pass transcriber that delegates to the ml-gateway /v1/asr
 * endpoint. The segment's source audio is exposed over a short-lived
 * HMAC-signed internal URL (see InternalAudioUrlSigner) so the
 * gateway can fetch the bytes itself — keeping the ASR contract
 * (audio_url + language) intact without round-tripping audio through
 * PHP.
 *
 * Confidence is derived from the per-segment confidences returned by
 * ml-gateway (mean if multiple, fallback to a fixed default when the
 * upstream omits the field).
 */
class MlGatewayAsrTranscriber implements SecondPassTranscriber
{
    public function __construct(
        private readonly InternalAudioUrlSigner $signer,
        private readonly string $gatewayBaseUrl,
        private readonly string $serviceToken = '',
        private readonly float $timeoutSeconds = 25.0,
        private readonly int $urlTtlSeconds = 300,
        private readonly float $missingConfidenceFallback = 0.6,
    ) {}

    public function retranscribe(S2sSegment $segment, SecondPassOptions $options): SecondPassResult
    {
        $audioUrl = $this->signer->url($segment->id, $this->urlTtlSeconds);

        $payload = [
            'audio_url' => $audioUrl,
            'language' => $options->language ?: 'auto',
            'context' => 'proceedings',
        ];

        $pending = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->asJson();
        if ($this->serviceToken !== '') {
            $pending = $pending->withToken($this->serviceToken);
        }
        $response = $pending->post(rtrim($this->gatewayBaseUrl, '/').'/v1/asr', $payload);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'ml-gateway /v1/asr returned %d: %s',
                $response->status(),
                substr((string) $response->body(), 0, 240),
            ));
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('ml-gateway /v1/asr returned non-JSON body');
        }

        $transcript = (string) ($json['transcript'] ?? '');
        $provider = (string) ($json['provider_used'] ?? 'ml-gateway');
        $confidence = $this->extractConfidence($json);

        return new SecondPassResult(
            text: $transcript,
            confidence: $confidence,
            provider: $provider,
            model: (string) ($json['model'] ?? 'ml-gateway-asr'),
            rawMeta: [
                'segments' => $json['segments'] ?? [],
                'language' => $json['language'] ?? $options->language,
            ],
        );
    }

    private function extractConfidence(array $json): float
    {
        $segments = $json['segments'] ?? [];
        if (! is_array($segments) || $segments === []) {
            return $this->missingConfidenceFallback;
        }
        $scores = [];
        foreach ($segments as $seg) {
            if (is_array($seg) && isset($seg['confidence']) && is_numeric($seg['confidence'])) {
                $scores[] = (float) $seg['confidence'];
            }
        }
        if ($scores === []) {
            return $this->missingConfidenceFallback;
        }
        $mean = array_sum($scores) / count($scores);
        return max(0.0, min(1.0, $mean));
    }
}

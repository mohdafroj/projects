<?php

namespace App\Modules\SpeechToSpeech\Services\Recheck;

use Illuminate\Support\Carbon;

/**
 * HMAC-signed URL generator for the internal-only segment-audio
 * fetch endpoint. Used by the recheck engine so the ml-gateway can
 * pull a single segment's source audio through a short-lived URL
 * scoped to the docker-internal hostname.
 *
 * Token shape (URL query params):
 *   e = expiry epoch seconds
 *   t = base64url( hmac_sha256(key, "<segment_id>.<expiry>") )
 *
 * Key is the application APP_KEY (already used for Laravel's signed
 * URL signatures); using it here keeps token rotation tied to the
 * same secret-management flow.
 */
class InternalAudioUrlSigner
{
    public function __construct(
        private readonly string $key,
        private readonly string $baseUrl,
    ) {}

    public function url(int $segmentId, int $ttlSeconds = 300): string
    {
        $expires = Carbon::now()->getTimestamp() + max(1, $ttlSeconds);
        $token = $this->sign($segmentId, $expires);

        return rtrim($this->baseUrl, '/').
            '/api/internal/s2s/audio/'.$segmentId.
            '?e='.$expires.
            '&t='.$token;
    }

    public function verify(int $segmentId, int $expires, string $providedToken): bool
    {
        if (Carbon::now()->getTimestamp() > $expires) {
            return false;
        }
        $expected = $this->sign($segmentId, $expires);
        return hash_equals($expected, $providedToken);
    }

    private function sign(int $segmentId, int $expires): string
    {
        $hmac = hash_hmac('sha256', $segmentId.'.'.$expires, $this->key, true);
        return rtrim(strtr(base64_encode($hmac), '+/', '-_'), '=');
    }
}

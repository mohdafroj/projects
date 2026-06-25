<?php

namespace App\Modules\Asr\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HmacVerifier
{
    private const MAX_SKEW_SECONDS = 300;

    public function verify(Request $request, string $secret): bool
    {
        if ($secret === '') {
            return false;
        }

        $provided = $request->header('X-Signature') ?: $request->header('X-Hub-Signature-256');
        if (! is_string($provided) || $provided === '') {
            return false;
        }

        $timestamp = $request->header('X-Signature-Timestamp');
        $nonce = $request->header('X-Signature-Nonce');

        if (! is_string($timestamp) || ! ctype_digit($timestamp) || ! is_string($nonce) || $nonce === '') {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::MAX_SKEW_SECONDS) {
            return false;
        }

        $provided = str_starts_with($provided, 'sha256=') ? substr($provided, 7) : $provided;
        $payloads = [$request->getContent()];
        if ($request->isJson()) {
            $payloads[] = json_encode($request->json()->all(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        foreach (array_filter(array_unique($payloads)) as $payload) {
            $signedPayload = $timestamp.'.'.$nonce.'.'.$payload;
            if (! hash_equals(hash_hmac('sha256', $signedPayload, $secret), $provided)) {
                continue;
            }

            return Cache::add(
                'hmac_nonce:'.hash('sha256', $nonce),
                true,
                now()->addSeconds(2 * self::MAX_SKEW_SECONDS)
            );
        }

        return false;
    }
}

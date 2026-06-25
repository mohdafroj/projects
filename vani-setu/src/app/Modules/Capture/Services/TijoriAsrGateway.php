<?php

namespace App\Modules\Capture\Services;

use App\Modules\Core\Models\Slot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TijoriAsrGateway
{
    /**
     * @return array{attempted:bool, provider:string, status:int|string|null, detail:mixed}
     */
    public function transcribeSlotAudio(Slot $slot, string $audioPath): array
    {
        $url = trim((string) config('services.tijori.asr_url', ''));
        if ($url === '') {
            return [
                'attempted' => false,
                'provider' => 'tijori',
                'status' => null,
                'detail' => 'TIJORI_ASR_URL not configured',
            ];
        }

        $disk = (string) config('filesystems.reporter_audio_disk', 'vani_audio');
        if (! Storage::disk($disk)->exists($audioPath)) {
            return [
                'attempted' => false,
                'provider' => 'tijori',
                'status' => 'audio_missing',
                'detail' => "Audio file not found: {$audioPath}",
            ];
        }

        try {
            $audio = Storage::disk($disk)->get($audioPath);
        } catch (\Throwable $exception) {
            return [
                'attempted' => false,
                'provider' => 'tijori',
                'status' => 'audio_missing',
                'detail' => $exception->getMessage(),
            ];
        }

        $mimeType = $this->guessMimeType($audioPath);
        $request = Http::timeout((int) config('services.tijori.timeout', 20))
            ->retry(
                (int) config('services.tijori.retries', 2),
                (int) config('services.tijori.retry_sleep_ms', 250)
            )
            ->withHeaders([
                'X-Pipeline-Class' => 'proceedings',
                'X-Slot-Id' => (string) $slot->id,
                'X-Reason-Code' => 'reporter_audio_close',
                'Idempotency-Key' => 'reporter-audio-close-'.$slot->id.'-'.hash('sha256', $audio),
            ]);

        $token = trim((string) config('services.tijori.token', ''));
        if ($token !== '') {
            $request = $request->withToken($token);
        }

        try {
            $filename = Str::afterLast($audioPath, '/') ?: 'slot.webm';
            $response = $request->post($url, [
                'audio_base64' => base64_encode($audio),
                'mime_type' => $mimeType,
                'source_language' => 'auto',
                'reason_code' => 'REPORTER_AUDIO_CLOSE',
                'fallback_on_prem_on_sarvam_5xx' => true,
                'metadata' => [
                    'filename' => $filename,
                    'storage_disk' => $disk,
                    'storage_path' => $audioPath,
                    'slot_id' => $slot->id,
                ],
            ]);

            $detail = $response->json() ?? $response->body();

            return [
                'attempted' => true,
                'provider' => 'tijori',
                'status' => $response->status(),
                'detail' => $detail,
                ...$this->normalizedTranscript($detail),
            ];
        } catch (\Throwable $exception) {
            return [
                'attempted' => true,
                'provider' => 'tijori',
                'status' => 'error',
                'detail' => $exception->getMessage(),
            ];
        }
    }

    private function guessMimeType(string $audioPath): string
    {
        return match (strtolower(pathinfo($audioPath, PATHINFO_EXTENSION))) {
            'wav' => 'audio/wav',
            'mp3' => 'audio/mpeg',
            'm4a', 'mp4' => 'audio/mp4',
            'ogg' => 'audio/ogg',
            default => 'audio/webm',
        };
    }

    /**
     * @return array{transcript:?string, language:?string, confidence:?float}
     */
    private function normalizedTranscript(mixed $detail): array
    {
        if (! is_array($detail)) {
            return ['transcript' => null, 'language' => null, 'confidence' => null];
        }

        $transcript = $detail['transcript']
            ?? $detail['text']
            ?? $detail['source_text']
            ?? $detail['transcribed_text']
            ?? data_get($detail, 'stt.transcript')
            ?? data_get($detail, 'stt.text')
            ?? data_get($detail, 'asr.transcript')
            ?? data_get($detail, 'asr.text')
            ?? null;
        $language = $detail['language']
            ?? $detail['lang']
            ?? $detail['detected_language']
            ?? data_get($detail, 'stt.language')
            ?? data_get($detail, 'stt.language_code')
            ?? data_get($detail, 'asr.language')
            ?? data_get($detail, 'asr.language_code')
            ?? null;
        $confidence = $detail['confidence']
            ?? data_get($detail, 'stt.confidence')
            ?? data_get($detail, 'asr.confidence')
            ?? null;

        return [
            'transcript' => filled($transcript) ? (string) $transcript : null,
            'language' => filled($language) ? (string) $language : null,
            'confidence' => is_numeric($confidence) ? (float) $confidence : null,
        ];
    }
}

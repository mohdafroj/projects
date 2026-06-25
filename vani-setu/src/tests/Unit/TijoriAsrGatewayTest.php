<?php

namespace Tests\Unit;

use App\Modules\Capture\Services\TijoriAsrGateway;
use App\Modules\Core\Models\Slot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TijoriAsrGatewayTest extends TestCase
{
    public function test_gateway_posts_vani_audio_to_tijori_sarvam_contract(): void
    {
        config([
            'filesystems.reporter_audio_disk' => 'vani_audio',
            'services.tijori.asr_url' => 'http://tijori.test/v1/asr/sarvam',
            'services.tijori.token' => 'token-123',
            'services.tijori.timeout' => 20,
            'services.tijori.retries' => 0,
            'services.tijori.retry_sleep_ms' => 0,
        ]);
        Storage::fake('vani_audio');
        Storage::disk('vani_audio')->put('reporter-audio/123/slot-123.webm', 'audio-bytes');

        Http::fake([
            'tijori.test/*' => Http::response([
                'backend' => 'sarvam-saarika-v2.5',
                'transcript' => 'Proceedings transcript.',
            ], 200),
        ]);

        $slot = new Slot;
        $slot->id = 123;

        $result = app(TijoriAsrGateway::class)->transcribeSlotAudio(
            $slot,
            'reporter-audio/123/slot-123.webm',
        );

        $this->assertTrue($result['attempted']);
        $this->assertSame('tijori', $result['provider']);
        $this->assertSame(200, $result['status']);
        $this->assertSame('sarvam-saarika-v2.5', $result['detail']['backend']);

        Http::assertSent(fn ($request) => $request->url() === 'http://tijori.test/v1/asr/sarvam'
            && ! str_contains($request->url(), 'api.sarvam.ai')
            && $request->hasHeader('Authorization', 'Bearer token-123')
            && $request->hasHeader('X-Pipeline-Class', 'proceedings')
            && $request->hasHeader('X-Slot-Id', '123')
            && $request->hasHeader('X-Reason-Code', 'reporter_audio_close')
            && $request->hasHeader('Idempotency-Key', 'reporter-audio-close-123-'.hash('sha256', 'audio-bytes'))
            && $request['audio_base64'] === base64_encode('audio-bytes')
            && $request['mime_type'] === 'audio/webm'
            && $request['source_language'] === 'auto'
            && $request['reason_code'] === 'REPORTER_AUDIO_CLOSE'
            && $request['fallback_on_prem_on_sarvam_5xx'] === true
            && $request['metadata']['filename'] === 'slot-123.webm');
    }

    public function test_gateway_normalizes_nested_transcript_fields(): void
    {
        config([
            'filesystems.reporter_audio_disk' => 'vani_audio',
            'services.tijori.asr_url' => 'http://tijori.test/v1/asr/sarvam',
            'services.tijori.retries' => 0,
        ]);
        Storage::fake('vani_audio');
        Storage::disk('vani_audio')->put('reporter-audio/456/slot-456.mp3', 'mp3-bytes');

        Http::fake([
            'tijori.test/*' => Http::response([
                'asr' => [
                    'text' => 'Nested ASR transcript.',
                    'language' => 'hi-IN',
                    'confidence' => 0.87,
                ],
            ], 200),
        ]);

        $slot = new Slot;
        $slot->id = 456;

        $result = app(TijoriAsrGateway::class)->transcribeSlotAudio($slot, 'reporter-audio/456/slot-456.mp3');

        $this->assertSame('Nested ASR transcript.', $result['transcript']);
        $this->assertSame('hi-IN', $result['language']);
        $this->assertSame(0.87, $result['confidence']);

        Http::assertSent(fn ($request) => $request['mime_type'] === 'audio/mpeg');
    }

    public function test_gateway_returns_structured_missing_audio_error(): void
    {
        config([
            'filesystems.reporter_audio_disk' => 'vani_audio',
            'services.tijori.asr_url' => 'http://tijori.test/v1/asr/sarvam',
        ]);
        Storage::fake('vani_audio');

        $slot = new Slot;
        $slot->id = 789;

        $result = app(TijoriAsrGateway::class)->transcribeSlotAudio($slot, 'missing.webm');

        $this->assertFalse($result['attempted']);
        $this->assertSame('audio_missing', $result['status']);
        Http::assertNothingSent();
    }
}

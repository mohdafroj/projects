<?php

namespace Tests\Unit\SpeechToSpeech;

use App\Modules\SpeechToSpeech\Services\Recheck\InternalAudioUrlSigner;
use Tests\TestCase;

class InternalAudioUrlSignerTest extends TestCase
{
    public function test_signed_url_round_trips(): void
    {
        $signer = new InternalAudioUrlSigner('test-key', 'http://vani-setu-web');
        $url = $signer->url(42, 300);

        $this->assertStringContainsString('/api/internal/s2s/audio/42', $url);
        $this->assertMatchesRegularExpression('/[?&]e=\d+/', $url);
        $this->assertMatchesRegularExpression('/[?&]t=[A-Za-z0-9_-]+/', $url);

        preg_match('/[?&]e=(\d+)/', $url, $eMatch);
        preg_match('/[?&]t=([A-Za-z0-9_-]+)/', $url, $tMatch);
        $expires = (int) $eMatch[1];
        $token = $tMatch[1];

        $this->assertTrue($signer->verify(42, $expires, $token));
    }

    public function test_verify_rejects_different_segment_id(): void
    {
        $signer = new InternalAudioUrlSigner('test-key', 'http://vani-setu-web');
        $url = $signer->url(42, 300);
        preg_match('/[?&]e=(\d+)/', $url, $eMatch);
        preg_match('/[?&]t=([A-Za-z0-9_-]+)/', $url, $tMatch);

        $this->assertFalse($signer->verify(43, (int) $eMatch[1], $tMatch[1]));
    }

    public function test_verify_rejects_tampered_token(): void
    {
        $signer = new InternalAudioUrlSigner('test-key', 'http://vani-setu-web');
        $url = $signer->url(42, 300);
        preg_match('/[?&]e=(\d+)/', $url, $eMatch);

        $this->assertFalse($signer->verify(42, (int) $eMatch[1], 'AAAAtampered_signature_AAAA'));
    }

    public function test_verify_rejects_expired_url(): void
    {
        $signer = new InternalAudioUrlSigner('test-key', 'http://vani-setu-web');
        $url = $signer->url(42, 1);

        preg_match('/[?&]e=(\d+)/', $url, $eMatch);
        preg_match('/[?&]t=([A-Za-z0-9_-]+)/', $url, $tMatch);
        $expires = (int) $eMatch[1];
        $token = $tMatch[1];

        sleep(2);
        $this->assertFalse($signer->verify(42, $expires, $token));
    }

    public function test_different_keys_produce_different_tokens(): void
    {
        $signerA = new InternalAudioUrlSigner('key-a', 'http://x');
        $signerB = new InternalAudioUrlSigner('key-b', 'http://x');
        $urlA = $signerA->url(42, 300);
        $urlB = $signerB->url(42, 300);

        preg_match('/[?&]t=([A-Za-z0-9_-]+)/', $urlA, $tA);
        preg_match('/[?&]t=([A-Za-z0-9_-]+)/', $urlB, $tB);

        $this->assertNotSame($tA[1], $tB[1]);
    }
}

<?php

namespace Tests\Feature\SpeechToSpeech;

use App\Modules\Core\Models\AuditLog;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientErrorReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_public_s2s_client_errors_are_audited_with_bounded_stack_payload(): void
    {
        $this->withHeader('User-Agent', 'VaniSetuBrowser/1.0')
            ->postJson('/speech-to-speech/client-errors', [
                'kind' => 'fetch_5xx',
                'message' => 'S2S request failed with HTTP 500',
                'source' => 'fetch',
                'url' => 'http://localhost/speech-to-speech/sessions/7/status',
                'status' => 500,
                'line' => 12,
                'column' => 34,
                'stack' => str_repeat('x', 5000),
                'session_id' => 7,
                'chunk_id' => 3,
                'language_code' => 'hi-IN',
            ])
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('status', 'ok');

        $audit = AuditLog::query()->where('action', 's2s.client.error')->firstOrFail();
        $this->assertSame('speech_to_speech', $audit->chain_segment);
        $this->assertSame('fetch_5xx', $audit->payload['kind']);
        $this->assertSame(500, $audit->payload['status']);
        $this->assertSame(7, $audit->payload['session_id']);
        $this->assertSame(3, $audit->payload['chunk_id']);
        $this->assertSame('hi-IN', $audit->payload['language_code']);
        $this->assertSame('VaniSetuBrowser/1.0', $audit->payload['user_agent']);
        $this->assertLessThanOrEqual(4000, strlen($audit->payload['stack']));
    }

    public function test_s2s_browser_script_reports_exceptions_and_5xx_fetches_without_recursing(): void
    {
        $script = file_get_contents(public_path('vanisetu-speech-to-speech/sarvam.jsx'));

        $this->assertIsString($script);
        $this->assertStringContainsString('const S2S_CLIENT_ERROR_ENDPOINT = "/speech-to-speech/client-errors"', $script);
        $this->assertStringContainsString('window.addEventListener("error"', $script);
        $this->assertStringContainsString('window.addEventListener("unhandledrejection"', $script);
        $this->assertStringContainsString('response.status >= 500', $script);
        $this->assertStringContainsString('url.includes("/speech-to-speech/")', $script);
        $this->assertStringContainsString('!url.includes(S2S_CLIENT_ERROR_ENDPOINT)', $script);
        $this->assertStringContainsString('language_code: String(details.language_code || "").slice(0, 16) || undefined', $script);
        $this->assertStringContainsString('Error reporting must never break chamber audio.', $script);
    }
}

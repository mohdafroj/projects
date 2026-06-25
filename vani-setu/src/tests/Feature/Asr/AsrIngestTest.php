<?php

namespace Tests\Feature\Asr;

use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Slot;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AsrIngestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response(['taskUid' => 1], 202)]);
        config(['services.asr.ingest_secret' => 'local-asr-secret']);

        $this->seed(DatabaseSeeder::class);
    }

    public function test_valid_hmac_updates_block_ai_text(): void
    {
        $block = Block::query()->firstOrFail();
        $payload = [
            'slot_id' => $block->slot_id,
            'start_ms' => $block->start_ms,
            'end_ms' => $block->end_ms,
            'text' => 'ASR corrected transcript text.',
            'lang' => $block->original_lang,
            'confidence' => 0.93,
        ];

        $this->postSignedJson('/api/asr/ingest', $payload)
            ->assertOk()
            ->assertJsonPath('block_id', $block->id);

        $this->assertDatabaseHas('blocks', [
            'id' => $block->id,
            'ai_text' => 'ASR corrected transcript text.',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'asr.block.ingested',
            'subject_type' => Block::class,
            'subject_id' => (string) $block->id,
        ]);
    }

    public function test_valid_hmac_creates_block_when_timestamp_is_not_covered(): void
    {
        $slot = Slot::query()->firstOrFail();
        $payload = [
            'slot_id' => $slot->id,
            'start_ms' => 999000,
            'end_ms' => 1002000,
            'text' => 'New ASR segment outside existing blocks.',
            'lang' => 'en',
            'confidence' => 0.82,
        ];

        $this->postSignedJson('/api/asr/ingest', $payload)
            ->assertOk()
            ->assertJsonPath('created', true);

        $this->assertDatabaseHas('blocks', [
            'slot_id' => $slot->id,
            'start_ms' => 999000,
            'end_ms' => 1002000,
            'ai_text' => 'New ASR segment outside existing blocks.',
            'text' => 'New ASR segment outside existing blocks.',
        ]);
    }

    public function test_valid_hmac_accepts_regional_language_code_when_creating_block(): void
    {
        $slot = Slot::query()->firstOrFail();
        $payload = [
            'slot_id' => $slot->id,
            'start_ms' => 1200000,
            'end_ms' => 1230000,
            'text' => 'Regional language ASR segment.',
            'lang' => 'hi-IN',
        ];

        $this->postSignedJson('/api/asr/ingest', $payload)
            ->assertOk()
            ->assertJsonPath('created', true);

        $this->assertDatabaseHas('blocks', [
            'slot_id' => $slot->id,
            'start_ms' => 1200000,
            'original_lang' => 'hi',
            'chief_lang' => 'hi',
        ]);
    }

    public function test_bad_hmac_returns_401(): void
    {
        $block = Block::query()->firstOrFail();

        $this->withHeaders(['X-Signature' => 'bad-signature'])
            ->postJson('/api/asr/ingest', [
                'slot_id' => $block->slot_id,
                'start_ms' => $block->start_ms,
                'text' => 'Should not be stored.',
            ])
            ->assertUnauthorized();

        $this->assertNotSame('Should not be stored.', $block->fresh()->ai_text);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSignedJson(string $uri, array $payload)
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $nonce = 'asr-ingest-test-'.bin2hex(random_bytes(8));
        $signature = hash_hmac('sha256', "{$timestamp}.{$nonce}.{$body}", 'local-asr-secret');

        return $this->call('POST', $uri, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => $signature,
            'HTTP_X_SIGNATURE_TIMESTAMP' => $timestamp,
            'HTTP_X_SIGNATURE_NONCE' => $nonce,
        ], $body);
    }
}

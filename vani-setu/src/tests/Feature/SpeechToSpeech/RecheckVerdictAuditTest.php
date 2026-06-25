<?php

namespace Tests\Feature\SpeechToSpeech;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\SpeechToSpeech\Models\S2sSegment;
use App\Modules\SpeechToSpeech\Models\S2sSession;
use App\Modules\SpeechToSpeech\Services\Recheck\RecheckService;
use App\Modules\SpeechToSpeech\Services\Recheck\SecondPassOptions;
use App\Modules\SpeechToSpeech\Services\Recheck\SecondPassResult;
use App\Modules\SpeechToSpeech\Services\Recheck\SecondPassTranscriber;
use App\Modules\SpeechToSpeech\Services\Recheck\TranscriptDriftAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class RecheckVerdictAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_passed_verdict_writes_audit_row_with_payload(): void
    {
        $segment = $this->seedSegment(['source_text' => 'budget discussion is underway', 'source_audio_path' => 's2s/seg.wav']);
        $service = $this->makeService(new StaticTranscriber('budget discussion is underway.', 0.92));

        $service->recheckSegment($segment);

        $audit = AuditLog::query()->where('action', 's2s.recheck.verdict.passed')->latest('id')->first();
        $this->assertNotNull($audit);
        $payload = is_array($audit->payload) ? $audit->payload : json_decode($audit->payload, true);
        $this->assertSame($segment->id, $payload['segment_id']);
        $this->assertSame('pending', $payload['previous_state']);
        $this->assertSame('passed', $payload['new_state']);
        $this->assertSame('static', $payload['second_pass_provider']);
        $this->assertSame(1, $payload['qa_attempts']);
        $this->assertFalse($payload['has_corrected_text']);
    }

    public function test_drift_verdict_writes_audit_row(): void
    {
        $segment = $this->seedSegment(['source_text' => 'budget for ministry of railways is approved today', 'source_audio_path' => 's2s/seg.wav']);
        $service = $this->makeService(new StaticTranscriber('audit for company of automakers is rejected today', 0.55));

        $service->recheckSegment($segment);

        $audit = AuditLog::query()->where('action', 's2s.recheck.verdict.drift')->latest('id')->first();
        $this->assertNotNull($audit);
        $payload = is_array($audit->payload) ? $audit->payload : json_decode($audit->payload, true);
        $this->assertSame('drift', $payload['new_state']);
        $this->assertGreaterThan(0.3, (float) $payload['wer']);
    }

    public function test_failed_verdict_writes_audit_with_error_payload(): void
    {
        $segment = $this->seedSegment(['source_text' => 'something', 'source_audio_path' => 's2s/seg.wav']);
        $service = $this->makeService(new ThrowingTranscriber('upstream 502'));

        $service->recheckSegment($segment);

        $audit = AuditLog::query()->where('action', 's2s.recheck.verdict.failed')->latest('id')->first();
        $this->assertNotNull($audit);
        $payload = is_array($audit->payload) ? $audit->payload : json_decode($audit->payload, true);
        $this->assertSame('failed', $payload['new_state']);
        $this->assertSame('upstream 502', $payload['error']);
    }

    public function test_skipped_verdict_writes_audit_with_reason(): void
    {
        $segment = $this->seedSegment(['source_text' => null, 'source_audio_path' => null]);
        $service = $this->makeService(new StaticTranscriber('unused', 1.0));

        $service->recheckSegment($segment);

        $audit = AuditLog::query()->where('action', 's2s.recheck.verdict.skipped')->latest('id')->first();
        $this->assertNotNull($audit);
        $payload = is_array($audit->payload) ? $audit->payload : json_decode($audit->payload, true);
        $this->assertSame('skipped', $payload['new_state']);
        $this->assertSame('no_audio_or_text', $payload['skipped_reason']);
    }

    public function test_audit_rows_form_a_hash_chain(): void
    {
        $segmentA = $this->seedSegment(['source_text' => 'first', 'source_audio_path' => 's2s/a.wav']);
        $segmentB = $this->seedSegment(['source_text' => 'second', 'source_audio_path' => 's2s/b.wav', 'sequence_no' => 2]);
        $service = $this->makeService(new StaticTranscriber('first', 0.95));

        $service->recheckSegment($segmentA);
        $service->recheckSegment($segmentB);

        $rows = AuditLog::query()
            ->where('subject_type', $segmentA->getMorphClass())
            ->whereIn('subject_id', [(string) $segmentA->id, (string) $segmentB->id])
            ->where('action', 'like', 's2s.recheck.verdict.%')
            ->orderBy('id')
            ->get(['id', 'prev_hash', 'this_hash', 'chain_segment']);

        $this->assertCount(2, $rows);
        // Both verdicts share the same chain_segment because they share
        // the same action namespace prefix in AuditLogger::chainSegment.
        $this->assertSame($rows[0]->chain_segment, $rows[1]->chain_segment);
        // Second row's prev_hash must equal first row's this_hash
        // (hash-chain invariant). Any tampering breaks this.
        $this->assertSame($rows[0]->this_hash, $rows[1]->prev_hash, 'chain invariant broken');
    }

    private function seedSegment(array $overrides = []): S2sSegment
    {
        $session = S2sSession::query()->create([
            'title' => 'Audit Test',
            'mode' => 'upload',
            'input_source' => 'uploaded_file',
            'listener_scope' => 'outside_house',
            'source_lang' => 'en-IN',
            'target_lang' => 'hi-IN',
            'available_target_langs' => ['hi-IN'],
            'status' => 'finished',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        return S2sSegment::query()->create(array_merge([
            'session_id' => $session->id,
            'sequence_no' => $overrides['sequence_no'] ?? 1,
            'source_text' => 'default text',
            'source_audio_path' => 's2s/default.wav',
            'qa_state' => 'pending',
            'qa_attempts' => 0,
        ], $overrides));
    }

    private function makeService(SecondPassTranscriber $transcriber): RecheckService
    {
        return new RecheckService(
            transcriber: $transcriber,
            analyzer: new TranscriptDriftAnalyzer(),
            audit: app(AuditLogger::class),
        );
    }
}

class StaticTranscriber implements SecondPassTranscriber
{
    public function __construct(
        private readonly string $text,
        private readonly float $confidence,
    ) {}

    public function retranscribe(S2sSegment $segment, SecondPassOptions $options): SecondPassResult
    {
        return new SecondPassResult($this->text, $this->confidence, 'static', 'test', []);
    }
}

class ThrowingTranscriber implements SecondPassTranscriber
{
    public function __construct(private readonly string $message) {}

    public function retranscribe(S2sSegment $segment, SecondPassOptions $options): SecondPassResult
    {
        throw new RuntimeException($this->message);
    }
}

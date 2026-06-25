<?php

namespace App\Modules\SpeechToSpeech\Services\Recheck;

use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\SpeechToSpeech\Models\S2sSegment;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecheckService
{
    public function __construct(
        private readonly SecondPassTranscriber $transcriber,
        private readonly TranscriptDriftAnalyzer $analyzer,
        private readonly AuditLogger $audit,
    ) {}

    public function recheckSegment(S2sSegment $segment): S2sSegment
    {
        if (! filled($segment->source_text) || ! filled($segment->source_audio_path)) {
            return $this->markSkipped($segment, 'no_audio_or_text');
        }

        try {
            $second = $this->transcriber->retranscribe(
                $segment,
                new SecondPassOptions(
                    language: $segment->source_language ?: 'auto',
                    glossaryPrompt: '',
                ),
            );
        } catch (Throwable $e) {
            Log::warning('s2s.recheck.second_pass_failed', [
                'segment_id' => $segment->id,
                'error' => $e->getMessage(),
            ]);
            return $this->markFailed($segment, $e->getMessage());
        }

        $verdict = $this->analyzer->compare(
            (string) $segment->source_text,
            $second->text,
            $second->confidence,
        );

        $previousState = (string) ($segment->qa_state ?? 'pending');
        $segment->forceFill([
            'qa_state' => $verdict['state'],
            'qa_score' => $verdict['score'],
            'qa_corrected_text' => $verdict['corrected_text'],
            'qa_engine_meta' => array_merge($verdict, [
                'second_pass' => $second->toArray(),
            ]),
            'qa_checked_at' => now(),
            'qa_attempts' => (int) ($segment->qa_attempts ?? 0) + 1,
        ])->save();

        $this->writeAudit($segment, $previousState, [
            'wer' => $verdict['wer'] ?? null,
            'score' => $verdict['score'] ?? null,
            'second_pass_provider' => $second->provider,
            'second_pass_confidence' => $second->confidence,
        ]);

        return $segment->refresh();
    }

    private function markSkipped(S2sSegment $segment, string $reason): S2sSegment
    {
        $previousState = (string) ($segment->qa_state ?? 'pending');
        $segment->forceFill([
            'qa_state' => 'skipped',
            'qa_score' => null,
            'qa_engine_meta' => ['skipped_reason' => $reason],
            'qa_checked_at' => now(),
            'qa_attempts' => (int) ($segment->qa_attempts ?? 0) + 1,
        ])->save();
        $this->writeAudit($segment, $previousState, ['skipped_reason' => $reason]);
        return $segment->refresh();
    }

    private function markFailed(S2sSegment $segment, string $error): S2sSegment
    {
        $previousState = (string) ($segment->qa_state ?? 'pending');
        $segment->forceFill([
            'qa_state' => 'failed',
            'qa_score' => null,
            'qa_engine_meta' => ['error' => $error],
            'qa_checked_at' => now(),
            'qa_attempts' => (int) ($segment->qa_attempts ?? 0) + 1,
        ])->save();
        $this->writeAudit($segment, $previousState, ['error' => $this->truncate($error, 240)]);
        return $segment->refresh();
    }

    /**
     * Hash-chained audit-log entry for a recheck verdict transition.
     * Action is namespaced 's2s.recheck.verdict.<state>' so chain
     * segmentation in AuditLogger keeps each verdict family on its
     * own segment for replay efficiency.
     */
    private function writeAudit(S2sSegment $segment, string $previousState, array $extra): void
    {
        try {
            $this->audit->log(
                's2s.recheck.verdict.'.$segment->qa_state,
                $segment,
                array_filter([
                    'segment_id' => $segment->id,
                    'session_id' => $segment->session_id,
                    'sequence_no' => $segment->sequence_no,
                    'previous_state' => $previousState,
                    'new_state' => $segment->qa_state,
                    'qa_attempts' => $segment->qa_attempts,
                    'has_corrected_text' => filled($segment->qa_corrected_text),
                    ...$extra,
                ], static fn ($v) => $v !== null),
            );
        } catch (Throwable $e) {
            // Audit failure must not block the recheck pipeline. Log
            // the audit error and continue — the verdict persistence
            // itself already succeeded.
            Log::warning('s2s.recheck.audit_write_failed', [
                'segment_id' => $segment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function truncate(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1).'…' : $s;
    }
}

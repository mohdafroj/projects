<?php

namespace App\Modules\SpeechToSpeech\Services\Recheck;

use App\Modules\SpeechToSpeech\Models\S2sSegment;
use App\Modules\SpeechToSpeech\Models\S2sSession;

class QaSummaryService
{
    /**
     * Roll up the recheck-engine verdicts for one session into a shape
     * suitable for both the artisan command and the JSON endpoint.
     *
     * @return array{
     *   session_id:int,
     *   total_segments:int,
     *   audio_segments:int,
     *   active_audio_segments:int,
     *   pruned_audio_segments:int,
     *   verdicts:array<int, array{state:string, count:int, avg_score:?float, min_score:?float, max_score:?float}>,
     *   sample_drift:array<int, array{segment_id:int, sequence_no:int, source_text:string, second_pass:string, score:?float, wer:?float}>,
     *   sample_corrected:array<int, array{segment_id:int, sequence_no:int, original:string, corrected:string, score:?float}>,
     *   last_checked_at:?string,
     * }
     */
    public function summarise(S2sSession $session, int $sampleLimit = 5): array
    {
        $segments = S2sSegment::query()
            ->where('session_id', $session->id)
            ->orderBy('sequence_no')
            ->get();

        $totalSegments = $segments->count();
        $activeAudioSegments = $segments->filter(fn (S2sSegment $segment): bool => $segment->hasActiveSourceAudio())->count();
        $prunedAudioSegments = $segments->filter(fn (S2sSegment $segment): bool => $segment->hasPrunedSourceAudioRecord())->count();
        $audioSegments = $segments->filter(fn (S2sSegment $segment): bool => $segment->hasSourceAudioLinkage())->count();

        $verdicts = $segments
            ->groupBy(fn (S2sSegment $s) => $s->qa_state ?? 'pending')
            ->map(function ($group, string $state) {
                $scores = $group->pluck('qa_score')->filter(fn ($v) => $v !== null);
                return [
                    'state' => $state,
                    'count' => $group->count(),
                    'avg_score' => $scores->isEmpty() ? null : round((float) $scores->avg(), 3),
                    'min_score' => $scores->isEmpty() ? null : round((float) $scores->min(), 3),
                    'max_score' => $scores->isEmpty() ? null : round((float) $scores->max(), 3),
                ];
            })
            ->values()
            ->all();

        $driftSegments = $segments
            ->where('qa_state', 'drift')
            ->sortBy('qa_score')
            ->take($sampleLimit)
            ->map(fn (S2sSegment $s) => [
                'segment_id' => $s->id,
                'sequence_no' => $s->sequence_no,
                'source_text' => (string) ($s->source_text ?? ''),
                'second_pass' => (string) ($s->qa_engine_meta['second_pass']['text'] ?? ''),
                'score' => $s->qa_score,
                'wer' => isset($s->qa_engine_meta['wer']) ? (float) $s->qa_engine_meta['wer'] : null,
            ])
            ->values()
            ->all();

        $correctedSegments = $segments
            ->where('qa_state', 'corrected')
            ->take($sampleLimit)
            ->map(fn (S2sSegment $s) => [
                'segment_id' => $s->id,
                'sequence_no' => $s->sequence_no,
                'original' => (string) ($s->source_text ?? ''),
                'corrected' => (string) ($s->qa_corrected_text ?? ''),
                'score' => $s->qa_score,
            ])
            ->values()
            ->all();

        $lastChecked = $segments
            ->pluck('qa_checked_at')
            ->filter()
            ->max();

        return [
            'session_id' => $session->id,
            'total_segments' => $totalSegments,
            'audio_segments' => $audioSegments,
            'active_audio_segments' => $activeAudioSegments,
            'pruned_audio_segments' => $prunedAudioSegments,
            'verdicts' => $verdicts,
            'sample_drift' => $driftSegments,
            'sample_corrected' => $correctedSegments,
            'last_checked_at' => $lastChecked instanceof \DateTimeInterface
                ? $lastChecked->format(\DateTimeInterface::ATOM)
                : ($lastChecked ? (string) $lastChecked : null),
        ];
    }

}

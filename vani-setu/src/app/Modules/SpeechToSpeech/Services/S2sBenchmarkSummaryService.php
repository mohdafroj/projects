<?php

namespace App\Modules\SpeechToSpeech\Services;

use App\Modules\SpeechToSpeech\Models\S2sOutput;
use App\Modules\SpeechToSpeech\Models\S2sSegment;

class S2sBenchmarkSummaryService
{
    public function __construct(
        private readonly S2sLanguageRegistry $languages,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(int $windowSegments = 250): array
    {
        $segments = S2sSegment::query()
            ->with('outputs')
            ->latest('id')
            ->limit(max(10, $windowSegments))
            ->get()
            ->sortBy('id')
            ->values();

        $latencies = $segments
            ->map(fn (S2sSegment $segment): mixed => data_get($segment->engine_meta, 'dispatch.server_latency_ms'))
            ->filter(fn (mixed $value): bool => is_numeric($value) && (int) $value > 0)
            ->map(fn (mixed $value): int => (int) $value)
            ->values()
            ->all();
        $stageLatency = $this->stageLatencySummary($segments->all());
        $languageReadiness = $this->languageReadiness();

        $outputRows = $segments->flatMap(fn (S2sSegment $segment) => $segment->outputs);
        $completedOutputs = $outputRows->filter(fn (S2sOutput $output): bool => in_array($output->status, ['completed', 'ready'], true))->count();
        $audioOutputs = $outputRows->filter(fn (S2sOutput $output): bool => filled($output->audio_output_path))->count();

        $processedSegments = $segments->filter(fn (S2sSegment $segment): bool => in_array($segment->status, ['processed', 'degraded'], true))->count();
        $qaApproved = $segments->filter(fn (S2sSegment $segment): bool => in_array($segment->qa_state, S2sSegment::QA_APPROVED_STATES, true))->count();
        $qaChecked = $segments->filter(fn (S2sSegment $segment): bool => filled($segment->qa_checked_at))->count();
        $qaCorrected = $segments->filter(fn (S2sSegment $segment): bool => $segment->qa_state === 'corrected')->count();
        $sourceAudioActive = $segments->filter(fn (S2sSegment $segment): bool => $segment->hasActiveSourceAudio())->count();
        $sourceAudioPruned = $segments->filter(fn (S2sSegment $segment): bool => $segment->hasPrunedSourceAudioRecord())->count();
        $sourceAudioLinked = $segments->filter(fn (S2sSegment $segment): bool => $segment->hasSourceAudioLinkage())->count();

        $metrics = [
            'segments_sampled' => $segments->count(),
            'latency_samples' => count($latencies),
            'p50_latency_ms' => $this->percentile($latencies, 50),
            'p95_latency_ms' => $this->percentile($latencies, 95),
            'stage_latency_ms' => $stageLatency,
            'bottleneck_stage' => $this->bottleneckStage($stageLatency),
            'success_rate' => $segments->count() > 0 ? round($processedSegments / $segments->count(), 4) : null,
            'qa_approved_rate' => $qaChecked > 0 ? round($qaApproved / $qaChecked, 4) : null,
            'qa_checked_segments' => $qaChecked,
            'qa_corrected_segments' => $qaCorrected,
            'source_audio_linkage_rate' => $segments->count() > 0 ? round($sourceAudioLinked / $segments->count(), 4) : null,
            'source_audio_active_rate' => $segments->count() > 0 ? round($sourceAudioActive / $segments->count(), 4) : null,
            'source_audio_pruned_rate' => $segments->count() > 0 ? round($sourceAudioPruned / $segments->count(), 4) : null,
            'tts_availability_rate' => $outputRows->count() > 0 ? round($audioOutputs / $outputRows->count(), 4) : null,
            'translation_completion_rate' => $outputRows->count() > 0 ? round($completedOutputs / $outputRows->count(), 4) : null,
            'language_readiness' => $languageReadiness,
        ];

        $vaniScores = [
            'latency' => $this->latencyScore($metrics['p95_latency_ms']),
            'asr_quality' => $this->rateScore($metrics['qa_approved_rate']),
            'translation' => $this->rateScore($metrics['translation_completion_rate']),
            'tts' => $this->rateScore($metrics['tts_availability_rate']),
            'reliability' => $this->rateScore($metrics['success_rate']),
        ];

        return [
            'generated_at' => now()->toISOString(),
            'window' => [
                'segments' => $metrics['segments_sampled'],
                'basis' => 'latest_s2s_segments',
            ],
            'metrics' => $metrics,
            'language_readiness' => $languageReadiness,
            'rollout_agents' => $this->rolloutAgents($metrics, $languageReadiness, $vaniScores),
            'benchmark_comparison' => $this->benchmarkComparison($vaniScores),
            'systems' => [
                [
                    'name' => 'Vani Setu',
                    'kind' => 'measured',
                    'grade' => $this->grade($vaniScores),
                    'scores' => $vaniScores,
                    'metrics' => $metrics,
                    'notes' => 'Measured from stored S2S sessions in this deployment.',
                ],
                ...$this->referenceSystems(),
            ],
            'grade_bands' => [
                'A' => '>= 85',
                'B' => '70-84',
                'C' => '55-69',
                'D' => '40-54',
                'E' => '< 40',
            ],
        ];
    }

    /**
     * @param  array<string, ?int>  $vaniScores
     * @return array<string, mixed>
     */
    private function benchmarkComparison(array $vaniScores): array
    {
        $referenceSystems = $this->referenceSystems();
        $bestReferenceScores = [];
        foreach (['latency', 'asr_quality', 'translation', 'tts', 'reliability'] as $key) {
            $bestReferenceScores[$key] = collect($referenceSystems)
                ->pluck('scores')
                ->map(fn (array $scores): ?int => isset($scores[$key]) ? (int) $scores[$key] : null)
                ->filter(fn (?int $score): bool => $score !== null)
                ->max();
        }

        $gaps = [];
        foreach ($bestReferenceScores as $key => $referenceScore) {
            $measured = $vaniScores[$key] ?? null;
            $gaps[$key] = ($measured === null || $referenceScore === null) ? null : $referenceScore - $measured;
        }

        return [
            'best_reference_scores' => $bestReferenceScores,
            'measured_gaps' => $gaps,
            'measured_average' => $this->averageScore($vaniScores),
            'best_reference_average' => $this->averageScore($bestReferenceScores),
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @param  array<string, mixed>  $languageReadiness
     * @param  array<string, ?int>  $vaniScores
     * @return list<array<string, mixed>>
     */
    private function rolloutAgents(array $metrics, array $languageReadiness, array $vaniScores): array
    {
        $readinessScore = (int) round((
            (float) ($languageReadiness['scheduled_coverage_rate'] ?? 0) +
            ((bool) ($languageReadiness['text_only_audio_fallback_ready'] ?? false) ? 1 : 0)
        ) * 50);
        $masterScore = $this->averageScore([
            $vaniScores['reliability'] ?? null,
            $vaniScores['translation'] ?? null,
            $readinessScore,
        ]);
        $transcriptScore = $this->averageScore([
            $this->rateScore($metrics['source_audio_linkage_rate'] ?? null),
            $this->rateScore($metrics['qa_approved_rate'] ?? null),
        ]);
        $uiScore = $this->averageScore([
            $vaniScores['reliability'] ?? null,
            $vaniScores['tts'] ?? null,
        ]);

        return [
            [
                'key' => 'master_orchestrator',
                'label' => 'Master orchestrator',
                'score' => $masterScore,
                'status' => $this->agentStatus($masterScore),
                'signal' => 'coverage '.$this->pct($languageReadiness['scheduled_coverage_rate'] ?? null).' · reliability '.$this->pct($metrics['success_rate'] ?? null),
                'focus' => 'Keep language coverage, fallback audio, and provider health moving together.',
            ],
            [
                'key' => 'latency_performance',
                'label' => 'Latency / performance',
                'score' => $vaniScores['latency'] ?? null,
                'status' => $this->agentStatus($vaniScores['latency'] ?? null),
                'signal' => 'p95 '.$this->ms($metrics['p95_latency_ms'] ?? null).' · bottleneck '.($metrics['bottleneck_stage'] ?? 'unknown'),
                'focus' => 'Reduce speak-to-hear delay and watch the slowest pipeline stage.',
            ],
            [
                'key' => 'transcript_audio',
                'label' => 'Transcript / audio',
                'score' => $transcriptScore,
                'status' => $this->agentStatus($transcriptScore),
                'signal' => 'audio links '.$this->pct($metrics['source_audio_linkage_rate'] ?? null).' · active '.$this->pct($metrics['source_audio_active_rate'] ?? null).' · pruned '.$this->pct($metrics['source_audio_pruned_rate'] ?? null).' · QA '.$this->pct($metrics['qa_approved_rate'] ?? null),
                'focus' => 'Preserve segment timestamps, replay links, and corrected transcript readiness.',
            ],
            [
                'key' => 'ui_stability',
                'label' => 'UI / stability',
                'score' => $uiScore,
                'status' => $this->agentStatus($uiScore),
                'signal' => 'TTS '.$this->pct($metrics['tts_availability_rate'] ?? null).' · processed '.$this->pct($metrics['success_rate'] ?? null),
                'focus' => 'Keep the console responsive and prevent visible server or playback failures.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function languageReadiness(): array
    {
        $all = $this->languages->all();
        $scheduled = array_diff(array_keys($all), ['en-IN']);
        $audioLanguages = array_values(array_filter(
            array_keys($all),
            fn (string $code): bool => (bool) ($all[$code]['audio_output'] ?? false),
        ));
        $scheduledAudio = array_values(array_intersect($scheduled, $audioLanguages));
        $textOnlyScheduled = array_values(array_filter(
            $scheduled,
            fn (string $code): bool => ! in_array($code, $scheduledAudio, true),
        ));
        $audibleFallback = 'hi-IN';
        $audibleFallbackReady = $this->languages->hasAudioOutput($audibleFallback);

        return [
            'registry_languages' => count($all),
            'scheduled_languages_required' => 22,
            'scheduled_languages_registered' => count($scheduled),
            'scheduled_coverage_rate' => count($scheduled) > 0 ? round(count($scheduled) / 22, 4) : null,
            'audio_output_languages' => count($audioLanguages),
            'scheduled_audio_output_languages' => count($scheduledAudio),
            'scheduled_audio_output_rate' => count($scheduled) > 0 ? round(count($scheduledAudio) / count($scheduled), 4) : null,
            'text_only_scheduled_languages' => $textOnlyScheduled,
            'audible_fallback_language' => $audibleFallback,
            'audible_fallback_label' => $this->languages->label($audibleFallback),
            'text_only_audio_fallback_ready' => count($textOnlyScheduled) === 0 || $audibleFallbackReady,
            'text_only_audio_fallback_targets' => $textOnlyScheduled,
            'priority_outputs' => collect(['en-IN', 'hi-IN'])->mapWithKeys(fn (string $code): array => [
                $code => [
                    'registered' => isset($all[$code]),
                    'audio_output' => (bool) ($all[$code]['audio_output'] ?? false),
                    'label' => $this->languages->label($code),
                ],
            ])->all(),
            'status' => count($scheduled) >= 22 && count($textOnlyScheduled) === 0
                ? 'ready_for_all_audio'
                : (count($scheduled) >= 22 ? 'translation_ready_audio_partial' : 'missing_language_registration'),
        ];
    }

    /**
     * @param  list<S2sSegment>  $segments
     * @return array<string, array{label:string, samples:int, p50_ms:?int, p95_ms:?int}>
     */
    private function stageLatencySummary(array $segments): array
    {
        $paths = [
            'first_byte' => [
                'label' => 'First audio',
                'paths' => [
                    'dispatch.response.first_byte_ms',
                    'dispatch.response.firstByteMs',
                    'dispatch.response.streaming.first_byte_ms',
                    'dispatch.response.timings.first_byte_ms',
                    'dispatch.response.latencies.first_byte_ms',
                ],
            ],
            'stt' => [
                'label' => 'STT',
                'paths' => [
                    'dispatch.response.stt_latency_ms',
                    'dispatch.response.stt.latency_ms',
                    'dispatch.response.timings.stt_ms',
                    'dispatch.response.latencies.stt_ms',
                    'dispatch.response.stage_latencies.stt_ms',
                    'dispatch.response.stage_latency_ms.stt',
                ],
            ],
            'translation' => [
                'label' => 'Translate',
                'paths' => [
                    'dispatch.response.translation_latency_ms',
                    'dispatch.response.translate_latency_ms',
                    'dispatch.response.translation.latency_ms',
                    'dispatch.response.translate.latency_ms',
                    'dispatch.response.timings.translation_ms',
                    'dispatch.response.timings.translate_ms',
                    'dispatch.response.latencies.translation_ms',
                    'dispatch.response.stage_latencies.translation_ms',
                    'dispatch.response.stage_latency_ms.translation',
                ],
            ],
            'tts' => [
                'label' => 'TTS',
                'paths' => [
                    'dispatch.response.tts_latency_ms',
                    'dispatch.response.tts.latency_ms',
                    'dispatch.response.timings.tts_ms',
                    'dispatch.response.latencies.tts_ms',
                    'dispatch.response.stage_latencies.tts_ms',
                    'dispatch.response.stage_latency_ms.tts',
                ],
            ],
        ];

        $summary = [];
        foreach ($paths as $key => $definition) {
            $values = [];
            foreach ($segments as $segment) {
                foreach ($definition['paths'] as $path) {
                    $value = data_get($segment->engine_meta, $path);
                    if (is_numeric($value) && (int) $value > 0) {
                        $values[] = (int) $value;
                        break;
                    }
                }
            }

            $summary[$key] = [
                'label' => $definition['label'],
                'samples' => count($values),
                'p50_ms' => $this->percentile($values, 50),
                'p95_ms' => $this->percentile($values, 95),
            ];
        }

        return $summary;
    }

    /**
     * @param  array<string, array{label:string, samples:int, p50_ms:?int, p95_ms:?int}>  $stageLatency
     */
    private function bottleneckStage(array $stageLatency): ?string
    {
        $worst = null;
        $worstP95 = null;
        foreach ($stageLatency as $stage => $metrics) {
            $p95 = $metrics['p95_ms'];
            if ($p95 === null) {
                continue;
            }

            if ($worstP95 === null || $p95 > $worstP95) {
                $worst = $stage;
                $worstP95 = $p95;
            }
        }

        return $worst;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function referenceSystems(): array
    {
        return [
            [
                'name' => 'OpenAI Realtime class',
                'kind' => 'reference_target',
                'grade' => 'A',
                'scores' => ['latency' => 90, 'asr_quality' => 88, 'translation' => 86, 'tts' => 90, 'reliability' => 86],
                'notes' => 'Reference target row for board-level comparison; replace with measured adapter runs.',
            ],
            [
                'name' => 'Google Cloud speech stack',
                'kind' => 'reference_target',
                'grade' => 'A',
                'scores' => ['latency' => 84, 'asr_quality' => 88, 'translation' => 86, 'tts' => 84, 'reliability' => 88],
                'notes' => 'Reference target row for STT + Translate + TTS stack.',
            ],
            [
                'name' => 'Microsoft Azure speech translation',
                'kind' => 'reference_target',
                'grade' => 'A',
                'scores' => ['latency' => 84, 'asr_quality' => 86, 'translation' => 85, 'tts' => 86, 'reliability' => 88],
                'notes' => 'Reference target row for managed speech translation stack.',
            ],
            [
                'name' => 'Meta Seamless class',
                'kind' => 'reference_target',
                'grade' => 'B',
                'scores' => ['latency' => 78, 'asr_quality' => 84, 'translation' => 84, 'tts' => 78, 'reliability' => 76],
                'notes' => 'Reference target row for speech-to-speech research-class comparison.',
            ],
        ];
    }

    private function percentile(array $values, int $percentile): ?int
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $index = (int) ceil(($percentile / 100) * count($values)) - 1;
        return $values[max(0, min(count($values) - 1, $index))];
    }

    private function latencyScore(?int $p95Ms): ?int
    {
        if ($p95Ms === null) {
            return null;
        }

        return (int) max(0, min(100, round(100 - (($p95Ms - 1500) / 45))));
    }

    private function rateScore(?float $rate): ?int
    {
        return $rate === null ? null : (int) round(max(0, min(1, $rate)) * 100);
    }

    /**
     * @param  list<?int>  $scores
     */
    private function averageScore(array $scores): ?int
    {
        $values = array_values(array_filter($scores, fn (?int $score): bool => $score !== null));
        return $values === [] ? null : (int) round(array_sum($values) / count($values));
    }

    private function agentStatus(?int $score): string
    {
        return match (true) {
            $score === null => 'collecting',
            $score >= 85 => 'ready',
            $score >= 65 => 'watch',
            default => 'action',
        };
    }

    private function pct(?float $rate): string
    {
        return $rate === null ? '—' : round($rate * 100).'%';
    }

    private function ms(mixed $value): string
    {
        return is_numeric($value) ? ((string) ((int) $value)).' ms' : '—';
    }

    /**
     * @param  array<string, ?int>  $scores
     */
    private function grade(array $scores): string
    {
        $values = array_values(array_filter($scores, fn (?int $score): bool => $score !== null));
        if ($values === []) {
            return 'N/A';
        }

        $avg = array_sum($values) / count($values);
        return match (true) {
            $avg >= 85 => 'A',
            $avg >= 70 => 'B',
            $avg >= 55 => 'C',
            $avg >= 40 => 'D',
            default => 'E',
        };
    }
}

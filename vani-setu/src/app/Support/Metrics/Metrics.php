<?php

namespace App\Support\Metrics;

use App\Modules\SpeechToSpeech\Models\S2sSegment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class Metrics
{
    private const REDIS_META_KEY = 'ops_metrics:meta';

    private const REDIS_VALUES_KEY = 'ops_metrics:values';

    /**
     * @var array<string, string>
     */
    private static array $fallbackMeta = [];

    /**
     * @var array<string, float>
     */
    private static array $fallbackValues = [];

    /**
     * @var array<string, array{type:string, help:string, buckets?:list<float>}>
     */
    private const DEFINITIONS = [
        'vani_http_requests_total' => [
            'type' => 'counter',
            'help' => 'Total HTTP requests handled by the Laravel API.',
        ],
        'vani_http_request_duration_seconds' => [
            'type' => 'histogram',
            'help' => 'HTTP request latency in seconds.',
            'buckets' => [0.05, 0.1, 0.25, 0.5, 1, 2, 5, 10],
        ],
        'vani_dependency_requests_total' => [
            'type' => 'counter',
            'help' => 'Outbound dependency requests by dependency and outcome.',
        ],
        'vani_dependency_request_duration_seconds' => [
            'type' => 'histogram',
            'help' => 'Outbound dependency request latency in seconds.',
            'buckets' => [0.1, 0.25, 0.5, 1, 2, 5, 10, 20, 30],
        ],
        'vani_queue_jobs_total' => [
            'type' => 'counter',
            'help' => 'Queue jobs processed by queue and status.',
        ],
        'vani_queue_job_duration_seconds' => [
            'type' => 'histogram',
            'help' => 'Queue job execution duration in seconds.',
            'buckets' => [0.1, 0.25, 0.5, 1, 2, 5, 10, 30, 60, 120],
        ],
        'vani_queue_depth' => [
            'type' => 'gauge',
            'help' => 'Live queue depth by queue name.',
        ],
        'horizon_queue_depth' => [
            'type' => 'gauge',
            'help' => 'Live queue depth by queue name for Horizon-style alerting.',
        ],
        'vani_failed_jobs_total' => [
            'type' => 'gauge',
            'help' => 'Failed jobs currently retained in storage.',
        ],
        'vani_service_dependency_up' => [
            'type' => 'gauge',
            'help' => 'Dependency reachability as observed during metrics export.',
        ],
        'vani_s2s_segments_qa_state' => [
            'type' => 'gauge',
            'help' => 'Count of S2S segments per recheck verdict state (passed, drift, corrected, failed, skipped, pending) over the past 7 days. Alert when {state="failed"} grows past N or {state="drift"} ratio exceeds the SLO.',
        ],
        'vani_s2s_recheck_attempts' => [
            'type' => 'gauge',
            'help' => 'Total qa_attempts logged across S2S segments per verdict state over the past 7 days. Lets ops separate "fail once, retry succeeded" from "fail repeatedly".',
        ],
        'vani_s2s_latency_samples' => [
            'type' => 'gauge',
            'help' => 'Number of recent S2S segment latency samples in the live latency SLO window.',
        ],
        'vani_s2s_latency_ms' => [
            'type' => 'gauge',
            'help' => 'Recent S2S speak-to-hear latency in milliseconds by quantile.',
        ],
        'vani_s2s_stage_latency_ms' => [
            'type' => 'gauge',
            'help' => 'Recent S2S stage latency in milliseconds by stage and quantile.',
        ],
        'vani_s2s_latency_bottleneck' => [
            'type' => 'gauge',
            'help' => 'One-hot gauge marking the current worst S2S latency stage in the live latency SLO window.',
        ],
        'vani_s2s_client_errors_recent' => [
            'type' => 'gauge',
            'help' => 'Recent browser, S2S fetch, and live streaming errors captured from the public speech-to-speech clients by kind and HTTP status bucket.',
        ],
        'vani_s2s_client_errors_by_language_recent' => [
            'type' => 'gauge',
            'help' => 'Recent public speech-to-speech client errors by error kind and target language code, emitted only when clients report language_code.',
        ],
        'vani_s2s_client_error_threshold_breach' => [
            'type' => 'gauge',
            'help' => 'Observed recent S2S client-error count when a configured readiness threshold is breached, labelled by breach type, live kind, language, and threshold.',
        ],
        'vani_s2s_client_errors_total_recent' => [
            'type' => 'gauge',
            'help' => 'Total recent browser, S2S fetch, and live streaming errors captured from the public speech-to-speech clients.',
        ],
        'vani_s2s_master_ready_for_live' => [
            'type' => 'gauge',
            'help' => 'Master orchestrator readiness for live Rajya Sabha S2S operation, derived from latency, stability, transcript QA, and archive storage.',
        ],
        'vani_s2s_master_status' => [
            'type' => 'gauge',
            'help' => 'One-hot master orchestrator status derived from S2S health domains.',
        ],
        'vani_s2s_master_domain_status' => [
            'type' => 'gauge',
            'help' => 'One-hot status per master orchestrator domain: performance, stability, transcript_audio, and storage.',
        ],
        'vani_s2s_audio_archive_segments' => [
            'type' => 'gauge',
            'help' => 'S2S source-audio archive segment counts by archive state over the recent archive metrics window.',
        ],
        'vani_s2s_audio_archive_bytes' => [
            'type' => 'gauge',
            'help' => 'S2S source-audio archive byte footprint by kind: original, stored, saved, and pruned_released.',
        ],
        'vani_s2s_audio_archive_savings_ratio' => [
            'type' => 'gauge',
            'help' => 'S2S source-audio archive compression savings ratio over the recent archive metrics window.',
        ],
        'vani_s2s_audio_archive_compression_segments' => [
            'type' => 'gauge',
            'help' => 'S2S source-audio archive segment count by compression codec over the recent archive metrics window.',
        ],
    ];

    /**
     * @param  array<string, scalar|null>  $labels
     */
    public function counter(string $name, array $labels = [], float $increment = 1.0): void
    {
        $definition = $this->definition($name, 'counter');
        $this->storeSample($name, $definition['type'], $definition['help'], $labels, 'increment', $increment);
    }

    /**
     * @param  array<string, scalar|null>  $labels
     */
    public function gauge(string $name, array $labels = [], float $value = 0.0): void
    {
        $definition = $this->definition($name, 'gauge');
        $this->storeSample($name, $definition['type'], $definition['help'], $labels, 'set', $value);
    }

    /**
     * @param  array<string, scalar|null>  $labels
     */
    public function histogram(string $name, array $labels = [], float $value = 0.0): void
    {
        $definition = $this->definition($name, 'histogram');
        $labels = $this->normalizeLabels($labels);

        foreach ($definition['buckets'] ?? [] as $bucket) {
            if ($value <= $bucket) {
                $this->storeSample(
                    $name.'_bucket',
                    'histogram',
                    $definition['help'],
                    array_merge($labels, ['le' => $this->formatNumber($bucket)]),
                    'increment',
                    1.0,
                );
            }
        }

        $this->storeSample($name.'_bucket', 'histogram', $definition['help'], array_merge($labels, ['le' => '+Inf']), 'increment', 1.0);
        $this->storeSample($name.'_sum', 'histogram', $definition['help'], $labels, 'increment', $value);
        $this->storeSample($name.'_count', 'histogram', $definition['help'], $labels, 'increment', 1.0);
    }

    public function render(array $sections = ['app', 'queue', 'dependencies']): string
    {
        if (in_array('queue', $sections, true)) {
            $this->refreshQueueDepthMetrics();
            $this->refreshFailedJobsMetric();
        }

        if (in_array('dependencies', $sections, true)) {
            $this->refreshDependencyGauges();
        }

        if (in_array('s2s', $sections, true)) {
            $this->refreshS2sQaStateMetrics();
            $this->refreshS2sLatencyMetrics();
            $this->refreshS2sClientErrorMetrics();
            $this->refreshS2sMasterReadinessMetrics();
            $this->refreshS2sAudioArchiveMetrics();
        }

        $meta = $this->readMeta();
        $values = $this->readValues();
        ksort($meta);
        ksort($values);

        $lines = [];
        $printed = [];

        foreach ($meta as $sampleKey => $metadataJson) {
            $metadata = json_decode($metadataJson, true);
            if (! is_array($metadata)) {
                continue;
            }

            $name = (string) ($metadata['name'] ?? '');
            $type = (string) ($metadata['type'] ?? 'gauge');
            $help = (string) ($metadata['help'] ?? $name);
            $labels = is_array($metadata['labels'] ?? null) ? $metadata['labels'] : [];
            $value = $values[$sampleKey] ?? 0.0;

            if (! isset($printed[$name])) {
                $lines[] = '# HELP '.$name.' '.$this->escapeHelp($help);
                $lines[] = '# TYPE '.$name.' '.$this->prometheusType($name, $type);
                $printed[$name] = true;
            }

            $lines[] = $this->formatSample($name, $labels, $value);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<string, scalar|null>  $labels
     */
    private function storeSample(string $name, string $type, string $help, array $labels, string $operation, float $value): void
    {
        $labels = $this->normalizeLabels($labels);
        $sampleKey = $this->sampleKey($name, $labels);
        $metadata = json_encode([
            'name' => $name,
            'type' => $type,
            'help' => $help,
            'labels' => $labels,
        ], JSON_THROW_ON_ERROR);

        try {
            Redis::connection()->command('HSET', [self::REDIS_META_KEY, $sampleKey, $metadata]);
            if ($operation === 'set') {
                Redis::connection()->command('HSET', [self::REDIS_VALUES_KEY, $sampleKey, $value]);
            } else {
                Redis::connection()->command('HINCRBYFLOAT', [self::REDIS_VALUES_KEY, $sampleKey, $value]);
            }

            return;
        } catch (Throwable) {
            self::$fallbackMeta[$sampleKey] = $metadata;
            self::$fallbackValues[$sampleKey] = $operation === 'set'
                ? $value
                : (self::$fallbackValues[$sampleKey] ?? 0.0) + $value;
        }
    }

    /**
     * @return array{name:string, type:string, help:string, buckets?:list<float>}
     */
    private function definition(string $name, string $expectedType): array
    {
        $definition = self::DEFINITIONS[$name] ?? null;

        if ($definition === null) {
            return [
                'name' => $name,
                'type' => $expectedType,
                'help' => $name,
            ];
        }

        return array_merge(['name' => $name], $definition);
    }

    /**
     * @param  array<string, scalar|null>  $labels
     * @return array<string, string>
     */
    private function normalizeLabels(array $labels): array
    {
        $normalized = [];

        foreach ($labels as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $normalized[(string) $key] = (string) $value;
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function sampleKey(string $name, array $labels): string
    {
        return hash('sha256', $name.'|'.json_encode($labels, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, string>
     */
    private function readMeta(): array
    {
        try {
            $result = Redis::connection()->command('HGETALL', [self::REDIS_META_KEY]);
            if (is_array($result)) {
                return $result;
            }
        } catch (Throwable) {
        }

        return self::$fallbackMeta;
    }

    /**
     * @return array<string, float>
     */
    private function readValues(): array
    {
        try {
            $result = Redis::connection()->command('HGETALL', [self::REDIS_VALUES_KEY]);
            if (is_array($result)) {
                return array_map(static fn ($value): float => (float) $value, $result);
            }
        } catch (Throwable) {
        }

        return self::$fallbackValues;
    }

    private function refreshQueueDepthMetrics(): void
    {
        foreach ($this->queueNames() as $queue) {
            $depth = 0;

            try {
                $depth = (int) Redis::connection()->llen('queues:'.$queue);
            } catch (Throwable) {
                $depth = 0;
            }

            $labels = ['queue' => $queue];
            $this->gauge('vani_queue_depth', $labels, $depth);
            $this->gauge('horizon_queue_depth', $labels, $depth);
        }
    }

    private function refreshFailedJobsMetric(): void
    {
        $count = 0;

        try {
            $count = (int) DB::table('failed_jobs')->count();
        } catch (Throwable) {
            $count = 0;
        }

        $this->gauge('vani_failed_jobs_total', [], $count);
    }

    private function refreshDependencyGauges(): void
    {
        foreach ([
            'postgres' => static fn (): bool => DB::connection()->getPdo() !== null,
            'redis' => static fn (): bool => Redis::connection()->ping() !== false,
        ] as $service => $probe) {
            $up = 0;

            try {
                $up = $probe() ? 1 : 0;
            } catch (Throwable) {
                $up = 0;
            }

            $this->gauge('vani_service_dependency_up', ['service' => $service], $up);
        }
    }

    /**
     * @return list<string>
     */
    private function queueNames(): array
    {
        $queues = ['default', 'audit'];

        foreach (array_keys((array) config('horizon.waits', [])) as $waitKey) {
            $parts = explode(':', $waitKey, 2);
            if (isset($parts[1]) && $parts[1] !== '') {
                $queues[] = $parts[1];
            }
        }

        $queues = array_values(array_unique($queues));
        sort($queues);

        return $queues;
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function formatSample(string $name, array $labels, float $value): string
    {
        if ($labels === []) {
            return $name.' '.$this->formatNumber($value);
        }

        $pairs = [];
        foreach ($labels as $key => $labelValue) {
            $pairs[] = sprintf('%s="%s"', $key, $this->escapeLabel($labelValue));
        }

        return sprintf('%s{%s} %s', $name, implode(',', $pairs), $this->formatNumber($value));
    }

    private function prometheusType(string $name, string $type): string
    {
        if (str_ends_with($name, '_bucket') || str_ends_with($name, '_sum') || str_ends_with($name, '_count')) {
            return 'histogram';
        }

        return $type;
    }

    private function escapeHelp(string $value): string
    {
        return str_replace(["\\", "\n"], ["\\\\", "\\n"], $value);
    }

    private function escapeLabel(string $value): string
    {
        return str_replace(["\\", "\"", "\n"], ["\\\\", "\\\"", "\\n"], $value);
    }

    private function formatNumber(float $value): string
    {
        if (is_infinite($value)) {
            return $value > 0 ? '+Inf' : '-Inf';
        }

        if ((float) ((int) $value) === $value) {
            return (string) ((int) $value);
        }

        return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
    }

    /**
     * Roll up S2S segment recheck verdicts into Prometheus gauges so
     * ops can graph drift rates and alert on stuck-failed segments
     * without scraping the database directly. Scoped to the past 7
     * days to keep the query cheap on long-lived sessions tables.
     */
    private function refreshS2sQaStateMetrics(): void
    {
        try {
            $cutoff = now()->subDays(7);
            $rows = DB::table('s2s_segments')
                ->where('created_at', '>=', $cutoff)
                ->selectRaw('qa_state, COUNT(*) AS n, COALESCE(SUM(qa_attempts), 0) AS total_attempts')
                ->groupBy('qa_state')
                ->get();
        } catch (Throwable) {
            return;
        }

        // Always emit a row per known state so absent verdicts read
        // as 0 in Grafana rather than missing samples — keeps SLO
        // ratio queries from going NaN on cold tables.
        $known = ['pending', 'passed', 'drift', 'corrected', 'failed', 'skipped'];
        $byState = [];
        foreach ($rows as $row) {
            $state = (string) ($row->qa_state ?? 'pending');
            $byState[$state] = $row;
        }
        foreach ($known as $state) {
            $row = $byState[$state] ?? null;
            $this->gauge('vani_s2s_segments_qa_state', ['state' => $state], (float) ($row->n ?? 0));
            $this->gauge('vani_s2s_recheck_attempts', ['state' => $state], (float) ($row->total_attempts ?? 0));
        }
    }

    private function refreshS2sLatencyMetrics(): void
    {
        $windowMinutes = max(1, (int) config('services.s2s.latency_health_window_minutes', 30));
        $window = $windowMinutes.'m';

        try {
            $rows = DB::table('s2s_segments')
                ->where('created_at', '>=', now()->subMinutes($windowMinutes))
                ->orderByDesc('id')
                ->limit(250)
                ->get(['engine_meta']);
        } catch (Throwable) {
            return;
        }

        $latencies = [];
        $stageValues = [
            'first_byte' => [],
            'stt' => [],
            'translation' => [],
            'tts' => [],
        ];

        foreach ($rows as $row) {
            $meta = $this->decodeJsonObject($row->engine_meta ?? null);
            $latency = data_get($meta, 'dispatch.server_latency_ms');
            if (is_numeric($latency) && (int) $latency > 0) {
                $latencies[] = (int) $latency;
            }

            foreach ($this->s2sStageLatencyPaths() as $stage => $paths) {
                foreach ($paths as $path) {
                    $value = data_get($meta, $path);
                    if (is_numeric($value) && (int) $value > 0) {
                        $stageValues[$stage][] = (int) $value;
                        break;
                    }
                }
            }
        }

        $this->gauge('vani_s2s_latency_samples', ['window' => $window], count($latencies));
        foreach (['p50' => 50, 'p95' => 95] as $quantile => $percentile) {
            $this->gauge(
                'vani_s2s_latency_ms',
                ['quantile' => $quantile, 'window' => $window],
                (float) ($this->percentile($latencies, $percentile) ?? 0),
            );
        }

        $bottleneck = null;
        $worstP95 = null;
        foreach ($stageValues as $stage => $values) {
            $p95 = $this->percentile($values, 95);
            foreach (['p50' => 50, 'p95' => 95] as $quantile => $percentile) {
                $this->gauge(
                    'vani_s2s_stage_latency_ms',
                    ['stage' => $stage, 'quantile' => $quantile, 'window' => $window],
                    (float) ($this->percentile($values, $percentile) ?? 0),
                );
            }

            if ($p95 !== null && ($worstP95 === null || $p95 > $worstP95)) {
                $bottleneck = $stage;
                $worstP95 = $p95;
            }
        }

        foreach (array_keys($stageValues) as $stage) {
            $this->gauge(
                'vani_s2s_latency_bottleneck',
                ['stage' => $stage, 'window' => $window],
                $bottleneck === $stage ? 1.0 : 0.0,
            );
        }
    }

    private function refreshS2sClientErrorMetrics(): void
    {
        $windowMinutes = 30;
        $window = $windowMinutes.'m';

        try {
            $rows = DB::table('audit_logs')
                ->where('action', 's2s.client.error')
                ->where('created_at', '>=', now()->subMinutes($windowMinutes))
                ->orderByDesc('id')
                ->limit(500)
                ->get(['payload']);
        } catch (Throwable) {
            return;
        }

        $counts = [];
        $kindCounts = [];
        $languageCounts = [];
        $languageTotals = [];
        foreach ($rows as $row) {
            $payload = $this->decodeJsonObject($row->payload ?? null);
            $kind = $this->metricLabel((string) ($payload['kind'] ?? 'unknown'));
            $kindCounts[$kind] = ($kindCounts[$kind] ?? 0) + 1;
            $statusBucket = $this->clientErrorStatusBucket($payload['status'] ?? null);
            $key = $kind.'|'.$statusBucket;
            $counts[$key] = ($counts[$key] ?? 0) + 1;

            $languageCode = trim((string) ($payload['language_code'] ?? ''));
            if ($languageCode !== '') {
                $languageCode = $this->metricLabel($languageCode);
                $languageKey = $kind.'|'.$languageCode;
                $languageCounts[$languageKey] = ($languageCounts[$languageKey] ?? 0) + 1;
                $languageTotals[$languageCode] = ($languageTotals[$languageCode] ?? 0) + 1;
            }
        }

        $this->gauge('vani_s2s_client_errors_total_recent', ['window' => $window], $rows->count());
        foreach ($counts as $key => $count) {
            [$kind, $statusBucket] = explode('|', $key, 2);
            $this->gauge(
                'vani_s2s_client_errors_recent',
                ['kind' => $kind, 'status_bucket' => $statusBucket, 'window' => $window],
                (float) $count,
            );
        }

        foreach ($this->s2sClientErrorBaselineKinds() as $kind) {
            $this->gauge(
                'vani_s2s_client_errors_recent',
                ['kind' => $kind, 'status_bucket' => 'none', 'window' => $window],
                (float) ($counts[$kind.'|none'] ?? 0),
            );
        }

        foreach ($languageCounts as $key => $count) {
            [$kind, $languageCode] = explode('|', $key, 2);
            $this->gauge(
                'vani_s2s_client_errors_by_language_recent',
                ['kind' => $kind, 'language_code' => $languageCode, 'window' => $window],
                (float) $count,
            );
        }

        $this->refreshS2sClientErrorThresholdBreachMetrics($rows->count(), $kindCounts, $languageTotals, $window);
    }

    /**
     * @return list<string>
     */
    private function s2sClientErrorBaselineKinds(): array
    {
        return [
            'window_error',
            'unhandledrejection',
            'fetch_5xx',
            'fetch_exception',
            'audio_blocked',
            'language_error',
            'audio_error',
            'stream_error',
        ];
    }

    /**
     * @param  array<string, int>  $kindCounts
     * @param  array<string, int>  $languageCounts
     */
    private function refreshS2sClientErrorThresholdBreachMetrics(
        int $totalErrors,
        array $kindCounts,
        array $languageCounts,
        string $window,
    ): void {
        $totalThreshold = max(1, (int) config('services.s2s.client_error_degraded_threshold', 5));
        $liveKindThreshold = max(1, (int) config('services.s2s.client_live_kind_degraded_threshold', 3));
        $languageThreshold = max(1, (int) config('services.s2s.client_language_degraded_threshold', 3));

        $this->gauge(
            'vani_s2s_client_error_threshold_breach',
            ['type' => 'total', 'kind' => 'none', 'language_code' => 'none', 'threshold' => $totalThreshold, 'window' => $window],
            $totalErrors >= $totalThreshold ? (float) $totalErrors : 0.0,
        );

        foreach (['audio_blocked', 'language_error', 'audio_error', 'stream_error'] as $kind) {
            $count = (int) ($kindCounts[$kind] ?? 0);
            $this->gauge(
                'vani_s2s_client_error_threshold_breach',
                ['type' => 'live_kind', 'kind' => $kind, 'language_code' => 'none', 'threshold' => $liveKindThreshold, 'window' => $window],
                $count >= $liveKindThreshold ? (float) $count : 0.0,
            );
        }

        foreach ($languageCounts as $languageCode => $count) {
            if ($count < $languageThreshold) {
                continue;
            }

            $this->gauge(
                'vani_s2s_client_error_threshold_breach',
                ['type' => 'language', 'kind' => 'none', 'language_code' => $languageCode, 'threshold' => $languageThreshold, 'window' => $window],
                (float) $count,
            );
        }
    }

    /**
     * @param  iterable<int, mixed>  $payloads
     * @return array{max_live_kind_count: int, max_language_count: int}
     */
    private function clientErrorBreakdown(iterable $payloads): array
    {
        $liveKinds = ['audio_blocked', 'language_error', 'audio_error', 'stream_error'];
        $byLiveKind = [];
        $byLanguage = [];

        foreach ($payloads as $payload) {
            $payload = $this->decodeJsonObject($payload);
            $kind = $this->metricLabel((string) ($payload['kind'] ?? 'unknown'));
            if (in_array($kind, $liveKinds, true)) {
                $byLiveKind[$kind] = ($byLiveKind[$kind] ?? 0) + 1;
            }

            $languageCode = trim((string) ($payload['language_code'] ?? ''));
            if ($languageCode !== '') {
                $languageCode = $this->metricLabel($languageCode);
                $byLanguage[$languageCode] = ($byLanguage[$languageCode] ?? 0) + 1;
            }
        }

        return [
            'max_live_kind_count' => $byLiveKind === [] ? 0 : max($byLiveKind),
            'max_language_count' => $byLanguage === [] ? 0 : max($byLanguage),
        ];
    }

    private function refreshS2sMasterReadinessMetrics(): void
    {
        $windowMinutes = 30;
        $window = $windowMinutes.'m';
        $statuses = ['up', 'watch', 'collecting', 'degraded', 'down', 'unknown'];
        $domains = [
            'performance' => 'unknown',
            'stability' => 'unknown',
            'transcript_audio' => 'unknown',
            'storage' => 'unknown',
        ];

        try {
            $watchMs = max(1, (int) config('services.s2s.latency_p95_watch_ms', 3500));
            $degradedMs = max($watchMs, (int) config('services.s2s.latency_p95_degraded_ms', 6000));
            $latencies = DB::table('s2s_segments')
                ->where('created_at', '>=', now()->subMinutes($windowMinutes))
                ->orderByDesc('id')
                ->limit(250)
                ->pluck('engine_meta')
                ->map(fn (mixed $meta): mixed => data_get($this->decodeJsonObject($meta), 'dispatch.server_latency_ms'))
                ->filter(fn (mixed $value): bool => is_numeric($value) && (int) $value > 0)
                ->map(fn (mixed $value): int => (int) $value)
                ->values()
                ->all();
            $p95 = $this->percentile($latencies, 95);
            $domains['performance'] = $p95 === null
                ? 'collecting'
                : ($p95 >= $degradedMs ? 'degraded' : ($p95 >= $watchMs ? 'watch' : 'up'));
        } catch (Throwable) {
            $domains['performance'] = 'unknown';
        }

        try {
            $recentSegments = DB::table('s2s_segments')
                ->where('created_at', '>=', now()->subMinutes($windowMinutes))
                ->count();
            $degradedSegments = DB::table('s2s_segments')
                ->where('created_at', '>=', now()->subMinutes($windowMinutes))
                ->where('status', 'degraded')
                ->count();
            $providerErrors = DB::table('s2s_outputs')
                ->where('created_at', '>=', now()->subMinutes($windowMinutes))
                ->where('status', 'provider_error')
                ->count();
            $clientErrorPayloads = DB::table('audit_logs')
                ->where('action', 's2s.client.error')
                ->where('created_at', '>=', now()->subMinutes($windowMinutes))
                ->orderByDesc('id')
                ->limit(500)
                ->pluck('payload');
            $clientErrors = $clientErrorPayloads->count();
            $clientBreakdown = $this->clientErrorBreakdown($clientErrorPayloads);
            $errorRate = $recentSegments > 0 ? (($degradedSegments + $providerErrors) / $recentSegments) : 0.0;
            $serverStatus = $errorRate >= 0.25 ? 'degraded' : 'up';
            $clientErrorThreshold = max(1, (int) config('services.s2s.client_error_degraded_threshold', 5));
            $liveKindThreshold = max(1, (int) config('services.s2s.client_live_kind_degraded_threshold', 3));
            $languageThreshold = max(1, (int) config('services.s2s.client_language_degraded_threshold', 3));
            $clientStatus = (
                $clientErrors >= $clientErrorThreshold
                || $clientBreakdown['max_live_kind_count'] >= $liveKindThreshold
                || $clientBreakdown['max_language_count'] >= $languageThreshold
            )
                ? 'degraded'
                : ($clientErrors > 0 ? 'watch' : 'up');
            $domains['stability'] = $this->worstStatus([$serverStatus, $clientStatus]);
        } catch (Throwable) {
            $domains['stability'] = 'unknown';
        }

        try {
            $recentQa = DB::table('s2s_segments')
                ->where('created_at', '>=', now()->subHours(6))
                ->count();
            $failedQa = DB::table('s2s_segments')
                ->where('created_at', '>=', now()->subHours(6))
                ->where('qa_state', 'failed')
                ->count();
            $stalePending = DB::table('s2s_segments')
                ->where('created_at', '<=', now()->subMinutes(30))
                ->where('qa_state', 'pending')
                ->whereNotNull('source_audio_path')
                ->count();
            $failureRate = $recentQa > 0 ? ($failedQa / $recentQa) : 0.0;
            $domains['transcript_audio'] = ($failureRate >= 0.20 || $stalePending >= 10) ? 'degraded' : 'up';
        } catch (Throwable) {
            $domains['transcript_audio'] = 'unknown';
        }

        try {
            $retentionDays = (int) config('services.s2s.audio_archive_retention_days', 30);
            $staleArchivedAudio = $retentionDays > 0
                ? DB::table('s2s_segments')
                    ->join('s2s_sessions', 's2s_sessions.id', '=', 's2s_segments.session_id')
                    ->whereNotNull('s2s_segments.source_audio_path')
                    ->whereNotNull('s2s_sessions.finished_at')
                    ->where('s2s_sessions.finished_at', '<', now()->subDays($retentionDays))
                    ->count()
                : 0;
            $domains['storage'] = $staleArchivedAudio > (int) config('services.s2s.audio_archive_prune_batch', 500) ? 'degraded' : 'up';
        } catch (Throwable) {
            $domains['storage'] = 'unknown';
        }

        $masterStatus = $this->worstStatus(array_values($domains));
        $readyForLive = in_array($masterStatus, ['up', 'watch'], true) ? 1.0 : 0.0;
        $this->gauge('vani_s2s_master_ready_for_live', ['window' => $window], $readyForLive);
        foreach ($statuses as $status) {
            $this->gauge(
                'vani_s2s_master_status',
                ['status' => $status, 'window' => $window],
                $masterStatus === $status ? 1.0 : 0.0,
            );
        }

        foreach ($domains as $domain => $domainStatus) {
            foreach ($statuses as $status) {
                $this->gauge(
                    'vani_s2s_master_domain_status',
                    ['domain' => $domain, 'status' => $status, 'window' => $window],
                    $domainStatus === $status ? 1.0 : 0.0,
                );
            }
        }
    }

    private function refreshS2sAudioArchiveMetrics(): void
    {
        $windowDays = 7;
        $window = $windowDays.'d';

        try {
            $rows = DB::table('s2s_segments')
                ->where('created_at', '>=', now()->subDays($windowDays))
                ->where(function ($query): void {
                    $query->whereNotNull('source_audio_path')
                        ->orWhereNotNull('engine_meta');
                })
                ->orderByDesc('id')
                ->limit(2000)
                ->get(['source_audio_path', 'engine_meta']);
        } catch (Throwable) {
            return;
        }

        $segments = [
            'cataloged' => 0,
            'active' => 0,
            'pruned' => 0,
            'compressed' => 0,
        ];
        $compressionSegments = ['gzip' => 0, 'none' => 0];
        $bytes = [
            'original' => 0,
            'stored' => 0,
            'saved' => 0,
            'pruned_released' => 0,
        ];

        foreach ($rows as $row) {
            $meta = $this->decodeJsonObject($row->engine_meta ?? null);
            $input = data_get($meta, 'input_audio');
            if (! is_array($input)) {
                continue;
            }
            $segment = new S2sSegment();
            $segment->forceFill([
                'source_audio_path' => $row->source_audio_path,
                'engine_meta' => $meta,
            ]);

            $original = $this->positiveInt($input['size'] ?? $input['original_size_bytes'] ?? null);
            $stored = $this->positiveInt($input['stored_size'] ?? $input['stored_size_bytes'] ?? null);
            $pruned = $this->positiveInt($input['pruned_stored_size'] ?? null);
            $compression = $this->metricLabel((string) ($input['compression'] ?? 'none'));
            $compression = $compression !== 'unknown' && $compression !== '' ? $compression : 'none';
            $hasAudioRecord = $original > 0 || $stored > 0 || $pruned > 0 || $segment->hasSourceAudioLinkage();
            if (! $hasAudioRecord) {
                continue;
            }

            $segments['cataloged']++;
            if ($segment->hasActiveSourceAudio()) {
                $segments['active']++;
            }
            if ($segment->hasPrunedSourceAudioRecord()) {
                $segments['pruned']++;
            }
            if ($compression !== 'none') {
                $segments['compressed']++;
            }

            $compressionSegments[$compression] = ($compressionSegments[$compression] ?? 0) + 1;
            $bytes['original'] += $original;
            $bytes['stored'] += $stored;
            $bytes['saved'] += max(0, $original - $stored);
            $bytes['pruned_released'] += $pruned;
        }

        foreach ($segments as $state => $count) {
            $this->gauge('vani_s2s_audio_archive_segments', ['state' => $state, 'window' => $window], (float) $count);
        }
        foreach ($bytes as $kind => $value) {
            $this->gauge('vani_s2s_audio_archive_bytes', ['kind' => $kind, 'window' => $window], (float) $value);
        }
        foreach ($compressionSegments as $compression => $count) {
            $this->gauge('vani_s2s_audio_archive_compression_segments', ['compression' => $compression, 'window' => $window], (float) $count);
        }

        $this->gauge(
            'vani_s2s_audio_archive_savings_ratio',
            ['window' => $window],
            $bytes['original'] > 0 ? round($bytes['saved'] / $bytes['original'], 6) : 0.0,
        );
    }

    /**
     * @return array<string, list<string>>
     */
    private function s2sStageLatencyPaths(): array
    {
        return [
            'first_byte' => [
                'dispatch.response.first_byte_ms',
                'dispatch.response.firstByteMs',
                'dispatch.response.streaming.first_byte_ms',
                'dispatch.response.timings.first_byte_ms',
                'dispatch.response.latencies.first_byte_ms',
            ],
            'stt' => [
                'dispatch.response.stt_latency_ms',
                'dispatch.response.stt.latency_ms',
                'dispatch.response.timings.stt_ms',
                'dispatch.response.latencies.stt_ms',
                'dispatch.response.stage_latencies.stt_ms',
                'dispatch.response.stage_latency_ms.stt',
            ],
            'translation' => [
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
            'tts' => [
                'dispatch.response.tts_latency_ms',
                'dispatch.response.tts.latency_ms',
                'dispatch.response.timings.tts_ms',
                'dispatch.response.latencies.tts_ms',
                'dispatch.response.stage_latencies.tts_ms',
                'dispatch.response.stage_latency_ms.tts',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function metricLabel(string $value): string
    {
        $label = strtolower(trim($value));
        $label = preg_replace('/[^a-z0-9_:-]+/', '_', $label) ?: 'unknown';

        return trim($label, '_') ?: 'unknown';
    }

    private function clientErrorStatusBucket(mixed $status): string
    {
        if (! is_numeric($status)) {
            return 'none';
        }

        $status = (int) $status;
        if ($status >= 500) {
            return '5xx';
        }
        if ($status >= 400) {
            return '4xx';
        }
        if ($status > 0) {
            return 'other';
        }

        return 'none';
    }

    private function positiveInt(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }

    /**
     * @param  list<string>  $statuses
     */
    private function worstStatus(array $statuses): string
    {
        $rank = [
            'down' => 5,
            'degraded' => 4,
            'watch' => 3,
            'collecting' => 2,
            'unknown' => 1,
            'up' => 0,
        ];
        $worst = 'up';
        foreach ($statuses as $status) {
            $status = isset($rank[$status]) ? $status : 'unknown';
            if ($rank[$status] > $rank[$worst]) {
                $worst = $status;
            }
        }

        return $worst;
    }

    /**
     * @param  list<int>  $values
     */
    private function percentile(array $values, int $percentile): ?int
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $index = (int) ceil(($percentile / 100) * count($values)) - 1;

        return $values[max(0, min(count($values) - 1, $index))];
    }
}

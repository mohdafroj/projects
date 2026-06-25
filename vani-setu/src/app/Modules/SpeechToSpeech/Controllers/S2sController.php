<?php

namespace App\Modules\SpeechToSpeech\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\SpeechToSpeech\Models\S2sOutput;
use App\Modules\SpeechToSpeech\Models\S2sSegment;
use App\Modules\SpeechToSpeech\Models\S2sSession;
use App\Modules\SpeechToSpeech\Models\S2sVocabularyRule;
use App\Modules\SpeechToSpeech\Jobs\RecheckSessionJob;
use App\Modules\SpeechToSpeech\Services\Recheck\QaSummaryService;
use App\Modules\SpeechToSpeech\Services\S2sBenchmarkSummaryService;
use App\Modules\SpeechToSpeech\Services\S2sConfigRepository;
use App\Modules\SpeechToSpeech\Services\S2sAudioArchive;
use App\Modules\SpeechToSpeech\Services\S2sLanguageRegistry;
use App\Modules\SpeechToSpeech\Services\S2sVocabularyService;
use App\Modules\SpeechToSpeech\Services\SarvamSpeechPipeline;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class S2sController extends Controller
{
    public function __construct(
        private readonly S2sConfigRepository $config,
        private readonly S2sLanguageRegistry $languages,
        private readonly S2sVocabularyService $vocabulary,
        private readonly SarvamSpeechPipeline $pipeline,
        private readonly S2sAudioArchive $audioArchive,
    ) {}

    public function adminDashboard(): array
    {
        $runtime = $this->config->pipeline();

        return [
            'generated_at' => now()->toISOString(),
            'runtime' => $runtime,
            'languages' => $this->languages->all(),
            'vocabulary' => [
                'total' => S2sVocabularyRule::query()->count(),
                'active' => S2sVocabularyRule::query()->where('is_active', true)->count(),
                'latest' => S2sVocabularyRule::query()->latest('id')->limit(20)->get(),
            ],
            'sessions' => S2sSession::query()
                ->withCount(['segments', 'outputs'])
                ->latest('id')
                ->limit(10)
                ->get(),
        ];
    }

    public function publicDashboard(Request $request): array
    {
        $targetLanguage = (string) $request->query('target_language', 'hi-IN');

        return [
            'generated_at' => now()->toISOString(),
            'selected_target_language' => $targetLanguage,
            'languages' => $this->languages->all(),
            'live_sessions' => S2sSession::query()
                ->with(['segments' => fn ($query) => $query->latest('sequence_no')->limit(10), 'outputs' => fn ($query) => $query->where('language_code', $targetLanguage)])
                ->whereIn('status', ['live', 'processing'])
                ->latest('started_at')
                ->limit(5)
                ->get(),
            'recent_outputs' => S2sOutput::query()
                ->where('language_code', $targetLanguage)
                ->latest('id')
                ->limit(20)
                ->get(),
        ];
    }

    public function configShow(): array
    {
        return $this->config->pipeline();
    }

    public function configUpdate(Request $request, AuditLogger $audit): array
    {
        $sourceLanguages = ['auto', ...array_keys($this->languages->all())];
        $targetLanguages = array_keys($this->languages->all());
        $data = $request->validate([
            'announcement_prefix' => ['required', 'string', 'max:500'],
            'default_mode' => ['required', Rule::in(['live', 'upload'])],
            'default_listener_scope' => ['required', Rule::in(['inside_house', 'outside_house', 'hybrid'])],
            'default_input_source' => ['required', Rule::in(['microphone', 'line_in', 'rtsp', 'uploaded_file', 'external_stream'])],
            'default_source_language' => ['required', 'string', 'max:16', Rule::in($sourceLanguages)],
            'target_languages' => ['required', 'array', 'min:1'],
            'target_languages.*' => ['required', 'string', 'max:16', Rule::in($targetLanguages)],
            'fallback_chain' => ['required', 'array', 'min:1'],
            'archive' => ['required', 'array'],
            'latency_policy' => ['required', 'array'],
        ]);

        $data['target_languages'] = $this->languages->withAudibleFallback($data['target_languages']);

        $config = $this->config->updatePipeline($data, $request->user());
        $audit->log('s2s.config.updated', null, $config);

        return $config;
    }

    public function vocabularyIndex(): array
    {
        return [
            'items' => S2sVocabularyRule::query()->orderBy('priority')->orderBy('id')->get(),
        ];
    }

    public function vocabularyStore(Request $request, AuditLogger $audit): S2sVocabularyRule
    {
        $data = $request->validate([
            'rule_type' => ['required', Rule::in(S2sVocabularyRule::RULE_TYPES)],
            'language_code' => ['nullable', 'string', 'max:16'],
            'source_phrase' => ['required', 'string', 'max:255'],
            'replacement_text' => ['nullable', 'string', 'max:255'],
            'phonetic_hint' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $rule = S2sVocabularyRule::query()->create([
            ...$data,
            'priority' => $data['priority'] ?? 100,
            'is_active' => $data['is_active'] ?? true,
            'created_by_user_id' => $request->user()?->id,
        ]);

        $audit->log('s2s.vocabulary.created', $rule, $rule->toArray());

        return $rule;
    }

    public function vocabularyUpdate(Request $request, int $id, AuditLogger $audit): S2sVocabularyRule
    {
        $rule = S2sVocabularyRule::query()->findOrFail($id);
        $data = $request->validate([
            'rule_type' => ['sometimes', Rule::in(S2sVocabularyRule::RULE_TYPES)],
            'language_code' => ['nullable', 'string', 'max:16'],
            'source_phrase' => ['sometimes', 'string', 'max:255'],
            'replacement_text' => ['nullable', 'string', 'max:255'],
            'phonetic_hint' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $rule->fill($data)->save();
        $audit->log('s2s.vocabulary.updated', $rule, $rule->toArray());

        return $rule->fresh();
    }

    public function create(Request $request, AuditLogger $audit): array
    {
        $runtime = $this->config->pipeline();
        $sourceLanguages = ['auto', ...array_keys($this->languages->all())];
        $targetLanguages = array_keys($this->languages->all());
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'mode' => ['nullable', Rule::in(['live', 'upload'])],
            'input_source' => ['nullable', Rule::in(['microphone', 'line_in', 'rtsp', 'uploaded_file', 'external_stream'])],
            'listener_scope' => ['nullable', Rule::in(['inside_house', 'outside_house', 'hybrid'])],
            'source_lang' => ['nullable', 'string', 'max:16', Rule::in($sourceLanguages)],
            'source_text' => ['nullable', 'string'],
            'target_langs' => ['nullable', 'array'],
            'target_langs.*' => ['required_with:target_langs', 'string', 'max:16', Rule::in($targetLanguages)],
            'sitting_id' => ['nullable', 'integer'],
            'capture_device_id' => ['nullable', 'string', 'max:255'],
            'device_id' => ['nullable', 'string', 'max:255'],
            'audio' => ['nullable', 'file', 'max:25600', 'mimetypes:audio/webm,video/webm,audio/wav,audio/x-wav,audio/mpeg,audio/mp3,audio/mpeg3,audio/mp4,video/mp4,audio/ogg'],
        ]);

        $targetLangs = $this->languages->withAudibleFallback($data['target_langs'] ?? $runtime['target_languages']);
        $sourceLang = $data['source_lang'] ?? $runtime['default_source_language'];
        $audioMeta = [];

        $session = S2sSession::query()->create([
            'sitting_id' => $data['sitting_id'] ?? null,
            'started_by_user_id' => $request->user()?->id,
            'title' => $data['title'] ?? 'House Translation Session',
            'mode' => $data['mode'] ?? $runtime['default_mode'],
            'input_source' => $data['input_source'] ?? $runtime['default_input_source'],
            'listener_scope' => $data['listener_scope'] ?? $runtime['default_listener_scope'],
            'source_lang' => $sourceLang,
            'target_lang' => $targetLangs[0] ?? 'hi-IN',
            'available_target_langs' => $targetLangs,
            'audio_input_meta' => $audioMeta,
            'archive_meta' => ['segments' => 0],
            'fallback_meta' => ['chain' => $runtime['fallback_chain']],
            'announcement_text' => $runtime['announcement_prefix'],
            'status' => ($data['mode'] ?? $runtime['default_mode']) === 'live' ? 'live' : 'queued',
            'engine_meta' => [
                'provider' => 'sarvam',
                'stt_model' => config('services.sarvam.stt.model'),
                'translate_model' => config('services.sarvam.translate.model'),
                'tts_model' => config('services.sarvam.tts.model'),
            ],
            'started_at' => now(),
        ]);

        $hasInitialSegment = $request->hasFile('audio') || filled($data['source_text'] ?? null);
        if ($request->hasFile('audio') && ! $hasInitialSegment) {
            $audioMeta = $this->audioArchive->storeSourceUpload($request->file('audio'), $session, $request);
            $session->forceFill(['audio_input_meta' => $audioMeta])->save();
        }

        $audit->log('s2s.session.created', $session, [
            'mode' => $session->mode,
            'input_source' => $session->input_source,
            'target_languages' => $targetLangs,
        ]);

        if ($hasInitialSegment) {
            $this->ingestSegment($request, $session, $audit, 1);
            $session = $session->fresh(['segments.outputs', 'outputs']);
        }

        return $this->sessionPayload($session->fresh());
    }

    public function show(int $id): array
    {
        $session = S2sSession::query()
            ->with(['segments.outputs', 'outputs'])
            ->findOrFail($id);

        return $this->sessionPayload($session);
    }

    public function segments(int $id): array
    {
        $session = S2sSession::query()->findOrFail($id);

        return [
            'session_id' => $session->id,
            'segments' => $session->segments()->with('outputs')->get(),
        ];
    }

    public function storeSegment(Request $request, int $id, AuditLogger $audit): array
    {
        $session = S2sSession::query()->findOrFail($id);
        $sourceLanguages = ['auto', ...array_keys($this->languages->all())];
        $data = $request->validate([
            'sequence_no' => ['required', 'integer', 'min:1'],
            'start_ms' => ['nullable', 'integer', 'min:0'],
            'end_ms' => ['nullable', 'integer', 'min:0'],
            'overlap_ms' => ['nullable', 'integer', 'min:0'],
            'source_language' => ['nullable', 'string', 'max:16', Rule::in($sourceLanguages)],
            'source_text' => ['nullable', 'string'],
            'capture_device_id' => ['nullable', 'string', 'max:255'],
            'device_id' => ['nullable', 'string', 'max:255'],
            'audio' => ['nullable', 'file', 'max:25600', 'mimetypes:audio/webm,video/webm,audio/wav,audio/x-wav,audio/mpeg,audio/mp3,audio/mpeg3,audio/mp4,video/mp4,audio/ogg'],
        ]);
        $this->ensureSegmentHasInput($request);

        $result = $this->ingestSegment($request, $session, $audit, $data['sequence_no']);

        return [
            'session_id' => $session->id,
            'segment' => $result['segment'],
            'dispatch' => $result['dispatch'],
        ];
    }

    /**
     * Streaming variant of storeSegment. Returns text/event-stream so the
     * browser can play the first synthesised sentence while later ones are
     * still being generated by Sarvam TTS. Feature-flagged via
     * S2S_STREAMING_TTS; when disabled, 404s and the client falls back to
     * the regular batched route at /sessions/{id}/segments.
     */
    public function streamSegment(Request $request, int $id, AuditLogger $audit): StreamedResponse
    {
        if (! (bool) config('services.s2s.streaming_tts', env('S2S_STREAMING_TTS', false))) {
            abort(404, 'streaming TTS disabled');
        }
        $session = S2sSession::query()->findOrFail($id);
        $sourceLanguages = ['auto', ...array_keys($this->languages->all())];
        $data = $request->validate([
            'sequence_no' => ['required', 'integer', 'min:1'],
            'start_ms' => ['nullable', 'integer', 'min:0'],
            'end_ms' => ['nullable', 'integer', 'min:0'],
            'overlap_ms' => ['nullable', 'integer', 'min:0'],
            'source_language' => ['nullable', 'string', 'max:16', Rule::in($sourceLanguages)],
            'source_text' => ['nullable', 'string'],
            'capture_device_id' => ['nullable', 'string', 'max:255'],
            'device_id' => ['nullable', 'string', 'max:255'],
            'audio' => ['nullable', 'file', 'max:25600', 'mimetypes:audio/webm,video/webm,audio/wav,audio/x-wav,audio/mpeg,audio/mp3,audio/mpeg3,audio/mp4,video/mp4,audio/ogg'],
            // ASR pipeline overrides forwarded to ml-gateway via stages.stt.
            'stt_model' => ['nullable', 'string', Rule::in(['saaras:v3', 'saarika:v2.5'])],
            'stt_mode' => ['nullable', 'string', Rule::in(['codemix', 'transcribe'])],
            'stt_diarize' => ['nullable', 'boolean'],
            'stt_timestamps' => ['nullable', 'boolean'],
        ]);
        $this->ensureSegmentHasInput($request);

        // Stage the segment row first so the SSE frames downstream carry a
        // real session_id/segment_id (matches the batched contract). When
        // deferring the archive (Iter-21 latency), the heavy source-audio
        // write is skipped here and run after the stream completes — the
        // uploaded temp file stays valid for the whole streamed response, and
        // the live forward to ml-gateway uses the in-memory inline payload.
        $deferArchive = (bool) config('services.s2s.defer_source_archive', env('S2S_DEFER_SOURCE_ARCHIVE', true));
        $segment = $this->prepareSegmentRow($request, $session, $data['sequence_no'], $deferArchive);
        $deferredAudioFile = $deferArchive ? $request->file('audio') : null;

        $sttOverrides = array_filter([
            'model' => $data['stt_model'] ?? null,
            'mode' => $data['stt_mode'] ?? null,
            'with_diarization' => isset($data['stt_diarize']) ? (bool) $data['stt_diarize'] : null,
            'with_timestamps' => isset($data['stt_timestamps']) ? (bool) $data['stt_timestamps'] : null,
        ], static fn ($v) => $v !== null);

        $response = new StreamedResponse(function () use ($session, $segment, $audit, $sttOverrides, $request, $deferredAudioFile) {
            // Symfony StreamedResponse keeps PHP's default buffering off,
            // and we additionally flush after each frame so each Sarvam-
            // synthesised sentence reaches the browser as soon as
            // ml-gateway emits it.
            // Ephemeral: announce the segment with no stored path. Nothing about
            // this segment is persisted — no row, no source audio, no transcript.
            echo "event: archive\ndata: ".json_encode([
                'session_id' => $session->id,
                'segment_id' => $segment->id,
                'start_ms' => $segment->start_ms,
                'end_ms' => $segment->end_ms,
                'duration_ms' => max(0, (int) $segment->end_ms - (int) $segment->start_ms),
                'ephemeral' => true,
            ], JSON_UNESCAPED_UNICODE)."\n\n";
            @ob_flush();
            @flush();

            // Relay the translated audio from ml-gateway straight to the browser.
            // The frame callback proxies each SSE frame as it arrives; nothing is
            // accumulated, stored, or written back to the database.
            $this->pipeline->dispatchSegmentStreaming(
                $session,
                $segment,
                function (string $frame): void {
                    echo $frame;
                    @ob_flush();
                    @flush();
                },
                $sttOverrides
            );
        });
        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');
        return $response;
    }

    private function prepareSegmentRow(Request $request, S2sSession $session, int $sequence, bool $deferArchive = false): S2sSegment
    {
        $sourceLanguage = (string) ($request->input('source_language') ?: $request->input('source_lang') ?: $session->source_lang);
        $sourceText = (string) $request->input('source_text', '');
        $vocabulary = $this->vocabulary->apply($sourceText, $sourceLanguage);
        $audioFile = $request->file('audio');
        // The inline payload (in-memory base64) is what the live forward to
        // ml-gateway uses, so it's always computed. The archive write is the
        // heavy part — skipped here when deferring (run post-stream instead).
        $inlineAudio = $audioFile instanceof UploadedFile ? $this->inlineAudioPayload($audioFile) : null;

        // Ephemeral s2s: no segment row is persisted and no source audio is
        // archived. Build an in-memory, UNSAVED segment so the relay/pipeline
        // have a context object (session_id, source language, inline audio to
        // forward to ml-gateway). It is never written to the database.
        $segment = new S2sSegment();
        $segment->forceFill([
            'id' => $sequence,
            'session_id' => $session->id,
            'sequence_no' => $sequence,
            'start_ms' => (int) $request->input('start_ms', 0),
            'end_ms' => (int) $request->input('end_ms', 0),
            'source_language' => $sourceLanguage,
            'source_text' => $vocabulary['clean_text'],
            'source_audio_path' => null,
            'status' => 'processing',
            'translated_segments' => [],
            'engine_meta' => [
                'vocabulary_matches' => $vocabulary['matches'],
                'capture' => [
                    'overlap_ms' => (int) $request->input('overlap_ms', 0),
                ],
                'input_audio_inline' => $inlineAudio,
            ],
        ]);
        $segment->exists = false;

        return $segment;
    }

    /**
     * @return array{segment:S2sSegment, dispatch:array<string, mixed>}
     */
    private function ingestSegment(Request $request, S2sSession $session, AuditLogger $audit, int $sequence): array
    {
        $segment = $this->prepareSegmentRow($request, $session, $sequence);

        $dispatchStartedAt = microtime(true);
        $dispatch = $this->pipeline->dispatchSegment($session, $segment);
        $dispatch['server_latency_ms'] = (int) round((microtime(true) - $dispatchStartedAt) * 1000);
        $providerResponse = is_array($dispatch['response'] ?? null) ? $dispatch['response'] : [];
        $providerSourceText = $this->providerSourceText($providerResponse);
        $providerSourceLanguage = $this->providerSourceLanguage($providerResponse);

        $finalSourceLanguage = filled($providerSourceLanguage) ? (string) $providerSourceLanguage : $segment->source_language;
        $finalSourceText = $segment->source_text;
        $finalVocabularyMatches = (array) data_get($segment->engine_meta, 'vocabulary_matches', []);
        if (filled($providerSourceText)) {
            $providerVocabulary = $this->vocabulary->apply((string) $providerSourceText, $finalSourceLanguage);
            $finalSourceText = $providerVocabulary['clean_text'];
            $finalVocabularyMatches = array_values(array_merge($finalVocabularyMatches, $providerVocabulary['matches']));
        }

        $segment->forceFill([
            'status' => $this->dispatchFailed($dispatch) ? 'degraded' : 'processed',
            'source_text' => $finalSourceText,
            'source_language' => $finalSourceLanguage,
            'translated_segments' => $segment->outputs()->get()->mapWithKeys(fn (S2sOutput $output) => [
                $output->language_code => [
                    'status' => $output->status,
                    'text_output' => $output->text_output,
                    'audio_output_path' => $output->audio_output_path,
                ],
            ])->all(),
            'engine_meta' => array_merge(
                collect($segment->engine_meta ?? [])->except('input_audio_inline')->all(),
                ['vocabulary_matches' => $finalVocabularyMatches, 'dispatch' => $dispatch],
            ),
        ])->save();

        $session->forceFill([
            'status' => 'processing',
            'archive_meta' => array_merge($session->archive_meta ?? [], ['segments' => $session->segments()->count()]),
        ])->save();

        $auditLog = $audit->log('s2s.segment.ingested', $segment, [
            'session_id' => $session->id,
            'sequence_no' => $segment->sequence_no,
            'source_language' => $segment->source_language,
            'vocabulary_matches' => data_get($segment->engine_meta, 'vocabulary_matches', []),
            'dispatch_status' => $dispatch['status'],
        ]);

        $segment->forceFill(['audit_log_id' => $auditLog->id])->save();

        return ['segment' => $segment->fresh()->load('outputs'), 'dispatch' => $dispatch];
    }

    public function qaSummary(int $id, QaSummaryService $summary): array
    {
        $session = S2sSession::query()->findOrFail($id);
        return $summary->summarise($session);
    }

    public function benchmarkSummary(S2sBenchmarkSummaryService $summary): array
    {
        return $summary->summary();
    }

    public function finish(Request $request, int $id, AuditLogger $audit): array
    {
        $session = S2sSession::query()->findOrFail($id);
        $session->forceFill([
            'status' => 'finished',
            'finished_at' => now(),
            'archive_meta' => array_merge($session->archive_meta ?? [], [
                'finished_at' => now()->toISOString(),
                'segments' => $session->segments()->count(),
                'outputs' => $session->outputs()->count(),
            ]),
        ])->save();

        $audit->log('s2s.session.finished', $session, [
            'segments' => $session->segments()->count(),
            'outputs' => $session->outputs()->count(),
        ]);

        if ((bool) config('services.s2s_recheck.auto_dispatch', false)) {
            RecheckSessionJob::dispatch($session->id);
        }

        return $this->sessionPayload($session->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionPayload(S2sSession $session): array
    {
        $session->loadMissing(['segments.outputs', 'outputs']);

        return [
            ...$session->toArray(),
            'target_language_labels' => collect($session->available_target_langs ?: [])->mapWithKeys(
                fn (string $code) => [$code => $this->languages->label($code)]
            )->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $dispatch
     */
    private function dispatchFailed(array $dispatch): bool
    {
        $status = $dispatch['status'] ?? null;

        return $status === 'error' || (is_int($status) && $status >= 400);
    }

    /**
     * @param  array<string, mixed>  $providerResponse
     */
    private function providerSourceText(array $providerResponse): mixed
    {
        return $providerResponse['source_text']
            ?? $providerResponse['transcript']
            ?? $providerResponse['transcribed_text']
            ?? $providerResponse['stt_text']
            ?? data_get($providerResponse, 'stt.text')
            ?? data_get($providerResponse, 'stt.transcript')
            ?? data_get($providerResponse, 'asr.text')
            ?? data_get($providerResponse, 'asr.transcript')
            ?? null;
    }

    /**
     * @param  array<string, mixed>  $providerResponse
     */
    private function providerSourceLanguage(array $providerResponse): mixed
    {
        return $providerResponse['source_language']
            ?? $providerResponse['detected_language']
            ?? $providerResponse['detected_lang']
            ?? data_get($providerResponse, 'stt.language_code')
            ?? data_get($providerResponse, 'stt.language')
            ?? data_get($providerResponse, 'asr.language_code')
            ?? data_get($providerResponse, 'asr.language')
            ?? null;
    }

    /**
     * @throws ValidationException
     */
    private function ensureSegmentHasInput(Request $request): void
    {
        if ($request->hasFile('audio') || filled($request->input('source_text'))) {
            return;
        }

        throw ValidationException::withMessages([
            'source_text' => 'Record audio, upload a file, or type transcript text before sending a segment.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function inlineAudioPayload(UploadedFile $file): array
    {
        $mime = strtolower((string) $file->getMimeType());
        if (in_array($mime, ['audio/x-wav', 'audio/wave'], true)) {
            $mime = 'audio/wav';
        }

        return [
            'audio_base64' => base64_encode((string) file_get_contents($file->getRealPath())),
            'mime_type' => $mime,
            'original_name' => $file->getClientOriginalName() ?: 'segment.webm',
        ];
    }
}

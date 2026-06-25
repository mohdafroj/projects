<?php

namespace App\Http\Controllers;

use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Search\Models\StoredArtifact;
use App\Modules\SpeechToSpeech\Jobs\RecheckSessionJob;
use App\Modules\SpeechToSpeech\Models\S2sOutput;
use App\Modules\SpeechToSpeech\Models\S2sSegment;
use App\Modules\SpeechToSpeech\Models\S2sSession;
use App\Modules\SpeechToSpeech\Models\S2sVocabularyRule;
use App\Modules\SpeechToSpeech\Services\S2sAudioArchive;
use App\Modules\SpeechToSpeech\Services\S2sBenchmarkSummaryService;
use App\Modules\SpeechToSpeech\Services\S2sConfigRepository;
use App\Modules\SpeechToSpeech\Services\S2sGlossaryService;
use App\Modules\SpeechToSpeech\Services\S2sLanguageRegistry;
use App\Modules\SpeechToSpeech\Services\S2sVocabularyService;
use App\Modules\SpeechToSpeech\Services\SarvamSpeechPipeline;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SpeechToSpeechPageController extends PublicDesignController
{
    public function __construct(
        private readonly S2sConfigRepository $config,
        private readonly S2sLanguageRegistry $languages,
        private readonly S2sVocabularyService $vocabulary,
        private readonly SarvamSpeechPipeline $pipeline,
        private readonly S2sGlossaryService $glossary,
        private readonly S2sAudioArchive $audioArchive,
    ) {}

    public function admin(): View
    {
        return view('public.s2s-admin');
    }

    public function listener(Request $request): View
    {
        $requestedTargetLanguage = (string) $request->query('target_language', 'en-IN');
        $targetLanguage = $this->languages->resolve($requestedTargetLanguage, 'en-IN');
        $listenerTargetLanguages = $this->languages->withAudibleFallback([$targetLanguage], 'hi-IN');

        return $this->renderPublicPage('public.s2s-listener', [
            'pageTitle' => 'Vani Setu Listener',
            'languages' => $this->languages->all(),
            'targetLanguage' => $targetLanguage,
            'requestedTargetLanguage' => $requestedTargetLanguage,
            'listenerTargetLanguages' => $listenerTargetLanguages,
            'listenerFallbackApplied' => $listenerTargetLanguages !== [$targetLanguage],
            'sessions' => S2sSession::query()
                ->with([
                    'segments' => fn ($query) => $query->latest('sequence_no')->limit(8),
                    'outputs' => fn ($query) => $query->whereIn('language_code', $listenerTargetLanguages)->latest('id'),
                ])
                ->latest('started_at')
                ->limit(8)
                ->get(),
        ]);
    }

    public function updateRuntime(Request $request): RedirectResponse
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
        ]);

        $current = $this->config->pipeline();
        $data['target_languages'] = $this->languages->withAudibleFallback($data['target_languages']);

        $this->config->updatePipeline([
            ...$current,
            ...$data,
        ]);

        return redirect()->route('public.s2s.admin')->with('status', 'Runtime defaults updated.');
    }

    public function storeSession(Request $request, AuditLogger $audit): RedirectResponse|JsonResponse
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
            'target_langs' => ['nullable', 'array', 'min:1'],
            'target_langs.*' => ['required_with:target_langs', 'string', 'max:16', Rule::in($targetLanguages)],
            'tts_speaker' => ['nullable', 'string', 'max:32', 'regex:/^[a-z0-9_\-]+$/i'],
            'capture_device_id' => ['nullable', 'string', 'max:255'],
            'device_id' => ['nullable', 'string', 'max:255'],
            'audio' => ['nullable', 'file', 'max:25600', 'mimetypes:audio/webm,video/webm,audio/wav,audio/x-wav,audio/mpeg,audio/mp3,audio/mpeg3,audio/mp4,video/mp4,audio/ogg'],
        ]);

        $targetLangs = $this->languages->withAudibleFallback($data['target_langs'] ?? $runtime['target_languages']);
        $mode = $data['mode'] ?? $runtime['default_mode'];
        $ttsSpeaker = $data['tts_speaker'] ?? null;

        $session = S2sSession::query()->create([
            'title' => $data['title'] ?? 'House Translation Session',
            'mode' => $mode,
            'input_source' => $data['input_source'] ?? $runtime['default_input_source'],
            'listener_scope' => $data['listener_scope'] ?? $runtime['default_listener_scope'],
            'source_lang' => $data['source_lang'] ?? $runtime['default_source_language'],
            'target_lang' => $targetLangs[0] ?? 'en-IN',
            'available_target_langs' => $targetLangs,
            'audio_input_meta' => [],
            'archive_meta' => ['segments' => 0],
            'fallback_meta' => ['chain' => $runtime['fallback_chain']],
            'announcement_text' => $runtime['announcement_prefix'],
            'status' => $mode === 'live' ? 'live' : 'queued',
            'engine_meta' => [
                'provider' => 'sarvam',
                'stt_model' => config('services.sarvam.stt.model'),
                'translate_model' => config('services.sarvam.translate.model'),
                'tts_model' => config('services.sarvam.tts.model'),
                'tts_speaker' => $ttsSpeaker,
                'created_from' => 'public_web_console',
            ],
            'started_at' => now(),
        ]);

        $hasInitialSegment = $request->hasFile('audio') || filled($data['source_text'] ?? null);
        if ($request->hasFile('audio') && ! $hasInitialSegment) {
            $session->forceFill([
                'audio_input_meta' => $this->audioArchive->storeSourceUpload($request->file('audio'), $session, $request),
            ])->save();
        }

        $audit->log('s2s.session.created', $session, [
            'mode' => $session->mode,
            'input_source' => $session->input_source,
            'target_languages' => $targetLangs,
            'source' => 'public_web_console',
        ]);

        if ($hasInitialSegment) {
            $this->ingestSegment($request, $session, $audit, 1);
            $session = $session->fresh(['segments.outputs', 'outputs']);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'message' => $session->segments->isNotEmpty()
                    ? 'Speech-to-speech session opened and first segment translated.'
                    : 'Speech-to-speech session opened.',
                'session' => $this->sessionPayload($session),
            ], 201);
        }

        return redirect()
            ->route('public.s2s.admin', ['selected_session' => $session->id])
            ->with('status', 'Speech-to-speech session opened.');
    }

    public function storeSegment(Request $request, S2sSession $session, AuditLogger $audit): RedirectResponse|JsonResponse
    {
        $sourceLanguages = ['auto', ...array_keys($this->languages->all())];
        $data = $request->validate([
            'sequence_no' => ['nullable', 'integer', 'min:1'],
            'start_ms' => ['nullable', 'integer', 'min:0'],
            'end_ms' => ['nullable', 'integer', 'min:0'],
            'source_language' => ['nullable', 'string', 'max:16', Rule::in($sourceLanguages)],
            'source_text' => ['nullable', 'string'],
            'capture_device_id' => ['nullable', 'string', 'max:255'],
            'device_id' => ['nullable', 'string', 'max:255'],
            'audio' => ['nullable', 'file', 'max:25600', 'mimetypes:audio/webm,video/webm,audio/wav,audio/x-wav,audio/mpeg,audio/mp3,audio/mpeg3,audio/mp4,video/mp4,audio/ogg'],
        ]);
        $this->ensureSegmentHasInput($request);

        $result = $this->ingestSegment($request, $session, $audit, $data['sequence_no'] ?? null);
        $segment = $result['segment'];
        $dispatch = $result['dispatch'];

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'message' => 'Segment sent through the speech-to-speech pipeline.',
                'segment' => $segment->fresh('outputs'),
                'dispatch' => $dispatch,
                'session' => $this->sessionPayload($session->fresh(['segments.outputs', 'outputs'])),
            ]);
        }

        return redirect()
            ->route('public.s2s.admin', ['selected_session' => $session->id])
            ->with('status', 'Segment sent through the speech-to-speech pipeline.');
    }

    public function updateSessionTargets(Request $request, S2sSession $session): JsonResponse
    {
        $targetLanguages = array_keys($this->languages->all());
        $data = $request->validate([
            'target_langs' => ['required', 'array', 'min:1'],
            'target_langs.*' => ['required', 'string', 'max:16', Rule::in($targetLanguages)],
            'primary_target' => ['nullable', 'string', 'max:16', Rule::in($targetLanguages)],
            'tts_speaker' => ['nullable', 'string', 'max:32', 'regex:/^[a-z0-9_\-]+$/i'],
        ]);

        $targetLangs = $this->languages->withAudibleFallback($data['target_langs']);
        $primaryTarget = $data['primary_target'] ?? ($targetLangs[0] ?? 'en-IN');
        if (! in_array($primaryTarget, $targetLangs, true)) {
            array_unshift($targetLangs, $primaryTarget);
        }
        $targetLangs = $this->languages->withAudibleFallback($targetLangs);

        $engineMeta = array_merge($session->engine_meta ?? [], [
            'targets_updated_at' => now()->toISOString(),
            'targets_updated_from' => 'public_web_console',
        ]);
        if (array_key_exists('tts_speaker', $data)) {
            $engineMeta['tts_speaker'] = $data['tts_speaker'];
        }

        $session->forceFill([
            'target_lang' => $primaryTarget,
            'available_target_langs' => $targetLangs,
            'engine_meta' => $engineMeta,
        ])->save();

        return response()->json([
            'status' => 'ok',
            'message' => 'Output languages updated. New audio chunks will use the selected channels.',
            'session' => $this->sessionPayload($session->fresh(['segments.outputs', 'outputs'])),
        ]);
    }

    /**
     * @return array{segment:S2sSegment, dispatch:array<string, mixed>}
     */
    private function ingestSegment(Request $request, S2sSession $session, AuditLogger $audit, ?int $sequence = null): array
    {
        $sequence ??= ($session->segments()->max('sequence_no') + 1);
        $sourceLanguage = (string) ($request->input('source_language') ?: $request->input('source_lang') ?: $session->source_lang);
        $vocabulary = $this->vocabulary->apply((string) $request->input('source_text', ''), $sourceLanguage);
        $audioFile = $request->file('audio');
        $inlineAudio = $audioFile instanceof UploadedFile ? $this->inlineAudioPayload($audioFile) : null;

        // Ephemeral s2s: no segment row is persisted and no source audio is
        // archived. Build an in-memory, UNSAVED segment so the pipeline can read
        // its context and forward the inline audio to ml-gateway.
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
                'created_from' => 'public_web_console',
                'input_audio_inline' => $inlineAudio,
            ],
        ]);
        $segment->exists = false;

        $dispatchStartedAt = microtime(true);
        $dispatch = $this->pipeline->dispatchSegment($session, $segment);
        $serverLatencyMs = (int) round((microtime(true) - $dispatchStartedAt) * 1000);
        $dispatch["server_latency_ms"] = $serverLatencyMs;
        $providerResponse = is_array($dispatch['response'] ?? null) ? $dispatch['response'] : [];
        $providerSourceText = $providerResponse['source_text']
            ?? $providerResponse['transcript']
            ?? $providerResponse['transcribed_text']
            ?? $providerResponse['stt_text']
            ?? data_get($providerResponse, 'stt.text')
            ?? data_get($providerResponse, 'stt.transcript')
            ?? data_get($providerResponse, 'asr.text')
            ?? data_get($providerResponse, 'asr.transcript')
            ?? null;
        $providerSourceLanguage = $providerResponse['source_language']
            ?? $providerResponse['detected_language']
            ?? $providerResponse['detected_lang']
            ?? data_get($providerResponse, 'stt.language_code')
            ?? data_get($providerResponse, 'stt.language')
            ?? data_get($providerResponse, 'asr.language_code')
            ?? data_get($providerResponse, 'asr.language')
            ?? null;

        $finalSourceLanguage = filled($providerSourceLanguage) ? (string) $providerSourceLanguage : $segment->source_language;
        $finalSourceText = $segment->source_text;
        $finalVocabularyMatches = $vocabulary['matches'];
        if (filled($providerSourceText)) {
            $providerVocabulary = $this->vocabulary->apply((string) $providerSourceText, $finalSourceLanguage);
            $finalSourceText = $providerVocabulary['clean_text'];
            $finalVocabularyMatches = array_values(array_merge($finalVocabularyMatches, $providerVocabulary['matches']));
        }

        // Ephemeral: nothing is persisted. Update the in-memory segment for the
        // response shape only — no DB write, no source/translated text stored,
        // no audio path, no audit row. The translated audio rode the dispatch
        // response inline and was already relayed to the listener.
        $segment->forceFill([
            'status' => $this->dispatchFailed($dispatch) ? 'degraded' : 'processed',
            'source_language' => $finalSourceLanguage,
            'translated_segments' => [],
        ]);

        return ['segment' => $segment, 'dispatch' => $dispatch];
    }

    public function status(S2sSession $session): JsonResponse
    {
        $sequenceNo = request()->integer('sequence_no');
        $this->pipeline->refreshPendingOutputs($session, $sequenceNo > 0 ? $sequenceNo : null);
        $session->unsetRelation('segments');

        // Status route is polled by sarvam.jsx::pollS2SStatus on the batched
        // fallback path. The poller now starts at 500ms and backs off on
        // unchanged responses, so keep this payload slim enough for fast checks.
        // and re-fetched on every segment POST too. Use a slim payload that
        // omits the duplicated session-level outputs[] and the columns the
        // poller never reads (engine_meta, audio_input_meta, fallback_meta,
        // archive_meta beyond a segment count, output_meta, channel_name, etc).
        // The full `sessionPayload()` is still used for the segment POST / show
        // surfaces where admins want everything.
        $session->load([
            'segments' => fn ($query) => $query
                ->select([
                    'id', 'session_id', 'sequence_no', 'start_ms', 'end_ms',
                    'source_language', 'source_text', 'source_audio_path',
                    'status', 'engine_meta', 'qa_state', 'qa_corrected_text',
                ])
                ->when($sequenceNo > 0, fn ($query) => $query->where('sequence_no', $sequenceNo))
                ->orderBy('sequence_no'),
            'segments.outputs' => fn ($query) => $query
                ->select(['id', 'segment_id', 'language_code', 'status', 'text_output', 'audio_output_path', 'output_meta']),
        ]);

        return response()->json($this->statusPayload($session));
    }

    public function exportTranscriptTxt(S2sSession $session, Request $request): StreamedResponse
    {
        // By default export EVERY segment that has a transcript — the source
        // speech-to-text is the deliverable, and QA recheck is optional (often
        // off, leaving segments 'pending' forever). Gating on QA-approval here
        // silently dropped ~all transcribed text. Pass ?qa=approved to restrict
        // to the verified-only official record; each line is labelled with its
        // qa_state either way so verification status stays transparent.
        $approvedOnly = $request->query('qa') === 'approved';
        $session->load([
            'segments' => fn ($q) => $q->orderBy('sequence_no'),
        ]);
        $filename = 'vanisetu-transcript-session-'.$session->id.($approvedOnly ? '-approved' : '').'.txt';
        return response()->streamDownload(function () use ($session, $approvedOnly): void {
            $approvedCount = $session->segments
                ->filter(fn (S2sSegment $segment): bool => in_array($segment->qa_state, S2sSegment::QA_APPROVED_STATES, true))
                ->count();
            $included = $session->segments
                ->filter(function (S2sSegment $segment) use ($approvedOnly): bool {
                    if (trim((string) $segment->approved_transcript) === '') { return false; }
                    return $approvedOnly ? in_array($segment->qa_state, S2sSegment::QA_APPROVED_STATES, true) : true;
                })
                ->values();

            echo "Vanisetu Speech-to-Speech Transcript\n";
            echo "Session #{$session->id} · {$session->title}\n";
            echo "Started: ".optional($session->started_at)->toISOString()."\n";
            echo "Source language: {$session->source_lang}\n";
            echo "Scope: ".($approvedOnly ? 'QA-approved only' : 'all transcribed segments')."\n";
            echo "QA approved: {$approvedCount} / {$session->segments->count()} segments\n";
            echo str_repeat('=', 60)."\n\n";

            if ($included->isEmpty()) {
                echo $approvedOnly
                    ? "No QA-approved transcript segments are available yet.\n"
                    : "No transcribed segments are available yet.\n";
                return;
            }

            foreach ($included as $segment) {
                $ts = number_format(($segment->start_ms ?? 0) / 1000, 2);
                $lang = $segment->source_language ?: 'auto';
                $text = trim((string) $segment->approved_transcript);
                echo "[{$ts}s · {$lang} · qa:{$segment->qa_state}] {$text}\n\n";
            }
        }, $filename, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function exportTranscriptSrt(S2sSession $session, Request $request): StreamedResponse
    {
        // Default to all transcribed segments (see exportTranscriptTxt); pass
        // ?qa=approved for the verified-only subtitle track.
        $approvedOnly = $request->query('qa') === 'approved';
        $session->load([
            'segments' => fn ($q) => $approvedOnly ? $q->qaApproved()->orderBy('sequence_no') : $q->orderBy('sequence_no'),
        ]);
        $filename = 'vanisetu-transcript-session-'.$session->id.($approvedOnly ? '-approved' : '').'.srt';
        $format = function (int $ms): string {
            $h = intdiv($ms, 3_600_000);
            $rem = $ms % 3_600_000;
            $m = intdiv($rem, 60_000);
            $rem = $rem % 60_000;
            $s = intdiv($rem, 1000);
            $msPart = $rem % 1000;
            return sprintf('%02d:%02d:%02d,%03d', $h, $m, $s, $msPart);
        };
        return response()->streamDownload(function () use ($session, $format): void {
            $i = 1;
            foreach ($session->segments as $segment) {
                $text = trim((string) $segment->approved_transcript);
                if ($text === '') { continue; }
                $start = (int) ($segment->start_ms ?? (($segment->sequence_no - 1) * 2000));
                $end   = (int) ($segment->end_ms ?: ($start + 2000));
                echo "$i\n{$format($start)} --> {$format($end)}\n{$text}\n\n";
                $i++;
            }
        }, $filename, ['Content-Type' => 'application/x-subrip']);
    }

    public function exportTranscriptJson(S2sSession $session): JsonResponse
    {
        $session->load([
            'segments' => fn ($query) => $query->orderBy('sequence_no'),
            'segments.outputs' => fn ($query) => $query->orderBy('language_code'),
        ]);

        $segments = $session->segments
            ->sortBy('sequence_no')
            ->values();
        $textCursor = 0;
        $jsonSegments = $segments
            ->map(function (S2sSegment $segment) use (&$textCursor): array {
                $payload = $this->transcriptJsonSegment($segment, $textCursor);
                $textCursor += mb_strlen((string) ($payload['approved_transcript'] ?? $payload['source_text'] ?? '')) + 1;

                return $payload;
            })
            ->all();
        $approvedCount = $segments
            ->filter(fn (S2sSegment $segment): bool => in_array($segment->qa_state, S2sSegment::QA_APPROVED_STATES, true))
            ->count();

        $payload = [
            'schema' => 'vanisetu.s2s.transcript.v1',
            'generated_at' => now()->toISOString(),
            'session' => [
                'id' => $session->id,
                'title' => $session->title,
                'mode' => $session->mode,
                'input_source' => $session->input_source,
                'listener_scope' => $session->listener_scope,
                'source_lang' => $session->source_lang,
                'target_lang' => $session->target_lang,
                'available_target_langs' => $session->available_target_langs ?? [],
                'status' => $session->status,
                'started_at' => $session->started_at?->toISOString(),
                'finished_at' => $session->finished_at?->toISOString(),
            ],
            'qa_summary' => [
                'approved_segments' => $approvedCount,
                'total_segments' => $segments->count(),
                'omitted_from_plain_exports' => $segments->count() - $approvedCount,
                'approved_states' => S2sSegment::QA_APPROVED_STATES,
            ],
            'segments' => $jsonSegments,
        ];

        return response()->json($payload)
            ->header('Content-Disposition', 'attachment; filename="vanisetu-transcript-session-'.$session->id.'.json"')
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Render the operator-built transcript (entries assembled client-side
     * from the live phrase chunks) as PDF, DOCX, or ODT and return the
     * binary as an attachment. Unlike the segment-backed exports above,
     * this takes the rendered entries as-is from the JSX TranscriptPanel
     * so what the operator sees is what they get on paper — including
     * speaker labels picked from the member roster.
     */
    public function exportTranscriptDocument(Request $request)
    {
        $data = $request->validate([
            'format' => ['required', 'string', Rule::in(['pdf', 'docx', 'odt'])],
            'session_id' => ['nullable', 'integer'],
            'output_language' => ['nullable', 'string', 'max:16'],
            'output_language_label' => ['nullable', 'string', 'max:64'],
            'entries' => ['required', 'array', 'min:1', 'max:5000'],
            'entries.*.ts' => ['nullable', 'numeric', 'min:0'],
            'entries.*.ts_label' => ['nullable', 'string', 'max:32'],
            'entries.*.speaker' => ['nullable', 'string', 'max:160'],
            'entries.*.speaker_role' => ['nullable', 'string', 'max:80'],
            'entries.*.source' => ['nullable', 'string', 'max:8000'],
            'entries.*.translated' => ['nullable', 'string', 'max:8000'],
        ]);

        $format = $data['format'];
        $sessionId = $data['session_id'] ?? null;
        $outputLabel = (string) ($data['output_language_label'] ?? $data['output_language'] ?? '');
        $entries = collect($data['entries']);
        $title = 'Vanisetu Speech-to-Speech Transcript';
        $subtitle = trim(($sessionId ? 'Session #'.$sessionId : 'Live session').($outputLabel ? ' · Output: '.$outputLabel : ''));
        $stamp = now()->format('Y-m-d_H-i-s');
        $baseName = 'vanisetu-transcript-'.$stamp;

        if ($format === 'pdf') {
            return $this->renderTranscriptPdf($title, $subtitle, $entries, $baseName);
        }
        if ($format === 'docx') {
            return $this->renderTranscriptWord($title, $subtitle, $entries, $baseName, 'docx');
        }
        return $this->renderTranscriptWord($title, $subtitle, $entries, $baseName, 'odt');
    }

    private function renderTranscriptPdf(string $title, string $subtitle, \Illuminate\Support\Collection $entries, string $baseName)
    {
        // dompdf needs a tiny bit of HTML; we keep the styling inline so
        // the template renders identically without touching the Blade
        // layer. Devanagari / Indic scripts need Unicode font support;
        // dompdf's built-in DejaVu Sans handles Hindi/English/Latin
        // well enough for this generation. If a target language needs
        // a script DejaVu misses (Tamil/Telugu/Kannada/Bengali), the
        // glyphs will show as boxes until an Indic font is wired in.
        $html = view('s2s.transcript-export', [
            'title' => $title,
            'subtitle' => $subtitle,
            'entries' => $entries,
            'format' => 'pdf',
        ])->render();
        $dompdf = new \Dompdf\Dompdf([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
            'defaultFont' => 'DejaVu Sans',
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$baseName.'.pdf"',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function renderTranscriptWord(string $title, string $subtitle, \Illuminate\Support\Collection $entries, string $baseName, string $extension)
    {
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $phpWord->setDefaultFontName('DejaVu Sans');
        $phpWord->setDefaultFontSize(11);
        $section = $phpWord->addSection();
        $section->addText($title, ['bold' => true, 'size' => 16]);
        if ($subtitle !== '') {
            $section->addText($subtitle, ['size' => 10, 'color' => '666666']);
        }
        $section->addTextBreak(1);

        foreach ($entries as $entry) {
            $tsLabel = (string) ($entry['ts_label'] ?? '');
            $speaker = trim((string) ($entry['speaker'] ?? ''));
            $speakerRole = trim((string) ($entry['speaker_role'] ?? ''));
            $source = trim((string) ($entry['source'] ?? ''));
            $translated = trim((string) ($entry['translated'] ?? ''));

            $header = $section->addTextRun();
            if ($tsLabel !== '') {
                $header->addText('['.$tsLabel.'] ', ['color' => '888888', 'size' => 10]);
            }
            if ($speaker !== '') {
                $header->addText($speaker.($speakerRole !== '' ? ' · '.$speakerRole : ''), ['bold' => true, 'color' => 'D17A1F']);
            }
            if ($source !== '') {
                $section->addText($source, ['color' => '000000', 'size' => 11]);
            }
            if ($translated !== '') {
                $section->addText($translated, ['color' => '2A7A40', 'italic' => true, 'size' => 11]);
            }
            $section->addTextBreak(1);
        }

        $writerName = $extension === 'odt' ? 'ODText' : 'Word2007';
        $mime = $extension === 'odt'
            ? 'application/vnd.oasis.opendocument.text'
            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $tmp = tempnam(sys_get_temp_dir(), 'vanisetu-tx-');
        try {
            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, $writerName);
            $writer->save($tmp);
            $bytes = file_get_contents($tmp) ?: '';
        } finally {
            if (is_file($tmp)) { @unlink($tmp); }
        }
        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="'.$baseName.'.'.$extension.'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function downloadSegmentAudio(S2sSegment $segment): StreamedResponse|JsonResponse
    {
        $path = $segment->source_audio_path;
        $meta = is_array($segment->engine_meta) ? $segment->engine_meta : [];
        $disk = (string) data_get($meta, 'input_audio.disk', config('filesystems.reporter_audio_disk', 's2s_input_audio'));
        $name = (string) data_get($meta, 'input_audio.original_name', 'segment-'.$segment->id.'.wav');
        $mime = (string) data_get($meta, 'input_audio.mime_type', 'audio/wav');
        if (! filled($path) || ! \Illuminate\Support\Facades\Storage::disk($disk)->exists($path)) {
            return response()->json(['error' => 'audio_missing'], 404);
        }
        $audio = $this->audioArchive->audioBytes($segment);
        if ($audio === null) {
            return response()->json([
                'error' => 'audio_unreadable',
                'message' => 'Stored segment audio exists but could not be decoded for replay.',
            ], 422);
        }
        return response()->streamDownload(function () use ($audio): void {
            echo $audio;
        }, $name, ['Content-Type' => $mime]);
    }

    public function correctSegmentTranscript(Request $request, S2sSegment $segment, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'corrected_text' => ['required', 'string', 'max:20000'],
        ]);

        $previous = [
            'qa_state' => $segment->qa_state,
            'qa_corrected_text' => $segment->qa_corrected_text,
        ];
        $corrected = trim($data['corrected_text']);
        if ($corrected === '') {
            throw ValidationException::withMessages([
                'corrected_text' => 'Correction text cannot be empty.',
            ]);
        }

        $segment->forceFill([
            'qa_state' => 'corrected',
            'qa_score' => 1.0,
            'qa_corrected_text' => $corrected,
            'qa_checked_at' => now(),
            'qa_engine_meta' => array_merge($segment->qa_engine_meta ?? [], [
                'manual_correction' => [
                    'source' => 'public_web_console',
                    'corrected_at' => now()->toISOString(),
                ],
            ]),
        ])->save();

        $audit->log('s2s.transcript.corrected', $segment, [
            'session_id' => $segment->session_id,
            'sequence_no' => $segment->sequence_no,
            'previous' => $previous,
            'corrected_text_length' => mb_strlen($corrected),
            'source_audio_path' => $segment->source_audio_path,
            'start_ms' => $segment->start_ms,
            'end_ms' => $segment->end_ms,
        ]);

        return response()->json([
            'status' => 'ok',
            'segment' => [
                'id' => $segment->id,
                'sequence_no' => $segment->sequence_no,
                'source_text' => $segment->source_text,
                'qa_state' => $segment->qa_state,
                'qa_corrected_text' => $segment->qa_corrected_text,
                'approved_transcript' => $segment->approved_transcript,
                'source_audio' => [
                    'segment_id' => $segment->id,
                    'download_url' => filled($segment->source_audio_path)
                        ? route('public.s2s.segments.audio', ['segment' => $segment->id])
                        : null,
                    'start_ms' => $segment->start_ms,
                    'end_ms' => $segment->end_ms,
                ],
            ],
        ]);
    }

    public function providersHealth(): JsonResponse
    {
        // Proxy ml-gateway's snapshot to the browser. ml-gateway accumulates
        // per-provider state from real traffic, so this is free — no extra
        // Sarvam calls per badge poll.
        $base = (string) config('services.ml_gateway.url', 'http://ml-gateway:8000');
        $localProviders = $this->localHealthProviders();
        try {
            $response = Http::timeout(3)->get(rtrim($base, '/').'/v1/providers/health');
            if ($response->successful()) {
                $payload = $response->json() ?? [];
                $providers = $this->withOrchestratorHealth(array_merge($localProviders, is_array($payload['providers'] ?? null) ? $payload['providers'] : []));
                $payload['providers'] = $providers;
                $payload['application'] = array_intersect_key($providers, $localProviders + ['master_orchestrator' => true]);

                return response()->json($payload, 200)
                    ->header('Cache-Control', 'no-store');
            }
            $providers = $this->withOrchestratorHealth($localProviders);

            return response()->json(['providers' => $providers, 'application' => $providers, 'error' => 'ml_gateway_'.$response->status()], 200)
                ->header('Cache-Control', 'no-store');
        } catch (\Throwable $exception) {
            $providers = $this->withOrchestratorHealth($localProviders);

            return response()->json(['providers' => $providers, 'application' => $providers, 'error' => $exception->getMessage()], 200)
                ->header('Cache-Control', 'no-store');
        }
    }

    public function reportClientError(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['nullable', 'string', 'max:64'],
            'message' => ['nullable', 'string', 'max:1000'],
            'source' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'integer', 'min:0', 'max:599'],
            'line' => ['nullable', 'integer', 'min:0'],
            'column' => ['nullable', 'integer', 'min:0'],
            'stack' => ['nullable', 'string', 'max:8000'],
            'session_id' => ['nullable', 'integer', 'min:1'],
            'chunk_id' => ['nullable', 'integer', 'min:1'],
            'language_code' => ['nullable', 'string', 'max:16'],
        ]);

        $stack = isset($data['stack']) ? substr((string) $data['stack'], 0, 4000) : null;

        $audit->log('s2s.client.error', null, [
            'kind' => $data['kind'] ?? 'browser',
            'message' => $data['message'] ?? null,
            'source' => $data['source'] ?? null,
            'url' => $data['url'] ?? null,
            'status' => $data['status'] ?? null,
            'line' => $data['line'] ?? null,
            'column' => $data['column'] ?? null,
            'stack' => $stack !== '' ? $stack : null,
            'session_id' => $data['session_id'] ?? null,
            'chunk_id' => $data['chunk_id'] ?? null,
            'language_code' => $data['language_code'] ?? null,
            'user_agent' => (string) $request->userAgent(),
        ]);

        return response()->json(['status' => 'ok'])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * @param  iterable<int, object>  $rows
     * @return array{by_kind: list<array{kind: string, count: int}>, by_language: list<array{language_code: string, count: int}>}
     */
    private function clientErrorBreakdown(iterable $rows): array
    {
        $byKind = [];
        $byLanguage = [];

        foreach ($rows as $row) {
            $payload = is_array($row->payload ?? null) ? $row->payload : [];
            $kind = trim((string) ($payload['kind'] ?? 'unknown')) ?: 'unknown';
            $byKind[$kind] = ($byKind[$kind] ?? 0) + 1;

            $languageCode = trim((string) ($payload['language_code'] ?? ''));
            if ($languageCode !== '') {
                $byLanguage[$languageCode] = ($byLanguage[$languageCode] ?? 0) + 1;
            }
        }

        arsort($byKind);
        arsort($byLanguage);

        return [
            'by_kind' => $this->counterRows($byKind, 'kind'),
            'by_language' => $this->counterRows($byLanguage, 'language_code'),
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array<string, int|string>>
     */
    private function counterRows(array $counts, string $label): array
    {
        $rows = [];
        foreach (array_slice($counts, 0, 8, true) as $value => $count) {
            $rows[] = [$label => $value, 'count' => (int) $count];
        }

        return $rows;
    }

    /**
     * @param  array{by_kind: list<array{kind: string, count: int}>, by_language: list<array{language_code: string, count: int}>}  $breakdown
     */
    private function clientErrorHealthStatus(int $recentClientErrors, array $breakdown): string
    {
        if ($this->clientErrorThresholdBreaches($recentClientErrors, $breakdown) !== []) {
            return 'degraded';
        }

        return $recentClientErrors > 0 ? 'watch' : 'up';
    }

    /**
     * @param  array{by_kind: list<array{kind: string, count: int}>, by_language: list<array{language_code: string, count: int}>}  $breakdown
     * @return list<array<string, int|string>>
     */
    private function clientErrorThresholdBreaches(int $recentClientErrors, array $breakdown): array
    {
        $thresholds = $this->clientErrorHealthThresholds();
        $breaches = [];

        if ($recentClientErrors >= $thresholds['total_degraded']) {
            $breaches[] = [
                'type' => 'total',
                'count' => $recentClientErrors,
                'threshold' => $thresholds['total_degraded'],
            ];
        }

        $liveKinds = ['audio_blocked', 'language_error', 'audio_error', 'stream_error'];
        foreach ($breakdown['by_kind'] as $row) {
            $kind = (string) ($row['kind'] ?? '');
            $count = (int) ($row['count'] ?? 0);
            if (in_array($kind, $liveKinds, true) && $count >= $thresholds['live_kind_degraded']) {
                $breaches[] = [
                    'type' => 'live_kind',
                    'kind' => $kind,
                    'count' => $count,
                    'threshold' => $thresholds['live_kind_degraded'],
                ];
            }
        }

        foreach ($breakdown['by_language'] as $row) {
            $languageCode = (string) ($row['language_code'] ?? '');
            $count = (int) ($row['count'] ?? 0);
            if ($languageCode !== '' && $count >= $thresholds['language_degraded']) {
                $breaches[] = [
                    'type' => 'language',
                    'language_code' => $languageCode,
                    'count' => $count,
                    'threshold' => $thresholds['language_degraded'],
                ];
            }
        }

        return array_slice($breaches, 0, 8);
    }

    /**
     * @return array{total_degraded: int, live_kind_degraded: int, language_degraded: int}
     */
    private function clientErrorHealthThresholds(): array
    {
        return [
            'total_degraded' => max(1, (int) config('services.s2s.client_error_degraded_threshold', 5)),
            'live_kind_degraded' => max(1, (int) config('services.s2s.client_live_kind_degraded_threshold', 3)),
            'language_degraded' => max(1, (int) config('services.s2s.client_language_degraded_threshold', 3)),
        ];
    }

    /**
     * @param  array<string, int|string>|null  $breach
     */
    private function clientErrorBreachSignal(?array $breach): string
    {
        if ($breach === null) {
            return '';
        }

        $label = match ($breach['type'] ?? '') {
            'live_kind' => (string) ($breach['kind'] ?? 'live_kind'),
            'language' => (string) ($breach['language_code'] ?? 'language'),
            default => (string) ($breach['type'] ?? 'client'),
        };

        return ' · breach '.$label.' '.($breach['count'] ?? 0).'/'.($breach['threshold'] ?? 0);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function localHealthProviders(): array
    {
        $providers = [
            'vani_setu_app' => [
                'status' => 'up',
                'last_error' => null,
                'checked_at' => now()->toISOString(),
            ],
        ];

        try {
            DB::select('select 1');
            $providers['s2s_db'] = ['status' => 'up', 'last_error' => null];
        } catch (\Throwable $exception) {
            $providers['s2s_db'] = ['status' => 'down', 'last_error' => $exception->getMessage()];
        }

        $disk = (string) config('filesystems.reporter_audio_disk', 's2s_input_audio');
        try {
            Storage::disk($disk);
            $sourceAudioArtifacts = StoredArtifact::query()
                ->where('source_module', 'speech_to_speech')
                ->whereJsonContains('tags', 'source-audio');
            $catalogedSourceAudio = (clone $sourceAudioArtifacts)->count();
            $activeCatalogedSourceAudio = (clone $sourceAudioArtifacts)
                ->whereNotNull('storage_path')
                ->count();
            $prunedCatalogedSourceAudio = (clone $sourceAudioArtifacts)
                ->whereJsonContains('tags', 'pruned')
                ->count();
            $retentionDays = (int) config('services.s2s.audio_archive_retention_days', 30);
            $staleArchivedAudio = $retentionDays > 0
                ? S2sSegment::query()
                    ->whereNotNull('source_audio_path')
                    ->whereHas('session', fn ($query) => $query
                        ->whereNotNull('finished_at')
                        ->where('finished_at', '<', now()->subDays($retentionDays)))
                    ->count()
                : 0;
            $providers['audio_archive'] = [
                'status' => $staleArchivedAudio > (int) config('services.s2s.audio_archive_prune_batch', 500) ? 'degraded' : 'up',
                'disk' => $disk,
                'retention_days' => $retentionDays,
                'prune_enabled' => $retentionDays > 0,
                'stale_finished_segments' => $staleArchivedAudio,
                'cataloged_source_audio' => $catalogedSourceAudio,
                'active_cataloged_source_audio' => $activeCatalogedSourceAudio,
                'pruned_cataloged_source_audio' => $prunedCatalogedSourceAudio,
                'last_error' => null,
            ];
        } catch (\Throwable $exception) {
            $providers['audio_archive'] = ['status' => 'down', 'disk' => $disk, 'last_error' => $exception->getMessage()];
        }

        try {
            $recentSegments = S2sSegment::query()->where('created_at', '>=', now()->subMinutes(30))->count();
            $degradedSegments = S2sSegment::query()
                ->where('created_at', '>=', now()->subMinutes(30))
                ->where('status', 'degraded')
                ->count();
            $providerErrors = S2sOutput::query()
                ->where('created_at', '>=', now()->subMinutes(30))
                ->where('status', 'provider_error')
                ->count();
            $errorRate = $recentSegments > 0 ? round(($degradedSegments + $providerErrors) / $recentSegments, 4) : 0.0;
            $providers['s2s_error_rate'] = [
                'status' => $errorRate >= 0.25 ? 'degraded' : 'up',
                'recent_segments' => $recentSegments,
                'degraded_segments' => $degradedSegments,
                'provider_errors' => $providerErrors,
                'error_rate' => $errorRate,
                'window_minutes' => 30,
            ];
        } catch (\Throwable $exception) {
            $providers['s2s_error_rate'] = ['status' => 'unknown', 'last_error' => $exception->getMessage()];
        }

        try {
            $clientErrorWindowMinutes = 30;
            $recentClientErrors = AuditLog::query()
                ->where('action', 's2s.client.error')
                ->where('created_at', '>=', now()->subMinutes($clientErrorWindowMinutes))
                ->count();
            $latestClientError = AuditLog::query()
                ->where('action', 's2s.client.error')
                ->where('created_at', '>=', now()->subMinutes($clientErrorWindowMinutes))
                ->latest('id')
                ->first(['payload', 'created_at']);
            $recentClientErrorRows = AuditLog::query()
                ->where('action', 's2s.client.error')
                ->where('created_at', '>=', now()->subMinutes($clientErrorWindowMinutes))
                ->latest('id')
                ->limit(500)
                ->get(['payload']);
            $clientErrorBreakdown = $this->clientErrorBreakdown($recentClientErrorRows);
            $providers['s2s_client_errors'] = [
                'status' => $this->clientErrorHealthStatus($recentClientErrors, $clientErrorBreakdown),
                'recent_errors' => $recentClientErrors,
                'window_minutes' => $clientErrorWindowMinutes,
                'thresholds' => $this->clientErrorHealthThresholds(),
                'threshold_breaches' => $this->clientErrorThresholdBreaches($recentClientErrors, $clientErrorBreakdown),
                'breakdown_sampled' => $recentClientErrors > $recentClientErrorRows->count(),
                'by_kind' => $clientErrorBreakdown['by_kind'],
                'by_language' => $clientErrorBreakdown['by_language'],
                'latest_error' => $latestClientError ? [
                    'kind' => $latestClientError->payload['kind'] ?? null,
                    'message' => $latestClientError->payload['message'] ?? null,
                    'url' => $latestClientError->payload['url'] ?? null,
                    'status' => $latestClientError->payload['status'] ?? null,
                    'language_code' => $latestClientError->payload['language_code'] ?? null,
                    'chunk_id' => $latestClientError->payload['chunk_id'] ?? null,
                    'created_at' => $latestClientError->created_at?->toISOString(),
                ] : null,
            ];
        } catch (\Throwable $exception) {
            $providers['s2s_client_errors'] = ['status' => 'unknown', 'last_error' => $exception->getMessage()];
        }

        try {
            $latencyWindowMinutes = max(1, (int) config('services.s2s.latency_health_window_minutes', 30));
            $watchMs = max(1, (int) config('services.s2s.latency_p95_watch_ms', 3500));
            $degradedMs = max($watchMs, (int) config('services.s2s.latency_p95_degraded_ms', 6000));
            $recentLatencySegments = S2sSegment::query()
                ->where('created_at', '>=', now()->subMinutes($latencyWindowMinutes))
                ->latest('id')
                ->limit(250)
                ->get(['id', 'engine_meta']);
            $latencies = $recentLatencySegments
                ->map(fn (S2sSegment $segment): mixed => data_get($segment->engine_meta, 'dispatch.server_latency_ms'))
                ->filter(fn (mixed $value): bool => is_numeric($value) && (int) $value > 0)
                ->map(fn (mixed $value): int => (int) $value)
                ->values()
                ->all();
            $stageLatency = $this->stageLatencySummary($recentLatencySegments->all());
            $p50Latency = $this->percentile($latencies, 50);
            $p95Latency = $this->percentile($latencies, 95);
            $latencyStatus = 'collecting';
            if ($p95Latency !== null) {
                $latencyStatus = $p95Latency >= $degradedMs
                    ? 'degraded'
                    : ($p95Latency >= $watchMs ? 'watch' : 'up');
            }
            $providers['s2s_latency_slo'] = [
                'status' => $latencyStatus,
                'samples' => count($latencies),
                'p50_ms' => $p50Latency,
                'p95_ms' => $p95Latency,
                'stage_latency_ms' => $stageLatency,
                'bottleneck_stage' => $this->bottleneckStage($stageLatency),
                'watch_ms' => $watchMs,
                'degraded_ms' => $degradedMs,
                'window_minutes' => $latencyWindowMinutes,
            ];
        } catch (\Throwable $exception) {
            $providers['s2s_latency_slo'] = ['status' => 'unknown', 'last_error' => $exception->getMessage()];
        }

        try {
            $recentQa = S2sSegment::query()
                ->where('created_at', '>=', now()->subHours(6))
                ->count();
            $failedQa = S2sSegment::query()
                ->where('created_at', '>=', now()->subHours(6))
                ->where('qa_state', 'failed')
                ->count();
            $stalePending = S2sSegment::query()
                ->where('created_at', '<=', now()->subMinutes(30))
                ->where('qa_state', 'pending')
                ->whereNotNull('source_audio_path')
                ->count();
            $qaFailureRate = $recentQa > 0 ? round($failedQa / $recentQa, 4) : 0.0;
            $providers['s2s_qa_recheck'] = [
                'status' => ($qaFailureRate >= 0.20 || $stalePending >= 10) ? 'degraded' : 'up',
                'recent_segments' => $recentQa,
                'failed_segments' => $failedQa,
                'stale_pending_segments' => $stalePending,
                'failure_rate' => $qaFailureRate,
                'window_hours' => 6,
                'stale_after_minutes' => 30,
            ];
        } catch (\Throwable $exception) {
            $providers['s2s_qa_recheck'] = ['status' => 'unknown', 'last_error' => $exception->getMessage()];
        }

        if (collect($providers)->contains(fn (array $provider): bool => ($provider['status'] ?? null) === 'down')) {
            $providers['vani_setu_app']['status'] = 'down';
        } elseif (collect($providers)->contains(fn (array $provider): bool => ($provider['status'] ?? null) === 'degraded')) {
            $providers['vani_setu_app']['status'] = 'degraded';
        }

        return $providers;
    }

    /**
     * @param  array<string, array<string, mixed>>  $providers
     * @return array<string, array<string, mixed>>
     */
    private function withOrchestratorHealth(array $providers): array
    {
        $domains = [
            'performance' => [
                'label' => 'Latency / performance',
                'status' => $providers['s2s_latency_slo']['status'] ?? 'unknown',
                'signal' => 'p95 '.($providers['s2s_latency_slo']['p95_ms'] ?? 'collecting').'ms · bottleneck '.($providers['s2s_latency_slo']['bottleneck_stage'] ?? 'unknown'),
            ],
            'stability' => [
                'label' => 'Server / client stability',
                'status' => $this->worstHealthStatus([
                    $providers['s2s_error_rate']['status'] ?? 'unknown',
                    $providers['s2s_client_errors']['status'] ?? 'unknown',
                    $providers['sarvam_stt']['status'] ?? 'unknown',
                    $providers['sarvam_translate']['status'] ?? 'unknown',
                    $providers['sarvam_tts']['status'] ?? 'unknown',
                ]),
                'signal' => 'server errors '.($providers['s2s_error_rate']['error_rate'] ?? 0)
                    .' · client errors '.($providers['s2s_client_errors']['recent_errors'] ?? 0)
                    .$this->clientErrorBreachSignal($providers['s2s_client_errors']['threshold_breaches'][0] ?? null),
            ],
            'transcript_audio' => [
                'label' => 'Transcript / audio QA',
                'status' => $providers['s2s_qa_recheck']['status'] ?? 'unknown',
                'signal' => 'failed QA '.($providers['s2s_qa_recheck']['failed_segments'] ?? 0).' · stale pending '.($providers['s2s_qa_recheck']['stale_pending_segments'] ?? 0),
            ],
            'storage' => [
                'label' => 'Audio archive storage',
                'status' => $providers['audio_archive']['status'] ?? 'unknown',
                'signal' => 'retention '.($providers['audio_archive']['retention_days'] ?? 'n/a').'d'
                    .' · stale '.($providers['audio_archive']['stale_finished_segments'] ?? 0)
                    .' · cataloged '.($providers['audio_archive']['cataloged_source_audio'] ?? 0)
                    .' · active '.($providers['audio_archive']['active_cataloged_source_audio'] ?? 0)
                    .' · pruned '.($providers['audio_archive']['pruned_cataloged_source_audio'] ?? 0),
            ],
        ];
        $status = $this->worstHealthStatus(array_map(fn (array $domain): string => (string) $domain['status'], $domains));
        $providers['master_orchestrator'] = [
            'status' => $status,
            'domains' => $domains,
            'ready_for_live' => in_array($status, ['up', 'watch'], true),
            'signal' => collect($domains)
                ->map(fn (array $domain): string => $domain['label'].': '.$domain['status'])
                ->implode(' · '),
            'checked_at' => now()->toISOString(),
        ];

        if (($providers['master_orchestrator']['status'] ?? null) === 'down') {
            $providers['vani_setu_app']['status'] = 'down';
        } elseif (($providers['master_orchestrator']['status'] ?? null) === 'degraded') {
            $providers['vani_setu_app']['status'] = 'degraded';
        }

        return $providers;
    }

    /**
     * @param  list<string>  $statuses
     */
    private function worstHealthStatus(array $statuses): string
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

    public function benchmarkSummary(S2sBenchmarkSummaryService $summary): JsonResponse
    {
        return response()
            ->json($summary->summary())
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Re-sign the MinIO audio URL for a given output. Iter-14 recovery
     * path: ml-gateway mints presigned URLs with a 15-minute TTL (see
     * ``s2s_audio_url_ttl_seconds`` in ml-gateway/app/config.py). If a
     * user pauses an S2S session past that window and resumes playback,
     * MinIO returns 403 on the stored URL. This endpoint asks ml-gateway
     * to mint a fresh URL for the same object key, persists the fresh URL
     * back to ``s2s_outputs.audio_output_path`` so subsequent fetches see
     * the new value, and returns it to the JSX caller.
     *
     * Service-auth-gated via the ML_GATEWAY_SERVICE_TOKEN config; the
     * browser hits Laravel anonymously and Laravel forwards with the
     * token in the Authorization header.
     */
    public function reSignAudio(S2sOutput $output): JsonResponse
    {
        $stored = (string) $output->audio_output_path;
        if (! str_starts_with($stored, 'https://')) {
            return response()->json(
                ['error' => 'output has no minio-backed audio'],
                404
            );
        }
        $key = $this->extractMinioKey($stored);
        if ($key === null) {
            return response()->json(
                ['error' => 'unable to extract minio key from stored URL'],
                422
            );
        }
        $token = (string) config('services.ml_gateway.service_token', '');
        $base = rtrim((string) config('services.ml_gateway.url', 'http://ml-gateway:8000'), '/');
        try {
            $request = \Illuminate\Support\Facades\Http::timeout(5);
            if ($token !== '') {
                $request = $request->withToken($token);
            }
            $response = $request->get($base.'/v1/audio/re-sign', ['key' => $key]);
        } catch (\Throwable $exception) {
            return response()->json(
                ['error' => 'ml_gateway_unreachable', 'message' => $exception->getMessage()],
                502
            );
        }
        if (! $response->successful()) {
            return response()->json(
                ['error' => 'ml_gateway_'.$response->status(), 'body' => $response->json() ?? []],
                502
            );
        }
        $freshUrl = (string) ($response->json('audio_url') ?? '');
        $expiresIn = (int) ($response->json('expires_in_seconds') ?? 900);
        if ($freshUrl !== '') {
            // Persist so the next poll/load sees the fresh URL without
            // hitting this endpoint again. The next 15-min pause will
            // require another round trip; that's acceptable since it
            // only fires on actual playback.
            $output->forceFill(['audio_output_path' => $freshUrl])->save();
        }
        return response()->json([
            'audio_url' => $freshUrl,
            'expires_in_seconds' => $expiresIn,
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Iter-15: companion to ``reSignAudio`` that keys off the bare MinIO
     * object key instead of an ``s2s_outputs`` row id.
     *
     * Streaming SSE-delivered sentences are not persisted as ``s2s_outputs``
     * rows (they live only in the live stream + the JSX audio queue), so
     * the output-id-keyed re-sign route can't refresh their signed URLs
     * once the 15-min TTL lapses. Each SSE ``audio`` frame carries an
     * ``audio_key`` field alongside ``audio_url``; the browser passes that
     * key here and Laravel forwards to ml-gateway /v1/audio/re-sign with
     * service auth.
     *
     * Validates the key shape conservatively — only accepts the
     * ``speech_to_speech_audio/...`` prefix that ml-gateway's
     * ``persist_binary`` emits — so a leaked endpoint can't be used to
     * mint signed URLs for arbitrary bucket objects.
     */
    public function reSignAudioByKey(Request $request): JsonResponse
    {
        $key = trim((string) $request->query('key', ''));
        if ($key === '') {
            return response()->json(['error' => 'key is required'], 422);
        }
        // Defence in depth: only allow keys produced by the s2s pipeline.
        // ml-gateway's ArtifactStore.persist_binary places audio under
        // ``speech_to_speech_audio/YYYY/MM/DD/<uuid>.<ext>`` — anything
        // else is either a bug or an attempt to fetch a different object.
        if (! str_starts_with($key, 'speech_to_speech_audio/')) {
            return response()->json(['error' => 'unsupported key prefix'], 422);
        }
        if (strlen($key) > 512 || preg_match('#[\s\?\#]#', $key) === 1) {
            return response()->json(['error' => 'invalid key shape'], 422);
        }
        $token = (string) config('services.ml_gateway.service_token', '');
        $base = rtrim((string) config('services.ml_gateway.url', 'http://ml-gateway:8000'), '/');
        try {
            $client = \Illuminate\Support\Facades\Http::timeout(5);
            if ($token !== '') {
                $client = $client->withToken($token);
            }
            $response = $client->get($base.'/v1/audio/re-sign', ['key' => $key]);
        } catch (\Throwable $exception) {
            return response()->json(
                ['error' => 'ml_gateway_unreachable', 'message' => $exception->getMessage()],
                502
            );
        }
        if (! $response->successful()) {
            return response()->json(
                ['error' => 'ml_gateway_'.$response->status(), 'body' => $response->json() ?? []],
                502
            );
        }
        $freshUrl = (string) ($response->json('audio_url') ?? '');
        $expiresIn = (int) ($response->json('expires_in_seconds') ?? 900);
        return response()->json([
            'audio_url' => $freshUrl,
            'expires_in_seconds' => $expiresIn,
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Pull the MinIO object key out of a stored signed URL.
     *
     * Stored URLs look like:
     *   https://vanisetu.rajyasabha.digital/minio-audio/<bucket>/<key>?AWSAccessKeyId=...
     * The bucket name is implicit (ml-gateway re-signs against
     * ``artifact_s3_bucket`` from its own config); we only need the key
     * portion. Returns null if the URL doesn't match the expected shape
     * so the caller can 422 cleanly.
     */
    private function extractMinioKey(string $url): ?string
    {
        if (preg_match('#/minio-audio/[^/]+/([^?]+)#', $url, $m)) {
            return $m[1];
        }
        return null;
    }

    public function finishSession(Request $request, S2sSession $session, AuditLogger $audit): RedirectResponse|JsonResponse
    {
        $segmentCount = $session->segments()->count();
        $outputCount = $session->outputs()->count();

        $session->forceFill([
            'status' => 'finished',
            'finished_at' => now(),
            'archive_meta' => array_merge($session->archive_meta ?? [], [
                'finished_at' => now()->toISOString(),
                'segments' => $segmentCount,
                'outputs' => $outputCount,
            ]),
        ])->save();

        $audit->log('s2s.session.finished', $session, [
            'segments' => $segmentCount,
            'outputs' => $outputCount,
            'source' => 'public_web_console',
        ]);

        if ((bool) config('services.s2s_recheck.auto_dispatch', false)) {
            RecheckSessionJob::dispatch($session->id);
        }

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => 'ok',
                'session' => [
                    'id' => $session->id,
                    'status' => $session->status,
                    'finished_at' => $session->finished_at?->toISOString(),
                    'segments' => $segmentCount,
                    'outputs' => $outputCount,
                ],
            ]);
        }

        return redirect()
            ->route('public.s2s.admin', ['selected_session' => $session->id])
            ->with('status', 'Speech-to-speech session closed.');
    }

    public function storeVocabulary(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'rule_type' => ['required', Rule::in(S2sVocabularyRule::RULE_TYPES)],
            'language_code' => ['nullable', 'string', 'max:16'],
            'source_phrase' => ['required', 'string', 'max:255'],
            'replacement_text' => ['nullable', 'string', 'max:255'],
            'phonetic_hint' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'notes' => ['nullable', 'string'],
        ]);

        $rule = S2sVocabularyRule::query()->create([
            ...$data,
            'priority' => $data['priority'] ?? 100,
            'is_active' => true,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok', 'rule' => $rule], 201);
        }

        return redirect()->route('public.s2s.admin')->with('status', 'Vocabulary rule added.');
    }

    public function vocabularyIndex(Request $request): JsonResponse
    {
        return response()->json([
            'items' => S2sVocabularyRule::query()
                ->orderBy('priority')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function updateVocabulary(Request $request, S2sVocabularyRule $rule): JsonResponse
    {
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

        return response()->json(['status' => 'ok', 'rule' => $rule->fresh()]);
    }

    public function glossaryIndex(Request $request): JsonResponse
    {
        $srcLang = trim((string) $request->query('src_lang', ''));
        $tgtLang = trim((string) $request->query('tgt_lang', ''));

        $items = $srcLang !== '' && $tgtLang !== ''
            ? $this->glossary->listForPair($srcLang, $tgtLang)
            : $this->glossary->listAll();

        return response()->json([
            'items' => $items->values(),
        ]);
    }

    public function glossaryUpsert(Request $request): JsonResponse
    {
        $data = $this->validateGlossaryEntry($request);
        $entry = $this->glossary->upsert($data);

        return response()->json([
            'status' => 'ok',
            'entry' => $entry,
        ]);
    }

    public function glossaryDelete(int $entry): JsonResponse
    {
        $deleted = $this->glossary->delete($entry);

        return response()->json([
            'status' => $deleted ? 'ok' : 'missing',
        ], $deleted ? 200 : 404);
    }

    public function glossaryImport(Request $request): JsonResponse
    {
        $sourceLanguages = ['auto', ...array_keys($this->languages->all())];
        $targetLanguages = array_keys($this->languages->all());
        $data = $request->validate([
            'src_lang' => ['required', 'string', 'max:16', Rule::in($sourceLanguages)],
            'tgt_lang' => ['required', 'string', 'max:16', Rule::in($targetLanguages)],
            'csv' => ['required', 'file', 'max:10240', 'mimes:csv,txt'],
        ]);

        $handle = fopen($request->file('csv')->getRealPath(), 'r');
        if (! is_resource($handle)) {
            throw ValidationException::withMessages(['csv' => 'Could not read the uploaded CSV file.']);
        }

        $header = fgetcsv($handle);
        $header = array_map(
            fn ($value): string => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $value))),
            is_array($header) ? $header : [],
        );
        $positions = array_flip($header);
        $errors = [];
        $imported = 0;

        foreach (['source_term', 'target_term'] as $required) {
            if (! array_key_exists($required, $positions)) {
                fclose($handle);
                throw ValidationException::withMessages(['csv' => "Missing required column: {$required}."]);
            }
        }

        $line = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            $cell = fn (string $name, string $default = ''): string => array_key_exists($name, $positions)
                ? trim((string) ($row[$positions[$name]] ?? $default))
                : $default;
            $entry = [
                'src_lang' => $cell('src_lang', $data['src_lang']) ?: $data['src_lang'],
                'tgt_lang' => $cell('tgt_lang', $data['tgt_lang']) ?: $data['tgt_lang'],
                'source_term' => $cell('source_term'),
                'target_term' => $cell('target_term'),
                'pronunciation' => $cell('pronunciation'),
                'notes' => $cell('notes'),
            ];

            if ($entry['source_term'] === '' || $entry['target_term'] === '') {
                $errors[] = "Line {$line}: source_term and target_term are required.";
                continue;
            }
            if (! in_array($entry['src_lang'], $sourceLanguages, true) || ! in_array($entry['tgt_lang'], $targetLanguages, true)) {
                $errors[] = "Line {$line}: invalid language pair.";
                continue;
            }

            $this->glossary->upsert($entry);
            $imported++;
        }

        fclose($handle);

        return response()->json([
            'status' => 'ok',
            'imported' => $imported,
            'errors' => array_slice($errors, 0, 10),
            'error_count' => count($errors),
        ]);
    }

    public function glossaryExport(): StreamedResponse
    {
        $entries = $this->glossary->listAll();

        return response()->streamDownload(function () use ($entries): void {
            $out = fopen('php://output', 'w');
            if (! is_resource($out)) {
                return;
            }

            fputcsv($out, ['src_lang', 'tgt_lang', 'source_term', 'target_term', 'pronunciation', 'notes']);
            foreach ($entries as $entry) {
                fputcsv($out, [
                    $entry->src_lang,
                    $entry->tgt_lang,
                    $entry->source_term,
                    $entry->target_term,
                    $entry->pronunciation,
                    $entry->notes,
                ]);
            }
        }, 'vanisetu-s2s-glossary.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function glossarySeedMembers(Request $request): JsonResponse
    {
        $sourceLanguages = ['auto', ...array_keys($this->languages->all())];
        $targetLanguages = array_keys($this->languages->all());
        $data = $request->validate([
            'src_lang' => ['required', 'string', 'max:16', Rule::in($sourceLanguages)],
            'tgt_lang' => ['required', 'string', 'max:16', Rule::in($targetLanguages)],
        ]);

        return response()->json([
            'status' => 'ok',
            ...$this->glossary->seedMembers($data['src_lang'], $data['tgt_lang']),
        ]);
    }

    private function selectedSession(): ?S2sSession
    {
        $id = request()->integer('selected_session');
        if ($id <= 0) {
            return S2sSession::query()->with(['segments.outputs', 'outputs'])->latest('id')->first();
        }

        return S2sSession::query()->with(['segments.outputs', 'outputs'])->find($id);
    }

    /**
     * @return array{src_lang:string, tgt_lang:string, source_term:string, target_term:string, pronunciation:string|null, notes:string|null}
     */
    private function validateGlossaryEntry(Request $request): array
    {
        $sourceLanguages = ['auto', ...array_keys($this->languages->all())];
        $targetLanguages = array_keys($this->languages->all());

        return $request->validate([
            'src_lang' => ['required', 'string', 'max:16', Rule::in($sourceLanguages)],
            'tgt_lang' => ['required', 'string', 'max:16', Rule::in($targetLanguages)],
            'source_term' => ['required', 'string', 'max:255'],
            'target_term' => ['required', 'string', 'max:255'],
            'pronunciation' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * Slim status payload for the React poller (sarvam.jsx::pollS2SStatus).
     * Returns ONLY the fields the poller actually reads,
     * to keep response size bounded as segment counts grow. The full
     * sessionPayload is retained for /show & segment POST responses.
     *
     * @return array<string, mixed>
     */
    private function statusPayload(S2sSession $session): array
    {
        $segments = $session->segments
            ->sortBy('sequence_no')
            ->values()
            ->map(fn (S2sSegment $segment) => [
                'id' => $segment->id,
                'sequence_no' => $segment->sequence_no,
                'status' => $segment->status,
                'start_ms' => $segment->start_ms,
                'end_ms' => $segment->end_ms,
                'source_language' => $segment->source_language,
                'source_text' => $segment->source_text,
                'qa_state' => $segment->qa_state,
                'qa_corrected_text' => $segment->qa_corrected_text,
                'approved_transcript' => $segment->approved_transcript,
                'latency_ms' => $segment->engine_meta['dispatch']['server_latency_ms'] ?? null,
                'source_audio_disk' => data_get($segment->engine_meta, 'input_audio.disk'),
                'source_audio_path' => $segment->source_audio_path,
                'source_audio_size' => data_get($segment->engine_meta, 'input_audio.size'),
                'source_audio_download_url' => filled($segment->source_audio_path)
                    ? route('public.s2s.segments.audio', ['segment' => $segment->id])
                    : null,
                'timing' => [
                    'start_ms' => (int) $segment->start_ms,
                    'end_ms' => (int) $segment->end_ms,
                    'duration_ms' => max(0, (int) $segment->end_ms - (int) $segment->start_ms),
                    'overlap_ms' => (int) data_get($segment->engine_meta, 'capture.overlap_ms', 0),
                ],
                'edit_locator' => [
                    'session_id' => $segment->session_id,
                    'segment_id' => $segment->id,
                    'sequence_no' => $segment->sequence_no,
                    'start_ms' => (int) $segment->start_ms,
                    'end_ms' => (int) $segment->end_ms,
                    'source_audio_url' => filled($segment->source_audio_path)
                        ? route('public.s2s.segments.audio', ['segment' => $segment->id])
                        : null,
                    'correction_url' => route('public.s2s.segments.correction', ['segment' => $segment->id]),
                    'replay_anchor' => '#s2s-segment-'.$segment->id,
                ],
                'source_audio' => $this->sourceAudioPayload($segment),
                'outputs' => $segment->outputs->map(fn (S2sOutput $output) => [
                    // ``id`` is included so the JSX audio queue can call the
                    // iter-14 ``/speech-to-speech/outputs/{output}/audio-url``
                    // re-sign endpoint when the stored URL is about to expire.
                    // Without this the poller-derived chunks can't recover
                    // from a long-paused session.
                    'id' => $output->id,
                    'language_code' => $output->language_code,
                    'status' => $output->status,
                    'text_output' => $output->text_output,
                    'error_message' => $this->outputErrorMessage($output),
                    'audio_output_path' => $this->browserAudioPath($output->audio_output_path),
                    'output_locator' => $this->outputLocator($segment, $output),
                ])->values()->all(),
            ])->all();

        return [
            'id' => $session->id,
            'status' => $session->status,
            'mode' => $session->mode,
            'source_lang' => $session->source_lang,
            'target_lang' => $session->target_lang,
            'available_target_langs' => $session->available_target_langs ?? [],
            'started_at' => $session->started_at?->toISOString(),
            'finished_at' => $session->finished_at?->toISOString(),
            'archive_meta' => [
                'segments' => (int) ($session->archive_meta['segments'] ?? count($segments)),
            ],
            'segments' => $segments,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionPayload(S2sSession $session): array
    {
        $segments = $session->segments
            ->sortBy('sequence_no')
            ->values()
            ->map(fn (S2sSegment $segment) => [
                'id' => $segment->id,
                'sequence_no' => $segment->sequence_no,
                'start_ms' => $segment->start_ms,
                'end_ms' => $segment->end_ms,
                'source_language' => $segment->source_language,
                'source_text' => $segment->source_text,
                'qa_state' => $segment->qa_state,
                'qa_corrected_text' => $segment->qa_corrected_text,
                'approved_transcript' => $segment->approved_transcript,
                'status' => $segment->status,
                'latency_ms' => $segment->engine_meta['dispatch']['server_latency_ms'] ?? null,
                'dispatch_status' => $segment->engine_meta['dispatch']['status'] ?? null,
                'source_audio_disk' => data_get($segment->engine_meta, 'input_audio.disk'),
                'source_audio_path' => $segment->source_audio_path,
                'source_audio_size' => data_get($segment->engine_meta, 'input_audio.size'),
                'source_audio_download_url' => filled($segment->source_audio_path)
                    ? route('public.s2s.segments.audio', ['segment' => $segment->id])
                    : null,
                'outputs' => $segment->outputs->map(fn (S2sOutput $output) => [
                    'id' => $output->id,
                    'language_code' => $output->language_code,
                    'channel_name' => $output->channel_name,
                    'status' => $output->status,
                    'text_output' => $output->text_output,
                    'audio_output_path' => $this->browserAudioPath($output->audio_output_path),
                    'audio_output_supported' => (bool) data_get($output->output_meta, 'audio_output_supported', false),
                    'latency_ms' => $segment->engine_meta['dispatch']['server_latency_ms'] ?? null,
                ])->values()->all(),
            ])->all();

        return [
            'id' => $session->id,
            'title' => $session->title,
            'mode' => $session->mode,
            'input_source' => $session->input_source,
            'listener_scope' => $session->listener_scope,
            'source_lang' => $session->source_lang,
            'target_lang' => $session->target_lang,
            'available_target_langs' => $session->available_target_langs ?? [],
            'status' => $session->status,
            'started_at' => $session->started_at?->toISOString(),
            'finished_at' => $session->finished_at?->toISOString(),
            'segments' => $segments,
            'outputs' => $session->outputs->map(fn (S2sOutput $output) => [
                'id' => $output->id,
                'segment_id' => $output->segment_id,
                'language_code' => $output->language_code,
                'channel_name' => $output->channel_name,
                'status' => $output->status,
                'text_output' => $output->text_output,
                'audio_output_path' => $this->browserAudioPath($output->audio_output_path),
                'audio_output_supported' => (bool) data_get($output->output_meta, 'audio_output_supported', false),
            ])->values()->all(),
        ];
    }

    private function browserAudioPath(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        if (str_starts_with($path, 'data:')) {
            return $path;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        if (str_starts_with($path, 'storage/')) {
            return '/'.$path;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceAudioPayload(S2sSegment $segment, bool $includeMimeType = false): array
    {
        $input = data_get($segment->engine_meta, 'input_audio', []);
        $payload = [
            'segment_id' => $segment->id,
            'disk' => data_get($input, 'disk'),
            'path' => $segment->source_audio_path,
            'size' => data_get($input, 'size'),
            'stored_size' => data_get($input, 'stored_size'),
            'compression' => data_get($input, 'compression'),
            'archive_layout' => $this->sourceAudioArchiveLayout($segment, $input),
            'download_url' => filled($segment->source_audio_path)
                ? route('public.s2s.segments.audio', ['segment' => $segment->id])
                : null,
            'pruned' => $segment->hasPrunedSourceAudioRecord(),
            'pruned_at' => data_get($input, 'pruned_at'),
            'pruned_reason' => data_get($input, 'pruned_reason'),
            'pruned_stored_size' => data_get($input, 'pruned_stored_size'),
        ];

        if ($includeMimeType) {
            $payload['mime_type'] = data_get($input, 'mime_type');
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|null
     */
    private function sourceAudioArchiveLayout(S2sSegment $segment, ?array $input): ?array
    {
        $input = is_array($input) ? $input : [];
        $layout = data_get($input, 'archive_layout');
        if (is_array($layout) && $layout !== []) {
            return $layout;
        }

        $path = (string) ($segment->source_audio_path ?: data_get($input, 'pruned_original_path', ''));
        if ($path === '') {
            return null;
        }

        return $this->sourceAudioArchiveLayoutFromPath($path, data_get($input, 'archive_layout.sequence_no'));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sourceAudioArchiveLayoutFromPath(string $path, mixed $sequenceNo = null): ?array
    {
        $matches = null;
        foreach ([
            '#^s2s/devices/([^/]+)/([^/]+)/(\d{4}-\d{2}-\d{2})/(\d{2})/sessions/(\d+)(?:/|$)#',
            '#^s2s/devices/([^/]+)/(\d{4}-\d{2}-\d{2})/(\d{2})/sessions/(\d+)(?:/|$)#',
        ] as $pattern) {
            if (preg_match($pattern, $path, $candidateMatches)) {
                $matches = $candidateMatches;
                break;
            }
        }

        if (! is_array($matches)) {
            return null;
        }

        if (count($matches) === 6) {
            $inputSource = $matches[1];
            $deviceBucket = $matches[2];
            $day = $matches[3];
            $hour = $matches[4];
            $sessionId = $matches[5];
        } else {
            $inputSource = $matches[1];
            $deviceBucket = 'default';
            $day = $matches[2];
            $hour = $matches[3];
            $sessionId = $matches[4];
        }

        return [
            'input_source' => $inputSource,
            'device_bucket' => $deviceBucket,
            'day' => $day,
            'hour' => $hour,
            'session_id' => is_numeric($sessionId) ? (int) $sessionId : $sessionId,
            'sequence_no' => is_numeric($sequenceNo) ? (int) $sequenceNo : $sequenceNo,
            'hierarchy' => ['s2s', 'devices', $inputSource, $deviceBucket, $day, $hour, 'sessions', $sessionId],
            'tags' => ['device', 'daywise', 'hourwise'],
            'partition_key' => implode('/', [$deviceBucket, $day, $hour]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transcriptJsonSegment(S2sSegment $segment, int $textOffsetStart = 0): array
    {
        $approvedForDownstream = in_array($segment->qa_state, S2sSegment::QA_APPROVED_STATES, true);
        $startMs = (int) ($segment->start_ms ?? 0);
        $endMs = (int) ($segment->end_ms ?? $startMs);
        $approvedText = (string) ($segment->approved_transcript ?? '');
        $sourceAudioDownloadUrl = filled($segment->source_audio_path)
            ? route('public.s2s.segments.audio', ['segment' => $segment->id])
            : null;
        $correctionUrl = route('public.s2s.segments.correction', ['segment' => $segment->id]);

        return [
            'id' => $segment->id,
            'sequence_no' => $segment->sequence_no,
            'status' => $segment->status,
            'source_language' => $segment->source_language,
            'source_text' => $segment->source_text,
            'approved_transcript' => $approvedText,
            'approved_for_downstream' => $approvedForDownstream,
            'qa' => [
                'state' => $segment->qa_state,
                'score' => $segment->qa_score,
                'corrected_text' => $segment->qa_corrected_text,
                'checked_at' => $segment->qa_checked_at?->toISOString(),
            ],
            'timing' => [
                'start_ms' => $startMs,
                'end_ms' => $endMs,
                'duration_ms' => max(0, $endMs - $startMs),
                'overlap_ms' => (int) data_get($segment->engine_meta, 'capture.overlap_ms', 0),
            ],
            'edit_locator' => [
                'session_id' => $segment->session_id,
                'segment_id' => $segment->id,
                'sequence_no' => $segment->sequence_no,
                'start_ms' => $startMs,
                'end_ms' => $endMs,
                'duration_ms' => max(0, $endMs - $startMs),
                'text_offset_start' => $textOffsetStart,
                'text_offset_end' => $textOffsetStart + mb_strlen($approvedText),
                'source_audio_url' => $sourceAudioDownloadUrl,
                'correction_url' => $correctionUrl,
                'replay_anchor' => '#s2s-segment-'.$segment->id,
            ],
            'source_audio' => $this->sourceAudioPayload($segment, includeMimeType: true),
            'outputs' => $segment->outputs
                ->map(fn (S2sOutput $output) => [
                    'id' => $output->id,
                    'language_code' => $output->language_code,
                    'channel_name' => $output->channel_name,
                    'status' => $output->status,
                    'text_output' => $output->text_output,
                    'error_message' => $this->outputErrorMessage($output),
                    'output_locator' => $this->outputLocator($segment, $output),
                    'audio' => [
                        'path' => $output->audio_output_path,
                        'url' => $this->browserAudioPath($output->audio_output_path),
                        'supported' => (bool) data_get($output->output_meta, 'audio_output_supported', false),
                    ],
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function outputLocator(S2sSegment $segment, S2sOutput $output): array
    {
        $startMs = (int) ($segment->start_ms ?? 0);
        $endMs = (int) ($segment->end_ms ?? $startMs);

        return [
            'session_id' => $segment->session_id,
            'segment_id' => $segment->id,
            'output_id' => $output->id,
            'sequence_no' => $segment->sequence_no,
            'language_code' => $output->language_code,
            'start_ms' => $startMs,
            'end_ms' => $endMs,
            'duration_ms' => max(0, $endMs - $startMs),
            'source_replay_anchor' => '#s2s-segment-'.$segment->id,
            'translated_audio_url' => $this->browserAudioPath($output->audio_output_path),
            'audio_resign_url' => filled($output->audio_output_path)
                ? route('public.s2s.audio.resign', ['output' => $output->id])
                : null,
        ];
    }

    private function outputErrorMessage(S2sOutput $output): ?string
    {
        $message = data_get($output->output_meta, 'provider_payload.error')
            ?? data_get($output->output_meta, 'provider_payload.message')
            ?? data_get($output->output_meta, 'tts_fallback.error');

        if (! is_string($message)) {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', $message);
        $message = trim(is_string($normalized) ? $normalized : $message);

        if ($message === '') {
            return null;
        }

        return Str::limit($message, 240, '');
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
     * @throws ValidationException
     */
    private function ensureSegmentHasInput(Request $request): void
    {
        if ($request->hasFile('audio') || filled($request->input('source_text'))) {
            return;
        }

        throw ValidationException::withMessages([
            'source_text' => 'Record audio or upload a file before sending a segment.',
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

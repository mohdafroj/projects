<?php

namespace App\Modules\SpeechToSpeech\Services;

use App\Modules\SpeechToSpeech\Models\S2sOutput;
use App\Modules\SpeechToSpeech\Models\S2sSegment;
use App\Modules\SpeechToSpeech\Models\S2sSession;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Forwards a captured segment to the internal voice-pipeline URL (which fronts
 * Sarvam's saaras:v3 STT, mayura:v1 translate, bulbul:v3 TTS). Stage params
 * (model, mode, codec, pace, sample_rate) come from the runtime config and
 * are passed through verbatim so the proxy speaks the gotchas:
 *
 *   - STT mode `codemix` keeps Hindi in Devanagari + English in Latin in the
 *     same transcript. saarika:v2.5 silently ignores `mode`.
 *   - `with_diarization=true` is rejected with HTTP 400 on the real-time
 *     endpoint — keep it batch-only.
 *   - Bulbul v3 has a wholly different speaker roster from v2; mixing in v2
 *     names (anushka/manisha/vidya/arya/abhilash/karun/hitesh) gets HTTP 400.
 *     See S2sConfigRepository::defaults()['voice_roster'].
 *   - TTS codec stays `wav` because the chamber player decodes via wave.open;
 *     switching to mp3/opus needs a chunk-fed player + decoder.
 *
 * Full PoC handover: docs/vani-setu-poc-handover.md.
 */
class SarvamSpeechPipeline
{
    public function __construct(
        private readonly S2sConfigRepository $config,
        private readonly S2sLanguageRegistry $languages,
        private readonly S2sGlossaryService $glossary,
        private readonly S2sAudioArchive $audioArchive,
    ) {}

    /**
     * @return array{provider:string, attempted:bool, status:int|string, request:array<string, mixed>, response:mixed}
     */
    public function dispatchSegment(S2sSession $session, S2sSegment $segment): array
    {
        $runtime = $this->config->pipeline();
        $targets = $session->available_target_langs ?: $runtime['target_languages'];
        $announcement = (string) ($session->announcement_text ?: $runtime['announcement_prefix']);
        $sarvam = is_array($runtime['sarvam'] ?? null) ? $runtime['sarvam'] : [];

        $ttsStage = $sarvam['tts'] ?? [
            'model' => 'bulbul:v3',
            'pace' => 1.1,
            'sample_rate' => 22050,
            'codec' => 'wav',
            'enable_preprocessing' => true,
        ];
        $sessionVoice = data_get($session->engine_meta, 'tts_speaker');
        if (is_string($sessionVoice) && $sessionVoice !== '') {
            $ttsStage = array_merge($ttsStage, ['speaker' => $sessionVoice]);
        }

        $requestPayload = [
            'session_id' => $session->id,
            'segment_id' => $segment->id,
            'source_language' => $segment->source_language ?: $session->source_lang,
            'source_text' => $segment->source_text,
            'source_audio_path' => $segment->source_audio_path,
            ...$this->audioPayload($segment),
            'target_languages' => $targets,
            'stages' => [
                'stt' => $sarvam['stt'] ?? [
                    'model' => 'saaras:v3',
                    'mode' => 'codemix',
                    'with_diarization' => false,
                ],
                'translate' => $sarvam['translate'] ?? [
                    'model' => 'mayura:v1',
                    'mode' => 'formal',
                    'enable_preprocessing' => true,
                ],
                'tts' => $ttsStage,
            ],
            'announcement_prefix' => $announcement,
            'fallback_chain' => $runtime['fallback_chain'],
        ];

        $url = trim((string) config('services.sarvam.voice_pipeline_url', ''));
        if ($url === '') {
            $this->upsertPlannedOutputs($session, $segment, $targets, $announcement, null);

            return [
                'provider' => 'sarvam',
                'attempted' => false,
                'status' => 'planned',
                'request' => $this->scrubAudioFields($requestPayload),
                'response' => 'SARVAM_VOICE_PIPELINE_URL not configured',
            ];
        }

        $request = $this->providerRequest($session, $segment);

        try {
            $response = $request->asJson()->post($url, $requestPayload);
            $payload = $response->json() ?? ['body' => $response->body()];
            $payload = is_array($payload) ? $this->pollProviderResult($request, $payload) : $payload;
            $this->upsertPlannedOutputs(
                $session,
                $segment,
                $targets,
                $announcement,
                is_array($payload) ? $payload : null,
                $response->failed() ? 'provider_error' : 'provider_pending',
            );

            return [
                'provider' => 'sarvam',
                'attempted' => true,
                'status' => $response->status(),
                'request' => $this->scrubAudioFields($requestPayload),
                'response' => $this->scrubAudioFields($payload),
            ];
        } catch (\Throwable $exception) {
            $this->upsertPlannedOutputs($session, $segment, $targets, $announcement, null, 'provider_error');

            return [
                'provider' => 'sarvam',
                'attempted' => true,
                'status' => 'error',
                'request' => $this->scrubAudioFields($requestPayload),
                'response' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Streaming variant of dispatchSegment. POSTs to the ml-gateway streaming
     * endpoint and invokes ``$writeFrame`` for each chunk read off the SSE
     * response so the browser can start playing sentence 1 while sentence 2+
     * are still being synthesised. Returns the same shape as dispatchSegment
     * so the caller can persist the dispatch status afterwards.
     *
     * @param  callable(string): void  $writeFrame
     * @return array{provider:string, attempted:bool, status:int|string, request:array<string, mixed>, response:mixed}
     */
    public function dispatchSegmentStreaming(S2sSession $session, S2sSegment $segment, callable $writeFrame, array $sttOverrides = []): array
    {
        $runtime = $this->config->pipeline();
        $targets = $session->available_target_langs ?: $runtime['target_languages'];
        $announcement = (string) ($session->announcement_text ?: $runtime['announcement_prefix']);
        $sarvam = is_array($runtime['sarvam'] ?? null) ? $runtime['sarvam'] : [];

        $ttsStage = $sarvam['tts'] ?? [
            'model' => 'bulbul:v3',
            'pace' => 1.1,
            'sample_rate' => 22050,
            'codec' => 'wav',
            'enable_preprocessing' => true,
        ];
        $sessionVoice = data_get($session->engine_meta, 'tts_speaker');
        if (is_string($sessionVoice) && $sessionVoice !== '') {
            $ttsStage = array_merge($ttsStage, ['speaker' => $sessionVoice]);
        }

        // Layer the per-segment ASR overrides from the JSX pipeline panel
        // (model/codemix/diarize/timestamps) on top of the session defaults
        // so flipping a toggle takes effect on the next chunk without
        // re-creating the session.
        $sttStage = array_merge(
            $sarvam['stt'] ?? [
                'model' => 'saaras:v3',
                'mode' => 'codemix',
                'with_diarization' => false,
            ],
            $sttOverrides,
        );

        $requestPayload = [
            'session_id' => $session->id,
            'segment_id' => $segment->id,
            'source_language' => $segment->source_language ?: $session->source_lang,
            'source_text' => $segment->source_text,
            'source_audio_path' => $segment->source_audio_path,
            ...$this->audioPayload($segment),
            'target_languages' => $targets,
            'stages' => [
                'stt' => $sttStage,
                'translate' => $sarvam['translate'] ?? [
                    'model' => 'mayura:v1',
                    'mode' => 'formal',
                    'enable_preprocessing' => true,
                ],
                'tts' => $ttsStage,
            ],
            'announcement_prefix' => $announcement,
            'fallback_chain' => $runtime['fallback_chain'],
        ];

        $batchedUrl = trim((string) config('services.sarvam.voice_pipeline_url', ''));
        if ($batchedUrl === '') {
            return [
                'provider' => 'sarvam',
                'attempted' => false,
                'status' => 'planned',
                'request' => $this->scrubAudioFields($requestPayload),
                'response' => 'SARVAM_VOICE_PIPELINE_URL not configured',
            ];
        }

        // The ml-gateway streaming endpoint sits next to the batched one:
        // /v1/speech-to-speech → /v1/speech-to-speech/stream. Allow an
        // explicit override (SARVAM_VOICE_PIPELINE_STREAM_URL) so ops can
        // point the stream at a different host if they front-channel it.
        $streamUrl = trim((string) config('services.sarvam.voice_pipeline_stream_url', ''));
        if ($streamUrl === '') {
            $streamUrl = preg_match('#/speech-to-speech/?$#', $batchedUrl) === 1
                ? rtrim($batchedUrl, '/').'/stream'
                : $batchedUrl.'/stream';
        }

        $request = $this->providerRequest($session, $segment);

        $streamState = [
            'buffer' => '',
            'source_text' => null,
            'source_language' => null,
            'outputs' => [],
            'events' => [],
            'done' => null,
        ];
        $response = null;
        $streamException = null;

        try {
            $response = $request
                ->asJson()
                ->withOptions([
                    'stream' => true,
                    'headers' => ['Accept' => 'text/event-stream'],
                ])
                ->post($streamUrl, $requestPayload);

            $psr = $response->toPsrResponse();
            $body = $psr->getBody();
            while (! $body->eof()) {
                $chunk = $body->read(4096);
                if ($chunk !== '') {
                    $writeFrame($chunk);
                    $this->observeStreamingChunk($chunk, $streamState);
                }
            }
            $this->observeStreamingChunk("\n\n", $streamState);
        } catch (\Throwable $exception) {
            $streamException = $exception;
            // Emit a final SSE error frame so the browser stops waiting on
            // an event-source that won't deliver more frames.
            $errorFrame = "event: stream_error\ndata: ".json_encode([
                'message' => $exception->getMessage(),
                'session_id' => $session->id,
                'segment_id' => $segment->id,
            ], JSON_UNESCAPED_UNICODE)."\n\n";
            $writeFrame($errorFrame);
        }

        // Persist whatever the SSE observer accumulated, even when the body
        // read raised mid-stream. Without this, an idle-timeout or reset
        // after a successful `translation` frame leaves the s2s_outputs row
        // stuck in `provider_pending` forever — refreshPendingOutputs has
        // nothing to refresh, and the browser already saw the live frames.
        $providerPayload = $this->streamingProviderPayload($streamState);
        $persistStatus = $streamException !== null || ($response !== null && $response->failed())
            ? 'provider_error'
            : 'provider_pending';
        $this->upsertPlannedOutputs(
            $session,
            $segment,
            $targets,
            $announcement,
            $providerPayload,
            $persistStatus,
        );

        if ($streamException !== null) {
            return [
                'provider' => 'sarvam',
                'attempted' => true,
                'status' => 'error',
                'request' => $this->scrubAudioFields($requestPayload),
                'response' => $this->scrubAudioFields([
                    'mode' => 'stream',
                    'error' => $streamException->getMessage(),
                    ...$providerPayload,
                ]),
            ];
        }

        return [
            'provider' => 'sarvam',
            'attempted' => true,
            'status' => $response->status(),
            'request' => $this->scrubAudioFields($requestPayload),
            'response' => $this->scrubAudioFields([
                'mode' => 'stream',
                ...$providerPayload,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function observeStreamingChunk(string $chunk, array &$state): void
    {
        $state['buffer'] = (string) ($state['buffer'] ?? '').$chunk;

        while (($idx = strpos((string) $state['buffer'], "\n\n")) !== false) {
            $rawFrame = substr((string) $state['buffer'], 0, $idx);
            $state['buffer'] = substr((string) $state['buffer'], $idx + 2);
            $frame = $this->parseStreamingFrame($rawFrame);
            if ($frame === null) {
                continue;
            }

            $this->consumeStreamingFrame($frame, $state);
        }
    }

    /**
     * @return array{event:string, data:array<string, mixed>}|null
     */
    private function parseStreamingFrame(string $rawFrame): ?array
    {
        $event = 'message';
        $data = '';
        foreach (preg_split('/\r?\n/', $rawFrame) ?: [] as $line) {
            if (str_starts_with($line, 'event:')) {
                $event = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $data .= trim(substr($line, 5));
            }
        }

        if ($data === '') {
            return null;
        }

        $decoded = json_decode($data, true);
        if (! is_array($decoded)) {
            return null;
        }

        return ['event' => $event, 'data' => $decoded];
    }

    /**
     * @param  array{event:string, data:array<string, mixed>}  $frame
     * @param  array<string, mixed>  $state
     */
    private function consumeStreamingFrame(array $frame, array &$state): void
    {
        $event = $frame['event'];
        $data = $frame['data'];
        $state['events'][] = [
            'event' => $event,
            'language_code' => $data['language_code'] ?? null,
            'sentence_index' => $data['sentence_index'] ?? null,
        ];

        if ($event === 'stt') {
            if (is_string($data['transcript'] ?? null) && trim($data['transcript']) !== '') {
                $state['source_text'] = $data['transcript'];
            }
            if (is_string($data['detected_language'] ?? null) && trim($data['detected_language']) !== '') {
                $state['source_language'] = $data['detected_language'];
            }

            return;
        }

        if ($event === 'done') {
            // Iter-21: capture the gateway's per-stage latency breakdown so
            // streamingProviderPayload() can surface it on dispatch.response
            // for the latency SLO badge / operator HUD.
            $state['done'] = $data;

            return;
        }

        $languageCode = $data['language_code'] ?? null;
        if (! is_string($languageCode) || trim($languageCode) === '') {
            return;
        }

        $output = &$this->streamingOutput($state, $languageCode);

        if ($event === 'translation') {
            if (is_string($data['text'] ?? null)) {
                $output['text_output'] = $data['text'];
            }
            $output['status'] = ! empty($data['translation_degraded']) ? 'translation_degraded' : 'completed';
            $output['translation_degraded'] = (bool) ($data['translation_degraded'] ?? false);

            return;
        }

        if ($event === 'audio') {
            // The top-level audio_url/audio_key on $output is what
            // upsertPlannedOutputs persists into the single
            // s2s_outputs.audio_output_path column. Multi-sentence streams
            // emit several 'audio' frames per language; only the first
            // sentence is anchored to the row so its URL stays addressable
            // for replay/QA. Later sentences accumulate in `sentences[]`
            // (which is preserved through engine_meta) for the streaming
            // playback queue.
            foreach (['audio_url', 'audio_base64', 'audio_mime_type', 'audio_key'] as $key) {
                if (filled($data[$key] ?? null) && ! filled($output[$key] ?? null)) {
                    $output[$key] = $data[$key];
                }
            }
            $output['sentences'] = $output['sentences'] ?? [];
            $output['sentences'][] = [
                'sentence_index' => $data['sentence_index'] ?? null,
                'sentence_text' => $data['sentence_text'] ?? null,
                'total_sentences' => $data['total_sentences'] ?? null,
                'audio_key' => $data['audio_key'] ?? null,
                'audio_url' => $data['audio_url'] ?? null,
            ];
            if (($output['status'] ?? 'provider_pending') === 'provider_pending') {
                $output['status'] = 'completed';
            }

            return;
        }

        if ($event === 'language_done') {
            // TTS-WebSocket path: there are no per-sentence 'audio' frames — the
            // assembled audio's signed URL/key/mime ride this frame so the output
            // row still gets an addressable audio_output_path for replay/QA.
            // No-op for the HTTP path (its language_done carries no audio_url).
            foreach (['audio_url', 'audio_mime_type', 'audio_key'] as $key) {
                if (filled($data[$key] ?? null) && ! filled($output[$key] ?? null)) {
                    $output[$key] = $data[$key];
                }
            }
            if (($output['status'] ?? 'provider_pending') === 'provider_pending' && filled($data['audio_url'] ?? null)) {
                $output['status'] = 'completed';
            }

            return;
        }

        if (in_array($event, ['language_error', 'audio_error'], true)) {
            $output['status'] = 'provider_error';
            $output['error'] = $data['message'] ?? $data['error'] ?? $event;
        }
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function streamingProviderPayload(array $state): array
    {
        $done = is_array($state['done'] ?? null) ? $state['done'] : [];

        return [
            'provider_used' => 'sarvam_stream',
            'source_text' => $state['source_text'] ?? null,
            'source_language' => $state['source_language'] ?? null,
            'outputs' => array_values($state['outputs'] ?? []),
            'stream_events' => $state['events'] ?? [],
            // Iter-21: per-stage latency from the gateway 'done' frame. These
            // scalar keys are what SpeechToSpeechPageController::stageLatencySummary
            // harvests off dispatch.response to drive the s2s_latency_slo badge
            // (first_byte/stt/translation/tts) and the operator latency HUD.
            'first_audio_ms' => $done['first_audio_ms'] ?? null,
            'first_byte_ms' => $done['first_byte_ms'] ?? null,
            'stt_latency_ms' => $done['stt_latency_ms'] ?? null,
            'translation_latency_ms' => $done['translation_latency_ms'] ?? null,
            'tts_latency_ms' => $done['tts_latency_ms'] ?? null,
            'stage_latency' => $done['stage_latency'] ?? null,
            'total_ms' => $done['total_ms'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function &streamingOutput(array &$state, string $languageCode): array
    {
        if (! isset($state['outputs'][$languageCode]) || ! is_array($state['outputs'][$languageCode])) {
            $state['outputs'][$languageCode] = [
                'language_code' => $languageCode,
                'status' => 'provider_pending',
            ];
        }

        return $state['outputs'][$languageCode];
    }

    public function refreshPendingOutputs(S2sSession $session, ?int $sequenceNo = null): void
    {
        $audioLanguages = $this->config->pipeline()['audio_output_languages'];

        if ($sequenceNo !== null && $sequenceNo > 0) {
            $segments = $session->segments()
                ->with('outputs')
                ->where('sequence_no', $sequenceNo)
                ->get();
        } else {
            $session->loadMissing(['segments.outputs']);
            $segments = $session->segments->sortByDesc('sequence_no');
            $segments = $segments->take(2);
        }

        foreach ($segments as $segment) {
            foreach ($segment->outputs as $output) {
                $audioCapable = (bool) data_get($output->output_meta, 'audio_output_supported', in_array($output->language_code, $audioLanguages, true));
                if (
                    $output->status === 'provider_pending'
                    && $audioCapable
                    && filled($output->text_output)
                    && ! filled($output->audio_output_path)
                ) {
                    $glossaryEntries = $this->glossary->listAll();
                    $ttsText = $this->glossary->applyPronunciationOverrides($glossaryEntries, $output->language_code, (string) $output->text_output);
                    $ttsFallback = $this->synthesizeTextAudio($session, $segment, $output->language_code, $ttsText);

                    $existingMeta = is_array($output->output_meta) ? $output->output_meta : [];
                    $existingGlossaryMeta = is_array($existingMeta['glossary'] ?? null) ? $existingMeta['glossary'] : [];
                    $outputMeta = array_merge($existingMeta, [
                        'glossary' => array_merge($existingGlossaryMeta, [
                            'tts_changed' => $ttsText !== (string) $output->text_output,
                            'tts_text' => $ttsText !== (string) $output->text_output ? $ttsText : null,
                        ]),
                        'tts_fallback' => $ttsFallback,
                    ]);

                    $output->forceFill([
                        'status' => filled($ttsFallback['path'] ?? null) ? 'completed' : 'provider_error',
                        'audio_output_path' => $ttsFallback['path'] ?? null,
                        'output_meta' => $outputMeta,
                    ])->save();
                }
            }

            $hasPending = $segment->outputs->contains(
                fn (S2sOutput $output): bool => $output->status === 'provider_pending'
            );
            if (! $hasPending) {
                continue;
            }

            $dispatch = is_array($segment->engine_meta['dispatch'] ?? null)
                ? $segment->engine_meta['dispatch']
                : [];
            $providerPayload = is_array($dispatch['response'] ?? null) ? $dispatch['response'] : null;
            $pollUrl = $this->providerPollUrl($providerPayload);
            if ($pollUrl === null) {
                continue;
            }

            try {
                $response = $this->providerRequest($session, $segment)->get($pollUrl);
                $payload = $response->json() ?? ['body' => $response->body()];
            } catch (\Throwable) {
                continue;
            }

            if (! is_array($payload)) {
                continue;
            }

            $hasOutput = $this->providerOutputs($payload) !== [] || $this->hasTopLevelOutput($payload);
            $status = strtolower((string) (data_get($payload, 'status') ?? data_get($payload, 'job.status') ?? ''));
            if (! $hasOutput && ! in_array($status, ['completed', 'complete', 'ready', 'success', 'succeeded', 'failed', 'error'], true)) {
                continue;
            }

            $runtime = $this->config->pipeline();
            $this->upsertPlannedOutputs(
                $session,
                $segment,
                $session->available_target_langs ?: $runtime['target_languages'],
                (string) ($session->announcement_text ?: $runtime['announcement_prefix']),
                $payload,
                in_array($status, ['failed', 'error'], true) ? 'provider_error' : 'provider_pending',
            );

            $segment->forceFill([
                'engine_meta' => array_merge($segment->engine_meta ?? [], [
                    'dispatch' => array_merge($dispatch, [
                        'response' => $this->scrubAudioFields($payload),
                        'refreshed_at' => now()->toISOString(),
                    ]),
                ]),
            ])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function pollProviderResult(mixed $request, array $payload): array
    {
        $pollUrl = $this->providerPollUrl($payload);

        if ($pollUrl === null) {
            return $payload;
        }

        $deadline = microtime(true) + min((int) config('services.sarvam.timeout', 90), 75);
        $latest = $payload;

        while (microtime(true) < $deadline) {
            usleep(750_000);

            try {
                $response = $request->get($pollUrl);
                $next = $response->json() ?? ['body' => $response->body()];
            } catch (\Throwable) {
                return $latest;
            }

            if (! is_array($next)) {
                continue;
            }

            $latest = $next;
            $status = strtolower((string) (data_get($next, 'status') ?? data_get($next, 'job.status') ?? ''));
            if ($this->providerOutputs($next) !== [] || $this->hasTopLevelOutput($next)) {
                return $next;
            }

            if (in_array($status, ['completed', 'complete', 'ready', 'success', 'succeeded', 'failed', 'error'], true)) {
                return $next;
            }
        }

        return $latest;
    }

    private function providerRequest(S2sSession $session, S2sSegment $segment): PendingRequest
    {
        return Http::timeout((int) config('services.sarvam.timeout', 90))
            ->retry(
                (int) config('services.sarvam.retries', 1),
                (int) config('services.sarvam.retry_sleep_ms', 250),
            )
            ->withHeaders([
                'X-S2S-Session' => (string) $session->id,
                'X-S2S-Segment' => (string) $segment->id,
                ...$this->mlGatewayAuthHeaders(),
            ]);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function providerPollUrl(?array $payload): ?string
    {
        $pollUrl = data_get($payload, 'status_url')
            ?? data_get($payload, 'poll_url')
            ?? data_get($payload, 'result_url')
            ?? data_get($payload, 'job.status_url')
            ?? data_get($payload, 'job.poll_url')
            ?? data_get($payload, 'job.result_url');

        return is_string($pollUrl) && str_starts_with($pollUrl, 'http') ? $pollUrl : null;
    }

    /**
     * @param  list<string>  $targets
     * @param  array<string, mixed>|null  $providerPayload
     */
    private function upsertPlannedOutputs(S2sSession $session, S2sSegment $segment, array $targets, string $announcement, ?array $providerPayload, string $errorStatus = 'provider_pending'): void
    {
        // Ephemeral s2s: translated text and audio are never persisted. This
        // used to upsert s2s_outputs rows (transcript + audio path); that was
        // removed so nothing is stored. The translated audio is relayed to the
        // listener inline over SSE and discarded.
        return;
        // @phpstan-ignore-next-line — dead code kept until the method is deleted.
        $providerPayload = $this->unwrapProviderPayload($providerPayload);
        $audioLanguages = $this->config->pipeline()['audio_output_languages'];
        $glossaryEntries = $this->glossary->listAll();
        $sourcePairLanguage = $session->source_lang === 'auto'
            ? 'auto'
            : (string) ($segment->source_language ?: $session->source_lang);
        $normalizedOutputs = $this->providerOutputs($providerPayload);
        $providerSourceText = $this->cleanProviderDraftLabel((string) ($providerPayload['source_text'] ?? $providerPayload['transcript'] ?? ''));
        $providerSawNoSpeech = ($providerPayload['provider_used'] ?? null) === 'sarvam_stt_unavailable'
            && trim($providerSourceText) === '';
        if ($normalizedOutputs === [] && count($targets) === 1 && $this->hasTopLevelOutput($providerPayload)) {
            $normalizedOutputs = [[
                ...$providerPayload,
                'language_code' => $targets[0],
            ]];
        }

        $providerOutputs = collect($normalizedOutputs)
            ->mapWithKeys(function (array $output) use ($targets): array {
                $languageCode = $output['language_code']
                    ?? $output['target_language']
                    ?? $output['target_lang']
                    ?? null;

                $normalized = filled($languageCode) ? $this->normalizeProviderLanguage((string) $languageCode, $targets) : null;

                return filled($normalized) ? [(string) $normalized => $output] : [];
            });

        foreach ($targets as $languageCode) {
            $providerOutput = $providerOutputs->get($languageCode, []);
            $audioCapable = in_array($languageCode, $audioLanguages, true);
            $status = $this->normalizeProviderStatus(
                $providerOutput['status'] ?? ($audioCapable ? $errorStatus : 'fallback_required'),
            );
            $rawTextOutput = $providerOutput['text_output']
                ?? $providerOutput['translated_text']
                ?? $providerOutput['translation']
                ?? $providerOutput['text']
                ?? null;
            $providerAudioMayContainDraftLabel = is_string($rawTextOutput) && $this->hasProviderDraftLabel($rawTextOutput);
            $textOutput = is_string($rawTextOutput) ? $this->cleanProviderDraftLabel($rawTextOutput) : $rawTextOutput;
            // Skip glossary work when the table is empty (the common case
            // today) — both helpers iterate even when there's nothing to do,
            // and a no-op apply still copies/regex's the text.
            $hasGlossary = $glossaryEntries->isNotEmpty();
            $displayTextOutput = ($hasGlossary && is_string($textOutput))
                ? $this->glossary->applyTranslationOverrides($glossaryEntries, $sourcePairLanguage, $languageCode, $textOutput)
                : $textOutput;
            $ttsTextOutput = ($hasGlossary && is_string($displayTextOutput))
                ? $this->glossary->applyPronunciationOverrides($glossaryEntries, $languageCode, $displayTextOutput)
                : $displayTextOutput;
            $glossaryChangedDisplay = is_string($textOutput) && is_string($displayTextOutput) && $textOutput !== $displayTextOutput;
            $glossaryChangedTts = is_string($displayTextOutput) && is_string($ttsTextOutput) && $displayTextOutput !== $ttsTextOutput;
            // Iter 12 (2026-05-27): prefer the signed http(s) URL the
            // ml-gateway puts in `audio_url` (via the /minio-audio/ Caddy
            // proxy) over the s3:// URI in `audio_output_path`. The browser
            // can fetch the https URL directly but not an s3:// reference,
            // which is what saves us the base64 round-trip and the ~80%
            // payload shrink on every status poll + SSE frame.
            $providerHttpAudioUrl = null;
            foreach (['audio_url', 'audio_file_url', 'output_audio_url'] as $urlKey) {
                $candidate = $providerOutput[$urlKey] ?? null;
                if (is_string($candidate) && (str_starts_with($candidate, 'http://') || str_starts_with($candidate, 'https://'))) {
                    $providerHttpAudioUrl = $candidate;
                    break;
                }
            }
            $audioOutputPath = $providerHttpAudioUrl
                ?? $providerOutput['audio_output_path']
                ?? $providerOutput['audio_url']
                ?? $providerOutput['audio_file_url']
                ?? $providerOutput['output_audio_url']
                ?? $providerOutput['output_audio_path']
                ?? null;
            if ($providerAudioMayContainDraftLabel || $glossaryChangedDisplay || $glossaryChangedTts) {
                $audioOutputPath = null;
                $providerHttpAudioUrl = null;
            }
            // When we have a real http(s) audio URL, skip the base64 →
            // disk/data-URL re-encode entirely. Glossary / draft-label
            // cases still go through the local TTS re-synthesis path
            // because the URL points at the pre-glossary audio.
            $storedAudioPath = ($providerAudioMayContainDraftLabel || $glossaryChangedDisplay || $glossaryChangedTts || $providerHttpAudioUrl !== null)
                ? null
                : $this->storeProviderAudio($session, $segment, $languageCode, $providerOutput);
            $ttsFallback = null;
            if ($storedAudioPath === null && ! filled($audioOutputPath) && $audioCapable && filled($ttsTextOutput)) {
                // Run the Laravel-side TTS fallback even for degraded
                // translations — some audio (with a "degraded" badge in the
                // UI) is better than silence. The badge comes from the
                // status field below, which preserves "translation_degraded".
                $ttsFallback = $this->synthesizeTextAudio($session, $segment, $languageCode, (string) $ttsTextOutput);
                $storedAudioPath = $ttsFallback['path'] ?? null;
            }
            if (($storedAudioPath !== null || filled($audioOutputPath)) && $status === $errorStatus) {
                $status = 'completed';
            }
            if ($providerSawNoSpeech && $status === 'provider_pending' && ! filled($displayTextOutput) && $storedAudioPath === null && ! filled($audioOutputPath)) {
                $status = 'no_speech';
            }
            if ($storedAudioPath === null && filled($ttsFallback['error'] ?? null) && $status === $errorStatus) {
                $status = 'provider_error';
            }

            S2sOutput::query()->updateOrCreate(
                [
                    'segment_id' => $segment->id,
                    'language_code' => $languageCode,
                ],
                [
                    'session_id' => $session->id,
                    'channel_name' => $this->languages->label($languageCode),
                    'status' => $status,
                    'text_output' => $displayTextOutput,
                    'audio_output_path' => $storedAudioPath ?? $audioOutputPath,
                    'output_meta' => [
                        'announcement_prefix' => $announcement,
                        'audio_output_supported' => $audioCapable,
                        'glossary' => [
                            'source_lang' => $sourcePairLanguage,
                            'display_changed' => $glossaryChangedDisplay,
                            'tts_changed' => $glossaryChangedTts,
                            'tts_text' => $glossaryChangedTts ? $ttsTextOutput : null,
                        ],
                        'provider_payload' => $this->scrubAudioFields($providerOutput),
                        'provider_response' => $this->scrubAudioFields($providerPayload),
                        'tts_fallback' => $ttsFallback,
                    ],
                ],
            );

            // Phase 4: fan this persisted output out to the per-listener language
            // channel (UN-booth). Off the speaker's hot path; best-effort so a
            // broadcast hiccup never fails segment processing. Audio rides as a
            // URL only when it's browser-fetchable (http) — text-only until MinIO
            // is wired into the gateway, at which point audio flows automatically.
            try {
                $listenerAudioPath = $storedAudioPath ?? $audioOutputPath;
                $listenerAudioUrl = (is_string($listenerAudioPath) && str_starts_with($listenerAudioPath, 'http'))
                    ? $listenerAudioPath
                    : null;
                \App\Modules\SpeechToSpeech\Events\S2sLiveOutput::dispatch(
                    (int) $session->id,
                    (string) $languageCode,
                    [
                        'status' => $status,
                        'text' => $displayTextOutput,
                        'audio_url' => $listenerAudioUrl,
                        'audio_mime' => 'audio/mpeg',
                        'segment_id' => $segment->id,
                    ],
                );
            } catch (\Throwable $e) {
                // listener fan-out is best-effort — never block segment processing
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function mlGatewayAuthHeaders(): array
    {
        $token = trim((string) config('services.ml_gateway.service_token', ''));

        return $token === '' ? [] : ['Authorization' => 'Bearer '.$token];
    }

    private function cleanProviderDraftLabel(string $text): string
    {
        return trim((string) preg_replace(
            '/^\s*\[IndicTrans2(?:\s+deterministic)?(?:\s+draft)?(?:\s+[a-z]{2}(?:-[A-Z]{2})?)?\]\s*/i',
            '',
            $text,
        ));
    }

    private function hasProviderDraftLabel(string $text): bool
    {
        return preg_match('/^\s*\[IndicTrans2(?:\s+deterministic)?(?:\s+draft)?(?:\s+[a-z]{2}(?:-[A-Z]{2})?)?\]\s*/i', $text) === 1;
    }

    /**
     * @return array<string, string>
     */
    private function audioPayload(S2sSegment $segment): array
    {
        $meta = is_array($segment->engine_meta) ? $segment->engine_meta : [];
        $input = is_array($meta['input_audio'] ?? null) ? $meta['input_audio'] : [];
        $inline = is_array($meta['input_audio_inline'] ?? null) ? $meta['input_audio_inline'] : [];

        if (is_string($inline['audio_base64'] ?? null)) {
            return [
                'audio_base64' => (string) $inline['audio_base64'],
                'audio_mime_type' => (string) ($inline['mime_type'] ?? $input['mime_type'] ?? 'audio/webm'),
                'audio_filename' => (string) ($inline['original_name'] ?? $input['original_name'] ?? 'segment.webm'),
            ];
        }

        $path = (string) ($input['path'] ?? $segment->source_audio_path ?? '');

        if ($path === '') {
            return [];
        }

        $audio = $this->audioArchive->audioBytes($segment);
        if ($audio === null || $audio === '') {
            return [];
        }

        return [
            'audio_base64' => base64_encode($audio),
            'audio_mime_type' => (string) ($input['mime_type'] ?? 'audio/webm'),
            'audio_filename' => (string) ($input['original_name'] ?? basename($path)),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function providerOutputs(?array $payload): array
    {
        $payload = $this->unwrapProviderPayload($payload);
        if (! $payload) {
            return [];
        }

        foreach (['outputs', 'translated_segments', 'translations', 'results', 'channels'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $outputs = [];
                foreach ($payload[$key] as $index => $output) {
                    if (! is_array($output)) {
                        continue;
                    }

                    if (is_string($index) && ! isset($output['language_code'], $output['target_language'], $output['target_lang'])) {
                        $output['language_code'] = $index;
                    }

                    $outputs[] = $output;
                }

                return $outputs;
            }
        }

        foreach (['language_code', 'target_language', 'target_lang'] as $key) {
            if (isset($payload[$key])) {
                return [$payload];
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function hasTopLevelOutput(?array $payload): bool
    {
        $payload = $this->unwrapProviderPayload($payload);
        if (! $payload) {
            return false;
        }

        foreach ([
            'text_output',
            'translated_text',
            'translation',
            'text',
            'audio_base64',
            'output_audio_base64',
            'audio_content',
            'audio',
            'audios',
            'audio_output_path',
            'audio_url',
            'audio_file_url',
            'output_audio_url',
            'output_audio_path',
        ] as $key) {
            if (filled($payload[$key] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    private function unwrapProviderPayload(?array $payload): ?array
    {
        if (! $payload) {
            return $payload;
        }

        foreach (['data', 'result', 'payload', 'response'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return $this->unwrapProviderPayload($payload[$key]);
            }
        }

        return $payload;
    }

    /**
     * @param  list<string>  $targets
     */
    private function normalizeProviderLanguage(string $languageCode, array $targets): ?string
    {
        if (in_array($languageCode, $targets, true)) {
            return $languageCode;
        }

        $lower = strtolower($languageCode);
        foreach ($targets as $target) {
            if (strtolower($target) === $lower || strtolower(strtok($target, '-')) === $lower) {
                return $target;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $providerOutput
     */
    private function storeProviderAudio(S2sSession $session, S2sSegment $segment, string $languageCode, array $providerOutput): ?string
    {
        $audioBase64 = $providerOutput['audio_base64']
            ?? $providerOutput['output_audio_base64']
            ?? $providerOutput['audio_content']
            ?? $providerOutput['audio']
            ?? null;
        if (! is_string($audioBase64) && is_array($providerOutput['audios'] ?? null)) {
            $audioBase64 = collect($providerOutput['audios'])->first(fn (mixed $audio): bool => is_string($audio) && trim($audio) !== '');
        }
        $audioDataUrl = $providerOutput['audio_url']
            ?? $providerOutput['audio_file_url']
            ?? $providerOutput['output_audio_url']
            ?? null;

        if ((! is_string($audioBase64) || trim($audioBase64) === '') && is_string($audioDataUrl) && str_starts_with($audioDataUrl, 'data:audio/')) {
            $audioBase64 = $audioDataUrl;
        }

        if (! is_string($audioBase64) || trim($audioBase64) === '') {
            return null;
        }

        $trimmedAudio = trim($audioBase64);
        $dataUrlMime = preg_match('/^data:(audio\/[^;]+);base64,/', $trimmedAudio, $matches) === 1
            ? $matches[1]
            : null;
        $audio = base64_decode(preg_replace('/^data:audio\/[^;]+;base64,/', '', $trimmedAudio), true);
        if (! is_string($audio) || $audio === '') {
            return null;
        }

        $mimeType = (string) ($providerOutput['audio_mime_type'] ?? $providerOutput['mime_type'] ?? $dataUrlMime ?? 'audio/wav');

        // Skip the storage round-trip on the live path: return a
        // data:audio/wav;base64,... URL so the browser plays the audio
        // straight from the JSON response (no extra HTTP fetch). Falls back
        // to disk-write for the listener page if S2S_DISK_AUDIO=1 is set.
        if (! (bool) config('services.s2s.disk_audio', env('S2S_DISK_AUDIO', false))) {
            return 'data:'.$mimeType.';base64,'.base64_encode($audio);
        }

        $extension = match (true) {
            str_contains($mimeType, 'mpeg'), str_contains($mimeType, 'mp3') => 'mp3',
            str_contains($mimeType, 'ogg') => 'ogg',
            str_contains($mimeType, 'webm') => 'webm',
            default => 'wav',
        };
        $safeLanguage = Str::slug($languageCode) ?: 'output';
        $path = "s2s/{$session->id}/segments/{$segment->sequence_no}/{$safeLanguage}.{$extension}";

        try {
            Storage::disk('public')->put($path, $audio);
        } catch (\Throwable) {
            return null;
        }

        return '/storage/'.$path;
    }

    /**
     * @return array{path?: string, error?: string, provider: string, speaker?: string, chunks?: int}
     */
    private function synthesizeTextAudio(S2sSession $session, S2sSegment $segment, string $languageCode, string $text): array
    {
        $apiKey = trim((string) config('services.sarvam.api_key', ''));
        if ($apiKey === '') {
            return [
                'provider' => 'sarvam',
                'error' => 'SARVAM_API_KEY is not configured',
            ];
        }

        $text = trim($text);
        if ($text === '') {
            return [
                'provider' => 'sarvam',
                'error' => 'No text available for TTS',
            ];
        }

        $baseUrl = rtrim((string) config('services.sarvam.api_base', 'https://api.sarvam.ai'), '/');
        $path = '/'.ltrim((string) config('services.sarvam.tts.path', '/text-to-speech'), '/');
        $sessionVoice = data_get($session->engine_meta, 'tts_speaker');
        $speaker = is_string($sessionVoice) && $sessionVoice !== ''
            ? $sessionVoice
            : (string) config('services.sarvam.tts.speaker', 'ritu');
        $codec = (string) config('services.sarvam.tts.codec', 'wav');
        $parts = $this->splitTextForTts($text);
        $audioParts = [];

        foreach ($parts as $part) {
            try {
                $response = Http::timeout((int) config('services.sarvam.timeout', 90))
                    ->withHeaders([
                        'api-subscription-key' => $apiKey,
                        'Content-Type' => 'application/json',
                    ])
                    ->post($baseUrl.$path, [
                        'inputs' => [$part],
                        'target_language_code' => $languageCode,
                        'model' => (string) config('services.sarvam.tts.model', 'bulbul:v3'),
                        'speaker' => $speaker,
                        'speech_sample_rate' => (int) config('services.sarvam.tts.sample_rate', 22050),
                        'pace' => (float) config('services.sarvam.tts.pace', 1.1),
                        'output_audio_codec' => $codec,
                        'enable_preprocessing' => (bool) config('services.sarvam.tts.enable_preprocessing', true),
                    ]);
            } catch (\Throwable $exception) {
                return [
                    'provider' => 'sarvam',
                    'speaker' => $speaker,
                    'chunks' => count($parts),
                    'error' => $exception->getMessage(),
                ];
            }

            $payload = $response->json() ?? [];
            $audioBase64 = is_array($payload) ? collect($payload['audios'] ?? [])->first() : null;
            if ($response->failed() || ! is_string($audioBase64) || trim($audioBase64) === '') {
                return [
                    'provider' => 'sarvam',
                    'speaker' => $speaker,
                    'chunks' => count($parts),
                    'error' => $response->failed()
                        ? 'Sarvam TTS HTTP '.$response->status().': '.Str::limit((string) $response->body(), 180)
                        : 'Sarvam TTS returned no audio',
                ];
            }

            $audio = base64_decode($audioBase64, true);
            if (! is_string($audio) || $audio === '') {
                return [
                    'provider' => 'sarvam',
                    'speaker' => $speaker,
                    'chunks' => count($parts),
                    'error' => 'Sarvam TTS returned invalid audio',
                ];
            }

            $audioParts[] = $audio;
        }

        $extension = match (strtolower($codec)) {
            'mp3', 'mpeg' => 'mp3',
            'ogg' => 'ogg',
            'webm' => 'webm',
            default => 'wav',
        };
        $mimeType = match ($extension) {
            'mp3' => 'audio/mpeg',
            'ogg' => 'audio/ogg',
            'webm' => 'audio/webm',
            default => 'audio/wav',
        };
        $audio = $this->joinAudioParts($audioParts, $extension);

        // Live path: return a data URL instead of disk-writing. Cuts the
        // storage round-trip + the browser's follow-up GET to /storage/...
        if (! (bool) config('services.s2s.disk_audio', env('S2S_DISK_AUDIO', false))) {
            return [
                'provider' => 'sarvam',
                'speaker' => $speaker,
                'chunks' => count($parts),
                'path' => 'data:'.$mimeType.';base64,'.base64_encode($audio),
            ];
        }

        $safeLanguage = Str::slug($languageCode) ?: 'output';
        $audioPath = "s2s/{$session->id}/segments/{$segment->sequence_no}/{$safeLanguage}-tts.{$extension}";

        try {
            Storage::disk('public')->put($audioPath, $audio);
        } catch (\Throwable $exception) {
            return [
                'provider' => 'sarvam',
                'speaker' => $speaker,
                'error' => $exception->getMessage(),
            ];
        }

        return [
            'provider' => 'sarvam',
            'speaker' => $speaker,
            'chunks' => count($parts),
            'path' => '/storage/'.$audioPath,
        ];
    }

    /**
     * @return list<string>
     */
    private function splitTextForTts(string $text): array
    {
        $limit = max(120, (int) config('services.sarvam.tts.max_chars', 450));
        $sentences = preg_split('/(?<=[।.!?])\s+/u', trim($text)) ?: [$text];
        $parts = [];
        $current = '';

        foreach ($sentences as $sentence) {
            $sentence = trim((string) $sentence);
            if ($sentence === '') {
                continue;
            }

            if ($this->textLength($sentence) > $limit) {
                foreach ($this->splitLongTextForTts($sentence, $limit) as $piece) {
                    if ($current !== '') {
                        $parts[] = $current;
                        $current = '';
                    }
                    $parts[] = $piece;
                }
                continue;
            }

            $candidate = trim($current.' '.$sentence);
            if ($current !== '' && $this->textLength($candidate) > $limit) {
                $parts[] = $current;
                $current = $sentence;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts !== [] ? $parts : [trim($text)];
    }

    /**
     * @return list<string>
     */
    private function splitLongTextForTts(string $text, int $limit): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [$text];
        $parts = [];
        $current = '';

        foreach ($words as $word) {
            $word = trim((string) $word);
            if ($word === '') {
                continue;
            }

            $candidate = trim($current.' '.$word);
            if ($current !== '' && $this->textLength($candidate) > $limit) {
                $parts[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    private function textLength(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    }

    /**
     * @param  list<string>  $audioParts
     */
    private function joinAudioParts(array $audioParts, string $extension): string
    {
        if (count($audioParts) <= 1) {
            return $audioParts[0] ?? '';
        }

        if ($extension !== 'wav') {
            return implode('', $audioParts);
        }

        $formatChunk = null;
        $data = '';
        foreach ($audioParts as $part) {
            $parsed = $this->parseWavAudio($part);
            if ($parsed === null) {
                return implode('', $audioParts);
            }
            $formatChunk ??= $parsed['format'];
            $data .= $parsed['data'];
        }

        return 'RIFF'.pack('V', 4 + (8 + strlen($formatChunk)) + (8 + strlen($data))).'WAVE'
            .'fmt '.pack('V', strlen($formatChunk)).$formatChunk
            .'data'.pack('V', strlen($data)).$data;
    }

    /**
     * @return array{format: string, data: string}|null
     */
    private function parseWavAudio(string $audio): ?array
    {
        if (strlen($audio) < 44 || substr($audio, 0, 4) !== 'RIFF' || substr($audio, 8, 4) !== 'WAVE') {
            return null;
        }

        $offset = 12;
        $format = null;
        $data = null;
        $length = strlen($audio);

        while ($offset + 8 <= $length) {
            $chunkId = substr($audio, $offset, 4);
            $chunkSize = unpack('V', substr($audio, $offset + 4, 4))[1] ?? 0;
            $chunkData = substr($audio, $offset + 8, $chunkSize);

            if ($chunkId === 'fmt ') {
                $format = $chunkData;
            } elseif ($chunkId === 'data') {
                $data = $chunkData;
            }

            $offset += 8 + $chunkSize + ($chunkSize % 2);
        }

        return is_string($format) && is_string($data) ? ['format' => $format, 'data' => $data] : null;
    }

    private function normalizeProviderStatus(mixed $status): string
    {
        if (! is_string($status) || trim($status) === '') {
            return 'provider_pending';
        }

        return match (strtolower(trim($status))) {
            'ready', 'success', 'succeeded', 'complete' => 'completed',
            default => $status,
        };
    }

    private function scrubAudioFields(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $scrubbed = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && in_array($key, ['audio_base64', 'output_audio_base64', 'audio_content', 'audio', 'audios'], true)) {
                if (! filled($item)) {
                    $scrubbed[$key] = $item;
                    continue;
                }

                $scrubbed[$key.'_meta'] = is_string($item) ? [
                    'omitted' => true,
                    'bytes_base64' => strlen($item),
                    'sha256' => hash('sha256', $item),
                ] : [
                    'omitted' => true,
                    'items' => is_array($item) ? count($item) : null,
                ];

                continue;
            }

            $scrubbed[$key] = is_array($item) ? $this->scrubAudioFields($item) : $item;
        }

        return $scrubbed;
    }
}

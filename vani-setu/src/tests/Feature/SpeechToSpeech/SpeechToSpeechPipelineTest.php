<?php

namespace Tests\Feature\SpeechToSpeech;

use App\Modules\Core\Models\User;
use App\Modules\Search\Models\StoredArtifact;
use App\Modules\SpeechToSpeech\Models\S2sOutput;
use App\Modules\SpeechToSpeech\Models\S2sGlossaryEntry;
use App\Modules\SpeechToSpeech\Models\S2sSegment;
use App\Modules\SpeechToSpeech\Models\S2sSession;
use App\Modules\SpeechToSpeech\Models\S2sVocabularyRule;
use App\Modules\SpeechToSpeech\Services\Recheck\InternalAudioUrlSigner;
use App\Modules\SpeechToSpeech\Services\S2sGlossaryService;
use App\Modules\SpeechToSpeech\Services\S2sVocabularyService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AssertsAuditChains;
use Tests\Concerns\MakesFakeAudio;
use Tests\TestCase;

class SpeechToSpeechPipelineTest extends TestCase
{
    use AssertsAuditChains;
    use MakesFakeAudio;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Storage::fake('vani_audio');
        Storage::fake('s2s_input_audio');
        Storage::fake('public');
    }

    public function test_admin_can_update_pipeline_config_and_add_vocabulary_rule(): void
    {
        Sanctum::actingAs(User::query()->where('employee_id', 'ADM-001')->firstOrFail());

        $this->putJson('/api/s2s/config', [
            'announcement_prefix' => 'AI translated voice. Please verify against the original voice.',
            'default_mode' => 'live',
            'default_listener_scope' => 'hybrid',
            'default_input_source' => 'microphone',
            'default_source_language' => 'auto',
            'target_languages' => ['en-IN', 'hi-IN', 'ta-IN'],
            'fallback_chain' => [['provider' => 'sarvam', 'role' => 'primary']],
            'archive' => ['store_source_audio' => true, 'store_translated_audio' => true, 'store_transcripts' => true],
            'latency_policy' => ['mode' => 'balanced', 'prefer_realtime' => true],
        ])->assertOk()
            ->assertJsonPath('target_languages.2', 'ta-IN');

        $this->postJson('/api/s2s/vocabulary', [
            'rule_type' => 'correction',
            'language_code' => 'en-IN',
            'source_phrase' => 'Gudia',
            'replacement_text' => 'Gujarati',
            'phonetic_hint' => 'guj-raa-tee',
            'priority' => 20,
        ])->assertCreated()
            ->assertJsonPath('replacement_text', 'Gujarati');

        $this->postJson('/api/s2s/vocabulary', [
            'rule_type' => 'filler',
            'language_code' => 'en-IN',
            'source_phrase' => 'you know',
            'priority' => 30,
        ])->assertCreated()
            ->assertJsonPath('rule_type', 'filler');
    }

    public function test_glossary_matcher_uses_ascii_word_boundaries_and_non_ascii_substrings(): void
    {
        $service = app(S2sGlossaryService::class);
        $entries = collect([
            [
                'src_lang' => 'en-IN',
                'tgt_lang' => 'hi-IN',
                'source_term' => 'RBI',
                'target_term' => 'रिजर्व बैंक',
                'pronunciation' => 'रिजर्व बैंक ऑफ इंडिया',
            ],
            [
                'src_lang' => 'hi-IN',
                'tgt_lang' => 'en-IN',
                'source_term' => 'भारत',
                'target_term' => 'India',
                'pronunciation' => '',
            ],
        ]);

        $this->assertSame(
            'रिजर्व बैंक rules apply, XRBI must not.',
            $service->applyTranslationOverrides($entries, 'en-IN', 'hi-IN', 'RBI rules apply, XRBI must not.'),
        );
        $this->assertSame(
            'जय Indiaमाला',
            $service->applyTranslationOverrides($entries, 'hi-IN', 'en-IN', 'जय भारतमाला'),
        );
        $this->assertSame(
            'रिजर्व बैंक ऑफ इंडिया rules apply.',
            $service->applyPronunciationOverrides($entries, 'hi-IN', 'रिजर्व बैंक rules apply.'),
        );
    }

    public function test_vocabulary_filler_rule_removes_phrase_and_preserves_match_metadata(): void
    {
        S2sVocabularyRule::query()->create([
            'rule_type' => 'filler',
            'language_code' => 'en-IN',
            'source_phrase' => 'you know',
            'priority' => 5,
            'is_active' => true,
        ]);
        S2sVocabularyRule::query()->create([
            'rule_type' => 'correction',
            'language_code' => 'en-IN',
            'source_phrase' => 'Gudia',
            'replacement_text' => 'Gujarati',
            'priority' => 10,
            'is_active' => true,
        ]);

        $result = app(S2sVocabularyService::class)->apply('Well, you know, switch the Gudia channel.', 'en-IN');

        $this->assertSame('Well, switch the Gujarati channel.', $result['clean_text']);
        $this->assertSame('filler', $result['matches'][0]['rule_type']);
        $this->assertSame('', $result['matches'][0]['replacement_text']);
        $this->assertSame('you know', $result['matches'][0]['matched_text']);
        $this->assertSame(['you know'], $result['matches'][0]['matched_texts']);
        $this->assertSame(1, $result['matches'][0]['match_count']);
        $this->assertSame('correction', $result['matches'][1]['rule_type']);
        $this->assertSame('Gujarati', $result['matches'][1]['replacement_text']);
    }

    public function test_public_vocabulary_ui_can_create_and_update_filler_rule(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $rule = $this->postJson('/speech-to-speech/vocabulary', [
            'rule_type' => 'filler',
            'language_code' => 'en-IN',
            'source_phrase' => 'sort of',
            'priority' => 30,
        ])->assertCreated()
            ->assertJsonPath('rule.rule_type', 'filler')
            ->json('rule');

        $this->putJson("/speech-to-speech/vocabulary/{$rule['id']}", [
            'rule_type' => 'filler',
            'language_code' => null,
            'source_phrase' => 'sort of',
            'priority' => 25,
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('rule.priority', 25)
            ->assertJsonPath('rule.is_active', false);

        $this->getJson('/speech-to-speech/vocabulary')
            ->assertOk()
            ->assertJsonPath('items.0.rule_type', 'filler');
    }

    public function test_provider_stt_text_is_cleaned_by_filler_rules_before_persisting(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        S2sVocabularyRule::query()->create([
            'rule_type' => 'filler',
            'language_code' => 'en-IN',
            'source_phrase' => 'you know',
            'priority' => 5,
            'is_active' => true,
        ]);

        Http::fake([
            'http://ml-gateway:8000/v1/speech-to-speech' => Http::response([
                'source_text' => 'Well, you know, the House is live.',
                'source_language' => 'en-IN',
                'outputs' => [
                    [
                        'language_code' => 'hi-IN',
                        'status' => 'completed',
                        'text_output' => 'सदन की कार्यवाही चल रही है।',
                    ],
                ],
            ], 200),
        ]);

        $session = $this->postJson('/speech-to-speech/sessions', [
            'title' => 'Filler Provider Cleanup',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'en-IN',
            'target_langs' => ['hi-IN'],
        ])->assertCreated()->json('session');

        $this->postJson("/speech-to-speech/sessions/{$session['id']}/segments", [
            'sequence_no' => 1,
            'source_language' => 'en-IN',
            'source_text' => 'Provider transcript will replace this.',
        ])->assertOk()
            ->assertJsonPath('segment.source_text', 'Well, the House is live.');

        $segment = S2sSegment::query()->where('session_id', $session['id'])->firstOrFail();
        $this->assertSame('filler', data_get($segment->engine_meta, 'vocabulary_matches.0.rule_type'));
    }

    public function test_public_pipeline_applies_glossary_display_and_pronunciation_overrides(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['services.sarvam.api_key' => 'test-key', 'services.s2s.disk_audio' => true]);

        S2sGlossaryEntry::query()->create([
            'src_lang' => 'en-IN',
            'tgt_lang' => 'hi-IN',
            'source_term' => 'RBI',
            'target_term' => 'रिजर्व बैंक',
            'pronunciation' => 'रिजर्व बैंक ऑफ इंडिया',
            'notes' => '',
        ]);

        Http::fake([
            'http://ml-gateway:8000/v1/speech-to-speech' => Http::response([
                'outputs' => [
                    [
                        'language_code' => 'hi-IN',
                        'status' => 'completed',
                        'text_output' => 'RBI ने CAPEX मंजूर किया।',
                        'audio_output_path' => '/stale-provider-audio.wav',
                    ],
                ],
            ], 200),
            'https://api.sarvam.ai/text-to-speech' => Http::response([
                'audios' => [base64_encode('RIFFtestWAVE')],
            ], 200),
        ]);

        $session = $this->postJson('/speech-to-speech/sessions', [
            'title' => 'Glossary Public Console',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'en-IN',
            'target_langs' => ['hi-IN'],
        ])->assertCreated()->json('session');

        $this->postJson("/speech-to-speech/sessions/{$session['id']}/segments", [
            'sequence_no' => 1,
            'source_language' => 'en-IN',
            'source_text' => 'RBI approved CAPEX.',
        ])->assertOk();

        $output = S2sOutput::query()->where('session_id', $session['id'])->where('language_code', 'hi-IN')->firstOrFail();
        $this->assertSame('रिजर्व बैंक ने CAPEX मंजूर किया।', $output->text_output);
        $this->assertNotSame('/stale-provider-audio.wav', $output->audio_output_path);
        $this->assertTrue((bool) data_get($output->output_meta, 'glossary.display_changed'));
        $this->assertTrue((bool) data_get($output->output_meta, 'glossary.tts_changed'));

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'https://api.sarvam.ai/text-to-speech'
                && ($data['inputs'][0] ?? null) === 'रिजर्व बैंक ऑफ इंडिया ने CAPEX मंजूर किया।';
        });
    }

    public function test_reporter_can_create_session_and_ingest_segment_with_vocabulary_cleanup(): void
    {
        Http::fake([
            'http://ml-gateway:8000/v1/speech-to-speech' => Http::response([
                'source_text' => 'Switch the Gujarati channel to Tamil.',
                'source_language' => 'en-IN',
                'provider_used' => 'text_input',
                'model_version' => 'inline-text',
                'fallback_used' => false,
                'outputs' => [
                    [
                        'language_code' => 'hi-IN',
                        'status' => 'provider_pending',
                        'text_output' => '[IndicTrans2 hi] Switch the Gujarati channel to Tamil.',
                        'audio_output_path' => null,
                        'provider_used' => 'indictrans2',
                        'model_version' => 'indictrans2-deterministic',
                        'fallback_used' => true,
                        'audio_output_supported' => true,
                    ],
                    [
                        'language_code' => 'ta-IN',
                        'status' => 'provider_pending',
                        'text_output' => '[IndicTrans2 ta] Switch the Gujarati channel to Tamil.',
                        'audio_output_path' => null,
                        'provider_used' => 'indictrans2',
                        'model_version' => 'indictrans2-deterministic',
                        'fallback_used' => true,
                        'audio_output_supported' => true,
                    ],
                    [
                        'language_code' => 'ur-IN',
                        'status' => 'fallback_required',
                        'text_output' => '[IndicTrans2 ur] Switch the Gujarati channel to Tamil.',
                        'audio_output_path' => null,
                        'provider_used' => 'indictrans2',
                        'model_version' => 'indictrans2-deterministic',
                        'fallback_used' => true,
                        'audio_output_supported' => false,
                    ],
                ],
            ], 200),
        ]);

        Sanctum::actingAs(User::query()->where('employee_id', 'ADM-001')->firstOrFail());
        $this->postJson('/api/s2s/vocabulary', [
            'rule_type' => 'correction',
            'language_code' => 'en-IN',
            'source_phrase' => 'Gudia',
            'replacement_text' => 'Gujarati',
            'priority' => 10,
        ])->assertCreated();

        Sanctum::actingAs(User::query()->where('employee_id', 'RPT-001')->firstOrFail());
        $session = $this->post('/api/s2s/sessions', [
            'title' => 'House Floor Feed',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'en-IN',
            'target_langs' => ['hi-IN', 'ta-IN', 'ur-IN'],
            'audio' => $this->fakeWavUpload('house-feed.wav', durationMs: 2000),
        ])->assertOk()
            ->assertJsonPath('mode', 'live')
            ->assertJsonPath('available_target_langs.2', 'ur-IN')
            ->json();

        $this->post("/api/s2s/sessions/{$session['id']}/segments", [
            'sequence_no' => 1,
            'start_ms' => 0,
            'end_ms' => 4000,
            'source_language' => 'en-IN',
            'source_text' => 'Switch the Gudia channel to Tamil.',
            'audio' => $this->fakeWavUpload('segment.wav', durationMs: 1500),
        ])->assertOk()
            ->assertJsonPath('segment.source_text', 'Switch the Gujarati channel to Tamil.')
            ->assertJsonPath('dispatch.status', 200);

        $this->assertDatabaseHas('s2s_sessions', ['id' => $session['id'], 'status' => 'processing']);
        $this->assertDatabaseHas('s2s_segments', ['session_id' => $session['id'], 'sequence_no' => 1, 'source_text' => 'Switch the Gujarati channel to Tamil.']);
        $this->assertSame(3, S2sOutput::query()->where('session_id', $session['id'])->count());
        $this->assertDatabaseHas('s2s_outputs', ['session_id' => $session['id'], 'language_code' => 'ur-IN', 'status' => 'fallback_required']);
        $this->assertSame(2, StoredArtifact::query()->where('source_module', 'speech_to_speech')->count());
        $this->assertDatabaseHas('stored_artifacts', ['source_module' => 'speech_to_speech', 'media_family' => 'audio']);
        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'http://ml-gateway:8000/v1/speech-to-speech'
                && ($data['audio_mime_type'] ?? null) === 'audio/wav'
                && base64_decode((string) ($data['audio_base64'] ?? ''), true) !== false
                && ($data['audio_filename'] ?? null) === 'segment.wav'
                && data_get($data, 'stages.stt.model') === 'saaras:v3'
                && data_get($data, 'stages.stt.mode') === 'codemix'
                && data_get($data, 'stages.stt.with_diarization') === false
                && data_get($data, 'stages.translate.model') === 'mayura:v1'
                && data_get($data, 'stages.tts.model') === 'bulbul:v3'
                && data_get($data, 'stages.tts.codec') === 'wav';
        });
        $this->assertAuditActionsChained('speech_to_speech', [
            's2s.session.created',
            's2s.segment.ingested',
        ]);
    }

    public function test_source_audio_archive_is_compressed_and_partitioned_by_device_day_and_hour(): void
    {
        config([
            'filesystems.reporter_audio_disk' => 's2s_input_audio',
            'services.s2s.compress_source_audio' => true,
            'services.s2s.compress_min_bytes' => 1,
        ]);

        Http::fake([
            'http://ml-gateway:8000/v1/speech-to-speech' => Http::response([
                'source_text' => 'The House is live.',
                'source_language' => 'en-IN',
                'outputs' => [
                    [
                        'language_code' => 'hi-IN',
                        'status' => 'completed',
                        'text_output' => 'सदन की कार्यवाही चल रही है।',
                    ],
                ],
            ], 200),
        ]);

        Sanctum::actingAs(User::query()->where('employee_id', 'RPT-001')->firstOrFail());
        $session = $this->postJson('/api/s2s/sessions', [
            'title' => 'Compressed Archive',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'auto',
            'target_langs' => ['hi-IN'],
        ])->assertOk()->json();

        $this->postJson("/api/s2s/sessions/{$session['id']}/segments", [
            'sequence_no' => 1,
            'start_ms' => 0,
            'end_ms' => 3000,
            'source_language' => 'auto',
            'capture_device_id' => 'committee-mic-bank-A',
            'audio' => $this->fakeWavUpload('floor-feed.wav', durationMs: 3000),
        ])->assertOk();

        $segment = S2sSegment::query()->where('session_id', $session['id'])->where('sequence_no', 1)->firstOrFail();
        $input = data_get($segment->engine_meta, 'input_audio');
        $path = (string) $segment->source_audio_path;

        $this->assertSame('gzip', $input['compression']);
        $this->assertTrue((bool) $input['compressed']);
        $this->assertSame(['device', 'daywise', 'hourwise'], $input['archive_layout']['tags']);
        $this->assertSame(
            ['s2s', 'devices', 'microphone', $input['archive_layout']['device_bucket'], $input['archive_layout']['day'], $input['archive_layout']['hour'], 'sessions', (string) $session['id']],
            $input['archive_layout']['hierarchy']
        );
        $this->assertSame($input['archive_layout']['device_bucket'].'/'.$input['archive_layout']['day'].'/'.$input['archive_layout']['hour'], $input['archive_layout']['partition_key']);
        $this->assertStringContainsString('s2s/devices/microphone/device-', $path);
        $this->assertMatchesRegularExpression('#/\d{4}-\d{2}-\d{2}/\d{2}/sessions/'.$session['id'].'/segments/1/floor-feed\.wav\.gz$#', $path);
        Storage::disk('s2s_input_audio')->assertExists($path);

        $stored = Storage::disk('s2s_input_audio')->get($path);
        $decoded = gzdecode($stored);
        $this->assertIsString($decoded);
        $this->assertStringStartsWith('RIFF', $decoded);
        $this->assertSame($input['size'], strlen($decoded));
        $this->assertLessThan($input['size'], $input['stored_size']);
        $download = $this->get(route('public.s2s.segments.audio', ['segment' => $segment->id]));
        $download->assertOk();
        $this->assertSame($decoded, $download->streamedContent());

        $signedUrl = app(InternalAudioUrlSigner::class)->url($segment->id, 300);
        $internalPath = parse_url($signedUrl, PHP_URL_PATH).'?'.parse_url($signedUrl, PHP_URL_QUERY);
        $internal = $this->get($internalPath);
        $internal->assertOk();
        $this->assertSame($decoded, $internal->streamedContent());

        $this->assertDatabaseHas('stored_artifacts', [
            'source_module' => 'speech_to_speech',
            'media_family' => 'audio',
            'storage_path' => $path,
        ]);
    }

    public function test_pipeline_forwards_sarvam_stage_config_without_exposing_api_key_to_proxy(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'services.sarvam.api_key' => 'must-not-be-forwarded',
            'services.sarvam.stt.model' => 'saaras:v3',
            'services.sarvam.stt.mode' => 'codemix',
            'services.sarvam.translate.model' => 'mayura:v1',
            'services.sarvam.translate.mode' => 'formal',
            'services.sarvam.tts.model' => 'bulbul:v3',
            'services.sarvam.tts.pace' => 1.1,
            'services.sarvam.tts.sample_rate' => 22050,
            'services.sarvam.tts.codec' => 'wav',
            'services.ml_gateway.service_token' => '',
        ]);

        Http::fake([
            'http://ml-gateway:8000/v1/speech-to-speech' => Http::response([
                'outputs' => [
                    [
                        'language_code' => 'hi-IN',
                        'status' => 'completed',
                        'text_output' => 'सत्र शुरू है।',
                    ],
                ],
            ], 200),
        ]);

        $session = $this->postJson('/speech-to-speech/sessions', [
            'title' => 'Sarvam Proxy Contract',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'auto',
            'target_langs' => ['hi-IN'],
        ])->assertCreated()->json('session');

        $this->postJson("/speech-to-speech/sessions/{$session['id']}/segments", [
            'sequence_no' => 1,
            'source_language' => 'auto',
            'source_text' => 'Session has started.',
        ])->assertOk()
            ->assertJsonPath('dispatch.status', 200);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'http://ml-gateway:8000/v1/speech-to-speech'
                && ! $request->hasHeader('Authorization')
                && ! $request->hasHeader('api-subscription-key')
                && ($data['stages']['stt']['model'] ?? null) === 'saaras:v3'
                && ($data['stages']['stt']['mode'] ?? null) === 'codemix'
                && ($data['stages']['stt']['with_diarization'] ?? null) === false
                && ($data['stages']['translate']['model'] ?? null) === 'mayura:v1'
                && ($data['stages']['translate']['mode'] ?? null) === 'formal'
                && ($data['stages']['tts']['model'] ?? null) === 'bulbul:v3'
                && ($data['stages']['tts']['pace'] ?? null) === 1.1
                && ($data['stages']['tts']['sample_rate'] ?? null) === 22050
                && ($data['stages']['tts']['codec'] ?? null) === 'wav';
        });
    }

    public function test_public_web_console_can_open_session_and_send_segment(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->withoutExceptionHandling();

        Http::fake([
            'http://ml-gateway:8000/v1/speech-to-speech' => Http::response([
                'outputs' => [
                    [
                        'language_code' => 'hi-IN',
                        'status' => 'provider_pending',
                        'text_output' => '[Hindi] House proceedings are live.',
                        'audio_output_path' => null,
                    ],
                ],
            ], 200),
        ]);

        $this->post('/speech-to-speech/sessions', [
            'title' => 'Public Console Smoke',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'en-IN',
            'target_langs' => ['hi-IN'],
        ])->assertRedirect();

        $session = S2sSession::query()->where('title', 'Public Console Smoke')->firstOrFail();

        $this->post("/speech-to-speech/sessions/{$session->id}/segments", [
            'sequence_no' => 1,
            'start_ms' => 0,
            'end_ms' => 3000,
            'source_language' => 'en-IN',
            'source_text' => 'House proceedings are live.',
        ])->assertRedirect(route('public.s2s.admin', ['selected_session' => $session->id]));

        $this->assertDatabaseHas('s2s_segments', [
            'session_id' => $session->id,
            'sequence_no' => 1,
            'status' => 'processed',
        ]);
        $this->assertDatabaseHas('s2s_outputs', [
            'session_id' => $session->id,
            'language_code' => 'hi-IN',
            'text_output' => '[Hindi] House proceedings are live.',
        ]);
    }

    public function test_public_web_console_suppresses_indictrans2_draft_label_from_outputs(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['services.sarvam.api_key' => 'test-sarvam-key', 'services.s2s.disk_audio' => true]);

        Http::fake([
            'http://ml-gateway:8000/v1/speech-to-speech' => Http::response([
                'source_text' => 'The House is now in session.',
                'source_language' => 'en-IN',
                'outputs' => [
                    [
                        'language_code' => 'hi-IN',
                        'status' => 'completed',
                        'text_output' => '[IndicTrans2 deterministic draft en] सदन की कार्यवाही अब शुरू हो गई है।',
                        'audio_output_path' => '/storage/s2s/clean-hi.wav',
                    ],
                ],
            ], 200),
            'https://api.sarvam.ai/text-to-speech' => Http::response([
                'audios' => [base64_encode('RIFF-clean-hindi-audio')],
            ], 200),
        ]);

        $session = $this->postJson('/speech-to-speech/sessions', [
            'title' => 'Clean IndicTrans2 Label',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'auto',
            'target_langs' => ['hi-IN'],
        ])->assertCreated()->json('session');

        $this->postJson("/speech-to-speech/sessions/{$session['id']}/segments", [
            'sequence_no' => 1,
            'source_language' => 'auto',
            'source_text' => 'The House is now in session.',
        ])->assertOk()
            ->assertJsonPath('session.segments.0.outputs.0.text_output', 'सदन की कार्यवाही अब शुरू हो गई है।')
            ->assertJsonPath('session.segments.0.outputs.0.audio_output_path', "/storage/s2s/{$session['id']}/segments/1/hi-in-tts.wav");

        $this->assertDatabaseHas('s2s_outputs', [
            'session_id' => $session['id'],
            'language_code' => 'hi-IN',
            'text_output' => 'सदन की कार्यवाही अब शुरू हो गई है।',
            'audio_output_path' => "/storage/s2s/{$session['id']}/segments/1/hi-in-tts.wav",
        ]);
    }

    public function test_default_session_targets_hindi_only_to_avoid_unwanted_fanout(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->postJson('/speech-to-speech/sessions', [
            'title' => 'Default Hindi',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'auto',
        ])->assertCreated()
            ->assertJsonPath('session.available_target_langs', ['hi-IN']);
    }

    public function test_text_only_session_target_keeps_hindi_audio_fallback(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->postJson('/speech-to-speech/sessions', [
            'title' => 'Urdu With Audio Fallback',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'auto',
            'target_langs' => ['ur-IN'],
        ])->assertCreated()
            ->assertJsonPath('session.target_lang', 'ur-IN')
            ->assertJsonPath('session.available_target_langs', ['ur-IN', 'hi-IN']);

        Sanctum::actingAs(User::query()->where('employee_id', 'RPT-001')->firstOrFail());

        $this->postJson('/api/s2s/sessions', [
            'title' => 'API Urdu With Audio Fallback',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'auto',
            'target_langs' => ['ur-IN'],
        ])->assertOk()
            ->assertJsonPath('target_lang', 'ur-IN')
            ->assertJsonPath('available_target_langs', ['ur-IN', 'hi-IN']);
    }

    public function test_text_only_mid_session_target_update_keeps_hindi_audio_fallback(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $session = $this->postJson('/speech-to-speech/sessions', [
            'title' => 'Switch To Text Only',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'auto',
            'target_langs' => ['hi-IN'],
        ])->assertCreated()->json('session');

        $this->postJson("/speech-to-speech/sessions/{$session['id']}/targets", [
            'target_langs' => ['ur-IN'],
            'primary_target' => 'ur-IN',
        ])->assertOk()
            ->assertJsonPath('session.target_lang', 'ur-IN')
            ->assertJsonPath('session.available_target_langs', ['ur-IN', 'hi-IN']);
    }

    public function test_public_web_console_json_flow_supports_live_status_polling(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        Http::fake([
            'http://ml-gateway:8000/v1/speech-to-speech' => Http::response([
                'source_text' => 'The House is now in session.',
                'outputs' => [
                    [
                        'language_code' => 'hi-IN',
                        'status' => 'completed',
                        'text_output' => 'सदन की कार्यवाही अब शुरू हो गई है।',
                        'audio_output_path' => '/storage/s2s/live-hi.wav',
                    ],
                ],
            ], 200),
        ]);

        $session = $this->postJson('/speech-to-speech/sessions', [
            'title' => 'Public Live JSON',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'en-IN',
            'target_langs' => ['hi-IN'],
        ])->assertCreated()
            ->assertJsonPath('session.title', 'Public Live JSON')
            ->assertJsonPath('session.available_target_langs.0', 'hi-IN')
            ->json('session');

        $this->postJson("/speech-to-speech/sessions/{$session['id']}/segments", [
            'sequence_no' => 1,
            'start_ms' => 0,
            'end_ms' => 4000,
            'source_language' => 'en-IN',
            'source_text' => 'The House is now in session.',
        ])->assertOk()
            ->assertJsonPath('dispatch.status', 200)
            ->assertJsonStructure([
                'dispatch' => ['server_latency_ms'],
                'session' => ['segments' => [['latency_ms', 'outputs' => [['latency_ms']]]]],
            ])
            ->assertJsonPath('session.segments.0.outputs.0.audio_output_path', '/storage/s2s/live-hi.wav');

        $this->getJson("/speech-to-speech/sessions/{$session['id']}/status")
            ->assertOk()
            ->assertJsonPath('segments.0.source_text', 'The House is now in session.')
            ->assertJsonPath('segments.0.outputs.0.text_output', 'सदन की कार्यवाही अब शुरू हो गई है।');
    }

    public function test_public_web_console_can_translate_first_uploaded_audio_while_opening_session(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['services.s2s.disk_audio' => true]);

        Http::fake([
            'http://ml-gateway:8000/v1/speech-to-speech' => Http::response([
                'source_text' => 'The minister is replying.',
                'source_language' => 'en-IN',
                'outputs' => [
                    [
                        'language_code' => 'hi-IN',
                        'status' => 'completed',
                        'text_output' => 'मंत्री उत्तर दे रहे हैं।',
                        'audio_mime_type' => 'audio/wav',
                        'audio_base64' => base64_encode('RIFF-first-upload'),
                    ],
                ],
            ], 200),
        ]);

        $session = $this->postJson('/speech-to-speech/sessions', [
            'title' => 'One Step Upload',
            'mode' => 'live',
            'input_source' => 'uploaded_file',
            'listener_scope' => 'hybrid',
            'source_lang' => 'auto',
            'target_langs' => ['hi-IN'],
            'audio' => $this->fakeWavUpload('floor-audio.wav', durationMs: 3000),
        ])->assertCreated()
            ->assertJsonPath('message', 'Speech-to-speech session opened and first segment translated.')
            ->assertJsonPath('session.segments.0.source_text', 'The minister is replying.')
            ->assertJsonPath('session.segments.0.outputs.0.text_output', 'मंत्री उत्तर दे रहे हैं।')
            ->json('session');

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'http://ml-gateway:8000/v1/speech-to-speech'
                && ($data['audio_mime_type'] ?? null) === 'audio/wav'
                && ($data['audio_filename'] ?? null) === 'floor-audio.wav';
        });
        Storage::disk('public')->assertExists('s2s/'.$session['id'].'/segments/1/hi-in.wav');
    }

    public function test_public_pipeline_stores_provider_audio_base64_as_browser_playable_file(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['services.s2s.disk_audio' => true]);

        Http::fake([
            'http://ml-gateway:8000/v1/speech-to-speech' => Http::response([
                'source_text' => 'Please start the translation channel.',
                'source_language' => 'en-IN',
                'outputs' => [
                    [
                        'language_code' => 'hi-IN',
                        'text_output' => 'कृपया अनुवाद चैनल शुरू करें।',
                        'audio_mime_type' => 'audio/wav',
                        'audio_base64' => base64_encode('RIFF-vani-setu-audio'),
                    ],
                ],
            ], 200),
        ]);

        $session = $this->postJson('/speech-to-speech/sessions', [
            'title' => 'Public Audio Output',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'auto',
            'target_langs' => ['hi-IN'],
        ])->assertCreated()->json('session');

        $audioPath = $this->postJson("/speech-to-speech/sessions/{$session['id']}/segments", [
            'sequence_no' => 1,
            'source_language' => 'auto',
            'source_text' => 'Please start the translation channel.',
        ])->assertOk()
            ->assertJsonPath('session.segments.0.outputs.0.status', 'completed')
            ->json('session.segments.0.outputs.0.audio_output_path');

        $this->assertSame('/storage/s2s/'.$session['id'].'/segments/1/hi-in.wav', $audioPath);
        Storage::disk('public')->assertExists('s2s/'.$session['id'].'/segments/1/hi-in.wav');
    }

    public function test_pipeline_accepts_single_target_top_level_provider_audio(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['services.s2s.disk_audio' => true]);

        Http::fake([
            'http://ml-gateway:8000/v1/speech-to-speech' => Http::response([
                'source_text' => 'The question hour has started.',
                'translated_text' => 'प्रश्नकाल शुरू हो गया है।',
                'audio_mime_type' => 'audio/wav',
                'audio_base64' => base64_encode('RIFF-top-level-audio'),
            ], 200),
        ]);

        $session = $this->postJson('/speech-to-speech/sessions', [
            'title' => 'Top Level Audio',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'auto',
            'target_langs' => ['hi-IN'],
        ])->assertCreated()->json('session');

        $this->postJson("/speech-to-speech/sessions/{$session['id']}/segments", [
            'sequence_no' => 1,
            'source_language' => 'auto',
            'source_text' => 'Question hour.',
        ])->assertOk()
            ->assertJsonPath('session.segments.0.source_text', 'The question hour has started.')
            ->assertJsonPath('session.segments.0.outputs.0.status', 'completed')
            ->assertJsonPath('session.segments.0.outputs.0.text_output', 'प्रश्नकाल शुरू हो गया है।');

        Storage::disk('public')->assertExists('s2s/'.$session['id'].'/segments/1/hi-in.wav');
    }

    public function test_pipeline_accepts_language_keyed_provider_outputs(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        Http::fake([
            'http://ml-gateway:8000/v1/speech-to-speech' => Http::response([
                'outputs' => [
                    'hi-IN' => [
                        'status' => 'completed',
                        'translated_text' => 'कार्यवाही जारी है।',
                    ],
                    'ta-IN' => [
                        'status' => 'completed',
                        'translated_text' => 'நடவடிக்கை நடைபெற்று வருகிறது.',
                    ],
                ],
            ], 200),
        ]);

        $session = $this->postJson('/speech-to-speech/sessions', [
            'title' => 'Language Keyed Outputs',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'en-IN',
            'target_langs' => ['hi-IN', 'ta-IN'],
        ])->assertCreated()->json('session');

        $this->postJson("/speech-to-speech/sessions/{$session['id']}/segments", [
            'sequence_no' => 1,
            'source_language' => 'en-IN',
            'source_text' => 'Proceedings are underway.',
        ])->assertOk()
            ->assertJsonPath('session.segments.0.outputs.0.text_output', 'कार्यवाही जारी है।')
            ->assertJsonPath('session.segments.0.outputs.1.text_output', 'நடவடிக்கை நடைபெற்று வருகிறது.');
    }

    public function test_pipeline_matches_short_provider_language_codes_to_requested_targets(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        Http::fake([
            'http://ml-gateway:8000/v1/speech-to-speech' => Http::response([
                'outputs' => [
                    [
                        'language_code' => 'hi',
                        'status' => 'completed',
                        'text_output' => 'सत्र शुरू है।',
                    ],
                ],
            ], 200),
        ]);

        $session = $this->postJson('/speech-to-speech/sessions', [
            'title' => 'Short Language Code',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'en-IN',
            'target_langs' => ['hi-IN'],
        ])->assertCreated()->json('session');

        $this->postJson("/speech-to-speech/sessions/{$session['id']}/segments", [
            'sequence_no' => 1,
            'source_language' => 'en-IN',
            'source_text' => 'The session has started.',
        ])->assertOk()
            ->assertJsonPath('session.segments.0.outputs.0.language_code', 'hi-IN')
            ->assertJsonPath('session.segments.0.outputs.0.text_output', 'सत्र शुरू है।');
    }

    public function test_pipeline_stores_provider_data_url_audio(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['services.s2s.disk_audio' => true]);

        Http::fake([
            'http://ml-gateway:8000/v1/speech-to-speech' => Http::response([
                'outputs' => [
                    [
                        'language_code' => 'hi-IN',
                        'text_output' => 'सदस्य बोल रहे हैं।',
                        'audio_url' => 'data:audio/mpeg;base64,'.base64_encode('MP3-audio'),
                    ],
                ],
            ], 200),
        ]);

        $session = $this->postJson('/speech-to-speech/sessions', [
            'title' => 'Data Url Audio',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'en-IN',
            'target_langs' => ['hi-IN'],
        ])->assertCreated()->json('session');

        $audioPath = $this->postJson("/speech-to-speech/sessions/{$session['id']}/segments", [
            'sequence_no' => 1,
            'source_language' => 'en-IN',
            'source_text' => 'The member is speaking.',
        ])->assertOk()
            ->assertJsonPath('session.segments.0.outputs.0.status', 'completed')
            ->json('session.segments.0.outputs.0.audio_output_path');

        $this->assertSame('/storage/s2s/'.$session['id'].'/segments/1/hi-in.mp3', $audioPath);
        Storage::disk('public')->assertExists('s2s/'.$session['id'].'/segments/1/hi-in.mp3');
    }

    public function test_pipeline_normalizes_gateway_ready_status_to_completed(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        Http::fake([
            'http://ml-gateway:8000/v1/speech-to-speech' => Http::response([
                'provider_used' => 'text_input',
                'source_text' => 'The committee is meeting.',
                'outputs' => [
                    [
                        'language_code' => 'hi-IN',
                        'status' => 'ready',
                        'text_output' => 'समिति की बैठक हो रही है।',
                        'audio_output_path' => '/storage/s2s/committee-hi.wav',
                    ],
                ],
            ], 200),
        ]);

        $session = $this->postJson('/speech-to-speech/sessions', [
            'title' => 'Gateway Ready Status',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'en-IN',
            'target_langs' => ['hi-IN'],
        ])->assertCreated()->json('session');

        $this->postJson("/speech-to-speech/sessions/{$session['id']}/segments", [
            'sequence_no' => 1,
            'source_language' => 'en-IN',
            'source_text' => 'The committee is meeting.',
        ])->assertOk()
            ->assertJsonPath('session.segments.0.outputs.0.status', 'completed');

        $this->assertDatabaseHas('s2s_outputs', [
            'session_id' => $session['id'],
            'language_code' => 'hi-IN',
            'status' => 'completed',
        ]);
    }

    public function test_pipeline_stores_sarvam_audios_array_for_single_target_response(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['services.s2s.disk_audio' => true]);

        Http::fake([
            'http://ml-gateway:8000/v1/speech-to-speech' => Http::response([
                'source_text' => 'The motion is adopted.',
                'translated_text' => 'प्रस्ताव स्वीकार किया गया है।',
                'audios' => [base64_encode('RIFF-sarvam-audios-array')],
            ], 200),
        ]);

        $session = $this->postJson('/speech-to-speech/sessions', [
            'title' => 'Sarvam Audios Array',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'en-IN',
            'target_langs' => ['hi-IN'],
        ])->assertCreated()->json('session');

        $audioPath = $this->postJson("/speech-to-speech/sessions/{$session['id']}/segments", [
            'sequence_no' => 1,
            'source_language' => 'en-IN',
            'source_text' => 'The motion is adopted.',
        ])->assertOk()
            ->assertJsonPath('session.segments.0.outputs.0.status', 'completed')
            ->json('session.segments.0.outputs.0.audio_output_path');

        $this->assertSame('/storage/s2s/'.$session['id'].'/segments/1/hi-in.wav', $audioPath);
        Storage::disk('public')->assertExists('s2s/'.$session['id'].'/segments/1/hi-in.wav');
    }

    public function test_api_segment_uses_provider_stt_transcript_and_language(): void
    {
        Http::fake([
            'http://ml-gateway:8000/v1/speech-to-speech' => Http::response([
                'stt' => [
                    'transcript' => 'Budget discussion is underway.',
                    'language_code' => 'en-IN',
                ],
                'outputs' => [
                    [
                        'language_code' => 'hi-IN',
                        'status' => 'provider_pending',
                        'text_output' => 'बजट चर्चा चल रही है।',
                    ],
                ],
            ], 200),
        ]);

        Sanctum::actingAs(User::query()->where('employee_id', 'RPT-001')->firstOrFail());

        $session = $this->postJson('/api/s2s/sessions', [
            'title' => 'Provider STT API',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'auto',
            'target_langs' => ['hi-IN'],
        ])->assertOk()->json();

        $this->postJson("/api/s2s/sessions/{$session['id']}/segments", [
            'sequence_no' => 1,
            'source_language' => 'auto',
            'source_text' => 'Budget.',
        ])->assertOk()
            ->assertJsonPath('segment.source_text', 'Budget discussion is underway.')
            ->assertJsonPath('segment.source_language', 'en-IN');
    }

    public function test_segment_ingest_rejects_empty_audio_and_text(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $session = $this->postJson('/speech-to-speech/sessions', [
            'title' => 'Empty Segment Guard',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'auto',
            'target_langs' => ['hi-IN'],
        ])->assertCreated()->json('session');

        $this->postJson("/speech-to-speech/sessions/{$session['id']}/segments", [
            'sequence_no' => 1,
            'source_language' => 'auto',
            'source_text' => '   ',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('source_text');

        $this->assertDatabaseMissing('s2s_segments', [
            'session_id' => $session['id'],
            'sequence_no' => 1,
        ]);
    }

    public function test_provider_http_failure_marks_segment_degraded_and_outputs_provider_error(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        Http::fake([
            'http://ml-gateway:8000/v1/speech-to-speech' => Http::response(['message' => 'provider unavailable'], 503),
        ]);

        $session = $this->postJson('/speech-to-speech/sessions', [
            'title' => 'Provider Failure',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'en-IN',
            'target_langs' => ['hi-IN'],
        ])->assertCreated()->json('session');

        $this->postJson("/speech-to-speech/sessions/{$session['id']}/segments", [
            'sequence_no' => 1,
            'source_language' => 'en-IN',
            'source_text' => 'This should degrade.',
        ])->assertOk()
            ->assertJsonPath('dispatch.status', 503)
            ->assertJsonPath('session.segments.0.status', 'degraded')
            ->assertJsonPath('session.segments.0.outputs.0.status', 'provider_error');
    }

    public function test_public_web_console_rejects_unknown_language_codes(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->postJson('/speech-to-speech/sessions', [
            'title' => 'Invalid Language',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'auto',
            'target_langs' => ['xx-IN'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('target_langs.0');
    }

    public function test_listener_page_exposes_playable_audio_and_translated_text(): void
    {
        $session = S2sSession::query()->create([
            'title' => 'Listener Playback',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'en-IN',
            'target_lang' => 'hi-IN',
            'available_target_langs' => ['hi-IN'],
            'status' => 'processing',
            'announcement_text' => 'AI translated voice. Please rely on the original voice for confirmation.',
            'started_at' => now(),
        ]);
        $segment = S2sSegment::query()->create([
            'session_id' => $session->id,
            'sequence_no' => 1,
            'start_ms' => 0,
            'end_ms' => 1000,
            'source_language' => 'en-IN',
            'source_text' => 'The House is in session.',
            'status' => 'processed',
        ]);
        S2sOutput::query()->create([
            'session_id' => $session->id,
            'segment_id' => $segment->id,
            'language_code' => 'hi-IN',
            'channel_name' => 'Hindi · हिन्दी',
            'status' => 'completed',
            'text_output' => 'सदन की कार्यवाही चल रही है।',
            'audio_output_path' => '/storage/s2s/listener-hi.wav',
        ]);

        $this->get('/speech-to-speech/listener?target_language=hi-IN')
            ->assertOk()
            ->assertSee('सदन की कार्यवाही चल रही है।')
            ->assertSee('src="/storage/s2s/listener-hi.wav"', false);
    }

    public function test_listener_page_falls_back_to_audible_language_for_text_only_target_without_missing_script(): void
    {
        $session = S2sSession::query()->create([
            'title' => 'Listener Fallback Playback',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'en-IN',
            'target_lang' => 'ur-IN',
            'available_target_langs' => ['ur-IN', 'hi-IN'],
            'status' => 'processing',
            'started_at' => now(),
        ]);
        $segment = S2sSegment::query()->create([
            'session_id' => $session->id,
            'sequence_no' => 1,
            'start_ms' => 0,
            'end_ms' => 1000,
            'source_language' => 'en-IN',
            'source_text' => 'The House is in session.',
            'status' => 'processed',
        ]);
        S2sOutput::query()->create([
            'session_id' => $session->id,
            'segment_id' => $segment->id,
            'language_code' => 'ur-IN',
            'channel_name' => 'Urdu · اردو',
            'status' => 'completed',
            'text_output' => 'ایوان کی کارروائی جاری ہے۔',
            'audio_output_path' => null,
        ]);
        S2sOutput::query()->create([
            'session_id' => $session->id,
            'segment_id' => $segment->id,
            'language_code' => 'hi-IN',
            'channel_name' => 'Hindi · हिन्दी',
            'status' => 'completed',
            'text_output' => 'सदन की कार्यवाही चल रही है।',
            'audio_output_path' => '/storage/s2s/listener-fallback-hi.wav',
        ]);

        $this->get('/speech-to-speech/listener?target_language=ur-IN')
            ->assertOk()
            ->assertSee('data-target-language="ur-IN"', false)
            ->assertSee('data-target-languages="ur-IN,hi-IN"', false)
            ->assertSee('data-fallback-applied="true"', false)
            ->assertSee('ایوان کی کارروائی جاری ہے۔')
            ->assertSee('src="/storage/s2s/listener-fallback-hi.wav"', false)
            ->assertDontSee('transcript.jsx');
    }

    public function test_listener_page_normalizes_unknown_target_language(): void
    {
        $this->get('/speech-to-speech/listener?target_language=xx-IN')
            ->assertOk()
            ->assertSee('data-target-language="en-IN"', false)
            ->assertSee('data-requested-target-language="xx-IN"', false)
            ->assertDontSee('transcript.jsx');
    }

    public function test_live_path_returns_inline_audio_data_url_instead_of_disk_file(): void
    {
        // Live captioning default: skip disk write, embed audio as
        // data:audio/wav;base64,... so the browser plays it from the JSON
        // response without a second /storage/ GET.
        $this->withoutMiddleware(ValidateCsrfToken::class);

        Http::fake([
            'http://ml-gateway:8000/v1/speech-to-speech' => Http::response([
                'source_text' => 'The motion is adopted.',
                'translated_text' => 'प्रस्ताव स्वीकार किया गया है।',
                'audio_mime_type' => 'audio/wav',
                'audio_base64' => base64_encode('RIFF-inline-audio-bytes'),
            ], 200),
        ]);

        $session = $this->postJson('/speech-to-speech/sessions', [
            'title' => 'Inline Audio Data URL',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'en-IN',
            'target_langs' => ['hi-IN'],
        ])->assertCreated()->json('session');

        $audioPath = $this->postJson("/speech-to-speech/sessions/{$session['id']}/segments", [
            'sequence_no' => 1,
            'source_language' => 'en-IN',
            'source_text' => 'The motion is adopted.',
        ])->assertOk()
            ->assertJsonPath('session.segments.0.outputs.0.status', 'completed')
            ->json('session.segments.0.outputs.0.audio_output_path');

        $this->assertStringStartsWith('data:audio/wav;base64,', $audioPath);
        $this->assertSame('RIFF-inline-audio-bytes', base64_decode(substr($audioPath, strlen('data:audio/wav;base64,'))));
        // No disk write happened.
        Storage::disk('public')->assertMissing('s2s/'.$session['id'].'/segments/1/hi-in.wav');
    }

    public function test_mid_session_target_change_routes_subsequent_segments_to_new_language(): void
    {
        // Regression for the silence bug: users would start with Hindi as
        // their output language, change the dropdown to (say) Tamil, then
        // hear nothing because the server session's available_target_langs
        // was never updated. The fix calls /sessions/{id}/targets on every
        // dropdown change; this test pins down that contract.
        $this->withoutMiddleware(ValidateCsrfToken::class);

        Http::fake([
            'http://ml-gateway:8000/v1/speech-to-speech' => Http::sequence()
                ->push([
                    'outputs' => [[
                        'language_code' => 'hi-IN',
                        'status' => 'completed',
                        'text_output' => 'पहले संदेश की हिन्दी अनुवाद।',
                    ]],
                ], 200)
                ->push([
                    'outputs' => [[
                        'language_code' => 'ta-IN',
                        'status' => 'completed',
                        'text_output' => 'இரண்டாவது செய்தியின் தமிழ் மொழிபெயர்ப்பு.',
                    ]],
                ], 200),
        ]);

        $session = $this->postJson('/speech-to-speech/sessions', [
            'title' => 'Mid-Session Target Change',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'en-IN',
            'target_langs' => ['hi-IN'],
        ])->assertCreated()->json('session');

        $this->postJson("/speech-to-speech/sessions/{$session['id']}/segments", [
            'sequence_no' => 1,
            'source_language' => 'en-IN',
            'source_text' => 'First message.',
        ])->assertOk()
            ->assertJsonPath('session.segments.0.outputs.0.language_code', 'hi-IN');

        $this->postJson("/speech-to-speech/sessions/{$session['id']}/targets", [
            'target_langs' => ['ta-IN'],
            'primary_target' => 'ta-IN',
        ])->assertOk()
            ->assertJsonPath('session.available_target_langs.0', 'ta-IN');

        $this->postJson("/speech-to-speech/sessions/{$session['id']}/segments", [
            'sequence_no' => 2,
            'source_language' => 'en-IN',
            'source_text' => 'Second message after switching to Tamil.',
        ])->assertOk();

        // The second segment must have produced a ta-IN output, NOT hi-IN.
        $this->assertDatabaseHas('s2s_outputs', [
            'session_id' => $session['id'],
            'segment_id' => S2sSegment::query()
                ->where('session_id', $session['id'])->where('sequence_no', 2)
                ->value('id'),
            'language_code' => 'ta-IN',
            'status' => 'completed',
        ]);
        $this->assertDatabaseMissing('s2s_outputs', [
            'session_id' => $session['id'],
            'segment_id' => S2sSegment::query()
                ->where('session_id', $session['id'])->where('sequence_no', 2)
                ->value('id'),
            'language_code' => 'hi-IN',
        ]);

        // The dispatch payload for the second segment must use ta-IN — this
        // is what tells the ml-gateway to translate into Tamil.
        Http::assertSent(function ($request): bool {
            $data = $request->data();
            return $request->url() === 'http://ml-gateway:8000/v1/speech-to-speech'
                && ($data['target_languages'] ?? []) === ['ta-IN'];
        });
    }

    public function test_session_finish_closes_archive_manifest(): void
    {
        $session = S2sSession::query()->create([
            'title' => 'Manual Session',
            'mode' => 'upload',
            'input_source' => 'uploaded_file',
            'listener_scope' => 'outside_house',
            'source_lang' => 'gu-IN',
            'target_lang' => 'ta-IN',
            'available_target_langs' => ['ta-IN'],
            'status' => 'processing',
            'started_at' => now(),
        ]);

        Sanctum::actingAs(User::query()->where('employee_id', 'RPT-001')->firstOrFail());

        $this->postJson("/api/s2s/sessions/{$session->id}/finish")
            ->assertOk()
            ->assertJsonPath('status', 'finished');

        $this->assertNotNull($session->fresh()->finished_at);
        $this->assertAuditActionsChained('speech_to_speech', [
            's2s.session.finished',
        ]);
    }
}

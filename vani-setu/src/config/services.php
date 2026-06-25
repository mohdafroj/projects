<?php

return [

    'delivery_channels' => [
        'send_email' => env('NOTIFICATION_SEND_EMAIL', true),
        'send_sms' => env('NOTIFICATION_SEND_SMS', false),
        'send_whatsapp' => env('NOTIFICATION_SEND_WHATSAPP', false),
    ],

    'notification_engine' => [
        'service_tokens' => array_filter(array_map('trim', explode(',', env('NOTIFICATION_ENGINE_SERVICE_TOKENS', '')))),
    ],

    'sms_gateway' => [
        'api_url' => env('SMS_GATEWAY_API_URL', ''),
        'username' => env('SMS_GATEWAY_USERNAME', ''),
        'pin' => env('SMS_GATEWAY_PIN', ''),
        'signature' => env('SMS_GATEWAY_SIGNATURE', ''),
        'dlt_entity_id' => env('SMS_GATEWAY_DLT_ENTITY_ID', ''),
        'timeout' => env('SMS_GATEWAY_TIMEOUT', 10),
    ],

    'whatsapp_gateway' => [
        'api_url' => env('WHATSAPP_GATEWAY_API_URL', ''),
        'token' => env('WHATSAPP_GATEWAY_TOKEN', ''),
        'timeout' => env('WHATSAPP_GATEWAY_TIMEOUT', 10),
    ],

    'ml_gateway' => [
        'url' => env('ML_GATEWAY_URL', 'http://ml-gateway:8000'),
        'service_token' => env('ML_GATEWAY_SERVICE_TOKEN', ''),
    ],

    'synopsis' => [
        'model_url' => env('SYNOPSIS_MODEL_URL', ''),
        'allowed_hosts' => array_filter(array_map('trim', explode(',', env('SYNOPSIS_MODEL_ALLOWED_HOSTS', 'ml-gateway')))),
        'model' => env('SYNOPSIS_MODEL', 'vani-setu-synopsis'),
        'token' => env('SYNOPSIS_MODEL_TOKEN', ''),
        'timeout' => env('SYNOPSIS_MODEL_TIMEOUT', 20),
        'retries' => env('SYNOPSIS_MODEL_RETRIES', 1),
        'retry_sleep_ms' => env('SYNOPSIS_MODEL_RETRY_SLEEP_MS', 250),
    ],

    'tijori' => [
        'asr_url' => env('TIJORI_ASR_URL', 'http://tijori-router.tijori-system.svc.cluster.local/v1/asr'),
        'token' => env('TIJORI_SERVICE_TOKEN', ''),
        'timeout' => env('TIJORI_ASR_TIMEOUT', 20),
        'retries' => env('TIJORI_ASR_RETRIES', 2),
        'retry_sleep_ms' => env('TIJORI_ASR_RETRY_SLEEP_MS', 250),
    ],

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://meilisearch:7700'),
        'key' => env('MEILISEARCH_KEY'),
    ],

    // Sarvam constants pinned from the Windows PoC (Ankit Chansoria, 2026-05-21).
    // Full handover: docs/vani-setu-poc-handover.md.
    'sarvam' => [
        'api_key' => env('SARVAM_API_KEY', ''),
        'api_base' => env('SARVAM_API_BASE', 'https://api.sarvam.ai'),
        // Internal voice-pipeline URL (ml-gateway proxy that fronts Sarvam +
        // Tijori). Laravel uses this for STT/translation and only calls Sarvam
        // TTS directly as a fallback when the gateway returns text without audio.
        // Empty = plan only (dispatch returns status='planned').
        'voice_pipeline_url' => env('SARVAM_VOICE_PIPELINE_URL', rtrim(env('ML_GATEWAY_URL', 'http://ml-gateway:8000'), '/').'/v1/speech-to-speech'),
        // Optional override for the SSE streaming endpoint. When unset,
        // SarvamSpeechPipeline derives it from voice_pipeline_url by
        // appending /stream. Useful when streaming traffic needs to hit
        // a different ingress (e.g. a Caddy listener that disables
        // response buffering globally).
        'voice_pipeline_stream_url' => env('SARVAM_VOICE_PIPELINE_STREAM_URL', ''),
        'timeout' => env('SARVAM_TIMEOUT', 90),
        'retries' => env('SARVAM_RETRIES', 1),
        'retry_sleep_ms' => env('SARVAM_RETRY_SLEEP_MS', 250),

        'stt' => [
            'model' => env('SARVAM_STT_MODEL', 'saaras:v3'),
            // codemix keeps Hindi in Devanagari and English in Latin in the
            // same transcript. transcribe normalises; saarika:v2.5 silently
            // ignores the `mode` field and transliterates English to Devanagari.
            'mode' => env('SARVAM_STT_MODE', 'codemix'),
            'path' => env('SARVAM_STT_PATH', '/speech-to-text'),
            // Diarization is batch-only — real-time /speech-to-text rejects
            // with_diarization=true with HTTP 400. Do NOT enable on live path.
            'with_diarization' => false,
        ],
        'translate' => [
            'model' => env('SARVAM_TRANSLATE_MODEL', 'mayura:v1'),
            'mode' => env('SARVAM_TRANSLATE_MODE', 'formal'),
            'path' => env('SARVAM_TRANSLATE_PATH', '/translate'),
            'enable_preprocessing' => true,
        ],
        'tts' => [
            'model' => env('SARVAM_TTS_MODEL', 'bulbul:v3'),
            'path' => env('SARVAM_TTS_PATH', '/text-to-speech'),
            'speaker' => env('SARVAM_TTS_SPEAKER', 'ritu'),
            'pace' => env('SARVAM_TTS_PACE', 1.1),
            'sample_rate' => env('SARVAM_TTS_SAMPLE_RATE', 22050),
            // Stays `wav` because the chamber-side player decodes via wave.open.
            // Switching to mp3/opus needs a chunk-fed player + decoder, not a flag flip.
            'codec' => env('SARVAM_TTS_CODEC', 'wav'),
            'enable_preprocessing' => true,
        ],
    ],

    's2s' => [
        'streaming_tts' => (bool) env('S2S_STREAMING_TTS', false),
        'disk_audio' => (bool) env('S2S_DISK_AUDIO', false),
        // Iter-21 latency: defer the source-audio archive (compression +
        // disk/MinIO write) out of the SSE critical path. The live forward to
        // ml-gateway only needs the in-memory inline payload, so the heavy
        // archive write runs AFTER the stream finishes rather than blocking
        // first-audio. The uploaded temp file stays valid for the duration of
        // the streamed response, so replay/QA archival is unaffected.
        'defer_source_archive' => (bool) env('S2S_DEFER_SOURCE_ARCHIVE', true),
        // Lossless archive compression for source WAV/PCM chunks. The live
        // request path still forwards the original in-memory audio so this
        // saves server storage without adding translation latency.
        'compress_source_audio' => (bool) env('S2S_COMPRESS_SOURCE_AUDIO', true),
        'compress_min_bytes' => (int) env('S2S_COMPRESS_MIN_BYTES', 65536),
        // Finished-session source audio is useful for QA and replay, but it
        // should not grow forever on the app server. Set <=0 to disable.
        'audio_archive_retention_days' => (int) env('S2S_AUDIO_ARCHIVE_RETENTION_DAYS', 30),
        'audio_archive_prune_batch' => (int) env('S2S_AUDIO_ARCHIVE_PRUNE_BATCH', 500),
        // Recent live SLO used by provider health. p95 above the degraded
        // threshold means Members will feel the delay even when requests pass.
        'latency_health_window_minutes' => (int) env('S2S_LATENCY_HEALTH_WINDOW_MINUTES', 30),
        'latency_p95_watch_ms' => (int) env('S2S_LATENCY_P95_WATCH_MS', 3500),
        'latency_p95_degraded_ms' => (int) env('S2S_LATENCY_P95_DEGRADED_MS', 6000),
        // Client-side chamber failures are tracked separately from provider
        // HTTP failures. Repeated live streaming errors for one kind/language
        // should degrade readiness before the total browser-error count gets high.
        'client_error_degraded_threshold' => (int) env('S2S_CLIENT_ERROR_DEGRADED_THRESHOLD', 5),
        'client_live_kind_degraded_threshold' => (int) env('S2S_CLIENT_LIVE_KIND_DEGRADED_THRESHOLD', 3),
        'client_language_degraded_threshold' => (int) env('S2S_CLIENT_LANGUAGE_DEGRADED_THRESHOLD', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'sb_iam' => [
        'url' => env('SB_IAM_URL', 'https://ft.rajyasabha.digital'),
        'client_id' => env('SB_IAM_CLIENT_ID'),
        'client_secret' => env('SB_IAM_CLIENT_SECRET'),
    ],

    'asr' => [
        'ingest_secret' => env('ASR_INGEST_SECRET'),
        'realtime_audit_secret' => env('REALTIME_AUDIT_SECRET'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'artifact_catalog' => [
        'default_disk' => env('ARTIFACT_CATALOG_DISK', 'vani_artifacts'),
        'monitored_locations' => [
            [
                'disk' => env('ARTIFACT_CATALOG_DISK', 'vani_artifacts'),
                'prefix' => env('ARTIFACT_CATALOG_PREFIX', ''),
            ],
        ],
    ],

    's2s_recheck' => [
        // Driver name: 'null' (default, surfaces 'failed' until configured)
        // or 'ml_gateway' (real second-pass ASR via the gateway).
        'transcriber' => env('S2S_RECHECK_TRANSCRIBER', 'null'),
        // ml-gateway base URL on the docker network.
        'gateway_base_url' => env('S2S_RECHECK_GATEWAY_URL', 'http://vani-setu-ml-gateway:8000'),
        // Internal app base URL that the ml-gateway uses to fetch
        // segment audio over the HMAC-signed route. Resolves on the
        // docker network; not exposed externally.
        'internal_base_url' => env('S2S_RECHECK_INTERNAL_URL', 'http://vani-setu-web'),
        // Seconds the signed audio URL is valid for.
        'url_ttl' => (int) env('S2S_RECHECK_URL_TTL', 300),
        // HTTP timeout (s) on the gateway call.
        'timeout' => (float) env('S2S_RECHECK_TIMEOUT', 25.0),
        // WER threshold above which a segment is flagged drift.
        'drift_threshold' => (float) env('S2S_RECHECK_DRIFT_THRESHOLD', 0.15),
        // Second-pass confidence needed to auto-write a corrected
        // transcript instead of just flagging drift.
        'correction_confidence' => (float) env('S2S_RECHECK_CORRECTION_CONFIDENCE', 0.85),
        // Off by default. When on, S2sController::finish enqueues
        // RecheckSessionJob (added in a follow-up slice).
        'auto_dispatch' => (bool) env('S2S_RECHECK_AUTO_DISPATCH', false),
        // Self-retry of qa_state='failed' segments (slice G). Picks up
        // segments where qa_attempts < max_attempts AND qa_checked_at
        // is older than cool_down_seconds, re-dispatches the recheck
        // job. Lets the engine recover automatically once upstream
        // stabilises instead of needing a manual `s2s:recheck --force`.
        'retry_max_attempts' => (int) env('S2S_RECHECK_RETRY_MAX_ATTEMPTS', 3),
        'retry_cool_down_seconds' => (int) env('S2S_RECHECK_RETRY_COOL_DOWN', 300),
        'retry_batch_size' => (int) env('S2S_RECHECK_RETRY_BATCH', 50),
    ],

];

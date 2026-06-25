# Tijori / Vani Audio and AI Architecture

## Decision

Vani Setu is a consumer of common services. It must not own ASR model selection,
vendor fallback, model weights, or direct Sarvam/Bhashini/Ollama/vLLM calls.

The corrected boundary is:

1. Vani receives reporter audio chunks.
2. Vani writes raw and final audio objects to MinIO through the `vani_audio`
   disk.
3. Vani records only object URI, checksum, byte count, slot id and audit
   metadata in its own database/audit log.
4. Vani calls Tijori `/v1/asr` with the audio object or multipart audio.
5. Tijori routes the request:
   - Sarvam Saarika for Vani proceedings where enabled, budgeted and
     credentialed.
   - On-prem Whisper large-v3 as fallback.
   - Future GPU-backed Whisper/Triton can replace the local runtime without
     changing Vani.
6. Translation stays inside Tijori:
   - Bhashini first when credentials are available.
   - IndicTrans2 as on-prem continuity/fallback.
7. LLM text-to-text stays inside Tijori:
   - vLLM primary for open-weight models.
   - Ollama fallback when enabled for dev/demo/offline operation.
8. Speech-to-speech remains a disabled placeholder until voice policy,
   licensing and consent controls are complete.

## Vani Integration Points

- `config/filesystems.php`
  - `REPORTER_AUDIO_DISK=vani_audio`
  - `VANI_MINIO_*`
  - `VANI_AUDIO_BUCKET=vani-audio-raw-rs`
- `config/services.php`
  - `TIJORI_ASR_URL`
  - `TIJORI_SERVICE_TOKEN`
  - retry/timeout knobs
- `app/Modules/Capture/Services/ReporterAudioStorage.php`
  - stores reporter audio chunks and final slot audio in MinIO.
- `app/Modules/Capture/Services/TijoriAsrGateway.php`
  - routes final audio to Tijori.
- `app/Modules/Capture/routes-api.php`
  - writes audit rows for chunk storage and ASR dispatch without leaking audio.

## Tijori Integration Points

- `src/tijori_setu/api/asr.py`
  - common `/v1/asr` and explicit `/v1/asr/onprem` / `/v1/asr/sarvam`.
- `src/tijori_setu/routing/asr.py`
  - Sarvam-first and on-prem routing.
- `src/tijori_setu/clients/whisper_local.py`
  - current on-prem Whisper implementation.
- `src/tijori_setu/api/translate.py`
  - Bhashini to IndicTrans2 translation chain.
- `src/tijori_setu/clients/ollama.py`
  - optional Ollama LLM fallback.
- `src/tijori_setu/api/models.py`
  - machine-readable catalogue of approved, fallback and placeholder engines.

## Operating Rule

All Setus call Tijori capability APIs. Individual Setus never call model
runtimes directly. This keeps audit, budget, no-training guardrails, fallback
and licence review in one service boundary.

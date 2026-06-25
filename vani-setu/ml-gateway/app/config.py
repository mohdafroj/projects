from functools import lru_cache
from pathlib import Path

from pydantic import Field, HttpUrl
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", env_file_encoding="utf-8", extra="ignore")

    sarvam_api_key: str | None = None
    sarvam_key_file: Path = Path("/run/secrets/sarvam_key")
    sarvam_base_url: str = "https://api.sarvam.ai"
    sarvam_asr_path: str = "/speech-to-text"
    sarvam_tts_path: str = "/text-to-speech"
    sarvam_translate_path: str = "/translate"
    sarvam_translate_model: str = "mayura:v1"
    sarvam_translate_mode: str = "formal"
    sarvam_translate_speaker_gender: str = "Male"
    sarvam_timeout_seconds: float = 20.0
    # Idle lifetime (seconds) of pooled keepalive connections to api.sarvam.ai.
    # The first call after the pool goes cold pays a full TLS+DNS handshake,
    # which adds ~1.1s to that utterance's translate (measured 2026-06-14:
    # 5-min-idle xlate ~1083ms vs warm ~420ms). httpx's default is 60s, so any
    # inter-speaker pause or short recess longer than a minute re-incurs it.
    # Raised to 600s so connections survive typical proceedings pauses; this is
    # a TCP/TLS-layer keepalive (socket kept open) — NOT extra Sarvam API calls,
    # so it respects the judicious-paid-use policy. Tunable via env without a
    # rebuild. Sarvam may still close the socket server-side, after which httpx
    # transparently reconnects.
    sarvam_http_keepalive_expiry: float = 600.0
    # Tight per-call budget for Sarvam /translate. Slow calls (typically
    # pa-IN and other long-tail languages where mayura:v1 takes a slower
    # path on Sarvam's side) abort here and fall through to IndicTrans2
    # immediately, instead of stalling the live S2S pipeline for 10-15 s
    # while waiting for a successful-but-slow upstream response.
    sarvam_translate_timeout_seconds: float = 4.0
    # Per-call budget for Sarvam /speech-to-text on live segments. Caps
    # worst-case live latency: a slow STT raises here rather than
    # holding the segment for the full 20 s global timeout. Timed-out
    # segments fall through to local Whisper (when whisper_enabled)
    # before giving up; if Whisper is also off they go through with
    # empty source_text and the recheck engine retries in the
    # background once upstream stabilises.
    sarvam_stt_timeout_seconds: float = 8.0

    # Local Whisper STT fallback. When enabled, segments that fail the
    # Sarvam STT path (timeout, 4xx/5xx, hallucination filter) get a
    # second chance via faster-whisper running in-process on CPU. Off
    # by default because the model has to be downloaded on first use
    # (~140 MB for "base") and inference adds ~1-3 s of CPU work per
    # segment — worth it as a fallback, not as a primary path. Flip
    # ML_GATEWAY_WHISPER_ENABLED=true to activate.
    whisper_enabled: bool = False
    whisper_model_size: str = "base"
    whisper_compute_type: str = "int8"
    whisper_device: str = "cpu"
    # Preferred Whisper backend: an HTTP POST to the existing
    # tijori-dev-whisper sidecar (Tijori CPU model services
    # POST /v1/transcribe). Weights are already deployed there; only
    # the docker-network bridge is needed for vani-setu-ml-gateway to
    # reach this URL. When set + reachable, _try_whisper_stt prefers
    # this over in-process faster-whisper. Falls back to local
    # automatically on HTTP failure so a single network blip doesn't
    # lose segments.
    tijori_whisper_url: str | None = None
    tijori_whisper_timeout_seconds: float = 12.0

    bhashini_base_url: str = "https://dhruva-api.bhashini.gov.in"
    bhashini_inference_path: str = "/services/inference/pipeline"
    bhashini_api_key: str | None = None
    bhashini_timeout_seconds: float = 20.0

    indictrans2_endpoint_url: HttpUrl | None = None
    indictrans2_model_name: str = "ai4bharat/indictrans2-en-indic-1B"
    indictrans2_local_model_enabled: bool = False

    pyannote_endpoint_url: HttpUrl | None = None
    pyannote_auth_token: str | None = None

    vani_api_url: str | None = None
    vani_asr_ingest_secret: str | None = None
    callback_timeout_seconds: float = 5.0
    ml_gateway_auth_required: bool = False
    ml_gateway_service_token: str | None = None
    cors_origins: list[str] = Field(
        default_factory=lambda: ["http://localhost:5173", "http://127.0.0.1:5173"]
    )

    provider_precedence: list[str] = Field(
        default_factory=lambda: ["sarvam", "bhashini", "whisper"]
    )
    proceedings_provider: str = "sarvam"
    sarvam_languages: set[str] = Field(
        default_factory=lambda: {"hi", "en", "ta", "te", "kn", "ml", "bn", "gu", "mr", "pa", "od"}
    )
    bhashini_languages: set[str] = Field(
        default_factory=lambda: {
            "as",
            "bn",
            "brx",
            "doi",
            "gu",
            "hi",
            "kn",
            "ks",
            "kok",
            "mai",
            "ml",
            "mni",
            "mr",
            "ne",
            "od",
            "pa",
            "sa",
            "sat",
            "sd",
            "ta",
            "te",
            "ur",
        }
    )

    retry_attempts: int = 3
    circuit_failure_threshold: int = 3
    circuit_reset_seconds: float = 30.0

    # Process-wide cap on concurrent in-flight Sarvam HTTP calls (STT,
    # translate, TTS). Iter-19a parallelised per-language TTS fanout in the
    # streaming path; without a gate, a 22-language session would dispatch
    # 22 concurrent TTS POSTs against the same API key and trip Sarvam's
    # per-key QPS limit.
    #
    # Measured 2026-06-14 (s2s-latency-probe). With the FULL 22-language fanout
    # (11 audio + 11 text-only translate-only), the text-only translate calls
    # compete for slots and delay audio TTS: at limit=6 the audio tail was
    # 3.9s, at 12 it was ~2.6s. At 16 the audio tail dropped to a stable
    # 1.2-1.5s across all 11 audio languages; 24 was no better and noisier
    # (1.2-2.8s — Sarvam-side variance dominates once nothing queues). Sarvam
    # showed NO rate-limiting up to 24 concurrent, so 16 sits comfortably under
    # the contracted QPS ceiling while giving the 11 audio languages full
    # parallelism plus headroom over text-only translates. Concurrency bursts
    # the same number of calls, not more — no extra paid spend, just lower tail.
    # Env-tunable via SARVAM_CONCURRENCY_LIMIT; revisit for concurrent
    # multi-speaker load (the cap is process-wide across all sessions).
    sarvam_concurrency_limit: int = 16

    # Per-sentence streaming TTS: when enabled, /v1/speech-to-speech/stream
    # dispatches Sarvam TTS one sentence at a time and yields SSE audio
    # chunks so the browser can begin playback before later sentences
    # finish synthesising. The batched /v1/speech-to-speech endpoint stays
    # the default; this flag opts in callers that have wired the SSE
    # consumer end-to-end.
    enable_s2s_streaming_tts: bool = True

    # Iter-21 latency: for the FIRST sentence of a streaming response, ship the
    # TTS audio inline (base64) on the SSE frame and persist to MinIO in the
    # background, instead of awaiting the MinIO PUT + presign on the critical
    # path. Only sentence 0 is inlined (the one whose latency the listener
    # feels); later sentences keep the persist→signed-URL path for small
    # payloads. Also enables clause-splitting the first sentence so a long
    # opening clause doesn't delay first audio. Flip off to restore the
    # uniform persist-then-signed-URL behaviour.
    s2s_inline_first_audio: bool = True

    # Sarvam WebSocket streaming (Phase 1). When enabled, the streaming s2s path
    # uses Sarvam's TTS-WS (bulbul, <250ms TTFB) / STT-WS (saaras) instead of the
    # batched HTTP per-sentence calls. Off by default; any WS error falls back to
    # the HTTP path so a single connection blip never loses a segment. Split
    # _tts/_stt flags let TTS (the dominant ~1.8s cost) roll out before STT.
    enable_sarvam_ws: bool = False
    enable_sarvam_ws_tts: bool = False
    enable_sarvam_ws_stt: bool = False
    sarvam_ws_tts_codec: str = "mp3"

    mongodb_trace_writes_enabled: bool = False
    mongodb_uri: str | None = None
    mongodb_collection_name: str = "ml_gateway_artifacts"
    mongodb_timeout_seconds: float = 3.0
    artifact_s3_endpoint: str | None = None
    # Public-facing endpoint used ONLY when minting presigned GET URLs handed
    # back to the browser. ``artifact_s3_endpoint`` is the internal docker
    # hostname (``http://vani-setu-minio:9000``) that the app uses for uploads;
    # ``artifact_s3_public_endpoint`` is the externally reachable URL prefix
    # (``https://vanisetu.rajyasabha.digital/minio-audio``) that Caddy
    # reverse-proxies back to MinIO. boto3 builds the presigned URL host from
    # the client's ``endpoint_url`` — so we construct a second client just for
    # the signing call. MinIO's default Sig V2 doesn't include the host in the
    # signature, so the URL produced by the public-endpoint client validates
    # against MinIO when Caddy forwards the request unchanged. Leave unset to
    # fall back to the internal endpoint (dev / non-browser callers).
    artifact_s3_public_endpoint: str | None = None
    artifact_s3_access_key: str | None = None
    artifact_s3_secret_key: str | None = None
    artifact_s3_bucket: str | None = None
    artifact_s3_region: str = "us-east-1"
    artifact_s3_prefix: str = "vani-artifacts-non-sensitive"
    artifact_s3_force_path_style: bool = True
    # Signed-URL lifetime (seconds) handed to Laravel/JSX for S2S TTS audio.
    # 15 min covers TTS-to-playback with comfortable margin for paused
    # sessions while staying short enough that a leaked URL expires before
    # it can be replayed at scale. Iter-9 added the URL alongside an inline
    # base64 copy; iter-13 dropped the base64 and made the signed URL the
    # sole audio delivery channel for `/v1/speech-to-speech`.
    s2s_audio_url_ttl_seconds: int = 900

    def sarvam_key(self) -> str | None:
        if self.sarvam_key_file.exists():
            value = self.sarvam_key_file.read_text(encoding="utf-8").strip()
            if value:
                return value
        return self.sarvam_api_key


@lru_cache
def get_settings() -> Settings:
    return Settings()

from pydantic import BaseModel, Field, HttpUrl


class ASRRequest(BaseModel):
    audio_url: HttpUrl
    language: str
    provider: str | None = None
    context: str | None = Field(
        default=None,
        description="Optional routing hint, e.g. proceedings.",
    )


class ASRSegment(BaseModel):
    start_ms: int
    end_ms: int
    text: str
    confidence: float | None = None


class ASRResponse(BaseModel):
    provider_used: str
    transcript: str
    segments: list[ASRSegment]
    duration_ms: int
    model_version: str
    fallback_used: bool


class TranslateRequest(BaseModel):
    text: str
    source_lang: str
    target_lang: str


class TranslateResponse(BaseModel):
    translation: str
    confidence: float | None = None
    model_version: str
    provider_used: str | None = None
    fallback_used: bool = False


class TranslateBatchItem(BaseModel):
    text: str
    source_lang: str
    target_lang: str


class TranslateBatchRequest(BaseModel):
    items: list[TranslateBatchItem] = Field(min_length=1, max_length=100)


class TranslateBatchResponse(BaseModel):
    translations: list[TranslateResponse]


class SpeechToSpeechStageConfig(BaseModel):
    model: str | None = None
    mode: str | None = None
    speaker: str | None = None
    pace: float | None = None
    sample_rate: int | None = None
    codec: str | None = None
    enable_preprocessing: bool | None = None
    with_diarization: bool | None = None
    with_timestamps: bool | None = None


class SpeechToSpeechRequest(BaseModel):
    session_id: int
    segment_id: int
    source_language: str
    source_text: str | None = None
    source_audio_path: str | None = None
    audio_base64: str | None = None
    audio_mime_type: str | None = None
    audio_filename: str | None = None
    target_languages: list[str] = Field(min_length=1)
    stages: dict[str, SpeechToSpeechStageConfig] = Field(default_factory=dict)
    announcement_prefix: str | None = None
    fallback_chain: list[dict[str, str]] = Field(default_factory=list)


class SpeechToSpeechOutput(BaseModel):
    language_code: str
    status: str
    text_output: str | None = None
    audio_output_path: str | None = None
    # Short-lived presigned MinIO URL for the TTS audio. Iter-9 introduced
    # this alongside ``audio_base64``; iter-12 flipped Laravel + JSX consumers
    # to read ``audio_url``; iter-13 drops ``audio_base64`` from the response
    # entirely (saves ~30-200 KB per segment on the JSON payload). Pre-iter-13
    # consumers that still expect the field will receive a missing key — they
    # already had to handle that case for the MinIO-outage path.
    audio_url: str | None = None
    audio_mime_type: str | None = None
    provider_used: str | None = None
    audio_output_supported: bool = False


class SpeechToSpeechResponse(BaseModel):
    provider_used: str
    source_text: str
    outputs: list[SpeechToSpeechOutput]


class DiarisationRequest(BaseModel):
    audio_url: HttpUrl
    duration_ms: int | None = Field(default=None, ge=1)
    num_speakers: int | None = Field(default=None, ge=1, le=12)
    min_speakers: int | None = Field(default=None, ge=1, le=12)
    max_speakers: int | None = Field(default=None, ge=1, le=12)


class DiarisationSegment(BaseModel):
    speaker: str
    start_ms: int
    end_ms: int
    confidence: float | None = None


class DiarisationResponse(BaseModel):
    provider_used: str
    segments: list[DiarisationSegment]
    model_version: str
    fallback_used: bool


class AlignmentRequest(BaseModel):
    audio_url: HttpUrl
    transcript: str
    language: str
    duration_ms: int | None = Field(default=None, ge=1)


class AlignmentWord(BaseModel):
    word: str
    start_ms: int
    end_ms: int
    confidence: float | None = None


class AlignmentResponse(BaseModel):
    provider_used: str
    language: str
    words: list[AlignmentWord]
    model_version: str
    fallback_used: bool


class LanguageDetectionRequest(BaseModel):
    text: str
    hint: str | None = None


class LanguageCandidate(BaseModel):
    language: str
    confidence: float


class LanguageDetectionResponse(BaseModel):
    language: str
    confidence: float
    candidates: list[LanguageCandidate]
    provider_used: str
    model_version: str
    fallback_used: bool


class NMTHealthResponse(BaseModel):
    ready: bool
    provider: str
    model_name: str
    version: str
    local_indictrans2_ready: bool
    bhashini_configured: bool


class ProviderState(BaseModel):
    name: str
    available: bool
    circuit_open: bool
    last_error: str | None = None


class ReadyResponse(BaseModel):
    ready: bool
    providers: list[ProviderState]


class RecentASRCall(BaseModel):
    provider_used: str
    language: str
    duration_ms: int
    model_version: str
    fallback_used: bool
    token_count: int
    created_at: str


class ASRStatusResponse(BaseModel):
    calls: list[RecentASRCall]

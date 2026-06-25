import asyncio
import base64
import json
import logging
import os
import re
import struct
import time
import unicodedata
from pathlib import Path
from typing import Annotated
from typing import AsyncIterator
from typing import TypedDict

from fastapi import HTTPException
from fastapi.responses import StreamingResponse

_log = logging.getLogger("s2s_timing")
_log.setLevel(logging.INFO)
if not _log.handlers:
    _h = logging.StreamHandler()
    _h.setFormatter(logging.Formatter("[s2s-timing] %(message)s"))
    _log.addHandler(_h)

import httpx
from fastapi import APIRouter, Depends, Request

from app.adapters.base import BaseAdapter
from app.adapters import sarvam_ws
from app.config import Settings, get_settings
from app.dependencies import get_adapters, get_artifact_store
from app.models.schemas import (
    SpeechToSpeechOutput,
    SpeechToSpeechRequest,
    SpeechToSpeechResponse,
    TranslateRequest,
)
from app.services.artifact_store import ArtifactStore
from app.services import provider_health
from app.routers.translate import translate_with_production_fallback

router = APIRouter(prefix="/v1", tags=["speech-to-speech"])

AdaptersDep = Annotated[dict[str, BaseAdapter], Depends(get_adapters)]
SettingsDep = Annotated[Settings, Depends(get_settings)]
ArtifactStoreDep = Annotated[ArtifactStore, Depends(get_artifact_store)]

AUDIO_LANGUAGES = {"hi-IN", "ta-IN", "te-IN", "kn-IN", "ml-IN", "bn-IN", "gu-IN", "mr-IN", "pa-IN", "od-IN", "en-IN"}

# Sarvam STT (saaras:v3) tends to hallucinate a short list of filler tokens on
# silent or near-silent audio chunks — typically the kind of pause we want
# preserved in proceedings, not papered over with text. When the entire
# transcript matches one of these tokens (case- and punctuation-insensitive),
# treat it as "no transcript" so the segment surfaces as a gap upstream.
# Operators can override via the ``S2S_STT_HALLUCINATION_FILTER`` env var
# (comma-separated, replaces the default list when set). An empty string
# disables the filter entirely.
_DEFAULT_HALLUCINATION_FILLERS: tuple[str, ...] = (
    "ठीक है",
    "अच्छा",
    "हाँ",
    "हम्म",
    "थैंक यू",
    "thank you",
    "okay",
    "ok",
    "umm",
    "hmm",
    "uhh",
    "अं",
    "अरे",
)


def _hallucination_fillers() -> frozenset[str]:
    """Return the active filler set, normalised for matching.

    Reads ``S2S_STT_HALLUCINATION_FILTER`` on every call (cheap; ~µs) so
    operators can flip the list without restarting the gateway. An empty
    env value disables filtering; an unset env value falls back to the
    built-in default list.
    """
    raw = os.environ.get("S2S_STT_HALLUCINATION_FILTER")
    if raw is None:
        tokens = _DEFAULT_HALLUCINATION_FILLERS
    else:
        if not raw.strip():
            return frozenset()
        tokens = tuple(part for part in (p.strip() for p in raw.split(",")) if part)
    return frozenset(_normalise_filler(t) for t in tokens if t)


# Punctuation we strip before filler-matching. Keeps the comparison robust to
# trailing stops / sentence-ender variants that Sarvam sometimes appends.
_FILLER_STRIP_PATTERN = re.compile(r"[\s\.\,\!\?\;\:।॥\"'`\-_…]+")


def _normalise_filler(text: str) -> str:
    """Lowercase + strip whitespace/punctuation for hallucination matching."""
    folded = unicodedata.normalize("NFKC", text).casefold()
    return _FILLER_STRIP_PATTERN.sub("", folded)


def _is_hallucinated_filler(transcript: str) -> bool:
    """True if the transcript is entirely a known Sarvam filler hallucination.

    Catches two patterns Sarvam's saaras:v3 produces on silent / near-silent
    chunks:
      1. a single filler token ("ठीक है", "thank you", "okay" …)
      2. the same filler token repeated N times ("हाँ हाँ हाँ …", which
         saaras:v3 emits on long silent stretches)
    Matching is case- and punctuation-insensitive after NFKC fold.
    """
    fillers = _hallucination_fillers()
    if not fillers:
        return False
    normalised = _normalise_filler(transcript)
    if normalised in fillers:
        return True
    # Repeated-filler case: split on whitespace BEFORE normalising so we keep
    # token boundaries, normalise each token, and check every token matches
    # the SAME filler. "हाँ हाँ हाँ" → all "हाँ" → filtered.
    tokens = [_normalise_filler(part) for part in transcript.split() if part.strip()]
    if not tokens:
        return False
    unique = set(tokens)
    return len(unique) == 1 and next(iter(unique)) in fillers


def _is_repeated_phrase_hallucination(
    transcript: str, min_repetitions: int = 3
) -> bool:
    """True if the transcript is the same n-gram repeated ``min_repetitions``+ times.

    Catches a saaras:v3 pattern that ``_is_hallucinated_filler`` misses:
    multi-token phrases repeated 3+ times on silent / near-silent audio.
    Examples that match:
      - ``"thank you thank you thank you"`` (2-gram, 3 reps)
      - ``"ठीक है ठीक है ठीक है ठीक है"`` (2-gram, 4 reps)
      - ``"yes yes yes yes yes"`` (1-gram, 5 reps — already caught by
        ``_is_hallucinated_filler``, but covered here too for completeness)
    Examples that do NOT match:
      - ``"thank you for joining"`` (no repetition)
      - ``"yes I will yes I will"`` (only 2 reps, below threshold)
      - ``"नमस्ते नमस्ते नमस्ते सबको"`` (3 reps then trailing extra → not
        pure repetition)

    The check scans n-gram sizes 1..4. We require the entire token stream
    to be exactly ``k`` repetitions of the same n-gram where ``k >= min_repetitions``;
    trailing/leading partial copies disqualify the match so genuine speech
    that happens to start with a repeated phrase still passes through.
    """
    if min_repetitions < 2:
        return False
    tokens = [_normalise_filler(part) for part in transcript.split() if part.strip()]
    n = len(tokens)
    if n < min_repetitions:
        return False
    # Try each n-gram size from 1 to 4. The smallest size that perfectly
    # partitions the token stream wins; that's the repeated unit.
    for size in range(1, 5):
        if n % size != 0:
            continue
        reps = n // size
        if reps < min_repetitions:
            continue
        unit = tokens[:size]
        if all(tokens[i * size : (i + 1) * size] == unit for i in range(reps)):
            return True
    return False


class TTSResult(TypedDict):
    path: str | None
    # Short-lived presigned MinIO URL for the rendered audio. Populated when
    # ``artifact_store.persist_binary`` + ``signed_url`` both succeed; falls
    # back to None on MinIO failure. Iter-13 dropped the redundant
    # ``audio_base64`` field — consumers read ``audio_url`` exclusively now,
    # and on MinIO outage the segment surfaces as ``provider_pending``.
    audio_url: str | None
    audio_mime_type: str


class STTResult(TypedDict):
    transcript: str
    detected_language: str | None


@router.get("/audio/re-sign")
async def audio_re_sign(
    artifact_store: ArtifactStoreDep,
    settings: SettingsDep,
    key: str,
    bucket: str | None = None,
) -> dict[str, object]:
    """Mint a fresh signed URL for an existing MinIO object.

    Used by Laravel to recover from signed-URL expiry on long-paused S2S
    sessions. ``audio_url`` lifetimes default to ``s2s_audio_url_ttl_seconds``
    (15 min) — if a user pauses playback past that window, the original URL
    embedded in ``s2s_outputs.audio_output_path`` 403s at MinIO. This route
    accepts the bare object key (extractable from the stored URL path:
    ``/minio-audio/<bucket>/<key>?...``) and returns a fresh signed URL with
    the same TTL. Service-auth gated by the existing ``require_service_auth``
    dependency on the router include in ``main.py``.
    """
    target_bucket = bucket or settings.artifact_s3_bucket
    if not target_bucket:
        raise HTTPException(status_code=503, detail="artifact bucket not configured")
    signer = getattr(artifact_store, "signed_url", None)
    if signer is None:
        # NoOpArtifactStore / MongoArtifactStore can't presign — only S3 can.
        raise HTTPException(status_code=503, detail="artifact store cannot mint signed URLs")
    try:
        url = await signer(key, ttl_seconds=settings.s2s_audio_url_ttl_seconds)
    except Exception as exc:
        raise HTTPException(status_code=502, detail=f"signed_url failed: {exc}") from exc
    return {
        "audio_url": url,
        "expires_in_seconds": settings.s2s_audio_url_ttl_seconds,
    }


@router.post("/speech-to-speech", response_model=SpeechToSpeechResponse)
async def speech_to_speech(
    request: SpeechToSpeechRequest,
    http_request: Request,
    adapters: AdaptersDep,
    settings: SettingsDep,
    artifact_store: ArtifactStoreDep,
) -> SpeechToSpeechResponse:
    sarvam_client: httpx.AsyncClient = http_request.app.state.sarvam_client
    # Iter-20: process-wide cap on concurrent Sarvam HTTP calls so the
    # parallelised per-language fanout (iter-17/19a) can't trip Sarvam's
    # per-key QPS limit on high-language-count sessions. Threaded the same
    # way as ``sarvam_client`` — pulled from ``app.state`` here and passed
    # down into the Sarvam-call helpers.
    sarvam_semaphore: asyncio.Semaphore = http_request.app.state.sarvam_semaphore
    source_text = (request.source_text or "").strip()
    effective_source_language = request.source_language
    provider_used = "text_input"
    t_total_start = time.perf_counter()
    stage_ms: dict[str, float] = {}
    if not source_text and request.audio_base64:
        t = time.perf_counter()
        stt_result = await _try_stt(request, settings, sarvam_client, sarvam_semaphore)
        stage_ms["stt"] = round((time.perf_counter() - t) * 1000, 1)
        if stt_result and stt_result["transcript"]:
            source_text = stt_result["transcript"]
            provider_used = "sarvam_stt"
            if stt_result["detected_language"]:
                effective_source_language = stt_result["detected_language"]
        else:
            # Sarvam STT failed or returned nothing. Try the local Whisper
            # fallback before giving up — see config.whisper_enabled. Skipped
            # cleanly (no-op) when whisper_enabled is false, so the
            # operational default matches the pre-slice-F behaviour.
            if settings.whisper_enabled:
                t = time.perf_counter()
                whisper_result = await _try_whisper_stt(request, settings)
                stage_ms["whisper_stt"] = round((time.perf_counter() - t) * 1000, 1)
                if whisper_result and whisper_result["transcript"]:
                    source_text = whisper_result["transcript"]
                    provider_used = "whisper_stt_fallback"
                    if whisper_result["detected_language"]:
                        effective_source_language = whisper_result["detected_language"]
                else:
                    provider_used = "sarvam_stt_unavailable"
            else:
                provider_used = "sarvam_stt_unavailable"
    # Iter-17: fan out translate+TTS per target language in parallel.
    # Previously this was a serial ``for language_code in target_languages``
    # loop, so total latency was ~N × (translate + TTS). With ``asyncio.gather``
    # the three Sarvam calls run concurrently and total latency collapses to
    # max-of-N rather than sum-of-N. Order is preserved by ``gather`` so
    # ``outputs[]`` still matches the request's ``target_languages`` order.
    async def _process_language(language_code: str) -> SpeechToSpeechOutput:
        translation_degraded = False
        if _same_language(language_code, effective_source_language):
            translated = source_text
            translation_provider = "passthrough"
        else:
            t = time.perf_counter()
            try:
                translated, translation_provider, translation_degraded = await _translate_for_s2s(
                    source_text,
                    effective_source_language,
                    language_code,
                    request,
                    adapters,
                    settings,
                    sarvam_client,
                    sarvam_semaphore,
                )
                # Iter-20: detect short identity-passthrough. Some providers
                # (notably IndicTrans2 fallback and even Sarvam on edge inputs)
                # echo the source verbatim when the input is too short / too
                # ambiguous to translate — e.g. ``"Smooth."`` → ``"Smooth."``
                # or ``"So,"`` → ``"So,"`` under a hi-IN/pa-IN target. The
                # iter-19e audit found 13+ such rows persisted as
                # ``translation_degraded`` even though no real degradation
                # happened — it's a passthrough on a single-word fragment.
                # Re-tag those as ``passthrough_short`` so the UI doesn't
                # surface a misleading "degraded translation" badge, and
                # clear the degraded flag so TTS proceeds on the (identical)
                # text. Threshold tuned to the iter-19e residue: ≤8 chars
                # total OR ≤2 whitespace-separated tokens, with the source
                # and translated strings matching after .strip().casefold().
                if (
                    translated
                    and _is_short_identity_passthrough(source_text, translated)
                ):
                    translation_degraded = False
                    translation_provider = "passthrough_short"
                stage_ms[f"translate_{language_code}"] = round((time.perf_counter() - t) * 1000, 1)
            except Exception as exc:
                # Both Sarvam translate AND the IndicTrans2 fallback failed.
                # Don't 502 the whole request — emit the STT transcript so the
                # UI at least shows what was said, and mark this output
                # provider_pending so the operator can see translation is
                # offline rather than receiving stale identical audio.
                return SpeechToSpeechOutput(
                    language_code=language_code,
                    status="provider_pending",
                    text_output=None,
                    audio_output_path=None,
                    audio_mime_type=None,
                    provider_used=f"translation_unavailable:{type(exc).__name__}",
                    audio_output_supported=language_code in AUDIO_LANGUAGES,
                )

        audio_supported = language_code in AUDIO_LANGUAGES
        audio_path: str | None = None
        audio_url: str | None = None
        audio_mime_type: str | None = None
        # Iter-18: ``_translate_for_s2s`` returns ``("", "no_translation_model", False)``
        # when IndicTrans2 has no real model loaded (no endpoint configured AND
        # no local transformers pipeline). Surface that as ``provider_pending``
        # + None text so the Laravel UI/proxy doesn't display the old
        # ``[IndicTrans2 deterministic draft <lang>]`` placeholder as if it
        # were a real translation. Per iter-16b's punt we pick approach (a):
        # emit ``provider_pending`` over ``fallback_required`` because the
        # latter implies a partial result is available.
        if translation_provider == "no_translation_model":
            return SpeechToSpeechOutput(
                language_code=language_code,
                status="provider_pending",
                text_output=None,
                audio_output_path=None,
                audio_url=None,
                audio_mime_type=None,
                provider_used="no_translation_model",
                audio_output_supported=audio_supported,
            )
        status = "ready" if translated else "provider_pending"

        if audio_supported and translated:
            # Always try TTS — even on degraded translations. Some output (even
            # imperfect) is better UX than silence. The UI shows a "degraded"
            # badge via the status field below so the user knows quality is
            # off. When Sarvam translate recovers, this branch goes back to
            # producing correct audio with no other change.
            t = time.perf_counter()
            tts_result = await _try_tts(
                language_code,
                translated,
                request,
                settings,
                artifact_store,
                sarvam_client,
                sarvam_semaphore,
            )
            stage_ms[f"tts_{language_code}"] = round((time.perf_counter() - t) * 1000, 1)
            if tts_result is None:
                status = "provider_pending"
            else:
                audio_path = tts_result["path"]
                audio_url = tts_result["audio_url"]
                audio_mime_type = tts_result["audio_mime_type"]
                if translation_degraded:
                    status = "translation_degraded"
        elif not audio_supported:
            status = "fallback_required"

        return SpeechToSpeechOutput(
            language_code=language_code,
            status=status,
            text_output=None,  # ephemeral: never return translated text
            audio_output_path=audio_path,
            audio_url=audio_url,
            audio_mime_type=audio_mime_type,
            provider_used=translation_provider,
            audio_output_supported=audio_supported,
        )

    gathered = await asyncio.gather(
        *(_process_language(lc) for lc in request.target_languages),
        return_exceptions=True,
    )
    # Convert any unhandled exception back into a provider_pending output so
    # the response shape is unchanged. ``_process_language`` already catches
    # translation failures inline; this branch only fires on genuinely
    # unexpected errors (e.g. an exception inside _try_tts or _persist_and_sign
    # that the existing code path would have surfaced as a 500). Threading
    # ``language_code`` through via ``zip`` keeps the per-output label correct.
    outputs: list[SpeechToSpeechOutput] = []
    for language_code, result in zip(request.target_languages, gathered):
        if isinstance(result, BaseException):
            outputs.append(
                SpeechToSpeechOutput(
                    language_code=language_code,
                    status="provider_pending",
                    provider_used=f"unhandled_exception:{type(result).__name__}",
                    audio_output_supported=language_code in AUDIO_LANGUAGES,
                    text_output=None,
                    audio_output_path=None,
                    audio_url=None,
                    audio_mime_type=None,
                )
            )
        else:
            outputs.append(result)

    total_ms = round((time.perf_counter() - t_total_start) * 1000, 1)
    _log.info("session=%s segment=%s total=%sms %s", request.session_id, request.segment_id, total_ms, stage_ms)
    return SpeechToSpeechResponse(provider_used=provider_used, source_text=None, outputs=outputs)


async def _try_stt(
    request: SpeechToSpeechRequest,
    settings: Settings,
    client: httpx.AsyncClient,
    sarvam_semaphore: asyncio.Semaphore,
) -> STTResult | None:
    key = settings.sarvam_key()
    if not key or not request.audio_base64:
        return None

    try:
        audio_base64 = request.audio_base64.strip()
        if audio_base64.startswith("data:audio/"):
            audio_base64 = audio_base64.split(",", 1)[1]
        audio = base64.b64decode(audio_base64, validate=True)
    except Exception:
        return None

    stt_config = request.stages.get("stt")
    data = {
        "model": stt_config.model if stt_config and stt_config.model else "saaras:v3",
        "mode": stt_config.mode if stt_config and stt_config.mode else "codemix",
    }
    if request.source_language and request.source_language != "auto":
        data["language_code"] = request.source_language

    filename = request.audio_filename or "segment.m4a"
    mime_type = request.audio_mime_type or "audio/mp4"
    files = {"file": (filename, audio, mime_type)}
    headers = {"api-subscription-key": key}

    try:
        async with sarvam_semaphore:
            response = await client.post(
                settings.sarvam_asr_path,
                data=data,
                files=files,
                headers=headers,
                timeout=settings.sarvam_stt_timeout_seconds,
            )
        response.raise_for_status()
        payload = response.json()
    except Exception as exc:
        provider_health.record_failure("sarvam_stt", str(exc))
        return None
    provider_health.record_success("sarvam_stt")

    transcript = (
        payload.get("transcript")
        or payload.get("text")
        or payload.get("transcribed_text")
        or payload.get("stt_text")
        or ""
    )
    transcript = str(transcript).strip()
    if not transcript:
        return None

    filler_hit = _is_hallucinated_filler(transcript)
    repeat_hit = False if filler_hit else _is_repeated_phrase_hallucination(transcript)
    if filler_hit or repeat_hit:
        # Tag the log line with which path fired so we can audit how often
        # each pattern actually shows up in production. "filler" = known
        # short-list match, "repeated_phrase" = same n-gram repeated 3+ times.
        _log.info(
            "filtered_hallucination session=%s segment=%s path=%s text=%r",
            request.session_id,
            request.segment_id,
            "filler" if filler_hit else "repeated_phrase",
            transcript,
        )
        return None

    detected_raw = (
        payload.get("language_code")
        or payload.get("detected_language")
        or payload.get("detected_language_code")
        or payload.get("source_language")
    )
    return {
        "transcript": transcript,
        "detected_language": _canonical_language_tag(detected_raw),
    }


async def _try_stt_ws(
    request: SpeechToSpeechRequest,
    settings: Settings,
) -> STTResult | None:
    """STT via Sarvam saaras WebSocket — step 1a of moving STT off the
    post-speech critical path (architectural lever #1).

    For now it transcribes the same single audio blob the HTTP path uses, so it
    is a drop-in alternative gated by ``enable_sarvam_ws_stt`` (default off). The
    real latency win arrives when the client streams audio continuously so
    STT-WS transcribes *during* speech (steps 1b-1d). Sarvam STT-WS accepts only
    raw PCM/WAV, so non-WAV input (the current m4a/webm capture) returns None and
    the caller falls back to the HTTP ``_try_stt`` — making this safe to enable
    before the client switches formats. Any error/empty also returns None.
    """
    key = settings.sarvam_key()
    if not key or not request.audio_base64 or not sarvam_ws.sdk_available():
        return None
    audio_b64 = request.audio_base64.strip()
    if audio_b64.startswith("data:audio/"):
        audio_b64 = audio_b64.split(",", 1)[1]
    mime = (request.audio_mime_type or "").lower()
    if not ("wav" in mime or "pcm" in mime or "x-raw" in mime):
        # Encoded audio (m4a/mp4/webm/ogg) won't decode on STT-WS — defer to HTTP.
        return None
    stt_config = request.stages.get("stt")
    model = stt_config.model if stt_config and stt_config.model else "saaras:v3"
    mode = stt_config.mode if stt_config and stt_config.mode else "codemix"
    lang = (
        request.source_language
        if (request.source_language and request.source_language != "auto")
        else None
    )
    transcript = ""

    async def _collect() -> str:
        nonlocal transcript
        async for ev in sarvam_ws.stream_stt(
            api_key=key,
            audio_b64=audio_b64,
            language_code=lang,
            encoding="audio/wav",
            model=model,
            mode=mode,
        ):
            if ev.get("type") == "data":
                latest = (ev.get("transcript") or "").strip()
                if latest:
                    transcript = latest
                    # stream_stt flushes after the blob, so the first non-empty
                    # transcript is the finalized result — stop here instead of
                    # waiting for an END_SPEECH that never comes for a blob.
                    break
        return transcript

    # HARD DEADLINE: Sarvam STT-WS stops on an END_SPEECH VAD signal, which a
    # discrete blob (no trailing silence) may never emit — the iterator would
    # hang forever. Bound it by the STT timeout and use whatever transcript
    # arrived; on timeout with nothing, fall back to HTTP. (Continuous client
    # streaming in steps 1b-1d gives STT-WS the silence tail it needs.)
    try:
        await asyncio.wait_for(_collect(), timeout=settings.sarvam_stt_timeout_seconds)
    except asyncio.TimeoutError:
        pass  # use partial transcript if any, else fall through to None below
    except Exception as exc:
        provider_health.record_failure("sarvam_stt", str(exc))
        return None
    transcript = transcript.strip()
    if not transcript:
        return None
    if _is_hallucinated_filler(transcript) or _is_repeated_phrase_hallucination(transcript):
        return None
    provider_health.record_success("sarvam_stt")
    return {"transcript": transcript, "detected_language": None}


# ──── Local Whisper STT fallback (slice F) ────────────────────────────────
#
# Singleton model instance — faster-whisper's WhisperModel load is slow
# (~1-3 s for "base" on CPU) and reads disk every time, so we cache it
# at module scope and protect concurrent init with an asyncio Lock. The
# first request after process start pays the load cost; subsequent ones
# reuse the in-memory model. The model itself is thread-safe for read.
_whisper_model = None
_whisper_load_lock: asyncio.Lock | None = None
_whisper_load_failed = False  # negative cache to stop hammering missing deps


def _get_whisper_load_lock() -> asyncio.Lock:
    global _whisper_load_lock
    if _whisper_load_lock is None:
        _whisper_load_lock = asyncio.Lock()
    return _whisper_load_lock


async def _get_whisper_model(settings: Settings):
    """Lazy-load and cache the faster-whisper model.

    Returns None and stays in negative-cache mode if faster-whisper isn't
    installed or model load fails — callers treat None as "Whisper not
    available, fall through to provider_pending".
    """
    global _whisper_model, _whisper_load_failed
    if _whisper_model is not None:
        return _whisper_model
    if _whisper_load_failed:
        return None
    async with _get_whisper_load_lock():
        if _whisper_model is not None:
            return _whisper_model
        if _whisper_load_failed:
            return None
        try:
            from faster_whisper import WhisperModel
        except ImportError:
            _log.info("whisper_load_failed reason=faster_whisper_not_installed")
            _whisper_load_failed = True
            return None

        loop = asyncio.get_event_loop()

        def _construct():
            return WhisperModel(
                settings.whisper_model_size,
                device=settings.whisper_device,
                compute_type=settings.whisper_compute_type,
            )

        try:
            _whisper_model = await loop.run_in_executor(None, _construct)
            _log.info(
                "whisper_loaded model=%s device=%s compute=%s",
                settings.whisper_model_size,
                settings.whisper_device,
                settings.whisper_compute_type,
            )
        except Exception as exc:
            _log.info("whisper_load_failed reason=%s", exc)
            _whisper_load_failed = True
            return None
    return _whisper_model


async def _try_tijori_whisper(
    request: SpeechToSpeechRequest,
    settings: Settings,
) -> STTResult | None:
    """POST audio_base64 to the existing Tijori CPU model services
    /v1/transcribe endpoint. Response shape:
      {transcript_text, detected_language, confidence, model,
       served_via, model_status}

    Returns None on any HTTP or shape failure so the caller can fall
    through to the in-process backend or skip the segment.
    """
    base = (settings.tijori_whisper_url or "").rstrip("/")
    if not base:
        return None

    # Strip region tag for the Tijori service — it expects ISO 639-1
    # like "hi", not "hi-IN". "auto" means let Whisper detect.
    language_hint = "auto"
    if request.source_language and request.source_language not in ("auto", "unknown"):
        language_hint = request.source_language.split("-")[0].lower()

    audio_b64 = request.audio_base64.strip()
    if audio_b64.startswith("data:audio/"):
        audio_b64 = audio_b64.split(",", 1)[1]

    payload = {
        "audio_base64": audio_b64,
        "media_type": request.audio_mime_type or "audio/wav",
        "source_language": language_hint,
    }

    try:
        async with httpx.AsyncClient(timeout=settings.tijori_whisper_timeout_seconds) as client:
            response = await client.post(f"{base}/v1/transcribe", json=payload)
            response.raise_for_status()
            data = response.json()
    except Exception as exc:
        _log.info("tijori_whisper_failed reason=%s", exc)
        return None

    transcript = str(data.get("transcript_text") or "").strip()
    if not transcript:
        return None

    # Same hallucination filter as the Sarvam path — Whisper has its
    # own well-known fillers on silent audio ("Thank you for watching",
    # ".", etc.). Catch them before they reach downstream translation.
    if _is_hallucinated_filler(transcript) or _is_repeated_phrase_hallucination(transcript):
        _log.info(
            "tijori_whisper_filtered_hallucination session=%s segment=%s text=%r",
            request.session_id,
            request.segment_id,
            transcript,
        )
        return None

    detected = data.get("detected_language")
    return {
        "transcript": transcript,
        "detected_language": _canonical_language_tag(detected),
    }


async def _try_whisper_stt(
    request: SpeechToSpeechRequest,
    settings: Settings,
) -> STTResult | None:
    """Transcribe the request's audio_base64 via the configured Whisper backend.

    Two backends supported, selected by ``whisper_backend``:
      - ``tijori`` (default when whisper_enabled and tijori URL set) —
        HTTP POST to the existing tijori-dev-whisper sidecar
        (POST /v1/transcribe). Preferred because the weights are
        already deployed; no model-staging needed on this host.
      - ``local`` — in-process faster-whisper. Falls back here when
        the tijori URL isn't set OR when the HTTP call fails. Needs
        ``ML_GATEWAY_WHISPER_ENABLED=true`` + pre-staged model
        weights (see task #14).

    Used as a fallback after _try_stt returns None (Sarvam timeout, 4xx/5xx,
    hallucination filter). Returns None on any failure so the caller treats
    the segment as "STT unavailable" and falls through to the empty-input
    short-circuit in _translate_for_s2s.
    """
    if not request.audio_base64:
        return None

    # Prefer the Tijori sidecar when its URL is set — saves model load
    # cost AND keeps the weights inside the existing GPU-budget envelope.
    if settings.tijori_whisper_url:
        result = await _try_tijori_whisper(request, settings)
        if result is not None:
            return result
        # Fall through to local on tijori failure so we don't lose the
        # segment just because the bridge had a hiccup.

    model = await _get_whisper_model(settings)
    if model is None:
        return None

    try:
        audio_base64 = request.audio_base64.strip()
        if audio_base64.startswith("data:audio/"):
            audio_base64 = audio_base64.split(",", 1)[1]
        audio_bytes = base64.b64decode(audio_base64, validate=True)
    except Exception:
        return None

    # faster-whisper accepts a file path. Write the audio to a temp file
    # within the executor (off the event loop) and pass that path to
    # model.transcribe.
    suffix = Path(request.audio_filename or "segment.wav").suffix or ".wav"

    # Strip region tag for the language hint — faster-whisper expects ISO
    # 639-1 codes like "hi", not "hi-IN". Pass None for "auto" so the model
    # detects the language itself.
    language_hint: str | None = None
    if request.source_language and request.source_language not in ("auto", "unknown"):
        language_hint = request.source_language.split("-")[0].lower()

    def _run() -> tuple[str, str | None]:
        import tempfile
        with tempfile.NamedTemporaryFile(suffix=suffix, delete=True) as handle:
            handle.write(audio_bytes)
            handle.flush()
            segments_iter, info = model.transcribe(
                handle.name,
                language=language_hint,
                beam_size=1,
                vad_filter=False,
            )
            text = " ".join(segment.text for segment in segments_iter).strip()
            detected = getattr(info, "language", None)
            return text, detected

    loop = asyncio.get_event_loop()
    try:
        transcript, detected_lang = await loop.run_in_executor(None, _run)
    except Exception as exc:
        _log.info("whisper_transcribe_failed reason=%s", exc)
        return None

    if not transcript:
        return None

    # Apply the same hallucination filters as Sarvam — Whisper has its own
    # well-documented filler hallucinations on silence (e.g. "Thank you for
    # watching") that we don't want feeding the live transcript either.
    if _is_hallucinated_filler(transcript) or _is_repeated_phrase_hallucination(transcript):
        _log.info(
            "whisper_filtered_hallucination session=%s segment=%s text=%r",
            request.session_id,
            request.segment_id,
            transcript,
        )
        return None

    return {
        "transcript": transcript,
        "detected_language": _canonical_language_tag(detected_lang),
    }


async def _translate_for_s2s(
    text: str,
    source_language: str,
    target_language: str,
    request: SpeechToSpeechRequest,
    adapters: dict[str, BaseAdapter],
    settings: Settings,
    client: httpx.AsyncClient,
    sarvam_semaphore: asyncio.Semaphore,
) -> tuple[str, str, bool]:
    """Translate text for the S2S pipeline.

    Tries Sarvam mayura:v1 first (matches engine_meta and user expectation);
    falls back to translate_with_production_fallback (Bhashini → IndicTrans2).
    Third return value is True when the result came from a degraded source
    (e.g. Tijori Qwen fallback) and should NOT be sent to TTS — Qwen ignores
    target_lang so speaking it produces repetitive same-language audio.
    """
    # Short-circuit on empty input. STT timeouts (capped at
    # settings.sarvam_stt_timeout_seconds) leave source_text empty; without
    # this guard IndicTrans2 still gets called with "" and many NMT models
    # produce non-empty garbage on empty input, which then escapes the
    # `if audio_supported and translated:` TTS guard and wastes ~2-3 s on a
    # nonsense TTS call. Return an empty-string verdict so the caller marks
    # the segment provider_pending and skips TTS cleanly. Iter-16 renamed
    # the provider tag from "no_input" to "stt_unavailable" so the persisted
    # row makes it obvious in s2s_outputs that STT (not translate) is the
    # broken stage — iter-15b's audit found ~19 hi-IN rows mis-labelled
    # `translation_degraded` for what were really STT outages.
    if not text or not text.strip():
        return "", "stt_unavailable", False

    sarvam_translation = await _try_sarvam_translate(
        text, source_language, target_language, request, settings, client, sarvam_semaphore
    )
    if sarvam_translation is not None:
        return sarvam_translation, "sarvam_mt", False

    translation = await translate_with_production_fallback(
        TranslateRequest(
            text=text,
            source_lang=_normalise_language(source_language),
            target_lang=_normalise_language(target_language),
        ),
        adapters,
        settings,
    )
    # Iter-18: when the IndicTrans2 adapter has no real model loaded (no
    # endpoint URL configured + no local transformers pipeline), it emits a
    # synthetic ``[IndicTrans2 deterministic draft <lang>] <text>`` placeholder
    # with ``model_version`` suffixed ``-deterministic``. Detect that path
    # here and rewrite to ``("", "no_translation_model", False)`` so the
    # caller surfaces the segment as ``provider_pending`` + None text instead
    # of letting the Laravel UI/proxy display the bracketed placeholder as if
    # it were a real translation. The /v1/translate direct route still sees
    # the placeholder for backwards compatibility (adapter-level test pinned).
    model_version = getattr(translation, "model_version", "") or ""
    if model_version.endswith("-deterministic"):
        return "", "no_translation_model", False
    return (
        translation.translation,
        translation.provider_used or "indictrans2",
        bool(getattr(translation, "fallback_used", False)),
    )


async def _try_sarvam_translate(
    text: str,
    source_language: str,
    target_language: str,
    request: SpeechToSpeechRequest,
    settings: Settings,
    client: httpx.AsyncClient,
    sarvam_semaphore: asyncio.Semaphore,
) -> str | None:
    key = settings.sarvam_key()
    if not key or not text.strip():
        return None

    # Circuit-breaker: if Sarvam translate has failed within the last
    # SARVAM_TRANSLATE_BREAK_SECONDS, skip the HTTP call entirely and let
    # the caller fall through to IndicTrans2 immediately. Avoids burning
    # ~100-200ms per chunk on the inevitable 500 during a Sarvam outage.
    # Auto-recovers after the window — next call will probe again.
    health_snap = provider_health.snapshot().get("sarvam_translate") or {}
    last_fail = health_snap.get("last_fail_seconds_ago")
    last_ok = health_snap.get("last_ok_seconds_ago")
    if (
        isinstance(last_fail, (int, float)) and last_fail <= 30.0
        and (last_ok is None or last_fail < last_ok)
    ):
        return None

    translate_config = request.stages.get("translate")
    model = translate_config.model if translate_config and translate_config.model else settings.sarvam_translate_model
    mode = translate_config.mode if translate_config and translate_config.mode else settings.sarvam_translate_mode
    enable_preprocessing = (
        translate_config.enable_preprocessing
        if translate_config is not None and translate_config.enable_preprocessing is not None
        else True
    )

    payload = {
        "input": text,
        "source_language_code": _canonical_language_tag(source_language) or "auto",
        "target_language_code": _canonical_language_tag(target_language) or target_language,
        "speaker_gender": settings.sarvam_translate_speaker_gender,
        "mode": mode,
        "model": model,
        "enable_preprocessing": bool(enable_preprocessing),
    }
    headers = {"api-subscription-key": key, "Content-Type": "application/json"}

    try:
        async with sarvam_semaphore:
            response = await client.post(
                settings.sarvam_translate_path,
                json=payload,
                headers=headers,
                timeout=settings.sarvam_translate_timeout_seconds,
            )
        response.raise_for_status()
        data = response.json()
    except Exception as exc:
        # Iter-16: surface Sarvam translate failures in docker logs. Without
        # this warning the failure was visible only to provider_health's
        # in-memory snapshot — operators tailing `docker logs` saw nothing,
        # which masked the degraded-translation outages flagged by iter-15b's
        # translation-correctness audit. Truncate exc to 200 chars to keep
        # log lines bounded on very long upstream tracebacks.
        _log.warning(
            "sarvam_translate_failed source=%s target=%s error=%s",
            source_language,
            target_language,
            str(exc)[:200],
        )
        provider_health.record_failure("sarvam_translate", str(exc))
        return None

    translated = (
        data.get("translated_text")
        or data.get("translation")
        or data.get("output")
        or ""
    )
    translated = str(translated).strip()
    if translated:
        provider_health.record_success("sarvam_translate")
        return translated
    provider_health.record_failure("sarvam_translate", "empty translation")
    return None


async def _try_tts(
    language_code: str,
    text: str,
    request: SpeechToSpeechRequest,
    settings: Settings,
    artifact_store: ArtifactStore,
    client: httpx.AsyncClient,
    sarvam_semaphore: asyncio.Semaphore,
) -> TTSResult | None:
    key = settings.sarvam_key()
    if not key:
        return None

    tts_config = request.stages.get("tts")
    codec = tts_config.codec if tts_config and tts_config.codec else "wav"
    payload_base = {
        "target_language_code": language_code,
        "model": tts_config.model if tts_config and tts_config.model else "bulbul:v3",
        "speaker": tts_config.speaker if tts_config and tts_config.speaker else "ritu",
        "pace": tts_config.pace if tts_config and tts_config.pace else 1.1,
        "speech_sample_rate": tts_config.sample_rate if tts_config and tts_config.sample_rate else 22050,
        "output_audio_codec": codec,
        "enable_preprocessing": True if tts_config is None or tts_config.enable_preprocessing is None else tts_config.enable_preprocessing,
    }
    headers = {"api-subscription-key": key}
    parts = _split_text_for_tts(text)
    if not parts:
        return None

    # Batched call: Sarvam TTS accepts `inputs: [s1, s2, ...]` and returns
    # `audios: [b64_1, b64_2, ...]`. One HTTP round-trip instead of N
    # sequential ones — wins ~(N-1)*1100ms on multi-sentence chunks.
    payload = {**payload_base, "inputs": parts}
    try:
        async with sarvam_semaphore:
            response = await client.post(settings.sarvam_tts_path, json=payload, headers=headers)
        response.raise_for_status()
        data = response.json()
    except Exception as exc:
        provider_health.record_failure("sarvam_tts", str(exc))
        return None

    audios_raw = data.get("audios") or []
    if not isinstance(audios_raw, list) or len(audios_raw) == 0:
        provider_health.record_failure("sarvam_tts", "no audios in response")
        return None

    audio_parts: list[bytes] = []
    for piece in audios_raw:
        if not isinstance(piece, str) or not piece.strip():
            provider_health.record_failure("sarvam_tts", "empty audio piece")
            return None
        try:
            audio_parts.append(base64.b64decode(piece))
        except Exception as exc:
            provider_health.record_failure("sarvam_tts", str(exc))
            return None
    provider_health.record_success("sarvam_tts")

    binary = _join_audio_parts(audio_parts, codec)

    content_type = f"audio/{'mpeg' if codec == 'mp3' else codec}"
    # Iter-9 introduced MinIO persistence + signed URL alongside an inline
    # base64 copy; iter-12 flipped consumers to read ``audio_url``; iter-13
    # dropped the base64 from the response entirely so the JSON payload is
    # ~30-200 KB lighter per segment. The signed URL is now the only audio
    # delivery channel — if MinIO is offline the segment surfaces as
    # ``provider_pending`` via the caller's ``audio_url is None`` check.
    path, audio_url, _audio_key = await _persist_and_sign(
        artifact_store,
        binary,
        codec=codec,
        content_type=content_type,
        request=request,
        language_code=language_code,
        settings=settings,
    )
    return {
        "path": path,
        "audio_url": audio_url,
        "audio_mime_type": content_type,
    }


async def _persist_and_sign(
    artifact_store: ArtifactStore,
    binary: bytes,
    *,
    codec: str,
    content_type: str,
    request: SpeechToSpeechRequest,
    language_code: str,
    settings: Settings,
) -> tuple[str | None, str | None, str | None]:
    """Ephemeral mode: audio is NEVER persisted.

    Vani s2s is fully ephemeral — translated audio is relayed inline to the
    listener and nothing is written to object storage. This used to persist the
    TTS blob to MinIO and mint a signed URL; that behaviour was removed so no
    audio is stored on the server. Always returns ``(None, None, None)``, which
    makes every caller fall back to the inline base64 audio path.
    """
    return None, None, None


def _sse_event(event: str, data: dict) -> bytes:
    """Format a single Server-Sent Event frame.

    SSE framing rules: ``event:`` line names the event, ``data:`` carries
    the JSON payload, and ``\\n\\n`` terminates the frame. Anything that
    breaks the frame (multi-line data, missing blank line) will silently
    desync the EventSource on the browser side, so all data goes through
    ``json.dumps`` with ``ensure_ascii=True`` to keep payloads single-line.
    """
    payload = json.dumps(data, ensure_ascii=True)
    return f"event: {event}\ndata: {payload}\n\n".encode("utf-8")


async def _tts_one_sentence(
    *,
    language_code: str,
    sentence: str,
    request: SpeechToSpeechRequest,
    settings: Settings,
    client: httpx.AsyncClient,
    artifact_store: ArtifactStore,
    sarvam_semaphore: asyncio.Semaphore,
    defer_persist: bool = False,
) -> tuple[str | None, str | None, str, str | None] | None:
    """Synthesize a single sentence via Sarvam TTS.

    Returns ``(audio_url_or_None, audio_key_or_None, mime_type, audio_b64_or_None)``
    on success and None on any failure. Unlike ``_try_tts`` this never batches;
    it's the per-sentence dispatch helper used by the streaming endpoint so the
    first sentence can ship while later sentences synthesise. ``audio_url`` is
    the short-lived signed MinIO URL added in iter-9; iter-13 dropped the
    inline base64 from the SSE frame to keep payloads small, but the streaming
    pipeline now restores it as a fallback (returned here as ``audio_b64``) so
    sessions still play when MinIO is not configured. Iter-15 added
    ``audio_key`` so the SSE handler can call ``/v1/audio/re-sign`` when the
    15-min TTL is close to expiry (streaming sentences have no ``s2s_outputs``
    row, so the regular Laravel proxy by output_id can't refresh them).
    """
    key = settings.sarvam_key()
    if not key:
        return None

    tts_config = request.stages.get("tts")
    codec = tts_config.codec if tts_config and tts_config.codec else "wav"
    payload = {
        "inputs": [sentence],
        "target_language_code": language_code,
        "model": tts_config.model if tts_config and tts_config.model else "bulbul:v3",
        "speaker": tts_config.speaker if tts_config and tts_config.speaker else "ritu",
        "pace": tts_config.pace if tts_config and tts_config.pace else 1.1,
        "speech_sample_rate": tts_config.sample_rate if tts_config and tts_config.sample_rate else 22050,
        "output_audio_codec": codec,
        "enable_preprocessing": True if tts_config is None or tts_config.enable_preprocessing is None else tts_config.enable_preprocessing,
    }
    headers = {"api-subscription-key": key}

    try:
        async with sarvam_semaphore:
            response = await client.post(settings.sarvam_tts_path, json=payload, headers=headers)
        response.raise_for_status()
        data = response.json()
    except Exception as exc:
        provider_health.record_failure("sarvam_tts", str(exc))
        return None

    audios = data.get("audios") or []
    if not isinstance(audios, list) or not audios:
        provider_health.record_failure("sarvam_tts", "no audios in streaming response")
        return None
    piece = audios[0]
    if not isinstance(piece, str) or not piece.strip():
        provider_health.record_failure("sarvam_tts", "empty streaming audio piece")
        return None
    provider_health.record_success("sarvam_tts")
    mime_type = f"audio/{'mpeg' if codec == 'mp3' else codec}"

    # Iter-9 persisted + minted a signed URL alongside the inline base64.
    # Iter-13 drops the base64 from the SSE frame; on persist/sign failure
    # we surface the sentence as None so the caller emits ``audio_error``
    # rather than silently shipping audio the consumer can no longer decode.
    # Iter-15 surfaces the bare S3 object key alongside the URL so JSX can
    # re-sign on TTL expiry via ``/v1/audio/re-sign?key=...``.
    audio_b64: str | None = None
    try:
        binary = base64.b64decode(piece)
    except Exception as exc:
        _log.warning("streaming_b64_decode_failed err=%s", exc, exc_info=True)
        binary = b""
    if binary:
        # Ephemeral: relay the audio inline as base64 and never persist it.
        # The browser plays it via a ``data:`` URI; nothing is written to disk
        # or object storage.
        audio_b64 = base64.b64encode(binary).decode("ascii")
    return None, None, mime_type, audio_b64


async def _stream_s2s(
    request: SpeechToSpeechRequest,
    adapters: dict[str, BaseAdapter],
    settings: Settings,
    sarvam_client: httpx.AsyncClient,
    artifact_store: ArtifactStore,
    sarvam_semaphore: asyncio.Semaphore,
) -> AsyncIterator[bytes]:
    """Async generator that yields SSE frames for the streaming endpoint.

    Sequence: stt? → translation+audio per language → done. STT is only
    emitted when audio input is supplied; for direct-text callers the
    stream jumps straight to per-language translation.
    """
    t_total_start = time.perf_counter()
    source_text = (request.source_text or "").strip()
    effective_source_language = request.source_language
    provider_used = "text_input"
    # Iter-21: per-stage + time-to-first-audio instrumentation for the
    # streaming path. The batched path already builds ``stage_ms``; the
    # streaming path previously logged only ``total=`` so we were optimising
    # latency blind. ``metrics`` is shared with the per-language producers
    # below — asyncio is single-threaded, so plain dict writes between awaits
    # need no lock. ``first_audio_ms`` is the metric that actually maps to
    # perceived speak->hear delay (when sentence 1 audio leaves the gateway).
    metrics: dict[str, object] = {
        "stt_ms": None,
        "first_audio_ms": None,
        "languages": {},
    }

    if not source_text and request.audio_base64:
        t_stt = time.perf_counter()
        # Step 1a (lever #1): prefer STT-WS when enabled; it returns None for
        # non-WAV input or any error, so the HTTP path is the automatic fallback.
        # Default-off flag means zero change to the live pipeline until enabled.
        stt_result = None
        if settings.enable_sarvam_ws and settings.enable_sarvam_ws_stt:
            stt_result = await _try_stt_ws(request, settings)
        if not stt_result:
            stt_result = await _try_stt(request, settings, sarvam_client, sarvam_semaphore)
        metrics["stt_ms"] = round((time.perf_counter() - t_stt) * 1000, 1)
        if stt_result and stt_result["transcript"]:
            source_text = stt_result["transcript"]
            provider_used = "sarvam_stt"
            if stt_result["detected_language"]:
                effective_source_language = stt_result["detected_language"]
        else:
            provider_used = "sarvam_stt_unavailable"
        # Ephemeral: emit STT status only — never the transcript text. The
        # source text is used internally for translation but is not sent to the
        # client (no text is shown or saved).
        yield _sse_event(
            "stt",
            {
                "detected_language": effective_source_language,
                "provider_used": provider_used,
            },
        )

    # Iter-17d: parallelize per-language fanout. Iter-17c already parallelized
    # the BATCHED path (8s -> 3.2s for 3 langs); the streaming path was punted
    # because SSE frames had an order constraint. We close that gap with a
    # frame queue + one producer coroutine per language. Each producer pushes
    # its translation/audio/language_done frames into a shared asyncio.Queue;
    # the main coroutine drains the queue and yields frames in arrival order.
    # Frontend SSE consumer already routes by ``language_code`` in payload, so
    # interleaved arrival across languages is fine. WITHIN a single language,
    # frame order is preserved because each producer pushes its own frames in
    # order into the FIFO queue.
    queue: asyncio.Queue[bytes | None] = asyncio.Queue()

    async def language_producer(language_code: str) -> None:
        t_lang = time.perf_counter()
        tts_session = None
        prewarm_task = None
        ws_codec = settings.sarvam_ws_tts_codec or "mp3"
        ws_tts_enabled = (
            settings.enable_sarvam_ws
            and settings.enable_sarvam_ws_tts
            and sarvam_ws.sdk_available()
            and bool(settings.sarvam_key())
        )
        try:
            # Pre-open the TTS-WS connection NOW so its ~260ms connect+TLS
            # handshake overlaps the translate call below instead of stacking on
            # top of first-audio. Opened for any audio language; closed in the
            # finally if it goes unused (empty translation) or after streaming.
            # The connect runs OUTSIDE the Sarvam concurrency semaphore (it's
            # just a socket) — the semaphore still gates the actual synthesis.
            if ws_tts_enabled and language_code in AUDIO_LANGUAGES:
                tts_session = sarvam_ws.TtsWsSession(
                    api_key=settings.sarvam_key(), output_audio_codec=ws_codec
                )
                prewarm_task = asyncio.create_task(tts_session.open())
            translation_degraded = False
            if _same_language(language_code, effective_source_language):
                translated = source_text
                translation_provider = "passthrough"
            else:
                try:
                    translated, translation_provider, translation_degraded = await _translate_for_s2s(
                        source_text,
                        effective_source_language,
                        language_code,
                        request,
                        adapters,
                        settings,
                        sarvam_client,
                        sarvam_semaphore,
                    )
                    # Iter-20: mirror the batched path's short-identity-passthrough
                    # detection so the SSE ``translation`` frame's
                    # ``translation_degraded`` flag matches what /v1/speech-to-speech
                    # would emit for the same input. See _process_language above
                    # for full rationale.
                    if (
                        translated
                        and _is_short_identity_passthrough(source_text, translated)
                    ):
                        translation_degraded = False
                        translation_provider = "passthrough_short"
                except Exception as exc:
                    await queue.put(
                        _sse_event(
                            "language_error",
                            {
                                "language_code": language_code,
                                "stage": "translation",
                                "error": type(exc).__name__,
                                "message": str(exc)[:200],
                            },
                        )
                    )
                    return

            lang_metrics = metrics["languages"].setdefault(language_code, {})
            lang_metrics["translate_ms"] = round((time.perf_counter() - t_lang) * 1000, 1)

            # Ephemeral: emit translation status only — the translated text is
            # used internally to drive TTS but is never sent to the client.
            await queue.put(
                _sse_event(
                    "translation",
                    {
                        "language_code": language_code,
                        "provider_used": translation_provider,
                        "translation_degraded": translation_degraded,
                    },
                )
            )

            audio_supported = language_code in AUDIO_LANGUAGES
            if not audio_supported or not translated:
                await queue.put(
                    _sse_event(
                        "language_done",
                        {
                            "language_code": language_code,
                            "total_sentences": 0,
                            "audio_supported": audio_supported,
                            "lang_ms": round((time.perf_counter() - t_lang) * 1000, 1),
                        },
                    )
                )
                return

            # Phase 1: Sarvam TTS-WS path — stream audio chunks at ~0.6s TTFB
            # (vs the ~1.8s per-sentence HTTP round-trip). Emits ``audio_chunk``
            # frames; the frontend plays them via MediaSource. Falls through to
            # the HTTP sentence loop below if the WS errors BEFORE the first
            # chunk, so a connection blip never loses a segment.
            if tts_session is not None:
                ws_mime = "audio/mpeg" if ws_codec == "mp3" else f"audio/{ws_codec}"
                seq = 0
                ws_error: Exception | None = None
                try:
                    # Await the connection we pre-opened during translate (its
                    # handshake is already overlapped, so this rarely blocks). A
                    # connect failure raises here and is caught below → fall
                    # through to the HTTP sentence loop, never losing the segment.
                    if prewarm_task is not None:
                        await prewarm_task
                    # Gate the actual synthesis by the shared Sarvam semaphore so
                    # a many-language session can't exceed the API key's
                    # concurrency; the first ``sarvam_concurrency_limit`` languages
                    # synthesize immediately so global first-audio is unaffected.
                    async with sarvam_semaphore:
                        async for chunk in tts_session.stream(
                            text=translated,
                            language_code=language_code,
                        ):
                            if seq == 0:
                                lang_metrics["tts_first_ms"] = round((time.perf_counter() - t_lang) * 1000, 1)
                            if metrics["first_audio_ms"] is None:
                                metrics["first_audio_ms"] = round((time.perf_counter() - t_total_start) * 1000, 1)
                            await queue.put(
                                _sse_event(
                                    "audio_chunk",
                                    {
                                        "language_code": language_code,
                                        "seq": seq,
                                        "first": seq == 0,
                                        "audio_base64": base64.b64encode(chunk).decode("ascii"),
                                        "audio_mime_type": ws_mime,
                                    },
                                )
                            )
                            seq += 1
                except Exception as exc:  # noqa: BLE001 - decide fall-through vs partial-close below
                    ws_error = exc
                if seq > 0:
                    provider_health.record_success("sarvam_tts")
                    # Ephemeral: the audio was already streamed inline via the
                    # ``audio_chunk`` frames; nothing is archived to object storage.
                    if ws_error is not None:
                        await queue.put(
                            _sse_event(
                                "audio_error",
                                {"language_code": language_code, "message": f"ws_interrupted: {str(ws_error)[:160]}"},
                            )
                        )
                    await queue.put(
                        _sse_event(
                            "language_done",
                            {
                                "language_code": language_code,
                                "total_chunks": seq,
                                "mode": "ws",
                                "audio_supported": True,
                                "audio_mime_type": ws_mime,
                                "lang_ms": round((time.perf_counter() - t_lang) * 1000, 1),
                            },
                        )
                    )
                    return
                # No chunk delivered (WS errored before first chunk or yielded
                # nothing): log and fall through to the HTTP sentence loop.
                _log.warning(
                    "sarvam_ws_tts_fallback lang=%s err=%s", language_code, ws_error
                )

            sentences = _split_sentences_for_streaming(translated)
            # Iter-21: if the opening sentence is long, break out its first
            # clause so the very first TTS dispatch is short and audio starts
            # sooner. Bounded — short replies are left untouched.
            if settings.s2s_inline_first_audio and sentences and len(sentences[0]) > 120:
                sentences = _split_first_clause(sentences)
            total = len(sentences)
            delivered = 0
            for idx, sentence in enumerate(sentences):
                t_sentence = time.perf_counter()
                try:
                    tts = await _tts_one_sentence(
                        language_code=language_code,
                        sentence=sentence,
                        request=request,
                        settings=settings,
                        client=sarvam_client,
                        artifact_store=artifact_store,
                        sarvam_semaphore=sarvam_semaphore,
                        defer_persist=(settings.s2s_inline_first_audio and idx == 0),
                    )
                except Exception as exc:  # producer must not bubble — push an audio_error frame
                    await queue.put(
                        _sse_event(
                            "audio_error",
                            {
                                "language_code": language_code,
                                "sentence_index": idx,
                                "total_sentences": total,
                                "error": type(exc).__name__,
                                "message": str(exc)[:200],
                            },
                        )
                    )
                    continue
                if tts is None:
                    await queue.put(
                        _sse_event(
                            "audio_error",
                            {
                                "language_code": language_code,
                                "sentence_index": idx,
                                "total_sentences": total,
                            },
                        )
                    )
                    continue
                _audio_url, _audio_key, mime_type, audio_b64 = tts
                delivered += 1
                # Record the first sentence's TTS cost per language, and the
                # global time-to-first-audio (the first audio frame across ALL
                # languages to reach the consumer). These feed the ``done``
                # frame + log line so we can attribute streaming latency.
                if idx == 0:
                    lang_metrics["tts_first_ms"] = round((time.perf_counter() - t_sentence) * 1000, 1)
                if metrics["first_audio_ms"] is None:
                    metrics["first_audio_ms"] = round((time.perf_counter() - t_total_start) * 1000, 1)
                # Ephemeral: audio is relayed inline as base64 (no signed URL,
                # no object key) and no sentence text is sent to the client.
                frame_payload = {
                    "language_code": language_code,
                    "sentence_index": idx,
                    "total_sentences": total,
                    "audio_mime_type": mime_type,
                }
                if audio_b64:
                    frame_payload["audio_base64"] = audio_b64
                await queue.put(_sse_event("audio", frame_payload))

            await queue.put(
                _sse_event(
                    "language_done",
                    {
                        "language_code": language_code,
                        "total_sentences": total,
                        "delivered_sentences": delivered,
                        "audio_supported": True,
                        "lang_ms": round((time.perf_counter() - t_lang) * 1000, 1),
                    },
                )
            )
        except Exception as exc:  # belt-and-braces: never let a producer kill the queue
            await queue.put(
                _sse_event(
                    "language_error",
                    {
                        "language_code": language_code,
                        "stage": "producer",
                        "error": type(exc).__name__,
                        "message": str(exc)[:200],
                    },
                )
            )
        finally:
            # Always settle the pre-warm task and close the TTS-WS connection so
            # a pre-opened session never leaks — whether it streamed, fell back
            # to HTTP, or the language returned early (empty translation). close()
            # is idempotent, so the normal post-stream path is a no-op here.
            if prewarm_task is not None and not prewarm_task.done():
                prewarm_task.cancel()
            if prewarm_task is not None:
                try:
                    await prewarm_task
                except (Exception, asyncio.CancelledError):
                    pass
            if tts_session is not None:
                await tts_session.close()

    producers = [
        asyncio.create_task(language_producer(lc)) for lc in request.target_languages
    ]

    async def _close_queue() -> None:
        await asyncio.gather(*producers, return_exceptions=True)
        await queue.put(None)

    closer = asyncio.create_task(_close_queue())

    try:
        while True:
            frame = await queue.get()
            if frame is None:
                break
            yield frame
    finally:
        # Ensure background tasks are awaited / cleaned up if the consumer
        # disconnects mid-stream. ``return_exceptions=True`` keeps one
        # producer failure from masking other producers' frames.
        for task in producers:
            if not task.done():
                task.cancel()
        if not closer.done():
            closer.cancel()
        await asyncio.gather(*producers, closer, return_exceptions=True)

    total_ms = round((time.perf_counter() - t_total_start) * 1000, 1)
    # Representative single-value stage latencies for the Laravel
    # ``stageLatencySummary`` harvester (it reads scalar ``*_latency_ms``
    # paths off dispatch.response). Use the fastest language's first-sentence
    # figures — that's the path the listener actually hears first.
    lang_vals = [v for v in metrics["languages"].values() if isinstance(v, dict)]
    translate_vals = [v["translate_ms"] for v in lang_vals if v.get("translate_ms") is not None]
    tts_vals = [v["tts_first_ms"] for v in lang_vals if v.get("tts_first_ms") is not None]
    _log.info(
        "stream session=%s segment=%s total=%sms first_audio=%sms stt=%sms",
        request.session_id,
        request.segment_id,
        total_ms,
        metrics["first_audio_ms"],
        metrics["stt_ms"],
    )
    yield _sse_event(
        "done",
        {
            "total_ms": total_ms,
            "provider_used": provider_used,
            "source_text": source_text,
            # Per-stage breakdown for the operator latency HUD / SLO badge.
            "first_audio_ms": metrics["first_audio_ms"],
            "stt_latency_ms": metrics["stt_ms"],
            "translation_latency_ms": min(translate_vals) if translate_vals else None,
            "tts_latency_ms": min(tts_vals) if tts_vals else None,
            "first_byte_ms": metrics["first_audio_ms"],
            "stage_latency": {
                "stt_ms": metrics["stt_ms"],
                "first_audio_ms": metrics["first_audio_ms"],
                "languages": metrics["languages"],
            },
        },
    )


@router.post("/speech-to-speech/stream")
async def speech_to_speech_stream(
    request: SpeechToSpeechRequest,
    http_request: Request,
    adapters: AdaptersDep,
    settings: SettingsDep,
    artifact_store: ArtifactStoreDep,
) -> StreamingResponse:
    """Per-sentence streaming TTS variant of /v1/speech-to-speech.

    Returns ``text/event-stream`` (SSE). The browser (or PHP proxy) can
    begin audio playback as soon as the first ``event: audio`` frame
    arrives, while later sentences are still being synthesised.
    Feature-flagged via ``ENABLE_S2S_STREAMING_TTS`` — when disabled the
    endpoint 404s so callers fall back to the batched route.
    """
    if not settings.enable_s2s_streaming_tts:
        raise HTTPException(status_code=404, detail="streaming TTS disabled")
    sarvam_client: httpx.AsyncClient = http_request.app.state.sarvam_client
    sarvam_semaphore: asyncio.Semaphore = http_request.app.state.sarvam_semaphore
    return StreamingResponse(
        _stream_s2s(request, adapters, settings, sarvam_client, artifact_store, sarvam_semaphore),
        media_type="text/event-stream",
        headers={
            # Disable buffering at intermediate proxies (nginx, Caddy) so
            # each SSE frame reaches the browser as soon as it's written.
            "X-Accel-Buffering": "no",
            "Cache-Control": "no-cache",
        },
    )


def _split_sentences_for_streaming(text: str, limit: int = 450) -> list[str]:
    """Per-sentence splitter for the streaming endpoint.

    Unlike ``_split_text_for_tts``, this never merges short sentences —
    each sentence becomes its own TTS dispatch so the browser can start
    playing immediately. Sentences longer than ``limit`` characters are
    further split on whitespace to keep individual Sarvam requests under
    the 450-char input limit.
    """
    cleaned = text.strip()
    if not cleaned:
        return []
    parts: list[str] = []
    for sentence in re.split(r"(?<=[।.!?])\s+", cleaned):
        sentence = sentence.strip()
        if not sentence:
            continue
        if len(sentence) > limit:
            parts.extend(_split_long_text_for_tts(sentence, limit))
        else:
            parts.append(sentence)
    return parts or [cleaned]


def _split_first_clause(sentences: list[str], min_head: int = 15, max_head: int = 120) -> list[str]:
    """Break the first sentence at its first clause boundary.

    Lets the streaming endpoint dispatch a short opening clause first so audio
    starts sooner, then continue with the remainder. Splits on a clause
    delimiter (comma / semicolon / colon / dash) followed by whitespace, only
    when the resulting head is in ``[min_head, max_head]`` chars — short enough
    to be quick, long enough to be a meaningful phrase. Returns the input
    unchanged when no suitable boundary exists, so genuine single-clause
    sentences are never fragmented.
    """
    if not sentences:
        return sentences
    first = sentences[0]
    split_at: int | None = None
    for match in re.finditer(r"[,;:—–]\s+", first):
        head_len = match.start() + 1
        if min_head <= head_len <= max_head:
            split_at = match.end()
            break
    if split_at is None:
        return sentences
    head = first[:split_at].strip()
    tail = first[split_at:].strip()
    if not head or not tail:
        return sentences
    return [head, tail, *sentences[1:]]


def _split_text_for_tts(text: str, limit: int = 450) -> list[str]:
    # Split after Devanagari (।) or Latin sentence-ending punctuation followed by
    # whitespace. The earlier form used "\\s+" (literal backslash-s), so the
    # regex matched nothing and the whole text always landed in one chunk —
    # which silently neutered per-sentence TTS streaming.
    sentences = [part.strip() for part in re.split(r"(?<=[।.!?])\s+", text.strip()) if part.strip()]
    parts: list[str] = []
    current = ""

    for sentence in sentences or [text.strip()]:
        if len(sentence) > limit:
            if current:
                parts.append(current)
                current = ""
            parts.extend(_split_long_text_for_tts(sentence, limit))
            continue

        candidate = f"{current} {sentence}".strip()
        if current and len(candidate) > limit:
            parts.append(current)
            current = sentence
        else:
            current = candidate

    if current:
        parts.append(current)

    return parts or [text.strip()]


def _split_long_text_for_tts(text: str, limit: int) -> list[str]:
    parts: list[str] = []
    current = ""
    for word in text.split():
        candidate = f"{current} {word}".strip()
        if current and len(candidate) > limit:
            parts.append(current)
            current = word
        else:
            current = candidate
    if current:
        parts.append(current)
    return parts


def _join_audio_parts(audio_parts: list[bytes], codec: str) -> bytes:
    if len(audio_parts) <= 1:
        return audio_parts[0] if audio_parts else b""

    if codec.lower() != "wav":
        return b"".join(audio_parts)

    fmt: bytes | None = None
    pcm = b""
    for part in audio_parts:
        parsed = _parse_wav(part)
        if parsed is None:
            return b"".join(audio_parts)
        fmt = fmt or parsed[0]
        pcm += parsed[1]

    assert fmt is not None
    return b"RIFF" + struct.pack("<I", 4 + (8 + len(fmt)) + (8 + len(pcm))) + b"WAVE" + b"fmt " + struct.pack("<I", len(fmt)) + fmt + b"data" + struct.pack("<I", len(pcm)) + pcm


def _parse_wav(audio: bytes) -> tuple[bytes, bytes] | None:
    if len(audio) < 44 or audio[:4] != b"RIFF" or audio[8:12] != b"WAVE":
        return None

    offset = 12
    fmt: bytes | None = None
    data: bytes | None = None
    while offset + 8 <= len(audio):
        chunk_id = audio[offset : offset + 4]
        chunk_size = struct.unpack("<I", audio[offset + 4 : offset + 8])[0]
        chunk_data = audio[offset + 8 : offset + 8 + chunk_size]
        if chunk_id == b"fmt ":
            fmt = chunk_data
        elif chunk_id == b"data":
            data = chunk_data
        offset += 8 + chunk_size + (chunk_size % 2)

    return (fmt, data) if fmt is not None and data is not None else None


def _normalise_language(language_code: str) -> str:
    return language_code.split("-", 1)[0].lower()


_LANGUAGE_TAG_REGION = {
    "hi": "hi-IN", "en": "en-IN", "bn": "bn-IN", "ta": "ta-IN", "te": "te-IN",
    "kn": "kn-IN", "ml": "ml-IN", "mr": "mr-IN", "gu": "gu-IN", "pa": "pa-IN",
    "od": "od-IN", "or": "od-IN",
}


def _canonical_language_tag(language_code: str | None) -> str | None:
    if not language_code:
        return None
    value = str(language_code).strip()
    if not value or value.lower() == "auto":
        return None
    if "-" in value:
        return value
    return _LANGUAGE_TAG_REGION.get(value.lower(), value)


def _same_language(a: str | None, b: str | None) -> bool:
    if not a or not b:
        return False
    if a.lower() == "auto" or b.lower() == "auto":
        return False
    return _normalise_language(a) == _normalise_language(b)


def _is_short_identity_passthrough(source: str, translated: str) -> bool:
    """True when the translator echoed a short source verbatim.

    Iter-19e audit found that Sarvam translate and the IndicTrans2 fallback
    both occasionally return the source string unchanged when the input is
    too short or too ambiguous (single-word fragments like ``"Smooth."`` or
    ``"So,"``). Those rows landed as ``translation_degraded`` because the
    IndicTrans2 adapter set ``fallback_used=True`` — but no degradation
    actually occurred; it's a benign passthrough on a fragment that has no
    meaningful translation. This helper detects that case so the caller can
    swap the status to a clean ``passthrough_short`` provider tag.

    Returns True when ALL hold:
      * both strings have content after ``.strip()``
      * ``.strip().casefold()`` of source == translated
      * source length ≤ 8 characters OR source has ≤ 2 whitespace-separated
        tokens (catches both ``"So,"`` and ``"thank you,"``)

    The dual threshold is deliberate: very short single-token fragments
    ("ओके") and 2-word fragments ("thank you") both surface this way; one
    rule alone misses half the cases.
    """
    src = (source or "").strip()
    tgt = (translated or "").strip()
    if not src or not tgt:
        return False
    if src.casefold() != tgt.casefold():
        return False
    # Token count first (cheaper than re-walking the string).
    tokens = [t for t in src.split() if t]
    if len(tokens) <= 2:
        return True
    return len(src) <= 8

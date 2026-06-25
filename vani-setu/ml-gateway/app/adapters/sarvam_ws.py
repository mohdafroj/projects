"""Sarvam streaming WebSocket adapters (TTS-WS + STT-WS).

Active only when ``settings.enable_sarvam_ws`` is true; the HTTP adapters in
``routers/speech_to_speech.py`` remain the default and the automatic fallback
on any error here. TTS-WS (bulbul) ships audio chunks at <250 ms TTFB instead
of the ~1.8 s per-sentence HTTP round-trip; STT-WS (saaras) streams partial +
final transcripts so translation can begin before the speaker finishes.

The ``sarvamai`` SDK encapsulates the wss:// URL, the ``api-subscription-key``
handshake auth, and the config/convert/flush framing. Import is guarded so a
gateway built without the SDK simply reports ``sdk_available() == False`` and
the caller stays on the HTTP path.
"""
from __future__ import annotations

import base64
import logging
import time
from collections.abc import AsyncIterator

_log = logging.getLogger("ml_gateway.sarvam_ws")

try:  # pragma: no cover - import guard
    from sarvamai import AsyncSarvamAI, AudioOutput, EventResponse

    _SARVAM_SDK = True
except Exception:  # pragma: no cover
    AsyncSarvamAI = None  # type: ignore[assignment]
    AudioOutput = EventResponse = None  # type: ignore[assignment]
    _SARVAM_SDK = False


def sdk_available() -> bool:
    """True when the ``sarvamai`` SDK imported — gate WS use on this."""
    return _SARVAM_SDK


# Reuse one AsyncSarvamAI client per api-key across calls instead of
# constructing a fresh one each synthesis. A new client builds a new underlying
# connector, so every call paid a cold TLS handshake to api.sarvam.ai (~330ms,
# measured 2026-06-14 — half of TTS-WS first-audio). Reusing the client lets the
# transport pool/resume TLS so the per-call WS connect cost drops.
_CLIENT_CACHE: dict[str, object] = {}


def _client_for(api_key: str):  # type: ignore[no-untyped-def]
    client = _CLIENT_CACHE.get(api_key)
    if client is None:
        client = AsyncSarvamAI(api_subscription_key=api_key)
        _CLIENT_CACHE[api_key] = client
    return client


class TtsWsSession:
    """A pre-openable Sarvam TTS-WS session.

    ``open()`` pays the per-call WS connect + TLS handshake (~260ms, measured
    2026-06-14 — about half of TTS-WS first-audio). Calling it eagerly, e.g.
    concurrently with the translate HTTP call (~420ms), hides that cost entirely
    behind translate, so ``stream()`` (once the translated text is ready) reaches
    Sarvam's own ~270ms TTFB without the handshake on the critical path. The
    connection is single-use (one synthesis); always ``close()`` it — including
    when a pre-opened session goes unused (passthrough / empty translation).
    """

    def __init__(self, *, api_key: str, model: str = "bulbul:v3", output_audio_codec: str = "mp3") -> None:
        self._api_key = api_key
        self._model = model
        self._codec = output_audio_codec
        self._cm = None
        self._ws = None
        self._t_open0 = 0.0
        self._t_conn = 0.0

    async def open(self) -> "TtsWsSession":
        if not _SARVAM_SDK:
            raise RuntimeError("sarvamai SDK not installed")
        self._t_open0 = time.monotonic()
        client = _client_for(self._api_key)
        self._cm = client.text_to_speech_streaming.connect(
            model=self._model, send_completion_event=True
        )
        self._ws = await self._cm.__aenter__()
        self._t_conn = time.monotonic()
        return self

    async def stream(
        self,
        *,
        text: str,
        language_code: str,
        speaker: str = "shubh",  # MUST be a bulbul:v3 voice — v3-WS silently emits no audio for v2 speakers (e.g. "ritu")
        pace: float = 1.1,
    ) -> AsyncIterator[bytes]:
        if self._ws is None:
            raise RuntimeError("TtsWsSession.stream() called before open()")
        ws = self._ws
        await ws.configure(
            target_language_code=language_code,
            speaker=speaker,
            pace=pace,
            output_audio_codec=self._codec,
        )
        await ws.convert(text)
        await ws.flush()
        t_sent = time.monotonic()
        first = True
        async for message in ws:
            if isinstance(message, AudioOutput):
                chunk = base64.b64decode(message.data.audio)
                if chunk:
                    if first:
                        # Sub-timing (debug, lazy). With pre-connect, connect_ms
                        # overlaps translate so it's off the critical path; the
                        # number here is just the handshake duration for ops.
                        now = time.monotonic()
                        _log.debug(
                            "tts_ws_timing lang=%s connect_ms=%.0f config_send_ms=%.0f sarvam_ttfb_ms=%.0f",
                            language_code,
                            (self._t_conn - self._t_open0) * 1000,
                            (t_sent - self._t_conn) * 1000,
                            (now - t_sent) * 1000,
                        )
                        first = False
                    yield chunk
            elif (
                isinstance(message, EventResponse)
                and getattr(message.data, "event_type", None) == "final"
            ):
                break

    async def close(self) -> None:
        cm = self._cm
        self._cm = None
        self._ws = None
        if cm is not None:
            try:
                await cm.__aexit__(None, None, None)
            except Exception:  # pragma: no cover - best-effort teardown
                pass


async def stream_tts_chunks(
    *,
    api_key: str,
    text: str,
    language_code: str,
    speaker: str = "shubh",  # MUST be a bulbul:v3 voice — v3-WS silently emits no audio for v2 speakers (e.g. "ritu"); validated 2026-06-13
    model: str = "bulbul:v3",
    pace: float = 1.1,
    output_audio_codec: str = "mp3",
) -> AsyncIterator[bytes]:
    """Yield raw audio byte chunks for ``text`` via Sarvam TTS WebSocket.

    Convenience wrapper that connects-then-streams in one call (no pre-connect
    overlap). ``mp3`` is the default codec because its frames are independently
    decodable, which makes chunk-by-chunk browser playback (MediaSource) clean —
    unlike a single-header WAV. Raises on any connection/SDK error so the caller
    can fall back to the HTTP per-sentence path without losing the segment.
    """
    session = TtsWsSession(api_key=api_key, model=model, output_audio_codec=output_audio_codec)
    await session.open()
    try:
        async for chunk in session.stream(text=text, language_code=language_code, speaker=speaker, pace=pace):
            yield chunk
    finally:
        await session.close()


async def stream_stt(
    *,
    api_key: str,
    audio_b64: str,
    language_code: str | None,
    sample_rate: int = 16000,
    encoding: str = "audio/wav",
    model: str = "saaras:v3",
    mode: str = "codemix",
) -> AsyncIterator[dict]:
    """Yield transcript events for one audio blob via Sarvam STT WebSocket.

    Each yielded dict is ``{"type": "events"|"data", ...}``; ``data`` carries
    ``transcript``. Phase 1b wires this in place of the HTTP ``_try_stt`` so
    translation can start on the first final segment. Raises on SDK/connection
    error for HTTP fallback.
    """
    if not _SARVAM_SDK:
        raise RuntimeError("sarvamai SDK not installed")
    client = _client_for(api_key)
    connect_kwargs: dict = {"model": model, "mode": mode, "high_vad_sensitivity": True}
    if language_code:
        connect_kwargs["language_code"] = language_code
    async with client.speech_to_text_streaming.connect(**connect_kwargs) as ws:
        await ws.transcribe(audio=audio_b64, sample_rate=sample_rate, encoding=encoding)
        # Force finalize: for a discrete blob there's no trailing silence, so the
        # server's END_SPEECH VAD signal never fires and the iterator would hang.
        # flush() makes Sarvam finalize the buffered audio immediately.
        try:
            await ws.flush()
        except Exception:  # pragma: no cover - older SDKs without flush()
            pass
        async for message in ws:
            mtype = getattr(message, "type", None)
            data = getattr(message, "data", None)
            if mtype == "data" and data is not None:
                yield {"type": "data", "transcript": getattr(data, "transcript", "")}
            elif mtype == "events" and data is not None:
                signal = getattr(data, "signal_type", None)
                yield {"type": "events", "signal_type": signal}
                if signal == "END_SPEECH":
                    break

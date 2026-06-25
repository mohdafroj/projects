import importlib.util

import httpx

from app.adapters.base import AdapterUnavailable, BaseAdapter
from app.models.schemas import ASRRequest, ASRResponse, TranslateRequest, TranslateResponse
from app.services import provider_health


# Iter-23: script-validation guard against translation_degraded false positives.
# Previously this adapter flipped fallback_used=True whenever the Tijori sidecar
# metadata smelled fishy (served_via contained "ollama"/"fallback" or
# model_status said "unavailable"). But the produced TEXT was often correct
# Devanagari/Tamil/Punjabi — so ~80 historic s2s_outputs rows were mis-flagged
# as translation_degraded even though the translation was fine. The right test
# is whether the OUTPUT IS BAD, not whether the metadata smells fishy. We now
# script-validate the translation against the target language Unicode block;
# the metadata sniff is a HINT only.
#
# Note: 'sd' (Sindhi) is dual-script — Arabic in Pakistan, Devanagari in India.
# We intentionally leave it OUT of this map and let the existing metadata
# heuristic decide for those rows.
_SCRIPT_RANGES = {
    "hi": (0x0900, 0x097F),  # Devanagari
    "mr": (0x0900, 0x097F),
    "sa": (0x0900, 0x097F),
    "kok": (0x0900, 0x097F),
    "ne": (0x0900, 0x097F),
    "bn": (0x0980, 0x09FF),  # Bengali
    "as": (0x0980, 0x09FF),
    "ta": (0x0B80, 0x0BFF),  # Tamil
    "te": (0x0C00, 0x0C7F),  # Telugu
    "kn": (0x0C80, 0x0CFF),  # Kannada
    "ml": (0x0D00, 0x0D7F),  # Malayalam
    "gu": (0x0A80, 0x0AFF),  # Gujarati
    "pa": (0x0A00, 0x0A7F),  # Gurmukhi
    "od": (0x0B00, 0x0B7F),  # Oriya
    "or": (0x0B00, 0x0B7F),
    "ur": (0x0600, 0x06FF),  # Arabic (Urdu)
}


def _script_matches_target(text: str, target_lang: str) -> bool:
    """Return True when at least 50% of the alphabetic chars in ``text``
    fall within the expected Unicode block for ``target_lang``. English
    target passes iff text is mostly ASCII letters. Empty text returns
    False (so the existing empty-translation guard stays in charge of
    that path)."""
    if not text or not text.strip():
        return False
    norm = target_lang.split("-", 1)[0].lower()
    if norm == "en":
        alpha = [c for c in text if c.isalpha()]
        if not alpha:
            return False
        ascii_alpha = sum(1 for c in alpha if c.isascii())
        return ascii_alpha / len(alpha) >= 0.5
    rng = _SCRIPT_RANGES.get(norm)
    if rng is None:
        # Unknown target (e.g. 'sd' Sindhi, dual-script) — don't override;
        # let the existing metadata heuristic decide for these rows.
        return False
    lo, hi = rng
    alpha = [c for c in text if c.isalpha() or (lo <= ord(c) <= hi)]
    if not alpha:
        return False
    in_range = sum(1 for c in alpha if lo <= ord(c) <= hi)
    return in_range / len(alpha) >= 0.5


class IndicTrans2Adapter(BaseAdapter):
    name = "indictrans2"
    model_version = "indictrans2"

    async def check(self) -> bool:
        if self.circuit.is_open:
            return False
        if self.settings.indictrans2_endpoint_url:
            self.circuit.last_error = None
            return True
        self.circuit.last_error = None
        return True

    async def transcribe(self, request: ASRRequest) -> ASRResponse:
        raise AdapterUnavailable("IndicTrans2 does not support ASR")

    async def translate(self, request: TranslateRequest) -> TranslateResponse:
        async def call() -> TranslateResponse:
            if self.settings.indictrans2_endpoint_url:
                # Tijori sidecar accepts source_text/source_lang/target_lang and
                # returns translated_text; keep "text" + "translation" as fallback
                # keys so a stock IndicTrans2 server still works.
                payload = {
                    "source_text": request.text,
                    "text": request.text,
                    "source_lang": request.source_lang,
                    "target_lang": request.target_lang,
                }
                try:
                    async with httpx.AsyncClient(timeout=20.0) as client:
                        response = await client.post(
                            str(self.settings.indictrans2_endpoint_url),
                            json=payload,
                        )
                        response.raise_for_status()
                        data = response.json()
                except Exception as exc:
                    provider_health.record_failure("indictrans2", str(exc))
                    raise
                provider_health.record_success("indictrans2")
                translation = str(
                    data.get("translated_text")
                    or data.get("translation")
                    or ""
                )
                # Tijori sidecar reports when it ran the Ollama fallback
                # instead of real IndicTrans2 weights. Historically we flagged
                # any such row as fallback_used=True so callers would skip
                # TTS (Qwen output once produced repetitive Hindi for every
                # chunk → playback bug). Iter-23: this heuristic mis-flagged
                # ~80 historic s2s_outputs rows whose OUTPUT TEXT was actually
                # correct (proper Devanagari/Tamil/Punjabi). The metadata
                # sniff is now a HINT only; script-validation against the
                # target language is authoritative.
                served_via = str(data.get("served_via") or "")
                model_status = str(data.get("model_status") or "")
                metadata_smells_fishy = (
                    "ollama" in served_via.lower()
                    or "fallback" in served_via.lower()
                    or "unavailable" in model_status.lower()
                )
                # Iter-23 override: if the translation is in the target
                # script we trust the OUTPUT regardless of served_via /
                # model_status. Sindhi ('sd') is intentionally excluded
                # from _SCRIPT_RANGES (dual-script) so it falls through.
                output_script_ok = _script_matches_target(
                    translation, request.target_lang
                )
                is_fallback = metadata_smells_fishy and not output_script_ok
                return TranslateResponse(
                    translation=translation,
                    confidence=0.0 if is_fallback else data.get("confidence"),
                    model_version=str(data.get("model") or data.get("model_version") or self.model_version),
                    provider_used=self.name,
                    fallback_used=is_fallback,
                )
            if (
                not self.settings.indictrans2_local_model_enabled
                or importlib.util.find_spec("transformers") is None
            ):
                # Iter-19: no real model loaded. Previously this branch emitted
                # a synthetic ``[IndicTrans2 deterministic draft <lang>] <text>``
                # placeholder so the s2s pipeline could detect "no real model"
                # via the ``-deterministic`` ``model_version`` suffix. But direct
                # ``/v1/translate`` callers (non-s2s NMT) were still seeing that
                # bracketed string verbatim. Return an explicit empty-translation
                # marker instead — same shape the s2s helper already rewrites to
                # — so both code paths see a clean signal.
                return TranslateResponse(
                    translation="",
                    confidence=0.0,
                    model_version=f"{self.model_version}-no-model",
                    provider_used="no_translation_model",
                    fallback_used=False,
                )
            try:
                from transformers import pipeline

                translator = pipeline("translation", model=self.settings.indictrans2_model_name)
                output = translator(
                    request.text,
                    src_lang=request.source_lang,
                    tgt_lang=request.target_lang,
                )
                translation = output[0].get("translation_text", "") if output else ""
            except Exception:
                # Iter-19: see note above — pipeline failed to load/run. Surface
                # the no-model marker shape instead of a bracketed placeholder.
                return TranslateResponse(
                    translation="",
                    confidence=0.0,
                    model_version=f"{self.model_version}-no-model",
                    provider_used="no_translation_model",
                    fallback_used=False,
                )
            return TranslateResponse(
                translation=translation,
                confidence=None,
                model_version=self.settings.indictrans2_model_name,
                provider_used=self.name,
                fallback_used=False,
            )

        return await self._with_resilience(call)

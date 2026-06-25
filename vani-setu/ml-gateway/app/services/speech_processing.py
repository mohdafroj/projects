import re
from collections import Counter

import httpx

from app.config import Settings
from app.models.schemas import (
    AlignmentRequest,
    AlignmentResponse,
    AlignmentWord,
    DiarisationRequest,
    DiarisationResponse,
    DiarisationSegment,
    LanguageCandidate,
    LanguageDetectionRequest,
    LanguageDetectionResponse,
)

REGIONAL_LANGUAGES_14 = {
    "as",
    "bn",
    "gu",
    "hi",
    "kn",
    "kok",
    "mai",
    "ml",
    "mr",
    "od",
    "pa",
    "ta",
    "te",
    "ur",
}

SCRIPT_RANGES = {
    "bn": (0x0980, 0x09FF),
    "gu": (0x0A80, 0x0AFF),
    "od": (0x0B00, 0x0B7F),
    "ta": (0x0B80, 0x0BFF),
    "te": (0x0C00, 0x0C7F),
    "kn": (0x0C80, 0x0CFF),
    "ml": (0x0D00, 0x0D7F),
    "pa": (0x0A00, 0x0A7F),
    "ur": (0x0600, 0x06FF),
    "hi": (0x0900, 0x097F),
}


async def diarise_audio(
    request: DiarisationRequest, settings: Settings
) -> DiarisationResponse:
    if settings.pyannote_endpoint_url and settings.pyannote_auth_token:
        try:
            return await _diarise_with_pyannote_endpoint(request, settings)
        except httpx.HTTPError:
            pass
    return _fallback_diarisation(request)


async def _diarise_with_pyannote_endpoint(
    request: DiarisationRequest, settings: Settings
) -> DiarisationResponse:
    payload = request.model_dump(mode="json")
    headers = {"Authorization": f"Bearer {settings.pyannote_auth_token}"}
    async with httpx.AsyncClient(timeout=30.0) as client:
        response = await client.post(
            str(settings.pyannote_endpoint_url),
            json=payload,
            headers=headers,
        )
        response.raise_for_status()
        data = response.json()

    segments = [
        DiarisationSegment(
            speaker=str(item.get("speaker", "SPEAKER_00")),
            start_ms=int(item.get("start_ms", 0)),
            end_ms=int(item.get("end_ms", 0)),
            confidence=item.get("confidence"),
        )
        for item in data.get("segments", [])
    ]
    return DiarisationResponse(
        provider_used="pyannote",
        segments=segments,
        model_version=str(data.get("model_version", "pyannote")),
        fallback_used=False,
    )


def _fallback_diarisation(request: DiarisationRequest) -> DiarisationResponse:
    duration_ms = request.duration_ms or 30_000
    speakers = request.num_speakers or request.min_speakers or 2
    speakers = min(max(speakers, 1), request.max_speakers or speakers)
    chunk_ms = 5_000
    segments: list[DiarisationSegment] = []
    for start_ms in range(0, duration_ms, chunk_ms):
        end_ms = min(start_ms + chunk_ms, duration_ms)
        speaker_index = (start_ms // chunk_ms) % speakers
        segments.append(
            DiarisationSegment(
                speaker=f"SPEAKER_{speaker_index:02d}",
                start_ms=start_ms,
                end_ms=end_ms,
                confidence=0.0,
            )
        )
    return DiarisationResponse(
        provider_used="pyannote-style-fallback",
        segments=segments,
        model_version="pyannote-style-deterministic",
        fallback_used=True,
    )


def align_transcript(request: AlignmentRequest) -> AlignmentResponse:
    words = re.findall(r"\S+", request.transcript.strip())
    if not words:
        return AlignmentResponse(
            provider_used="forced-alignment-fallback",
            language=request.language,
            words=[],
            model_version="deterministic-even-word-aligner",
            fallback_used=True,
        )

    duration_ms = request.duration_ms or max(len(words) * 450, 1_000)
    step = max(duration_ms // len(words), 1)
    aligned_words: list[AlignmentWord] = []
    for index, word in enumerate(words):
        start_ms = index * step
        end_ms = (
            duration_ms
            if index == len(words) - 1
            else min((index + 1) * step, duration_ms)
        )
        aligned_words.append(
            AlignmentWord(word=word, start_ms=start_ms, end_ms=end_ms, confidence=0.0)
        )

    return AlignmentResponse(
        provider_used="forced-alignment-fallback",
        language=request.language,
        words=aligned_words,
        model_version="deterministic-even-word-aligner",
        fallback_used=True,
    )


def detect_regional_language_fallback(
    request: LanguageDetectionRequest,
) -> LanguageDetectionResponse:
    counts: Counter[str] = Counter()
    for char in request.text:
        codepoint = ord(char)
        for language, (start, end) in SCRIPT_RANGES.items():
            if start <= codepoint <= end:
                counts[language] += 1
                break

    if request.hint in REGIONAL_LANGUAGES_14:
        counts[request.hint] += 1

    if not counts:
        language = request.hint if request.hint in REGIONAL_LANGUAGES_14 else "hi"
        candidates = [LanguageCandidate(language=language, confidence=0.25)]
    else:
        total = sum(counts.values())
        candidates = [
            LanguageCandidate(language=language, confidence=round(count / total, 4))
            for language, count in counts.most_common()
            if language in REGIONAL_LANGUAGES_14
        ]
        if not candidates:
            candidates = [LanguageCandidate(language="hi", confidence=0.25)]
        language = candidates[0].language

    return LanguageDetectionResponse(
        language=language,
        confidence=candidates[0].confidence,
        candidates=candidates[:3],
        provider_used="regional-script-fallback",
        model_version="unicode-script-v1",
        fallback_used=True,
    )

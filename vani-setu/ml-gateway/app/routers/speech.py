from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException

from app.adapters.base import AdapterError, AdapterUnavailable, BaseAdapter
from app.config import Settings, get_settings
from app.dependencies import get_adapters
from app.models.schemas import (
    AlignmentRequest,
    AlignmentResponse,
    DiarisationRequest,
    DiarisationResponse,
    LanguageDetectionRequest,
    LanguageDetectionResponse,
)
from app.services.speech_processing import (
    REGIONAL_LANGUAGES_14,
    align_transcript,
    detect_regional_language_fallback,
    diarise_audio,
)

router = APIRouter(prefix="/v1", tags=["speech"])

AdaptersDep = Annotated[dict[str, BaseAdapter], Depends(get_adapters)]
SettingsDep = Annotated[Settings, Depends(get_settings)]


@router.post("/diarisation", response_model=DiarisationResponse)
@router.post("/diarization", response_model=DiarisationResponse)
async def diarisation(
    request: DiarisationRequest,
    settings: SettingsDep,
) -> DiarisationResponse:
    return await diarise_audio(request, settings)


@router.post("/alignment", response_model=AlignmentResponse)
@router.post("/forced-alignment", response_model=AlignmentResponse)
async def forced_alignment(request: AlignmentRequest) -> AlignmentResponse:
    return align_transcript(request)


@router.post("/language/regional", response_model=LanguageDetectionResponse)
async def regional_language_detection(
    request: LanguageDetectionRequest,
    adapters: AdaptersDep,
    settings: SettingsDep,
) -> LanguageDetectionResponse:
    if settings.bhashini_api_key:
        try:
            response = await adapters["bhashini"].detect_language(request)
            if response.language in REGIONAL_LANGUAGES_14:
                return response
        except (AdapterError, AdapterUnavailable):
            pass

    fallback = detect_regional_language_fallback(request)
    if not fallback.candidates:
        raise HTTPException(status_code=422, detail="unable to detect regional language")
    return fallback

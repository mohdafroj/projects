from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException

from app.adapters.base import AdapterError, AdapterUnavailable, BaseAdapter
from app.config import Settings, get_settings
from app.dependencies import get_adapters
from app.models.schemas import (
    NMTHealthResponse,
    TranslateBatchRequest,
    TranslateBatchResponse,
    TranslateRequest,
    TranslateResponse,
)

router = APIRouter(prefix="/v1", tags=["translate"])
ml_router = APIRouter(prefix="/ml/nmt", tags=["nmt"])

AdaptersDep = Annotated[dict[str, BaseAdapter], Depends(get_adapters)]
SettingsDep = Annotated[Settings, Depends(get_settings)]


async def translate_with_production_fallback(
    request: TranslateRequest,
    adapters: dict[str, BaseAdapter],
    settings: Settings,
) -> TranslateResponse:
    if settings.bhashini_api_key:
        try:
            return await adapters["bhashini"].translate(request)
        except (AdapterError, AdapterUnavailable):
            pass

    try:
        # Iter-23: do NOT override fallback_used based on bhashini_api_key
        # configuration here. The adapter that ACTUALLY served the response
        # owns that flag — IndicTrans2Adapter sets it authoritatively based
        # on whether the output text is in the target script.
        return await adapters["indictrans2"].translate(request)
    except AdapterError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc


@router.post("/translate", response_model=TranslateResponse)
async def translate(
    request: TranslateRequest,
    adapters: AdaptersDep,
    settings: SettingsDep,
) -> TranslateResponse:
    return await translate_with_production_fallback(request, adapters, settings)


@ml_router.post("/translate", response_model=TranslateResponse)
async def nmt_translate(
    request: TranslateRequest,
    adapters: AdaptersDep,
    settings: SettingsDep,
) -> TranslateResponse:
    return await translate_with_production_fallback(request, adapters, settings)


@ml_router.post("/translate/batch", response_model=TranslateBatchResponse)
async def nmt_translate_batch(
    request: TranslateBatchRequest,
    adapters: AdaptersDep,
    settings: SettingsDep,
) -> TranslateBatchResponse:
    translations: list[TranslateResponse] = []
    for item in request.items:
        translations.append(
            await translate_with_production_fallback(
                TranslateRequest(
                    text=item.text,
                    source_lang=item.source_lang,
                    target_lang=item.target_lang,
                ),
                adapters,
                settings,
            )
        )
    return TranslateBatchResponse(translations=translations)


@ml_router.get("/health", response_model=NMTHealthResponse)
async def nmt_health(adapters: AdaptersDep, settings: SettingsDep) -> NMTHealthResponse:
    local_ready = await adapters["indictrans2"].check()
    provider = "bhashini" if settings.bhashini_api_key else "indictrans2-local-fallback"
    return NMTHealthResponse(
        ready=True,
        provider=provider,
        model_name=settings.indictrans2_model_name,
        version=adapters["indictrans2"].model_version,
        local_indictrans2_ready=local_ready,
        bhashini_configured=bool(settings.bhashini_api_key),
    )

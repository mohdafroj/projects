from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException

from app.adapters.base import AdapterError
from app.config import Settings, get_settings
from app.dependencies import get_fallback_chain, get_provider_selector, get_recent_call_log
from app.models.schemas import ASRRequest, ASRResponse, ASRStatusResponse
from app.services.callbacks import post_asr_ingest
from app.services.fallback_chain import FallbackChain
from app.services.provider_selector import ProviderSelector
from app.services.recent_calls import RecentASRCallLog

router = APIRouter(prefix="/v1", tags=["asr"])

ProviderSelectorDep = Annotated[ProviderSelector, Depends(get_provider_selector)]
FallbackChainDep = Annotated[FallbackChain, Depends(get_fallback_chain)]
SettingsDep = Annotated[Settings, Depends(get_settings)]
RecentCallLogDep = Annotated[RecentASRCallLog, Depends(get_recent_call_log)]


@router.post("/asr", response_model=ASRResponse)
async def asr(
    request: ASRRequest,
    selector: ProviderSelectorDep,
    chain: FallbackChainDep,
    settings: SettingsDep,
    recent_calls: RecentCallLogDep,
) -> ASRResponse:
    try:
        response = await chain.transcribe(request, selector.asr_chain(request))
    except AdapterError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc
    recent_calls.record(request, response)
    await post_asr_ingest(settings, response)
    return response


@router.get("/asr/recent", response_model=ASRStatusResponse)
async def recent_asr_calls(recent_calls: RecentCallLogDep) -> ASRStatusResponse:
    return ASRStatusResponse(calls=recent_calls.list())

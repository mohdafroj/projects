from typing import Annotated

from fastapi import APIRouter, Depends

from app.adapters.base import BaseAdapter
from app.dependencies import get_adapters
from app.models.schemas import ProviderState, ReadyResponse
from app.services import provider_health

router = APIRouter(tags=["health"])

AdaptersDep = Annotated[dict[str, BaseAdapter], Depends(get_adapters)]


@router.get("/healthz")
async def healthz() -> dict[str, str]:
    return {"status": "ok"}


@router.get("/v1/providers/health")
async def providers_health() -> dict[str, object]:
    """Snapshot of upstream LLM/API health derived from real traffic.

    Updated by speech_to_speech.py + IndicTrans2Adapter on every call —
    no extra probes, so checking this endpoint is free for our Sarvam budget.
    """
    return {"providers": provider_health.snapshot()}


@router.get("/readyz", response_model=ReadyResponse)
async def readyz(adapters: AdaptersDep) -> ReadyResponse:
    providers: list[ProviderState] = []
    for name, adapter in adapters.items():
        available = await adapter.check()
        providers.append(
            ProviderState(
                name=name,
                available=available,
                circuit_open=adapter.circuit.is_open,
                last_error=adapter.circuit.last_error,
            )
        )
    asr_ready = any(
        provider.available and provider.name in {"sarvam", "bhashini", "whisper"}
        for provider in providers
    )
    return ReadyResponse(ready=asr_ready, providers=providers)

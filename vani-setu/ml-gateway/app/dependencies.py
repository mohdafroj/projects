from functools import lru_cache
import hmac
from typing import Annotated

from fastapi import Depends, Header, HTTPException, status
from app.adapters.base import BaseAdapter
from app.adapters.bhashini import BhashiniAdapter
from app.adapters.indictrans2 import IndicTrans2Adapter
from app.adapters.sarvam import SarvamAdapter
from app.adapters.whisper import WhisperAdapter
from app.config import Settings, get_settings
from app.services.fallback_chain import FallbackChain
from app.services.provider_selector import ProviderSelector
from app.services.recent_calls import RecentASRCallLog
from app.services.artifact_store import ArtifactStore, build_artifact_store


@lru_cache
def get_adapters() -> dict[str, BaseAdapter]:
    settings = get_settings()
    return {
        "sarvam": SarvamAdapter(settings),
        "bhashini": BhashiniAdapter(settings),
        "whisper": WhisperAdapter(settings),
        "indictrans2": IndicTrans2Adapter(settings),
    }


def get_provider_selector() -> ProviderSelector:
    return ProviderSelector(get_settings())


def get_fallback_chain() -> FallbackChain:
    return FallbackChain(get_adapters())


@lru_cache
def get_recent_call_log() -> RecentASRCallLog:
    return RecentASRCallLog()


@lru_cache
def get_artifact_store() -> ArtifactStore:
    return build_artifact_store(get_settings())


def require_service_auth(
    authorization: Annotated[str | None, Header()] = None,
    x_ml_gateway_token: Annotated[str | None, Header(alias="X-ML-Gateway-Token")] = None,
    settings: Settings = Depends(get_settings),
) -> None:
    expected_token = settings.ml_gateway_service_token

    if not settings.ml_gateway_auth_required and not expected_token:
        return

    if not expected_token:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="ML gateway service auth is required but no token is configured",
        )

    supplied_token = x_ml_gateway_token
    if authorization and authorization.lower().startswith("bearer "):
        supplied_token = authorization[7:].strip()

    if not supplied_token or not hmac.compare_digest(supplied_token, expected_token):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid service credentials",
        )

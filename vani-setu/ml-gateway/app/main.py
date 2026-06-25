import asyncio
from contextlib import asynccontextmanager

import httpx
from fastapi import Depends, FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.config import get_settings
from app.dependencies import require_service_auth
from app.routers import asr, health, speech, speech_to_speech, translate

settings = get_settings()


@asynccontextmanager
async def lifespan(app: FastAPI):
    # Long-lived HTTP/2 client against api.sarvam.ai so TLS + DNS are not
    # paid per request. Cold call was ~5s; reusing the pool collapses warm
    # requests to ~300-1500ms (translate/TTS server time only).
    # HTTP/1.1 keepalive is enough — Sarvam endpoints are independent so we
    # mostly benefit from skipping TLS handshakes, not from multiplexing.
    # (Upgrading to HTTP/2 would need the `h2` package which is not in the
    # ml-gateway image yet.)
    limits = httpx.Limits(
        max_keepalive_connections=20,
        max_connections=40,
        keepalive_expiry=settings.sarvam_http_keepalive_expiry,
    )
    app.state.sarvam_client = httpx.AsyncClient(
        base_url=settings.sarvam_base_url,
        timeout=settings.sarvam_timeout_seconds,
        limits=limits,
    )
    # Process-wide gate on concurrent Sarvam HTTP calls — see config.py
    # comment on ``sarvam_concurrency_limit``. Created here (inside the
    # running event loop) so the semaphore binds to the right loop.
    app.state.sarvam_semaphore = asyncio.Semaphore(settings.sarvam_concurrency_limit)
    try:
        yield
    finally:
        await app.state.sarvam_client.aclose()


app = FastAPI(title="ml-gateway", version="0.1.0", lifespan=lifespan)
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.cors_origins,
    allow_credentials=False,
    allow_methods=["GET", "POST"],
    allow_headers=["*"],
)
app.include_router(health.router)
service_auth = [Depends(require_service_auth)]
app.include_router(asr.router, dependencies=service_auth)
app.include_router(speech.router, dependencies=service_auth)
app.include_router(speech_to_speech.router, dependencies=service_auth)
app.include_router(translate.router, dependencies=service_auth)
app.include_router(translate.ml_router, dependencies=service_auth)

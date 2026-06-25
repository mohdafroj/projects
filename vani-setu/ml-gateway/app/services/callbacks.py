import hashlib
import hmac
import json
import logging

import httpx

from app.config import Settings
from app.models.schemas import ASRResponse

logger = logging.getLogger(__name__)


def audit_payload(response: ASRResponse) -> dict[str, object]:
    confidences = [
        segment.confidence for segment in response.segments if segment.confidence is not None
    ]
    confidence_summary = {
        "min": min(confidences) if confidences else None,
        "mean": sum(confidences) / len(confidences) if confidences else None,
        "max": max(confidences) if confidences else None,
    }
    return {
        "provider": response.provider_used,
        "model_version": response.model_version,
        "duration_ms": response.duration_ms,
        "confidence_summary": confidence_summary,
        "token_count": len(response.transcript.split()),
        "fallback_used": response.fallback_used,
    }


async def post_asr_ingest(settings: Settings, response: ASRResponse) -> None:
    if not settings.vani_api_url or not settings.vani_asr_ingest_secret:
        return
    payload = response.model_dump(mode="json")
    payload["audit"] = audit_payload(response)
    body = json.dumps(payload, separators=(",", ":")).encode("utf-8")
    signature = hmac.new(
        settings.vani_asr_ingest_secret.encode("utf-8"),
        body,
        hashlib.sha256,
    ).hexdigest()
    url = f"{settings.vani_api_url.rstrip('/')}/api/asr/ingest"
    try:
        async with httpx.AsyncClient(timeout=settings.callback_timeout_seconds) as client:
            result = await client.post(
                url,
                content=body,
                headers={
                    "content-type": "application/json",
                    "x-ml-gateway-signature": f"sha256={signature}",
                },
            )
        logger.info("asr ingest callback status=%s", result.status_code)
    except httpx.HTTPError as exc:
        logger.warning("asr ingest callback failed: %s", exc.__class__.__name__)

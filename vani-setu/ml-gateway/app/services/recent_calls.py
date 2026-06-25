from collections import deque
from datetime import UTC, datetime

from app.models.schemas import ASRRequest, ASRResponse, RecentASRCall


class RecentASRCallLog:
    def __init__(self, maxlen: int = 25) -> None:
        self._calls: deque[RecentASRCall] = deque(maxlen=maxlen)

    def record(self, request: ASRRequest, response: ASRResponse) -> None:
        self._calls.appendleft(
            RecentASRCall(
                provider_used=response.provider_used,
                language=request.language,
                duration_ms=response.duration_ms,
                model_version=response.model_version,
                fallback_used=response.fallback_used,
                token_count=len(response.transcript.split()),
                created_at=datetime.now(UTC).isoformat(),
            )
        )

    def list(self) -> list[RecentASRCall]:
        return list(self._calls)

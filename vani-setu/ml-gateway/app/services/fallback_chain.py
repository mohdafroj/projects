from collections.abc import Mapping

from app.adapters.base import AdapterError, BaseAdapter
from app.models.schemas import ASRRequest, ASRResponse


class FallbackChain:
    def __init__(self, adapters: Mapping[str, BaseAdapter]) -> None:
        self.adapters = adapters

    async def transcribe(self, request: ASRRequest, providers: list[str]) -> ASRResponse:
        errors: list[str] = []
        for index, provider_name in enumerate(providers):
            adapter = self.adapters.get(provider_name)
            if adapter is None:
                errors.append(f"{provider_name}: not registered")
                continue
            try:
                response = await adapter.transcribe(request)
                response.fallback_used = index > 0
                response.provider_used = provider_name
                return response
            except AdapterError as exc:
                errors.append(f"{provider_name}: {exc.__class__.__name__}")
                continue
        raise AdapterError("all ASR providers failed: " + "; ".join(errors))

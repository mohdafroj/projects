import pytest

from app.adapters.base import AdapterError, BaseAdapter
from app.config import Settings
from app.models.schemas import ASRRequest, ASRResponse, ASRSegment
from app.services.fallback_chain import FallbackChain


class FailingAdapter(BaseAdapter):
    name = "failing"
    model_version = "test"

    async def transcribe(self, request: ASRRequest) -> ASRResponse:
        raise AdapterError("boom")


class PassingAdapter(BaseAdapter):
    name = "passing"
    model_version = "test"

    async def transcribe(self, request: ASRRequest) -> ASRResponse:
        return ASRResponse(
            provider_used=self.name,
            transcript="hello",
            segments=[ASRSegment(start_ms=0, end_ms=100, text="hello", confidence=0.9)],
            duration_ms=100,
            model_version=self.model_version,
            fallback_used=False,
        )


@pytest.mark.asyncio
async def test_fallback_chain_marks_fallback_used() -> None:
    settings = Settings()
    chain = FallbackChain(
        {
            "failing": FailingAdapter(settings),
            "passing": PassingAdapter(settings),
        }
    )

    response = await chain.transcribe(
        ASRRequest(audio_url="https://example.test/a.wav", language="hi"),
        ["failing", "passing"],
    )

    assert response.provider_used == "passing"
    assert response.fallback_used is True


@pytest.mark.asyncio
async def test_fallback_chain_raises_when_all_fail() -> None:
    settings = Settings()
    chain = FallbackChain({"failing": FailingAdapter(settings)})

    with pytest.raises(AdapterError, match="all ASR providers failed"):
        await chain.transcribe(
            ASRRequest(audio_url="https://example.test/a.wav", language="hi"),
            ["failing"],
        )

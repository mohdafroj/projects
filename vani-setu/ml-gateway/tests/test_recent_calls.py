from app.models.schemas import ASRRequest, ASRResponse, ASRSegment
from app.services.recent_calls import RecentASRCallLog


def test_recent_call_log_records_newest_first() -> None:
    log = RecentASRCallLog(maxlen=1)
    request = ASRRequest(audio_url="https://example.test/a.wav", language="hi")

    log.record(
        request,
        ASRResponse(
            provider_used="sarvam",
            transcript="hello world",
            segments=[ASRSegment(start_ms=0, end_ms=100, text="hello world")],
            duration_ms=100,
            model_version="test",
            fallback_used=False,
        ),
    )

    calls = log.list()
    assert len(calls) == 1
    assert calls[0].provider_used == "sarvam"
    assert calls[0].language == "hi"
    assert calls[0].token_count == 2

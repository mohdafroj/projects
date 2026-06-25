from app.models.schemas import ASRResponse, ASRSegment
from app.services.callbacks import audit_payload


def test_audit_payload_summarizes_confidence_and_tokens() -> None:
    payload = audit_payload(
        ASRResponse(
            provider_used="sarvam",
            transcript="one two",
            segments=[
                ASRSegment(start_ms=0, end_ms=1, text="one", confidence=0.5),
                ASRSegment(start_ms=1, end_ms=2, text="two", confidence=0.9),
            ],
            duration_ms=2,
            model_version="m",
            fallback_used=True,
        )
    )

    assert payload["confidence_summary"] == {"min": 0.5, "mean": 0.7, "max": 0.9}
    assert payload["token_count"] == 2
    assert payload["fallback_used"] is True

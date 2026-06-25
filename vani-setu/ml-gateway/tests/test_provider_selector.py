from app.config import Settings
from app.models.schemas import ASRRequest
from app.services.provider_selector import ProviderSelector


def request(language: str, provider: str | None = None, context: str | None = None) -> ASRRequest:
    return ASRRequest(
        audio_url="https://example.test/audio.wav",
        language=language,
        provider=provider,
        context=context,
    )


def test_proceedings_prefers_sarvam() -> None:
    # Pin provider_precedence explicitly so the container env
    # PROVIDER_PRECEDENCE='["sarvam","whisper"]' (no "bhashini") can't bleed
    # into Settings() and shrink the expected chain.
    selector = ProviderSelector(
        Settings(_env_file=None, provider_precedence=["sarvam", "bhashini", "whisper"])
    )

    assert selector.asr_chain(request("hi", context="proceedings")) == [
        "sarvam",
        "bhashini",
        "whisper",
    ]


def test_provider_override_keeps_fallbacks() -> None:
    selector = ProviderSelector(Settings())

    assert selector.asr_chain(request("hi", provider="bhashini")) == [
        "bhashini",
        "sarvam",
        "whisper",
    ]


def test_regional_language_routes_to_bhashini_when_sarvam_missing() -> None:
    selector = ProviderSelector(Settings())

    assert selector.asr_chain(request("as")) == ["bhashini", "sarvam", "whisper"]

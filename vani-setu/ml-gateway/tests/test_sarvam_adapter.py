import respx
from httpx import Response

from app.adapters.sarvam import SarvamAdapter, _language_code_for_sarvam
from app.config import Settings
from app.models.schemas import ASRRequest


@respx.mock
async def test_sarvam_adapter_transcribes() -> None:
    settings = Settings(sarvam_api_key="test-key", sarvam_base_url="https://api.sarvam.test")
    respx.get("https://example.test/audio.wav").mock(
        return_value=Response(200, content=b"RIFF....WAVEfake", headers={"content-type": "audio/wav"})
    )
    stt_route = respx.post("https://api.sarvam.test/speech-to-text").mock(
        return_value=Response(
            200,
            json={
                "transcript": "namaste",
                "duration_ms": 400,
                "model_version": "sarvam-test",
                "segments": [{"start_ms": 0, "end_ms": 400, "text": "namaste", "confidence": 0.8}],
            },
        )
    )

    response = await SarvamAdapter(settings).transcribe(
        ASRRequest(audio_url="https://example.test/audio.wav", language="hi")
    )

    assert response.provider_used == "sarvam"
    assert response.transcript == "namaste"
    assert response.segments[0].confidence == 0.8

    sent = stt_route.calls.last.request
    body = sent.content.decode("latin-1")
    assert "name=\"file\"" in body
    assert "RIFF....WAVEfake" in body
    assert "name=\"model\"" in body and "saarika:v2.5" in body
    assert "name=\"language_code\"" in body and "hi-IN" in body


def test_language_code_normalisation() -> None:
    assert _language_code_for_sarvam("hi") == "hi-IN"
    assert _language_code_for_sarvam("hi-IN") == "hi-IN"
    assert _language_code_for_sarvam("auto") == "unknown"
    assert _language_code_for_sarvam(None) == "unknown"
    assert _language_code_for_sarvam("or") == "od-IN"

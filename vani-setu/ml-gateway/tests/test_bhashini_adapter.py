import respx
from httpx import Response

from app.adapters.bhashini import BhashiniAdapter
from app.config import Settings
from app.models.schemas import ASRRequest, TranslateRequest


@respx.mock
async def test_bhashini_adapter_transcribes() -> None:
    settings = Settings(
        bhashini_api_key="test-key",
        bhashini_base_url="https://bhashini.test",
    )
    respx.post("https://bhashini.test/services/inference/pipeline").mock(
        return_value=Response(
            200,
            json={"pipelineResponse": [{"output": [{"source": "vanakkam", "duration_ms": 250}]}]},
        )
    )

    response = await BhashiniAdapter(settings).transcribe(
        ASRRequest(audio_url="https://example.test/audio.wav", language="ta")
    )

    assert response.provider_used == "bhashini"
    assert response.transcript == "vanakkam"
    assert response.duration_ms == 250


@respx.mock
async def test_bhashini_adapter_translates() -> None:
    settings = Settings(
        bhashini_api_key="test-key",
        bhashini_base_url="https://bhashini.test",
    )
    respx.post("https://bhashini.test/services/inference/pipeline").mock(
        return_value=Response(
            200,
            json={"pipelineResponse": [{"output": [{"target": "नमस्कार"}]}]},
        )
    )

    response = await BhashiniAdapter(settings).translate(
        TranslateRequest(text="hello", source_lang="en", target_lang="hi")
    )

    assert response.provider_used == "bhashini"
    assert response.translation == "नमस्कार"

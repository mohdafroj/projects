import respx
from fastapi.testclient import TestClient
from httpx import Response

from app.adapters.bhashini import BhashiniAdapter
from app.adapters.indictrans2 import IndicTrans2Adapter
from app.config import Settings, get_settings
from app.dependencies import get_adapters
from app.main import app


def test_diarisation_uses_pyannote_style_fallback_without_credentials() -> None:
    app.dependency_overrides[get_settings] = lambda: Settings()
    client = TestClient(app)

    response = client.post(
        "/v1/diarisation",
        json={"audio_url": "https://example.test/audio.wav", "duration_ms": 12_000},
    )

    app.dependency_overrides.clear()
    assert response.status_code == 200
    body = response.json()
    assert body["provider_used"] == "pyannote-style-fallback"
    assert body["fallback_used"] is True
    assert [segment["speaker"] for segment in body["segments"]] == [
        "SPEAKER_00",
        "SPEAKER_01",
        "SPEAKER_00",
    ]


def test_forced_alignment_spreads_words_across_duration() -> None:
    client = TestClient(app)

    response = client.post(
        "/v1/alignment",
        json={
            "audio_url": "https://example.test/audio.wav",
            "transcript": "Lok Sabha adjourned",
            "language": "en",
            "duration_ms": 900,
        },
    )

    assert response.status_code == 200
    words = response.json()["words"]
    assert [word["word"] for word in words] == ["Lok", "Sabha", "adjourned"]
    assert words[0]["start_ms"] == 0
    assert words[-1]["end_ms"] == 900


def test_regional_language_detection_fallback_detects_tamil_script() -> None:
    app.dependency_overrides[get_settings] = lambda: Settings(bhashini_api_key=None)
    client = TestClient(app)

    response = client.post("/v1/language/regional", json={"text": "வணக்கம் தமிழ்நாடு"})

    app.dependency_overrides.clear()
    assert response.status_code == 200
    body = response.json()
    assert body["language"] == "ta"
    assert body["provider_used"] == "regional-script-fallback"
    assert body["fallback_used"] is True


@respx.mock
def test_regional_language_detection_prefers_bhashini_when_configured() -> None:
    settings = Settings(
        bhashini_api_key="test-key",
        bhashini_base_url="https://bhashini.test",
        retry_attempts=1,
    )
    app.dependency_overrides[get_settings] = lambda: settings
    app.dependency_overrides[get_adapters] = lambda: {"bhashini": BhashiniAdapter(settings)}
    respx.post("https://bhashini.test/services/inference/pipeline").mock(
        return_value=Response(
            200,
            json={"pipelineResponse": [{"output": [{"langPrediction": "gu", "score": 0.91}]}]},
        )
    )
    client = TestClient(app)

    response = client.post("/v1/language/regional", json={"text": "નમસ્તે"})

    app.dependency_overrides.clear()
    assert response.status_code == 200
    body = response.json()
    assert body["language"] == "gu"
    assert body["provider_used"] == "bhashini"
    assert body["fallback_used"] is False


@respx.mock
def test_nmt_batch_preserves_bhashini_primary_indictrans2_fallback_chain() -> None:
    settings = Settings(
        bhashini_api_key="test-key",
        bhashini_base_url="https://bhashini.test",
        retry_attempts=1,
    )
    app.dependency_overrides[get_settings] = lambda: settings
    app.dependency_overrides[get_adapters] = lambda: {
        "bhashini": BhashiniAdapter(settings),
        "indictrans2": IndicTrans2Adapter(settings),
    }
    respx.post("https://bhashini.test/services/inference/pipeline").mock(
        return_value=Response(503, json={"error": "unavailable"})
    )
    client = TestClient(app)

    response = client.post(
        "/ml/nmt/translate/batch",
        json={
            "items": [
                {"text": "hello", "source_lang": "en", "target_lang": "hi"},
                {"text": "house", "source_lang": "en", "target_lang": "ta"},
            ]
        },
    )

    app.dependency_overrides.clear()
    assert response.status_code == 200
    translations = response.json()["translations"]
    assert len(translations) == 2
    # Iter-19c: IndicTrans2 deterministic-draft placeholder removed.
    # When Bhashini 503s and no real IndicTrans2 model is loaded, the chain
    # now surfaces the no_translation_model marker (still proves fallback fired).
    #
    # Iter-23: the proof that fallback fired is provider_used="no_translation_model"
    # — NOT fallback_used. The old `response.fallback_used = ... or bool(bhashini_api_key)`
    # guard in translate.py was a misplaced OR that flipped the flag whenever
    # the Bhashini key was configured, regardless of whether the no-model
    # branch had any "fallback" semantics to report. Deleted in iter-23.
    assert {item["provider_used"] for item in translations} == {"no_translation_model"}
    assert all(item["fallback_used"] is False for item in translations)
    assert all(item["translation"] == "" for item in translations)

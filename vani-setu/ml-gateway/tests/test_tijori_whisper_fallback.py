"""Slice L coverage: Tijori CPU-Whisper sidecar as STT fallback.

When the Sarvam STT call returns nothing (timeout / 4xx / 5xx /
hallucination filter), the live S2S endpoint should fall through to
the Tijori /v1/transcribe HTTP endpoint when whisper_enabled is true
and tijori_whisper_url is set. The provider_used tag flips to
``whisper_stt_fallback`` and ``stage_ms.whisper_stt`` shows up so
operators can see the fallback fired.
"""

from collections.abc import Mapping

import respx
from fastapi.testclient import TestClient
from httpx import Response

from app.adapters.indictrans2 import IndicTrans2Adapter
from app.adapters.sarvam import SarvamAdapter
from app.config import Settings, get_settings
from app.dependencies import get_adapters, get_artifact_store
from app.main import app
from app.services.artifact_store import ArtifactStore


class InMemoryArtifactStore(ArtifactStore):
    def __init__(self) -> None:
        self.binary_paths: list[str] = []

    async def persist(
        self,
        artifact_type: str,
        request_payload: Mapping[str, object],
        response_payload: Mapping[str, object],
        metadata: Mapping[str, object] | None = None,
    ) -> None:
        return None

    async def persist_binary(
        self,
        artifact_type: str,
        binary_payload: bytes,
        metadata: Mapping[str, object] | None = None,
        *,
        extension: str = "bin",
        content_type: str = "application/octet-stream",
    ) -> str | None:
        path = f"memory://{artifact_type}/{len(self.binary_paths) + 1}.{extension}"
        self.binary_paths.append(path)
        return path


@respx.mock
def test_tijori_whisper_fallback_fires_when_sarvam_stt_fails() -> None:
    """Sarvam STT 500 → Tijori /v1/transcribe is called → transcript
    rides through translate + TTS as if Sarvam had returned it."""
    store = InMemoryArtifactStore()
    app.dependency_overrides[get_settings] = lambda: Settings(
        bhashini_api_key=None,
        sarvam_api_key="test-key",
        sarvam_base_url="https://api.sarvam.test",
        ml_gateway_auth_required=False,
        ml_gateway_service_token=None,
        whisper_enabled=True,
        tijori_whisper_url="http://tijori-whisper.test:8000",
    )
    settings = app.dependency_overrides[get_settings]()
    app.dependency_overrides[get_adapters] = lambda: {
        "sarvam": SarvamAdapter(settings),
        "indictrans2": IndicTrans2Adapter(settings),
    }
    app.dependency_overrides[get_artifact_store] = lambda: store

    # Sarvam STT goes 500 → _try_stt returns None → fallback should run.
    respx.post(url__regex=r"https://api\.sarvam\.(test|ai)/speech-to-text").mock(
        return_value=Response(500, json={"detail": "upstream blip"})
    )
    # Tijori sidecar returns a clean transcript.
    tijori_call = respx.post("http://tijori-whisper.test:8000/v1/transcribe").mock(
        return_value=Response(
            200,
            json={
                "transcript_text": "सदन की कार्यवाही चल रही है",
                "detected_language": "hi",
                "confidence": 0.91,
                "model": "tiny",
                "served_via": "cpu_openai_whisper",
                "model_status": "loaded",
            },
        )
    )
    # Translate + TTS still need to land so the segment completes.
    respx.post(url__regex=r"https://api\.sarvam\.(test|ai)/translate").mock(
        return_value=Response(200, json={"translated_text": "The House is in session"})
    )
    respx.post(url__regex=r"https://api\.sarvam\.(test|ai)/text-to-speech").mock(
        return_value=Response(200, json={"audios": ["ZmFrZS13YXY="]})
    )

    with TestClient(app) as client:
        response = client.post(
            "/v1/speech-to-speech",
            json={
                "session_id": 555,
                "segment_id": 5,
                "source_language": "hi-IN",
                "audio_base64": "ZmFrZS1tNGE=",
                "audio_mime_type": "audio/wav",
                "audio_filename": "seg.wav",
                "target_languages": ["en-IN"],
            },
        )

    app.dependency_overrides.clear()
    assert response.status_code == 200
    body = response.json()
    assert body["provider_used"] == "whisper_stt_fallback"
    assert body["source_text"] == "सदन की कार्यवाही चल रही है"
    assert tijori_call.called
    # Confirm the adapter sent the audio_base64 + the right language hint
    # (stripped to ISO 639-1 "hi", not the IETF "hi-IN").
    sent = tijori_call.calls[0].request
    import json

    payload = json.loads(sent.content.decode())
    assert payload["audio_base64"] == "ZmFrZS1tNGE="
    assert payload["source_language"] == "hi"


@respx.mock
def test_tijori_whisper_falls_back_to_local_on_http_error() -> None:
    """If the Tijori sidecar 500s, the helper should return None and
    let _try_whisper_stt fall through to the in-process backend
    (which here is also missing → segment surfaces as
    sarvam_stt_unavailable, not whisper_stt_fallback)."""
    store = InMemoryArtifactStore()
    app.dependency_overrides[get_settings] = lambda: Settings(
        bhashini_api_key=None,
        sarvam_api_key="test-key",
        sarvam_base_url="https://api.sarvam.test",
        ml_gateway_auth_required=False,
        ml_gateway_service_token=None,
        whisper_enabled=True,
        tijori_whisper_url="http://tijori-whisper.test:8000",
    )
    settings = app.dependency_overrides[get_settings]()
    app.dependency_overrides[get_adapters] = lambda: {
        "sarvam": SarvamAdapter(settings),
        "indictrans2": IndicTrans2Adapter(settings),
    }
    app.dependency_overrides[get_artifact_store] = lambda: store

    respx.post(url__regex=r"https://api\.sarvam\.(test|ai)/speech-to-text").mock(
        return_value=Response(500, json={"detail": "upstream blip"})
    )
    respx.post("http://tijori-whisper.test:8000/v1/transcribe").mock(
        return_value=Response(503, json={"detail": "tijori down too"})
    )

    with TestClient(app) as client:
        response = client.post(
            "/v1/speech-to-speech",
            json={
                "session_id": 556,
                "segment_id": 6,
                "source_language": "hi-IN",
                "audio_base64": "ZmFrZS1tNGE=",
                "audio_mime_type": "audio/wav",
                "target_languages": ["en-IN"],
            },
        )

    app.dependency_overrides.clear()
    assert response.status_code == 200
    body = response.json()
    # Local faster-whisper isn't installed in the test env; the
    # _get_whisper_model() loader hits the negative cache and returns
    # None. So we surface as sarvam_stt_unavailable.
    assert body["provider_used"] == "sarvam_stt_unavailable"
    assert body["source_text"] == ""


@respx.mock
def test_tijori_whisper_skipped_when_url_unset() -> None:
    """No tijori_whisper_url → adapter is a no-op and we go straight
    to the in-process backend (also missing in tests) → segment goes
    through with sarvam_stt_unavailable."""
    store = InMemoryArtifactStore()
    app.dependency_overrides[get_settings] = lambda: Settings(
        bhashini_api_key=None,
        sarvam_api_key="test-key",
        sarvam_base_url="https://api.sarvam.test",
        ml_gateway_auth_required=False,
        ml_gateway_service_token=None,
        whisper_enabled=True,
        tijori_whisper_url=None,
    )
    settings = app.dependency_overrides[get_settings]()
    app.dependency_overrides[get_adapters] = lambda: {
        "sarvam": SarvamAdapter(settings),
        "indictrans2": IndicTrans2Adapter(settings),
    }
    app.dependency_overrides[get_artifact_store] = lambda: store

    respx.post(url__regex=r"https://api\.sarvam\.(test|ai)/speech-to-text").mock(
        return_value=Response(500, json={"detail": "blip"})
    )

    with TestClient(app) as client:
        response = client.post(
            "/v1/speech-to-speech",
            json={
                "session_id": 557,
                "segment_id": 7,
                "source_language": "en-IN",
                "audio_base64": "ZmFrZS1tNGE=",
                "audio_mime_type": "audio/wav",
                "target_languages": ["en-IN"],
            },
        )

    app.dependency_overrides.clear()
    assert response.status_code == 200
    body = response.json()
    assert body["provider_used"] == "sarvam_stt_unavailable"

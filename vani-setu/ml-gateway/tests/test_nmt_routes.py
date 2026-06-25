from fastapi.testclient import TestClient

from app.main import app


def test_nmt_health_and_translate_do_not_502() -> None:
    client = TestClient(app)

    health = client.get("/ml/nmt/health")
    assert health.status_code == 200
    assert health.json()["ready"] is True

    response = client.post(
        "/ml/nmt/translate",
        json={"text": "Treasury Benches", "source_lang": "en", "target_lang": "hi"},
    )

    assert response.status_code == 200
    body = response.json()
    # Iter-19c: IndicTrans2 deterministic-draft placeholder removed.
    # Direct /v1/translate callers now get an empty string + no_translation_model marker.
    assert body["translation"] == ""
    assert body["provider_used"] == "no_translation_model"
    assert "[IndicTrans2 deterministic draft" not in body["translation"]

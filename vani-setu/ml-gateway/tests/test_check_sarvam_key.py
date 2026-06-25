from pathlib import Path

from app.config import Settings


def test_sarvam_key_prefers_secret_file(tmp_path: Path) -> None:
    secret = tmp_path / "sarvam_key"
    secret.write_text("from-file\n", encoding="utf-8")

    settings = Settings(sarvam_key_file=secret, sarvam_api_key="from-env")

    assert settings.sarvam_key() == "from-file"

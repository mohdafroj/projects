# ml-gateway

FastAPI service that fronts Sarvam, Bhashini, local Whisper, and IndicTrans2 translation.

## Local commands

```bash
poetry install
poetry run pytest
poetry run ruff check .
poetry run python scripts/check-sarvam-key.py
poetry run uvicorn app.main:app --host 0.0.0.0 --port 8000
```

Secrets are read from `/run/secrets/sarvam_key` first and `SARVAM_API_KEY` second.

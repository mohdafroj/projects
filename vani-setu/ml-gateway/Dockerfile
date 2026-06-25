FROM python:3.12-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1

WORKDIR /app

RUN useradd --create-home --shell /usr/sbin/nologin appuser

COPY --chown=appuser:appuser pyproject.toml README.md requirements.runtime.txt ./
COPY --chown=appuser:appuser app ./app
COPY --chown=appuser:appuser scripts ./scripts
RUN pip install --no-cache-dir -r requirements.runtime.txt \
    && rm -rf /root/.cache/pip

# Whisper model cache. faster-whisper looks up models via HF Hub — this
# host's NIC Cloud egress is locked down (sovereignty + DPDP) and can't
# reach huggingface.co, so the model weights need to be pre-staged
# here by ops (download elsewhere → push to MinIO → mount at build /
# runtime). Until that's done, set ML_GATEWAY_WHISPER_ENABLED=false
# (the default) and the loader stays in negative-cache mode.
ENV HF_HUB_CACHE=/opt/whisper-cache \
    XDG_CACHE_HOME=/opt/whisper-cache
RUN mkdir -p /opt/whisper-cache && chown -R appuser:appuser /opt/whisper-cache

RUN chown -R appuser:appuser /app
USER appuser

EXPOSE 8000
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]

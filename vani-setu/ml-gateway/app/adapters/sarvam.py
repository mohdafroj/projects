import httpx

from app.adapters.base import AdapterUnavailable, BaseAdapter
from app.models.schemas import ASRRequest, ASRResponse, ASRSegment


_REGION_BY_LANG = {
    "hi": "hi-IN", "en": "en-IN", "bn": "bn-IN", "ta": "ta-IN", "te": "te-IN",
    "kn": "kn-IN", "ml": "ml-IN", "mr": "mr-IN", "gu": "gu-IN", "pa": "pa-IN",
    "od": "od-IN", "or": "od-IN",
}


def _language_code_for_sarvam(language: str | None) -> str:
    # Sarvam's STT expects an IETF-style tag (e.g. "hi-IN"); "auto" / "unknown"
    # tell it to detect. Bare two-letter codes (e.g. "hi") are mapped to their
    # India regional form so the API doesn't 400 on legacy callers.
    if not language or language.lower() in {"auto", "unknown"}:
        return "unknown"
    value = language.strip()
    if "-" in value:
        return value
    return _REGION_BY_LANG.get(value.lower(), value)


class SarvamAdapter(BaseAdapter):
    name = "sarvam"
    model_version = "sarvam-api"
    default_model = "saarika:v2.5"

    async def check(self) -> bool:
        if self.circuit.is_open:
            return False
        if not self.settings.sarvam_key():
            self.circuit.last_error = "missing_config"
            return False
        self.circuit.last_error = None
        return True

    async def transcribe(self, request: ASRRequest) -> ASRResponse:
        key = self.settings.sarvam_key()
        if not key:
            raise AdapterUnavailable("Sarvam API key is not configured")

        async def call() -> ASRResponse:
            headers = {"api-subscription-key": key}
            async with httpx.AsyncClient(
                base_url=self.settings.sarvam_base_url,
                timeout=self.settings.sarvam_timeout_seconds,
            ) as client:
                # Sarvam retired the JSON `audio_url` form of /speech-to-text;
                # the live API only accepts multipart with a `file` field. Pull
                # the audio bytes from the caller-provided URL and forward them.
                audio_response = await client.get(str(request.audio_url))
                audio_response.raise_for_status()
                audio_bytes = audio_response.content
                content_type = audio_response.headers.get("content-type", "audio/wav")
                filename = str(request.audio_url).rsplit("/", 1)[-1].split("?", 1)[0] or "audio.wav"

                form = {
                    "model": self.default_model,
                    "language_code": _language_code_for_sarvam(request.language),
                }
                response = await client.post(
                    self.settings.sarvam_asr_path,
                    headers=headers,
                    data=form,
                    files={"file": (filename, audio_bytes, content_type)},
                )
                response.raise_for_status()
                data = response.json()
            transcript = data.get("transcript") or data.get("text") or ""
            segments = [
                ASRSegment(
                    start_ms=int(item.get("start_ms", item.get("start", 0))),
                    end_ms=int(item.get("end_ms", item.get("end", 0))),
                    text=str(item.get("text", "")),
                    confidence=item.get("confidence"),
                )
                for item in data.get("segments", [])
            ]
            if not segments and transcript:
                segments = [
                    ASRSegment(
                        start_ms=0,
                        end_ms=int(data.get("duration_ms", 0)),
                        text=transcript,
                    )
                ]
            return ASRResponse(
                provider_used=self.name,
                transcript=transcript,
                segments=segments,
                duration_ms=int(data.get("duration_ms", 0)),
                model_version=str(data.get("model_version", self.model_version)),
                fallback_used=False,
            )

        return await self._with_resilience(call)

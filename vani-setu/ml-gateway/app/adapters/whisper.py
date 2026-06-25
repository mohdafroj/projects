import importlib.util
import tempfile
from pathlib import Path

import httpx

from app.adapters.base import AdapterUnavailable, BaseAdapter
from app.models.schemas import ASRRequest, ASRResponse, ASRSegment


class WhisperAdapter(BaseAdapter):
    name = "whisper"
    model_version = "faster-whisper-local"

    async def check(self) -> bool:
        if self.circuit.is_open:
            return False
        if importlib.util.find_spec("faster_whisper") is None:
            self.circuit.last_error = "missing_dependency"
            return False
        self.circuit.last_error = None
        return True

    async def transcribe(self, request: ASRRequest) -> ASRResponse:
        async def call() -> ASRResponse:
            try:
                from faster_whisper import WhisperModel
            except ImportError as exc:
                raise AdapterUnavailable("faster-whisper is not installed") from exc

            async with httpx.AsyncClient(timeout=30.0) as client:
                response = await client.get(str(request.audio_url))
                response.raise_for_status()

            suffix = Path(str(request.audio_url)).suffix or ".audio"
            with tempfile.NamedTemporaryFile(suffix=suffix) as handle:
                handle.write(response.content)
                handle.flush()
                model = WhisperModel("base", device="cpu", compute_type="int8")
                segments_iter, info = model.transcribe(handle.name, language=request.language)
                segments = [
                    ASRSegment(
                        start_ms=int(segment.start * 1000),
                        end_ms=int(segment.end * 1000),
                        text=segment.text.strip(),
                        confidence=None,
                    )
                    for segment in segments_iter
                ]
            transcript = " ".join(segment.text for segment in segments).strip()
            duration_ms = int(getattr(info, "duration", 0) * 1000)
            return ASRResponse(
                provider_used=self.name,
                transcript=transcript,
                segments=segments,
                duration_ms=duration_ms,
                model_version=self.model_version,
                fallback_used=False,
            )

        return await self._with_resilience(call)

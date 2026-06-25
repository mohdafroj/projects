import httpx

from app.adapters.base import AdapterUnavailable, BaseAdapter
from app.models.schemas import (
    ASRRequest,
    ASRResponse,
    ASRSegment,
    LanguageCandidate,
    LanguageDetectionRequest,
    LanguageDetectionResponse,
    TranslateRequest,
    TranslateResponse,
)


class BhashiniAdapter(BaseAdapter):
    name = "bhashini"
    model_version = "bhashini-ulca"

    async def check(self) -> bool:
        if self.circuit.is_open:
            return False
        if not self.settings.bhashini_api_key:
            self.circuit.last_error = "missing_config"
            return False
        self.circuit.last_error = None
        return True

    async def transcribe(self, request: ASRRequest) -> ASRResponse:
        if not self.settings.bhashini_api_key:
            raise AdapterUnavailable("Bhashini API key is not configured")

        async def call() -> ASRResponse:
            payload = {
                "pipelineTasks": [
                    {
                        "taskType": "asr",
                        "config": {"language": {"sourceLanguage": request.language}},
                    }
                ],
                "inputData": {"audio": [{"audioUri": str(request.audio_url)}]},
            }
            headers = {"Authorization": self.settings.bhashini_api_key}
            async with httpx.AsyncClient(
                base_url=self.settings.bhashini_base_url,
                timeout=self.settings.bhashini_timeout_seconds,
            ) as client:
                response = await client.post(
                    self.settings.bhashini_inference_path,
                    json=payload,
                    headers=headers,
                )
                response.raise_for_status()
                data = response.json()
            output = (data.get("pipelineResponse") or [{}])[0].get("output") or [{}]
            transcript = output[0].get("source") or output[0].get("text") or ""
            duration_ms = int(output[0].get("duration_ms", 0))
            return ASRResponse(
                provider_used=self.name,
                transcript=transcript,
                segments=[
                    ASRSegment(start_ms=0, end_ms=duration_ms, text=transcript)
                ]
                if transcript
                else [],
                duration_ms=duration_ms,
                model_version=self.model_version,
                fallback_used=False,
            )

        return await self._with_resilience(call)

    async def translate(self, request: TranslateRequest) -> TranslateResponse:
        if not self.settings.bhashini_api_key:
            raise AdapterUnavailable("Bhashini API key is not configured")

        async def call() -> TranslateResponse:
            payload = {
                "pipelineTasks": [
                    {
                        "taskType": "translation",
                        "config": {
                            "language": {
                                "sourceLanguage": request.source_lang,
                                "targetLanguage": request.target_lang,
                            }
                        },
                    }
                ],
                "inputData": {"input": [{"source": request.text}]},
            }
            headers = {"Authorization": self.settings.bhashini_api_key}
            async with httpx.AsyncClient(
                base_url=self.settings.bhashini_base_url,
                timeout=self.settings.bhashini_timeout_seconds,
            ) as client:
                response = await client.post(
                    self.settings.bhashini_inference_path,
                    json=payload,
                    headers=headers,
                )
                response.raise_for_status()
                data = response.json()
            output = (data.get("pipelineResponse") or [{}])[0].get("output") or [{}]
            translation = output[0].get("target") or output[0].get("text") or ""
            return TranslateResponse(
                translation=translation,
                confidence=None,
                model_version=self.model_version,
                provider_used=self.name,
                fallback_used=False,
            )

        return await self._with_resilience(call)

    async def detect_language(
        self, request: LanguageDetectionRequest
    ) -> LanguageDetectionResponse:
        if not self.settings.bhashini_api_key:
            raise AdapterUnavailable("Bhashini API key is not configured")

        async def call() -> LanguageDetectionResponse:
            payload = {
                "pipelineTasks": [
                    {
                        "taskType": "language-detection",
                        "config": {"serviceId": "bhashini-language-detection"},
                    }
                ],
                "inputData": {"input": [{"source": request.text}]},
            }
            headers = {"Authorization": self.settings.bhashini_api_key}
            async with httpx.AsyncClient(
                base_url=self.settings.bhashini_base_url,
                timeout=self.settings.bhashini_timeout_seconds,
            ) as client:
                response = await client.post(
                    self.settings.bhashini_inference_path,
                    json=payload,
                    headers=headers,
                )
                response.raise_for_status()
                data = response.json()

            output = (data.get("pipelineResponse") or [{}])[0].get("output") or [{}]
            result = output[0] if output else {}
            language = (
                result.get("langPrediction")
                or result.get("language")
                or result.get("lang")
                or request.hint
                or "hi"
            )
            confidence = float(result.get("confidence") or result.get("score") or 0.0)
            return LanguageDetectionResponse(
                language=language,
                confidence=confidence,
                candidates=[LanguageCandidate(language=language, confidence=confidence)],
                provider_used=self.name,
                model_version=self.model_version,
                fallback_used=False,
            )

        return await self._with_resilience(call)

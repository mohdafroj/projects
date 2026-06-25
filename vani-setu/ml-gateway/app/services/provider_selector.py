from app.config import Settings
from app.models.schemas import ASRRequest


class ProviderSelector:
    def __init__(self, settings: Settings) -> None:
        self.settings = settings

    def asr_chain(self, request: ASRRequest) -> list[str]:
        if request.provider:
            return self._with_fallbacks(request.provider)
        if request.context == "proceedings":
            return self._with_fallbacks(self.settings.proceedings_provider)
        language = request.language.lower()
        if language in self.settings.sarvam_languages:
            return self._with_fallbacks("sarvam")
        if language in self.settings.bhashini_languages:
            return self._with_fallbacks("bhashini")
        return self._with_fallbacks("whisper")

    def _with_fallbacks(self, first: str) -> list[str]:
        ordered = [first]
        ordered.extend(
            provider for provider in self.settings.provider_precedence if provider not in ordered
        )
        return ordered

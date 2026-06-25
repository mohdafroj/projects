import asyncio
import random
import time
from abc import ABC, abstractmethod
from collections.abc import Awaitable, Callable
from dataclasses import dataclass
from typing import TypeVar

from app.config import Settings
from app.models.schemas import (
    ASRRequest,
    ASRResponse,
    LanguageDetectionRequest,
    LanguageDetectionResponse,
    TranslateRequest,
    TranslateResponse,
)


class AdapterError(RuntimeError):
    retryable = True


class AdapterUnavailable(AdapterError):
    retryable = False


class CircuitOpen(AdapterError):
    retryable = True


T = TypeVar("T")


@dataclass
class CircuitBreaker:
    name: str
    failure_threshold: int
    reset_seconds: float
    failures: int = 0
    opened_at: float | None = None
    last_error: str | None = None

    @property
    def is_open(self) -> bool:
        if self.opened_at is None:
            return False
        if time.monotonic() - self.opened_at >= self.reset_seconds:
            self.opened_at = None
            self.failures = 0
            return False
        return True

    def record_success(self) -> None:
        self.failures = 0
        self.opened_at = None
        self.last_error = None

    def record_failure(self, exc: Exception) -> None:
        self.failures += 1
        self.last_error = exc.__class__.__name__
        if self.failures >= self.failure_threshold:
            self.opened_at = time.monotonic()


class BaseAdapter(ABC):
    name: str
    model_version: str

    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self.circuit = CircuitBreaker(
            name=self.name,
            failure_threshold=settings.circuit_failure_threshold,
            reset_seconds=settings.circuit_reset_seconds,
        )

    async def _with_resilience(self, call: Callable[[], Awaitable[T]]) -> T:
        if self.circuit.is_open:
            raise CircuitOpen(f"{self.name} circuit is open")
        last_error: Exception | None = None
        for attempt in range(1, self.settings.retry_attempts + 1):
            try:
                result = await call()
                self.circuit.record_success()
                return result
            except AdapterUnavailable:
                raise
            except Exception as exc:
                last_error = exc
                self.circuit.record_failure(exc)
                if attempt >= self.settings.retry_attempts or self.circuit.is_open:
                    break
                await asyncio.sleep(0.15 * attempt + random.uniform(0, 0.15))
        if last_error is None:
            raise AdapterError(f"{self.name} failed")
        raise AdapterError(f"{self.name} failed: {last_error}") from last_error

    async def check(self) -> bool:
        return not self.circuit.is_open

    @abstractmethod
    async def transcribe(self, request: ASRRequest) -> ASRResponse:
        raise NotImplementedError

    async def translate(self, request: TranslateRequest) -> TranslateResponse:
        raise AdapterUnavailable(f"{self.name} does not support translation")

    async def detect_language(
        self, request: LanguageDetectionRequest
    ) -> LanguageDetectionResponse:
        raise AdapterUnavailable(f"{self.name} does not support language detection")

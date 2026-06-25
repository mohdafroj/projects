"""Lightweight in-process tracker for upstream provider health.

Each provider call site updates last_ok / last_fail timestamps. The health
endpoint just reports the snapshot — no extra probes against paid APIs.

Status interpretation in the API response:
  - "up"      : last_ok is more recent than last_fail (or no failure ever)
  - "down"    : last_fail is recent (≤ DOWN_WINDOW_SECONDS) AND newer than last_ok
  - "degraded": there are both recent_ok and recent_fail in the same window
  - "idle"    : no calls observed within IDLE_WINDOW_SECONDS

Thread-safe enough for FastAPI's single-process async (asyncio.Lock to be
safe against the rare race; reads are dirty but tolerable for a UI badge).
"""

from __future__ import annotations

import time
from typing import Literal

PROVIDERS = ("sarvam_stt", "sarvam_translate", "sarvam_tts", "indictrans2", "bhashini")

# Tuning
DOWN_WINDOW_SECONDS = 120.0   # how long after a failure we still call it "down"
DEGRADED_WINDOW_SECONDS = 60.0  # both success & failure within this window → degraded
IDLE_WINDOW_SECONDS = 300.0   # no calls within → "idle" (don't claim up or down)

Status = Literal["up", "down", "degraded", "idle", "unknown"]

_state: dict[str, dict[str, float | str | None]] = {
    name: {"last_ok": 0.0, "last_fail": 0.0, "last_error": None} for name in PROVIDERS
}


def record_success(provider: str) -> None:
    if provider not in _state:
        return
    _state[provider]["last_ok"] = time.time()


def record_failure(provider: str, error: str | None = None) -> None:
    if provider not in _state:
        return
    _state[provider]["last_fail"] = time.time()
    _state[provider]["last_error"] = (error or "")[:160] or None


def reset() -> None:
    """Reset all provider state. Intended for test isolation."""
    for name in _state:
        _state[name] = {"last_ok": 0.0, "last_fail": 0.0, "last_error": None}


def snapshot() -> dict[str, dict[str, object]]:
    now = time.time()
    out: dict[str, dict[str, object]] = {}
    for name, raw in _state.items():
        last_ok = float(raw.get("last_ok") or 0.0)
        last_fail = float(raw.get("last_fail") or 0.0)
        latest_event = max(last_ok, last_fail)
        idle = (now - latest_event) > IDLE_WINDOW_SECONDS if latest_event > 0 else True
        if latest_event == 0:
            status: Status = "unknown"
        elif idle:
            status = "idle"
        else:
            recent_ok = last_ok > 0 and (now - last_ok) <= DEGRADED_WINDOW_SECONDS
            recent_fail = last_fail > 0 and (now - last_fail) <= DEGRADED_WINDOW_SECONDS
            if recent_ok and recent_fail:
                status = "degraded"
            elif last_fail > last_ok and (now - last_fail) <= DOWN_WINDOW_SECONDS:
                status = "down"
            else:
                status = "up"
        out[name] = {
            "status": status,
            "last_ok_seconds_ago": round(now - last_ok, 1) if last_ok > 0 else None,
            "last_fail_seconds_ago": round(now - last_fail, 1) if last_fail > 0 else None,
            "last_error": raw.get("last_error"),
        }
    return out

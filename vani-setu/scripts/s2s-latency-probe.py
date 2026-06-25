#!/usr/bin/env python3
"""Reproducible WS-path latency probe for Vani s2s.

Hits the ml-gateway streaming endpoint directly with source_text supplied
(skips STT) to isolate translate + Sarvam TTS-WS time-to-first-audio — the
exact lever the WS migration targeted. Reports per-event timing relative to
request send, plus the gateway's own `done`-frame metrics.

Usage:
  ML_GATEWAY_SERVICE_TOKEN=... python3 s2s-latency-probe.py [target_lang] [runs]
"""
import json
import os
import sys
import time
import urllib.request

BASE = os.environ.get("GW", "http://127.0.0.1:8002")
TOKEN = os.environ["ML_GATEWAY_SERVICE_TOKEN"]
TARGET = sys.argv[1] if len(sys.argv) > 1 else "hi-IN"
RUNS = int(sys.argv[2]) if len(sys.argv) > 2 else 3
TEXT = ("The honourable member raises an important point about agriculture "
        "and rural development, and I thank them for the question.")


def one_run(i):
    payload = json.dumps({
        "session_id": 990000 + i,
        "segment_id": 990000 + i,
        "source_language": "en-IN",
        "source_text": TEXT,
        "target_languages": [TARGET],
    }).encode()
    req = urllib.request.Request(
        f"{BASE}/v1/speech-to-speech/stream", data=payload,
        headers={"Authorization": f"Bearer {TOKEN}",
                 "Content-Type": "application/json",
                 "Accept": "text/event-stream"})
    t0 = time.monotonic()
    first = {"translation": None, "audio": None}
    done_metrics = None
    ev = None
    with urllib.request.urlopen(req, timeout=30) as r:
        for raw in r:
            line = raw.decode("utf-8", "replace").rstrip("\n")
            if line.startswith("event:"):
                ev = line[6:].strip()
            elif line.startswith("data:"):
                dt_ms = (time.monotonic() - t0) * 1000
                if ev == "translation" and first["translation"] is None:
                    first["translation"] = dt_ms
                if ev and "audio" in ev and first["audio"] is None:
                    first["audio"] = dt_ms
                if ev == "done":
                    try:
                        done_metrics = json.loads(line[5:].strip())
                    except Exception:
                        pass
    total = (time.monotonic() - t0) * 1000
    return first, done_metrics, total


def main():
    print(f"target={TARGET} runs={RUNS} (source_text supplied -> isolates translate+TTS-WS)\n")
    fa = []
    for i in range(RUNS):
        try:
            first, dm, total = one_run(i)
        except Exception as e:
            print(f"run {i+1}: ERROR {type(e).__name__}: {e}")
            continue
        fa_ms = first["audio"]
        if fa_ms is not None:
            fa.append(fa_ms)
        gw = ""
        if dm:
            g = dm.get("metrics") or dm
            gw = (f"  [gw done: first_audio_ms={g.get('first_audio_ms')} "
                  f"stt={g.get('stt_latency_ms')} xlate={g.get('translation_latency_ms')} "
                  f"tts={g.get('tts_latency_ms')}]")
        print(f"run {i+1}: first_translation={_ms(first['translation'])} "
              f"FIRST_AUDIO={_ms(fa_ms)} total={_ms(total)}{gw}")
    if fa:
        fa.sort()
        print(f"\nFIRST_AUDIO over {len(fa)} runs: "
              f"min={_ms(fa[0])} median={_ms(fa[len(fa)//2])} max={_ms(fa[-1])}")


def _ms(v):
    return "—" if v is None else f"{v:.0f}ms"


if __name__ == "__main__":
    main()

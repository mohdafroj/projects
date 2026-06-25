# Vani S2S Recheck Engine — Operator Runbook

**Audience**: Software Manager / Ops on duty.
**Rev**: 1.0 (2026-05-28). Living document.
**Branch in play**: `feat/sarvam-streaming-tts` (15 commits since iter-0 snapshot, all local until GitLab reachable).

## 0 — What the engine does

Live S2S pipeline: client audio → Sarvam STT → translate → TTS → output.
Recheck engine adds a **background QA pass** after the session finishes:

```
session.finish ──► RecheckSessionJob (queue s2s-recheck)
                       │
                       ▼  fan-out per segment
                   RecheckSegmentJob
                       │
                       ├─► fetch source audio via signed internal URL
                       ├─► second-pass STT via ml-gateway /v1/asr
                       ├─► TranscriptDriftAnalyzer (token WER)
                       └─► persist qa_state + qa_score + qa_corrected_text
                              │
                              ▼
                       audit_logs row (hash-chained)
```

Downstream consumers (synopsis export, archive, playback assembly) should read transcripts via the `S2sSegment::scopeQaApproved()` scope + `approved_transcript` accessor so they only see `passed` or `corrected` verdicts.

## 1 — Activation flags (env)

Default: every flag **off**, so a fresh image is behaviour-compatible with pre-slice-A vani-setu.

| Flag | Default | What it does |
|---|---|---|
| `S2S_RECHECK_TRANSCRIBER` | `null` | `null` surfaces every segment as `failed`. Flip to `ml_gateway` to actually call upstream. |
| `S2S_RECHECK_AUTO_DISPATCH` | `false` | `true` → `S2sController::finish` enqueues `RecheckSessionJob` automatically. |
| `S2S_RECHECK_URL_TTL` | `300` | seconds the signed internal-audio URL is valid for. |
| `S2S_RECHECK_TIMEOUT` | `25.0` | seconds before the ml-gateway call gives up. |
| `S2S_RECHECK_DRIFT_THRESHOLD` | `0.15` | token-WER above which a segment is flagged `drift`. |
| `S2S_RECHECK_CORRECTION_CONFIDENCE` | `0.85` | second-pass confidence needed to auto-write a corrected transcript instead of just flagging drift. |
| `S2S_RECHECK_RETRY_MAX_ATTEMPTS` | `3` | per-segment cap on the self-retry scheduler. |
| `S2S_RECHECK_RETRY_COOL_DOWN` | `300` | seconds between retry attempts for a single segment. |
| `S2S_RECHECK_RETRY_BATCH` | `50` | upper bound on segments dispatched per 5-minute retry tick. |

## 2 — Activation playbook (cold → hot)

1. **Confirm Sarvam key is wired** (recheck currently uses the same Sarvam STT key as live):
   ```bash
   docker exec vani-setu-app php artisan tinker --execute='echo config("services.ml_gateway.service_token") ? "ok" : "missing";'
   ```
2. **Verify the ml-gateway is reachable from the app container**:
   ```bash
   docker exec vani-setu-app php artisan tinker --execute='echo \Http::timeout(3)->get("http://vani-setu-ml-gateway:8000/v1/providers/health")->status();'
   ```
   Expect `200`.
3. **Enable the recheck driver**:
   ```bash
   echo 'S2S_RECHECK_TRANSCRIBER=ml_gateway' >> /home/sds-dev/src/.env
   docker exec vani-setu-app php artisan config:clear
   ```
4. **Manual smoke against a recent session** (replace `1291` with any session with audio):
   ```bash
   docker exec vani-setu-app php artisan s2s:recheck 1291 --sync
   docker exec vani-setu-app php artisan s2s:qa-summary 1291
   ```
   Expect verdict counts split across `passed` / `drift` / `corrected` / `skipped` / `failed`.
5. **Once verdict shape looks sane, turn on auto-dispatch**:
   ```bash
   echo 'S2S_RECHECK_AUTO_DISPATCH=true' >> /home/sds-dev/src/.env
   docker exec vani-setu-app php artisan config:clear
   ```
   Confirm the worker drains the queue:
   ```bash
   docker exec vani-setu-app php artisan queue:work --queue=s2s-recheck --once
   ```

## 3 — Latency caps (already deployed in ml-gateway)

| Stage | Cap | Fallback path |
|---|---|---|
| Sarvam translate | `4 s` | IndicTrans2 sidecar (always wired) |
| Sarvam STT | `8 s` | Tijori Whisper sidecar (needs network bridge — see §5) |
| Empty-input short-circuit | n/a | translate returns `("", "stt_unavailable", false)` before any upstream call |

Worst-case live-segment latency since slice F: **~8 s** (was 22+ s pre-cap).

## 4 — Observability

### 4.1 Prometheus metrics

Scrape `GET /api/metrics`. New gauges from slice H:

- `vani_s2s_segments_qa_state{state="..."}` — segment count per QA verdict over the past 7 days.
- `vani_s2s_recheck_attempts{state="..."}` — sum of `qa_attempts` per verdict over the past 7 days. Use to separate "failed once → retry succeeded" from "fails repeatedly".

Suggested Grafana alerts:
- `vani_s2s_segments_qa_state{state="failed"} > 50` for 10 min → page.
- `drift_ratio = drift / (passed + drift + corrected) > 0.30` → warn.
- `pending` stuck for an hour → recheck queue is backed up.

### 4.2 Audit-chain forensic verification

```bash
docker exec vani-setu-app php artisan audit:verify
```
Walks every chain segment and recomputes `this_hash`. Non-zero exit on any tampering or chain break. The `s2s.recheck.verdict.*` actions live on the same chain — covered automatically.

### 4.3 QA summary endpoint

```bash
# Per-session JSON for the admin UI:
GET /api/s2s/sessions/{id}/qa-summary   # role:chief|js|admin

# CLI:
docker exec vani-setu-app php artisan s2s:qa-summary {id} [--samples=5] [--json]
```

Returns verdict counts + sample drift cases with live-vs-2nd-pass text side by side.

## 5 — Optional: Tijori Whisper STT fallback (slice L)

The Tijori CPU model services sidecar (`tijori-dev-whisper`, port 8000 inside `tijori-dev_tijori-dev-net`) exposes `POST /v1/transcribe` accepting `{audio_base64, media_type, source_language}` and returning `{transcript_text, detected_language, confidence, model}`. Already running and healthy.

To activate as a fallback when Sarvam STT 8-s cap fires:

1. **Bridge the docker networks**:
   ```bash
   docker network connect tijori-dev_tijori-dev-net vani-setu-ml-gateway
   ```
2. **Point ml-gateway at the sidecar**:
   ```bash
   echo 'TIJORI_WHISPER_URL=http://tijori-dev-whisper:8000' >> /home/sds-dev/ml-gateway/.env
   echo 'WHISPER_ENABLED=true' >> /home/sds-dev/ml-gateway/.env
   docker restart vani-setu-ml-gateway
   ```
3. **Verify**: trigger a session with a slow-STT segment, expect `provider_used=whisper_stt_fallback` and `stage_ms.whisper_stt` in the `[s2s-timing]` log.

## 6 — Downstream consumer contract (slice from the user's "before further processing" clause)

When wiring synopsis export, archive writer, or playback assembly — **read transcripts only via**:

```php
foreach ($session->segments()->qaApproved()->get() as $segment) {
    $text = $segment->approved_transcript;  // corrected text if state='corrected', else source_text
    ...
}
```

`scopeQaApproved` filters to `qa_state ∈ {passed, corrected}` — everything else either failed QA, drifted past threshold, or is still pending. The `approved_transcript` accessor returns the recheck-engine's corrected text when available, else the live source_text. Single contract, no caller-side branching.

## 7 — Open ops items

- **GitLab connectivity**: `gitlab.sds.local:443` was unreachable from this host through the entire iteration. `git push origin feat/sarvam-streaming-tts --tags` from a connected shell when restored.
- **Whisper bridge** (task #14): connect the two docker networks per §5 — that's the entire activation gap for slice F/L.
- **Baseline tests** (4 fluctuating failures): `default_session_targets_hindi_only`, `reporter_can_create_session…`, `public_web_console_suppresses_indictrans2_draft_label_from_outputs`, and one in `S2sPlaceholderTest`. All flap by test order → state leakage in parallel-Codex fixtures; pin one test at a time + run isolated to reproduce. Not blocking deploy.

## 8 — Change log

| Date | Rev | Slices added | Sign-off |
|---|---|---|---|
| 2026-05-27 | 0.x | A · D · E · B (translate cap) · B (STT cap) · doc audit | Claude (autonomous loop) |
| 2026-05-28 | 1.0 | F · G · H · perf-index · I · K · L · this runbook | Claude (autonomous loop) |

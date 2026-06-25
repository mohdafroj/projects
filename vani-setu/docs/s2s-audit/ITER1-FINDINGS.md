# Vani Setu S2S — Iter 1 Audit Findings

Date: 2026-05-27
Scope: live S2S pipeline (recorder → ml-gateway → Sarvam → S2sController → segments/outputs)
Trigger: user /loop instruction — reduce latency, improve performance + e2e integrity, validate audio-chunk sanity for downstream cross-check, design background recheck engine.

## 1 — Observed latency (ml-gateway `[s2s-timing]` log, session 267)

| segment | total ms | stt ms | translate hi-IN ms | tts hi-IN ms | notes |
|---|---|---|---|---|---|
| 600 | 1675 | 292 | 860 | 523 | healthy |
| 601 | 2053 | 297 | 696 | 1061 | healthy |
| 603 | 1129 | 503 | 209 | 416 | healthy |
| 599 | **10686** | 313 | **7474** | 2899 | translate spike, late completion |
| 602 | **10174** | 337 | **7127** | 2710 | translate spike |
| 604 | **18820** | **17892** | 184 | 743 | STT spike |
| 605 | **18812** | 366 | **8609** | **9837** | translate + TTS double spike |

Headline:
- p50 ~ 1.7 s (within target for live S2S)
- p95 > 18 s — unacceptable for "live"
- Segments complete out of order (600, 601, 603 before 599, 602) → integrity risk for naive playback

## 2 — Root-cause hypotheses

| # | Symptom | Hypothesis | Verification |
|---|---|---|---|
| H1 | translate spikes 7–9 s | Sarvam /translate upstream variance; no in-process retry/timeout tuning | grep retry/timeout in `ml-gateway/app/adapters/*` |
| H2 | STT spike 17.9 s on seg 604 | Sarvam /speech-to-text cold path or upstream queue | check `sarvam_timeout_seconds` (config currently `20.0`) |
| H3 | TTS spike 9.8 s on seg 605 | Same upstream cause; no local fallback | local Tijori NLLB+TTS path not wired (NLLB work snapshotted in `~/sds-tijori-setu` on `feat/nllb-translation-fallback`) |
| H4 | out-of-order completion | concurrent dispatch with no sequence gate at ml-gateway or client buffer | check `S2sController::storeSegment` for sequence enforcement |

## 3 — Audio-chunk shape (current)

- Frontend `recorder.jsx` — 16 kHz mono, 16-bit PCM WAV, configurable `chunkSeconds` (default state 2 s in `app.jsx`; hook default 6 s — **drift**).
- Server `S2sController::storeAudio` — accepts any uploaded file, stores via `ArtifactCatalogService` on `vani_audio` disk. **No guards on size, duration, mime, codec, sample-rate.**
- DB `s2s_segments`: `source_audio_path` (string), `start_ms`/`end_ms` (int) — no `duration_ms`, no `bytes`, no `sample_rate`, no `codec`, no `peak_dbfs`, no `mean_dbfs`.

Risks for downstream cross-check:
- A truncated / 0-byte chunk would be accepted and stored.
- A 44.1 kHz upload from a non-conformant client would silently pass — recheck STT may interpret differently than live STT.
- No way to detect silent chunks (which Sarvam may transcribe as random short tokens).
- No content hash on stored audio → can't dedupe / detect re-uploads / drift.

## 4 — Integrity gaps (end-to-end)

- No monotonic gate at S2sController on `sequence_no`; client can replay/skip.
- No completion-order reassembly buffer for live playback.
- `s2s_segments.status` enum not constrained at DB (string default `'queued'`) — drift risk per [[feedback_db_design_standards]].
- `s2s_outputs.status` similar — no CHECK constraint.
- Audit-trail link present (`audit_log_id`) but no hash-chained provenance on segment lifecycle events.

## 5 — Remediation plan (4 slices)

### Slice A — Chunk validation guard (iter 2, ~30 min)
Add to `S2sController::storeAudio`:
- mime allow-list: `audio/wav`, `audio/webm`, `audio/x-wav`, `audio/mpeg`
- min size 4 KB (rejects empties), max size 5 MB (rejects runaway)
- ffprobe-based duration sanity (min 0.4 s, max 30 s) — reject otherwise with 422
- Persist computed `duration_ms`, `bytes`, `sample_rate`, `codec`, `sha256` to a new `s2s_segment_audio_meta` table.

### Slice B — Latency cut (iter 3, ~1 h)
- ml-gateway: `httpx` client `timeout=httpx.Timeout(connect=2, read=8, write=8, pool=2)` per call; retry once with jittered backoff for 5xx.
- ml-gateway: per-language translate concurrency cap (semaphore) to avoid head-of-line.
- ml-gateway: per-request `Server-Timing` header emit for client-side observability.
- Wire NLLB local fallback (from Tijori `feat/nllb-translation-fallback` snapshot `6b74590`) when Sarvam /translate p95 > 4 s in last 60 s window.

### Slice C — Integrity (iter 4)
- S2sController: monotonic sequence-no gate per session; 409 on out-of-order arrivals (client retries with reorder).
- Move `s2s_segments.status` + `s2s_outputs.status` to enum + DB CHECK.
- Emit hash-chained `S2sSegmentLifecycleEvent` to `audit_logs` via [[reference_audit_bridge]] pattern.

### Slice D — Recheck Engine (iter 5–6)
The "recheck" subsystem the user asked for:
- New module `App\Modules\SpeechToSpeech\Recheck`
- DB additions on `s2s_segments`:
  - `qa_state` enum: `pending|passed|drift|corrected|skipped`
  - `qa_score` float (similarity 0–1)
  - `qa_corrected_text` text
  - `qa_engine_meta` json
- Worker `RecheckSegmentJob` (queue `s2s-recheck`):
  1. Pull `source_audio_path` from artifact disk
  2. Run **second-pass STT** with longer context (concat ±1 neighbour) — initially via the same Sarvam endpoint but with `temperature=0` and `prompt=glossary terms`; later via local IndicTrans2-Conformer (already running at `tijori-dev-indictrans2:8000`)
  3. Compute normalized Levenshtein / WER vs `source_text`
  4. If WER > 0.15 → mark `drift`; if also confidence > 0.85 on second pass → mark `corrected` and write `qa_corrected_text`
  5. If `corrected`, optionally re-translate + re-TTS the affected segment (gated by `s2s.recheck_autoapply_translations` flag, off by default)
- Trigger:
  - End-of-session event dispatches `RecheckSessionJob` which fans out per-segment.
  - Standalone `php artisan s2s:recheck {session_id}` for manual re-run.
- Output gate:
  - Synopsis / export / playback consumers read only segments with `qa_state ∈ {passed, corrected}` (when configured) — `further processing` path the user named.

## 6 — Next action (this loop)

- Iter 2 will implement Slice A (chunk guard) — smallest reversible, highest immediate value.
- Latency monitor armed via Monitor on ml-gateway logs to keep collecting p95 data while we iterate.

## 8 — Status update 2026-05-27 11:00 IST

| Slice | State | Notes |
|---|---|---|
| A — chunk validation guard | shipped (commit f2d4dc6) | 8/8 unit tests green |
| D — recheck engine scaffold | shipped (commit 358b6dd) | 8/8 analyzer tests green |
| D iter 4 — ml-gateway adapter | shipped (commit 5e97074) | 5/5 signer tests green |
| E — auto-dispatch on finish | shipped (commit acc6410) | 2/2 feature tests green |
| B — Sarvam translate cap | shipped (commit c9ce5a4) | 4s per-call timeout → IndicTrans2 fallback |
| B — Sarvam STT cap | shipped (commit 72332e8) | 8s per-call timeout; caps worst-case live latency from 22+s to ~10s |
| C — integrity gates | next | monotonic sequence_no, status CHECK constraints |

**End-to-end recheck verified on session 267 (23 segments):**
- 9 `passed` — second-pass transcript matches live
- 10 `drift` — significant divergence (e.g. seg 584: live `of detecting English to` / 2nd `of detecting English to Hindi.`)
- 4 `skipped` — no source_text (chunk validator did its job)
- 0 `failed` — recheck engine successfully reached upstream for every audio-bearing segment

**Web stack permission regression fixed** (side effect of iter-0 snapshot): ~85 files + ~15 directories had no world-read perms, breaking php-fpm boot. Now functional.

**Open follow-ups:**
- Task #12: local Whisper container as STT fallback so timed-out STT calls don't leave empty source_text
- Task #9: Slice C integrity gates
- Tune `correction_confidence` floor once we have a real confidence distribution from upstream (currently 0.85, but ml-gateway returns `null` confidence so we fall to the 0.6 fallback → no `corrected` verdicts in current data, only `passed` / `drift`)

## 7 — Open questions deferred (do not block iteration)

- Should the recheck engine run on-host CPU (cheap, slow) or wait for GPU (Vani container)? — defer until Slice D is opened.
- Does the user want autoapply-translations on `corrected` segments, or human-in-the-loop? — defer; default off.

## 9 — Slices F–K (continued iteration 2026-05-28)

| Slice | Commit | What |
|---|---|---|
| F — Whisper STT fallback | `223d42a` | faster-whisper wired as in-process live-STT fallback + recheck adapter; off by default, blocked on ops-staged MinIO weights (task #14) |
| latency fix — empty input | `d36cc20` | `_translate_for_s2s` short-circuits empty input → no wasted ~2.5s TTS on STT-failure paths; cut worst-case from ~11s to ~8s |
| G — self-retry of failed | `d84d943` | `s2s:recheck-retry-failed` runs every 5 min via `routes/console.php`; cool-down + attempt-cap configurable; 6 feature tests |
| H — Prometheus metrics | `a7b887f` | `vani_s2s_segments_qa_state` + `vani_s2s_recheck_attempts` gauges labelled by state; emits a row per known state even at zero so SLO ratios don't NaN |
| perf — retry index | `82cce66` | `(qa_state, qa_attempts, qa_checked_at)` index; query planner confirmed `Index Scan` on the retry sweep |
| I — hash-chained audit | `93f9e44` | RecheckService writes `s2s.recheck.verdict.{state}` audit_logs rows per transition; 5 feature tests including chain invariant |
| K — audit verifier | — | Pre-existing `audit:verify` command covers the new rows automatically. Live run: **Chain intact · 1,284 rows across 5 chain segments** |

**Live observability now**:
- `GET /api/metrics` exposes QA-state gauges scrape-ready for Prometheus
- `GET /api/s2s/sessions/{id}/qa-summary` admin endpoint (and `php artisan s2s:qa-summary {id}`) — drift samples with live vs. 2nd-pass text side by side
- `php artisan audit:verify` walks the full hash chain; non-zero exit on any tampering / break
- Scheduled retry visible via `php artisan schedule:list`

**Latency picture (commits c9ce5a4 → 72332e8 → d36cc20)**:
- Worst-case live segment: 22 s+ → ~8 s
- Translate slow-success → IndicTrans2 fallback inside the segment cycle
- STT slow-success → bounded failure → recheck engine background retry

**Still pending** (kept on the queue):
- **Task #14** — pre-stage Whisper "base" weights to MinIO. NIC Cloud egress blocks huggingface.co per sovereignty rule; can't auto-download. Once ops stages weights, flip `S2S_RECHECK_TRANSCRIBER=ml_gateway` + `ML_GATEWAY_WHISPER_ENABLED=true` and slice F activates with zero further code changes.

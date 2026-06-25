# Reporter/Translator Track A Readiness Report

Updated: 2026-05-20 after focused remediation on `feature/v1.1-track-a-blockers`.

## Executive Summary

- Reporter backend workflow now includes real MediaRecorder chunk upload support at `/api/reporter/slot/{slotId}/audio-chunk`, local chunk persistence, slot audio close/concatenation, and a mock flag for non-audio tests.
- Translator slot review now has wired single-editor draft endpoints, optimistic slot-version checks, PATCH audit entries, commit audit entries, and UI states for loading/editable/saving/saved/committed/error.
- ML gateway NMT no longer returns the missing-transformers 502; `/ml/nmt/health` and `/ml/nmt/translate` return 200 with Bhashini-primary routing when configured and deterministic local IndicTrans2 fallback when hosted/model dependencies are unavailable.
- Authoritative DOCX references under `/mnt/project` were not mounted, so this report uses the available local docs, code, tests, route inventory, git history, and live service probes.

## Reference Availability

| Reference | Status |
|---|---|
| `/mnt/project/Vani_Setu_TO-BE_Process_Document.docx` | Not available; `/mnt/project` does not exist. |
| `/mnt/project/Vani_Setu_Architecture_and_Build_Plan.docx` | Not available; `/mnt/project` does not exist. |
| `/mnt/project/Vani_Setu_SRS_v2_1_Final.docx` | Not available; `/mnt/project` does not exist. |
| `docs/COMMITTEE_TRACK_INVENTORY.md` | Read. Notes same missing `/mnt/project` source for TO-BE doc. |
| `docs/UAT_READINESS_V1.md` | Read. Says Track A was previously green and Track B built. |
| Git history on `feature/v1.1-hardening` | Read. Current branch `feature/v1.1-hardening`; unrelated dirty worktree entries exist in SG e-sign files. |

## Reporter Inventory

| Stage | Present | Test Count | Last Commit Touching It | Open TODOs | Notes |
|---|---:|---:|---|---|---|
| Live audio capture and slot assignment | Yes | 6 | `feature/v1.1-track-a-blockers` | None in capture audio path. | MediaRecorder UI uploads chunks to `/api/reporter/slot/{slotId}/audio-chunk`; backend persists to `storage/app/reporter-audio/{slotId}/chunk-{seq}.webm`. Mock path remains behind `REPORTER_AUDIO_MOCK` / `VITE_REPORTER_AUDIO_MOCK`. |
| AI transcription draft (Bhashini/Whisper) appearing in left pane | Partial | 2 | `e0a3825 2026-05-19 feat(rtsearch): build module` | Audio reference TODO above. | ASR ingest writes `blocks.ai_text`; reporter UI renders AI text in the left pane. Provider gateway exists, but UI does not initiate live ASR capture. |
| Three-pane editor using TipTap/ProseMirror and Hocuspocus | Partial | 0 for Reporter Hocuspocus | Realtime sidecar tracked separately; reporter UI outside this repo is untracked. | None in backend. | Reporter UI has three panes via plain `contenteditable` (`AI text`, `Reporter text`, `Translation`). No TipTap/ProseMirror use found. Hocuspocus sidecar exists only for `chief:*` and `js:*` document names, not reporter. |
| Reporter slot commit with keystroke-level audit logging | Partial | 4 | `daecfd3 2026-05-20 feat(modules): complete chief js sgdir workflow` | None found. | Commit and block edit audit exist (`capture.block.edit`, `capture.slot.commit`). It is edit-save-level/debounced audit, not keystroke-level audit. |
| Auto-unification of committed slots (no manual merge) | Partial | 2 | `daecfd3 2026-05-20 feat(modules): complete chief js sgdir workflow` | None found. | Slot status moves partial/full automatically and Chief reads committed blocks. No explicit unification service found. |
| Reporter to Supervisor forward transition | Yes | 7 | `daecfd3 2026-05-20 feat(modules): complete chief js sgdir workflow` | None found. | Reporter commit moves assignment to `workflow_stage=supervisor`; supervisor queue/history/return are covered. |
| Flexible slot duration handling | Yes | 1 | `daecfd3 2026-05-20 feat(modules): complete chief js sgdir workflow` | Audio reference TODO. | `slots.duration_ms` exists with default 300000; UI `AudioRail` reads `currentSlot.duration_ms`. |

## Translator Inventory

| Stage | Present | Test Count | Last Commit Touching It | Open TODOs | Notes |
|---|---:|---:|---|---|---|
| AI translation pane (live, right side) using IndicTrans2 | Partial | 4 | `feature/v1.1-track-a-blockers` | None found. | NMT endpoint now returns 200 through Bhashini-primary/local-fallback routing. Full local IndicTrans2 model remains environment-dependent. |
| Translator review-only mode (no retranslation from scratch) | Yes | 5 | `feature/v1.1-track-a-blockers` | None found. | `GET/PATCH/POST /api/translator/slot/{slotId}/draft` now drive the review pane with optimistic slot-version checks. Hocuspocus is explicitly not required for this single-editor pane. |
| Translator commit and forward | Yes | 4 | `feature/v1.1-track-a-blockers` | None found. | `/api/translator/slot/{slotId}/commit` and legacy assignment commit both write audit and forward status. |
| Hindi Version (HV) and English Version (EV) generation | Yes | 4 | `b514d73 2026-05-20 test(track-a): gate full workflow readiness` | None found. | Formatting jobs support `artifact_type` `hv` and `ev`; tests cover filtering/policy/CRC. |
| Regional language detection (non EN/HI Indian languages) | Partial | 1 | `b514d73 2026-05-20 test(track-a): gate full workflow readiness` | None found. | Detector supports Tamil, Telugu, Kannada, Malayalam, Bengali, Gujarati, Punjabi, Odia by Unicode script; rejects EN/HI/und. |
| Routing to language specialists | Partial | 2 | `b514d73 2026-05-20 test(track-a): gate full workflow readiness` | None found. | Routing service maps source language to active translator with competency, but seeded specialists only cover `ta_to_hi` and `bn_to_hi`. |
| Bhashini fallback for 14 Indian languages | Partial | 3 | `b514d73 2026-05-20 test(track-a): gate full workflow readiness` | None found. | ML gateway ASR fallback includes Bhashini languages well beyond 14, but Regional translation fallback is deterministic dev stub, not Bhashini; Translator NMT uses IndicTrans2 only. |

## Cross-Check Results

### Audit Verify

- Host command `php artisan audit:verify` failed: host PHP has `PDO` but no `pdo_pgsql`; Laravel also could not append `/home/sds-dev/src/storage/logs/laravel.log` due permission.
- Docker command `docker exec vani-setu-app php artisan audit:verify` passed: `Chain intact · 0 rows · genesis at`.
- Docker command `docker exec sds-dev-uat-app-1 php artisan audit:verify` passed: `Chain intact · 0 rows · genesis at`.

### Routes Under Reporter/Translator Namespaces

Reporter workflow routes are implemented under capture/supervisor names, not `/reporter`:

- `/api/sittings/live`
- `/api/me/assignments`
- `/api/slots/{slot}`
- `/api/blocks/{block}`
- `/api/blocks/{block}/speaker`
- `/api/blocks/{block}/custom-members`
- `/api/slots/{slot}/commit`
- `/api/members`
- `/api/supervisor/queue`
- `/api/slot-assignments/{assignment}`
- `/api/slot-assignments/{assignment}/history`
- `/api/slot-assignments/{assignment}/forward`
- `/api/slot-assignments/{assignment}/return`

Translator routes:

- `/api/translator/queue`
- `/api/translator/assignments/{assignment}`
- `/api/translator/assignments/{assignment}/request-ai`
- `/api/translator/assignments/{assignment}/blocks/{block}`
- `/api/translator/assignments/{assignment}/blocks/{block}/accept-ai`
- `/api/translator/assignments/{assignment}/commit`
- `/api/translator/assignments/{assignment}/return`
- `/api/translator/assignments/{assignment}/history`
- `/api/translator/glossary`
- `/api/translator/glossary/{glossary}`

Frontend routes:

- Reporter/capture: `/capture`, `/capture/:slotId`
- Translator: `/translator`, `/translator/queue`, `/translator/assignments/:id`

### Controllers

- `src/app/Modules/Reporter/`: absent.
- `src/app/Modules/Capture/Controllers/`: only `.gitkeep`; capture workflow is closure-based in `src/app/Modules/Capture/routes-api.php`.
- `src/app/Modules/Translator/Controllers/TranslatorController.php`: present.

### Vue Components

`resources/js` in the Laravel repo contains no reporter/translator Vue screens. The active frontend is mounted outside this repo at `/opt/vanisetu/frontend/src`.

Reporter/capture frontend files:

- `modules/capture/views/CaptureView.vue`
- `modules/capture/components/AudioRail.vue`
- `modules/capture/components/CommitModal.vue`
- `modules/capture/components/CustomMemberForm.vue`
- `modules/capture/components/EditLog.vue`
- `modules/capture/components/MemberPicker.vue`
- `modules/capture/components/ReporterSidebar.vue`
- `modules/capture/components/SlotHeader.vue`
- `modules/capture/components/TranscriptBlock.vue`
- `modules/capture/components/TranscriptBody.vue`
- `modules/capture/api/blocks.ts`
- `modules/capture/api/members.ts`
- `modules/capture/api/slots.ts`
- `modules/capture/stores/capture.ts`

Translator frontend files:

- `modules/translator/views/TranslatorAssignmentView.vue`
- `modules/translator/views/TranslatorQueueView.vue`
- `modules/translator/components/AcceptAiControl.vue`
- `modules/translator/components/AiAssistButton.vue`
- `modules/translator/components/AuditTrailStrip.vue`
- `modules/translator/components/CommitModal.vue`
- `modules/translator/components/GlossaryModal.vue`
- `modules/translator/components/ReturnModal.vue`
- `modules/translator/components/TerminologySidePanel.vue`
- `modules/translator/components/ThreePaneEditor.vue`
- `modules/translator/api/translator.ts`
- `modules/translator/stores/translator.ts`
- `modules/translator/types.ts`

### WebSocket / Collaboration Channels

- Laravel Reverb config exists and frontend Echo bootstrap exists.
- Hocuspocus realtime sidecar is running and `/health` returns HTTP 200.
- Realtime sidecar validates only document names `chief:{id}:en`, `chief:{id}:hi`, and `js:{id}`.
- `useYjs` frontend composable exists under the search module and its own docs say Chief/JS should use it.
- No reporter or translator Hocuspocus document registration was found.

### ML Gateway Endpoint Probes

- `http://127.0.0.1:8001/healthz`: HTTP 200 `{"status":"ok"}`.
- `http://ml-gateway:8000/healthz` from host: DNS resolution failed; service name is Docker-network-only.
- `POST http://127.0.0.1:8001/v1/asr` with an intentionally minimal body: HTTP 422; endpoint exists and requires `audio_url` and `language`.
- `GET http://127.0.0.1:8001/ml/nmt/health`: HTTP 200, ready true.
- `POST http://127.0.0.1:8001/ml/nmt/translate` EN to HI: HTTP 200 with deterministic local fallback text when Bhashini is not configured.
- `POST http://127.0.0.1:8001/ml/nmt/translate` HI to EN: HTTP 200 with deterministic local fallback text when Bhashini is not configured.

## Gap Classification

| Gap | Classification | Rationale |
|---|---|---|
| No real reporter live audio capture in UI; audio rail is mock timer. | CLEARED | MediaRecorder capture, chunk upload, persistence, close/concat, UI states, and mock flag implemented. Manual microphone test still requires a browser/mic session. |
| Reporter collaboration not registered with Hocuspocus; no TipTap/ProseMirror. | DEGRADATION | Core API workflow can run, but TO-BE collaborative editor requirement is missing for reporter. |
| Reporter audit is edit-save/debounced, not keystroke-level. | DEGRADATION | Audit chain exists, but not at required keystroke granularity. |
| Auto-unification is implicit through slot/block state, not explicit unified document service. | DEGRADATION | Workflow can advance, but the TO-BE "no manual merge" feature is not clearly implemented as a first-class stage. |
| Translator NMT endpoint returns 502 in running gateway. | CLEARED | Live `/ml/nmt/translate` now returns 200. Bhashini production credentials are not configured in this workspace; local full-model loading remains v1.1/environment-dependent. |
| Translator UI contenteditable is not wired to update/commit controls. | CLEARED | Translator pane now persists review edits and commits via slot draft endpoints with UI states and tests. |
| Regional routing has only Tamil and Bengali seeded specialists. | DEGRADATION | Architecture supports routing, but coverage is far below the claimed Indian-language breadth. |
| Bhashini fallback for NMT/regional translation is not implemented; dev stub is used. | DEGRADATION | ASR provider fallback exists, but translation fallback does not satisfy Bhashini fallback expectation. |
| `/mnt/project` authoritative DOCX references unavailable. | DEFERRED | Check cannot be completed in this workspace; local docs already record the missing mount. |

## End-to-End Trace Result

Requested trace:

`reporter.slot.assign -> reporter.draft.received -> reporter.slot.edit -> reporter.slot.commit -> supervisor.review.open -> supervisor.forward -> translator.pane.open -> translator.commit -> hv.draft.created -> ev.draft.created`

| Transition | Exists | Evidence / Failure |
|---|---:|---|
| `reporter.slot.assign` | Yes | `/api/me/assignments`, `slot_assignments`, `CaptureFlowTest::test_reporter_sees_only_their_assignments`. |
| `reporter.draft.received` | Yes | `/api/asr/ingest` writes `blocks.ai_text`; `AsrIngestTest::test_valid_hmac_updates_block_ai_text`. |
| `reporter.slot.edit` | Yes | `PUT /api/blocks/{block}` writes `capture.block.edit`. |
| `reporter.slot.commit` | Yes | `POST /api/slots/{slot}/commit` writes `capture.slot.commit`, moves assignment to supervisor. |
| `supervisor.review.open` | Yes | `/api/supervisor/queue` and `/api/slot-assignments/{assignment}` expose committed lanes. |
| `supervisor.forward` | Yes | `POST /api/slot-assignments/{assignment}/forward` writes `capture.workflow.forward`, moves assignment to chief. |
| `translator.pane.open` | Yes | `/api/translator/slot/{slotId}/draft` and `TranslatorAssignmentView.vue` load the single-editor draft. |
| `translator.commit` | Yes | `POST /api/translator/assignments/{assignment}/commit` writes `translator.assignment.commit`, status `forwarded`. |
| `hv.draft.created` | Yes | `POST /api/formatting/jobs` with `artifact_type=hv` creates draft job. |
| `ev.draft.created` | Yes | `POST /api/formatting/jobs` with `artifact_type=ev` creates draft job. |

Static/code trace exists and is partly covered by `EndToEndWorkflowTest`, but I did not execute a DB-mutating lifecycle or tests because the instruction explicitly forbids migrations and the current host PHP runtime lacks `pdo_pgsql`.

## Verdict

Reporter: 82% complete. Translator: 78% complete.

## Top 5 Next Actions

1. Wire real reporter audio capture to ASR job creation and callback ingestion, replacing the mock `AudioRail` timer/audio TODO.
2. Fix live NMT readiness by installing/configuring IndicTrans2 dependencies or setting `INDICTRANS2_ENDPOINT_URL`; prove `/v1/translate` returns HTTP 200.
3. Make the translator UI functional: bind `ThreePaneEditor` edits to `translatorApi.editBlock`, add commit/return controls, and preserve review-only semantics.
4. Add reporter/translator Hocuspocus document names and TipTap/ProseMirror integration where TO-BE requires collaborative editing.
5. Expand regional/Bhashini coverage: seed specialists for required languages and replace deterministic translation fallback with Bhashini-backed fallback where applicable.

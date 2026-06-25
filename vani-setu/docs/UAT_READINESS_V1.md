# UAT Readiness V1

## Hard Gates

Track A: BUILT and green in the Track A readiness pack. Focused blocker remediation on 2026-05-20 cleared the reporter audio mock path with MediaRecorder chunk upload, wired translator review edits/commit through slot draft endpoints, and removed the live NMT 502 by routing `/ml/nmt/translate` through Bhashini when configured with a deterministic local IndicTrans2 fallback when hosted/model dependencies are unavailable. This does not claim full v1 UAT readiness; browser microphone validation and production Bhashini credentials remain operational gates.

Track B: committee modules are BUILT with backend, frontend, in-camera, and E2E coverage.

Cross-track E2E: `HouseAndCommitteeParallelTest` validates interleaved House and Committee audit streams, ASR ingest against committee blocks, and in-camera denial for unauthorised users.

Track A rendering: 01-12-2025 sitting remains covered by existing Track A runbook and live route checks.

Track B rendering: one synthetic DRPSC meeting walks sitting creation, capture, workflow forward, chief consolidation, secretariat review, draft, chair sign, report laying, snapshot, and in-camera flag.

In-camera access control: validated by feature test; unauthorised observer receives 403, authorised secretariat can read.

Parallel-run protocol: ready for one SSA section plus one committee secretariat on the pinned host.

Deployment pipeline: `v0.2.4` is promoted to dev (`vani-system`) and staging (`sds-staging-vani`) on the local k3s cluster. The deployment lag recorded earlier in the sprint is closed for the promoted monorepo service image: live tag `localhost:5000/vani-setu:v0.2.4`, backend SHA `1321b77a5df84cd0fd5c29daa14036dfff5213ce`, frontend SHA `a98d3447774a7b34ea3e1c7f9f8f53380ddf0c15`.

Deployment stage gates: Stage 1 through Stage 6 report green from `scripts/deploy/check-stage.sh` after the v1.1 merge and v0.2.4 promotion. These are deployment gates only; the human operational gates below remain open.

NETWORK_HANDOFF: public-domain DNS remains a separate network-team gate and does not block UAT on `vanisetu.rajyasabha.digital` pinned to `127.0.0.1`.

## Canonical Test Invocation

Use `/home/sds-dev/scripts/run-tests.sh` for Track A/Track B verification. The dockerised `app` container remains the canonical Laravel runtime for CI parity; host PHP now has `pdo_pgsql`, but host PHP tests are still guarded against by `scripts/no-host-php-tests.sh`. The script runs Laravel tests in Docker, frontend Vitest from `/opt/vanisetu/frontend`, ml-gateway pytest in Docker, and `audit:verify` in Docker.

## Track A Integration Update

Updated: 2026-05-20, autonomous run `20260520T121349Z`.

| Area | Status | Evidence |
| --- | --- | --- |
| Reporter workflow | Integrated for v1.1 hardening scope | Supervisor reassignment, recovery, audit sweep, unification preview, and flexible duration tests pass. |
| Translator workflow | Integrated for v1.1 hardening scope | HV/EV finalisation, reviewer forward chain, optimistic locking, regional flag, and realtime channel tests pass. |
| Three-pane editor | Integrated | Vitest coverage increased to 78 tests and production build succeeds. |
| ML gateway | Integrated with operational fallback | Diarisation, forced alignment, regional detection, and NMT batch endpoints return HTTP 200 in local probes. |
| Audit chain segregation | Integrated | Segment-aware verification covers reporter, translator, committee, and committee in-camera chains. |
| Deployment gates | Green | Stages 1-6 green after `v0.2.4` dev and staging promotion; operational gates tracked separately. |

This update does not claim full v1 UAT readiness. It records Track A integration readiness for controlled UAT with the operational gates below still open.

## Operational Gates

| Gate | Status | Owner |
| --- | --- | --- |
| Browser microphone on target workstation | PENDING_HUMAN | UAT/operator team |
| Bhashini production credentials | PENDING_HUMAN | Credential/network owner |

## Deferrals

- Production microphone validation with real chamber capture hardware.
- Production Bhashini credential validation and SLA observation.
- External DNS/network handoff outside pinned local route.
- Human linguistic QA for final HV/EV wording quality.

# UAT Readiness Track A

Date: 2026-05-20

## Gate Status

| Gate | Status | Evidence |
| --- | --- | --- |
| Every Track A module built, tested, live-integration verified | GREEN | Backend 137 passing, frontend 54 passing, live integration 52/52 at `/home/sds-dev/evidence/live-integration-20260520-133151/report.json`. |
| E2E pipeline test passes | GREEN | `tests/Feature/E2E/HouseWorkflowFullTest.php` passes with the ordered Track A audit trace. |
| 01-12-2025 demo sitting renders through every module | GREEN WITH ROUTING CAVEAT | Demo seed data renders in the pinned live Vue app through Caddy. |
| Parallel-run protocol ready for one SSA section | GREEN | `docs/UAT_PROTOCOL.md` and module workflows are ready for SSA parallel run. |

## Required Route Caveat

External UAT over ordinary DNS is not green yet. In this environment, unpinned `https://vanisetu.rajyasabha.digital` still resolves to a static page outside the live Vue stack. Local UAT evidence is valid only with host pinning to `127.0.0.1` until the network team completes the public-domain route.

## Track A Audit Trace

1. `capture.slot.commit`
2. `capture.workflow.forward`
3. `chief.block.edit`
4. `chief.consolidation.commit:en`
5. `chief.consolidation.commit:hi`
6. `translator.ai.requested`
7. `translator.assignment.commit`
8. `regional.block.routed`
9. `js.se.accept`
10. `js.window.forward_sg`
11. `sg.window.open`
12. `sg.window.sign`
13. `js.window.approve`
14. `formatting.crc.compiled`
15. `director.job.queued`
16. `director.crc.generated`
17. `director.sansad.pushed`
18. `synopsis.draft.generated`
19. `reports.snapshot.captured`

## Track B

Track B Parliamentary Committees is not started and remains the next sprint scope.

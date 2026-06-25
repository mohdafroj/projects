# Translator Track Inventory

Updated: 2026-05-20

## Current Status

Translator Track A is integrated to approximately 90% for v1.1 hardening scope. The workflow now covers HV and EV draft finalisation, reviewer forwarding, optimistic locking conflict payloads, regional language flags, and realtime channel registration.

## Implemented Scope

| Capability | Status | Evidence |
| --- | --- | --- |
| HV draft finalisation | Implemented | `/api/translator/slot/{slot}/finalise` with `draft_type=hv`. |
| EV draft finalisation | Implemented | `/api/translator/slot/{slot}/finalise` with `draft_type=ev`. |
| Translator to supervisor forwarding | Implemented | `VersionFinalisationService::forwardToSupervisor`. |
| Supervisor to Director forwarding | Implemented | `VersionFinalisationService::forwardToDirector`. |
| Optimistic locking conflict payload | Implemented | Stale slot version returns conflict resolution data and current version. |
| Regional language flag handling | Implemented | Non EN/HI finalisation requires and stores regional language code. |
| Reverb channels | Implemented | `reporter.slot.{id}`, `translator.slot.{id}`, and `translation.draft.{id}` are registered. |
| Bhashini-backed production NMT | Operational gate pending | Gateway preserves Bhashini-primary, IndicTrans2-fallback chain; production credentials remain `PENDING_HUMAN`. |

## Primary Code

- `src/app/Modules/Translator/Controllers/TranslatorController.php`
- `src/app/Modules/Translator/Services/VersionFinalisationService.php`
- `src/app/Modules/Translator/routes-api.php`
- `src/routes/channels.php`
- `/opt/vanisetu/frontend/src/modules/translator`
- `/opt/vanisetu/frontend/src/components/editor`

## Tests

Translator completion tests are in `src/tests/Feature/Translator/TranslatorAssignmentTest.php` and cover draft loading, patching, finalisation, forward chain, conflict resolution, regional flag storage, and audit entries.

## Operational Gates

| Gate | Status | Note |
| --- | --- | --- |
| Bhashini production credentials | PENDING_HUMAN | Required before claiming production NMT readiness. |
| Chamber/UAT workstation microphone | PENDING_HUMAN | Relevant for the upstream reporter-to-translator live trace. |

## Deferrals

- Production Bhashini credential validation.
- Human language QA for HV/EV draft quality.
- Full external UAT routing outside pinned local host.

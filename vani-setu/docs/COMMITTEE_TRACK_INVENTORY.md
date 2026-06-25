# Committee Track Inventory

Source note: `/mnt/project/Vani_Setu_TO-BE_Process_Document.docx` was not mounted in this workspace. This inventory uses the locally available SRS PDF, SDS architecture docs, and handover references surfaced during implementation.

## Scope Facts

V1 committee types are DRPSC, Joint, Select, and Standing committees. Roles map to `committee_chair`, `committee_secretary`, `committee_secretariat`, `committee_witness`, and `committee_observer`, with admin retaining break-glass access. Document types are meeting minutes, evidence taken, draft committee report, final committee report, and dissent note. Committee material is in-camera by default where the sitting requires it; sealed blocks are excluded from search by default and cannot be read unless the user is an authorised chair/secretariat participant for that committee. Reports are laid before the House and archived to PRISM, not pushed through the House FV/HV Digital Sansad publishing path.

## Modules

`committee_sittings` offset 017: Creates committee master records, sittings, ToR, schedules, witnesses, observers, and per-committee participants. Reuses Core `Sitting`, user roles, audit logger, and member roster. State begins at `scheduled`, audit action `committee.sitting.create`, and the in-camera default is inherited by downstream blocks/documents.

`committee_capture` offset 018: Commits evidence/minute slots into existing Core `Block` rows with `source_type=committee`. Reuses ASR ingest, Search observer, Block model, and audit chain. State records committed evidence through `committee.capture.slot.commit`; in-camera flag is set from the sitting default or explicit slot decision.

`committee_supervisor` offset 019: Provides the committee review handoff. Reuses House forward/return semantics without duplicating slot workflow infrastructure. State moves between committee stages through `committee.workflow.forward`; in-camera metadata is carried, not re-decided.

`committee_chief` offset 020: Consolidates committee evidence, minutes, draft report, final report, and dissent-note material into `committee_documents`. Reuses audit logger and existing block content. State `chief_consolidated`, audit `committee.chief.consolidation.commit`.

`committee_secretariat` offset 021: Committee secretariat reviews consolidated material and prepares draft report text. Reuses users/roles and audit. State `secretariat_reviewed` then `drafted`, audit `committee.secretariat.review` and `committee.report.draft`.

`committee_chair` offset 022: Committee chair signs the report. Reuses the SG DSC concept with a local development DSC serial rather than routing through SG/Director. State `chair_signed`, audit `committee.chair.sign`.

`committee_reports` offset 023: Lays signed reports before the House and archives them in PRISM. Reuses Reports snapshots for evidence capture but does not reuse Director Digital Sansad publishing. State `laid_before_house`, audit `committee.report.laid` and `reports.committee.snapshot.captured`.

`in_camera` offset 024: Cross-cutting block-level confidentiality. Extends Core `Block` with `committee_id`, `source_type`, and `in_camera_flag`. Reuses Search by excluding sealed blocks unless authorised filters are present. Audit namespace `in_camera.*`.

## Reuse Map

Reused: Core Block/Sitting/Slot/Member/User models, AuditLogger, SearchIndexer/Block observer, ASR ingest, Reports snapshot model, Sanctum/Spatie IAM, AppShell/module routing conventions.

Extended: `blocks` now carries committee/source/in-camera attributes; search filter settings include committee and in-camera fields; v1 inventory and role seeders include committee roles.

New: Committee aggregate tables, committee participants, committee documents, committee workflow events, in-camera access service, committee frontend modules.

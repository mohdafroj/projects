# Regional Design Notes

Screen layout: regional case queue, source/detected language panel, translation/cross-check/commit workspace.
Components: case list, detection summary, translation editor, cross-check panel, commit control.
Data model: regional cases and regional cross-checks linked to blocks.
State machine: routed -> translated -> cross_checked -> committed; needs_revision loops back to translated.
Audit actions: `regional.case.routed`, `regional.case.translated`, `regional.case.cross_checked`, `regional.case.committed`.
Role boundaries: regional specialists by language competency; chief/admin can inspect through routed surfaces.

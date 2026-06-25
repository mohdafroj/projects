# Synopsis Design Notes

Screen layout: queue of accepted chief chunks, editor workspace, attribution panel, finalise/export controls.
Components: chunk selector, AI draft action, synopsis editor, attribution list, PDF export status.
Data model: synopsis documents and edits tied to chief consolidations.
State machine: empty -> draft -> submitted -> final.
Audit actions: `synopsis.draft.generate`, `synopsis.draft.save`, `synopsis.draft.submit`, `synopsis.finalise`, `synopsis.pdf.export`.
Role boundaries: synopsis writer role, currently assigned to editorial/admin users for Track A.

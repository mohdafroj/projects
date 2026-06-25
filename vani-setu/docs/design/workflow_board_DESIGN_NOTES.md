# Workflow Board Design Notes

Screen layout: kanban columns by workflow stage with cards per assignment lane.
Components: workflow columns, assignment cards, drag/transition action, return reason.
Data model: existing slot assignments and workflow events.
State machine: reporter -> supervisor -> chief -> js -> sg/director with role-checked transitions where applicable.
Audit actions: `workflow_board.forward`, `workflow_board.return`.
Role boundaries: role-checked server transitions; broad read access for operational roles.

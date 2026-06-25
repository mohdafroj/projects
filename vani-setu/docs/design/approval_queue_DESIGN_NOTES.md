# Approval Queue Design Notes

Screen layout: module filter rail, pending item list, selected item detail with acknowledge/snooze/clear actions.
Components: queue filter, priority marker, detail panel, acknowledge and snooze controls.
Data model: computed pending items plus approval queue action records.
State machine: pending -> acknowledged/snoozed/cleared.
Audit actions: `approval_queue.viewed`, `approval_queue.acknowledged`, `approval_queue.snoozed`, `approval_queue.cleared`.
Role boundaries: each role sees only items awaiting their action.

# Reports Design Notes

Screen layout: analytics filters, summary cards, chart panels, snapshot/export controls.
Components: multidimensional filter bar, chart data table, snapshot button, CSV/PDF export actions.
Data model: report snapshots plus read queries over workflow, users, sittings, and audit data.
State machine: generated view -> snapshot captured -> exported.
Audit actions: `reports.summary.viewed`, `reports.charts.viewed`, `reports.snapshot.captured`, `reports.export.csv`, `reports.export.pdf`.
Role boundaries: chief, JS, SG, and admin access.

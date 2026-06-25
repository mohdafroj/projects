# SG Dashboard Design Notes

Screen layout: SG MIS with date switcher, pipeline summary, ageing buckets, live activity feed, drill-down windows.
Components: date picker, pipeline cards, ageing chart/list, feed, window detail.
Data model: read model over JS windows, SG reviews, handoffs, workflow and audit logs.
State machine: read-only MIS distinct from SG decision tray.
Audit actions: `sg_dash.date_switcher`, `sg_dash.pipeline`, `sg_dash.ageing`, `sg_dash.feed`, `sg_dash.drilldown`, `sg_dash.window`.
Role boundaries: SG-only frontend route; backend routes protected by SG role.

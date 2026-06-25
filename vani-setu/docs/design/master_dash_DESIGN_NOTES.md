# Master Dashboard Design Notes

Screen layout: cross-role landing with sitting overview, pendency cards, roster summary, audit snapshot.
Components: live sitting strip, pendency table, roster role counts, audit feed.
Data model: read model over sittings, slots, assignments, users, audit logs.
State machine: read-only dashboard; no persistent module transition.
Audit actions: `master_dash.overview.view`, `master_dash.pendency.view`, `master_dash.roster.view`.
Role boundaries: available to all authenticated Track A roles with role-aware navigation.

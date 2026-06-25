# Admin Full Design Notes

Screen layout: tabbed admin surface for users/roles, templates, member masters, audit explorer, settings.
Components: user table, role list, sitting/slot templates, member/custom-member lists, audit chain explorer, config toggles.
Data model: admin-owned templates/config/custom-member master plus core users/roles/members/sittings/audit logs.
State machine: CRUD resources are active/inactive where supported; audit chain verification is read-only.
Audit actions: `admin_full.*` for mutating and explorer actions.
Role boundaries: admin-only route and API middleware.

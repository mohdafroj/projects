# Vani Setu UI Smoke Summary

Timestamp: 20260520T051714Z

Base URL: http://localhost:5173
Backend health: http://localhost/api/health

Result: PASS

Validated role walkthroughs:

- Reporter `RPT-001 / reporter123`: dashboard, slot capture, edit, commit.
- Supervisor `SUP-EN-001 / sup123`: queue, lane review, forward to Chief.
- Chief EN `CHF-EN-001 / chief123`: queue, consolidation view, edit, commit EN lane.
- Chief HI `CHF-HI-001 / chief123`: queue, same consolidation view, edit, commit HI lane.
- JS `JS-001 / js123`: queue, window review, suggested edit accept, forward to SG.
- SG `SG-001 / sg123`: tray, window review, open, confirm expunge, DSC stub sign.
- Director `DIR-001 / director123`: inbox, publish job, push, CRC preview.

Screenshots:

- `01-reporter-dashboard.png` through `25-director-crc-preview.png`

Notes:

- The smoke test authenticates with the seeded credentials through `/api/auth/login`, then walks the browser UI for each role.
- The frontend dev proxy was corrected to target the HTTP web container, so `http://localhost:5173/api/health` now reaches Laravel.

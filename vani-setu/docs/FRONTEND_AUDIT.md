# Frontend Audit

Date: 2026-05-20

## Headline Finding

The active live frontend is the Vue app mounted from `/opt/vanisetu/frontend`, served through the `vani-setu-frontend` container and Caddy. Earlier Translator/Speech-to-Speech walkthrough evidence was not live Vue evidence; it came from static prototype/scaffold paths, including `/home/sds-dev/gov/vanisetu/prototype/vanisetu-translator-v2.html` and the ignored `/home/sds-dev/frontend/src/modules` tree.

## App Shell

There is a single Vue app shell:

- Entry point: `/opt/vanisetu/frontend/src/main.ts`
- Root component: `/opt/vanisetu/frontend/src/App.vue`
- Shell component: `/opt/vanisetu/frontend/src/views/AppShell.vue`
- Router: `/opt/vanisetu/frontend/src/router/index.ts`

`main.ts` creates the Vue app, installs Pinia and Vue Router, and mounts to `#app`.

## Router Pattern

The router imports `MODULE_INDEX` from `@/modules/MODULE_INDEX` and uses `import.meta.glob('../modules/*/routes.ts', { eager: true, import: 'default' })` to flatten module routes in index order.

The current module index contains 21 modules:

- `capture`
- `master_dash`
- `supervisor`
- `workflow_board`
- `approval_queue`
- `live_chamber`
- `regional`
- `search`
- `chief`
- `synopsis`
- `formatting`
- `js`
- `sg`
- `sg_dash`
- `director`
- `reports`
- `asr`
- `translator`
- `speech_to_speech`
- `admin`
- `admin_full`

## Routed And Reachable Modules

The live Vue app has route files for all indexed V1 modules:

- Capture: `/capture`
- Master Dashboard: `/master-dash`
- Supervisor: `/supervisor/queue`
- Workflow Board: `/workflow-board`
- Approval Queue: `/approval-queue`
- Live Chamber: `/live-chamber`
- Regional Workflow: `/regional`
- Search: `/search`
- Chief: `/chief/queue`
- Synopsis Studio: `/synopsis`
- Formatting Studio: `/formatting`
- JS: `/js/queue`
- SG Decision Tray: `/sg/tray`
- SG Dashboard MIS: `/sg/dash`
- Director: `/director/inbox`
- Reports and Analytics: `/reports`
- ASR status: `/admin/asr-status`
- Translator: `/translator/queue`
- Speech-to-Speech placeholder: `/s2s`
- Admin system health: `/admin/system-health`
- Admin Full: `/admin-full`

Live Playwright evidence was generated at `/home/sds-dev/evidence/live-integration-20260520-133151/report.json` with screenshots for 52 role/module checks. The run passed against `https://vanisetu.rajyasabha.digital` when Chromium pinned `vanisetu.rajyasabha.digital` to `127.0.0.1`, which exercises local Caddy and the mounted Vue app.

## Scaffolded But Not Routed

No V1 Track A module remains scaffolded-but-unrouted in the active Vue app. Translator and Speech-to-Speech were previously present only in the ignored `/home/sds-dev/frontend/src/modules` scaffold; they are now in `/opt/vanisetu/frontend/src/modules` and included in `MODULE_INDEX.ts`.

## Public URL Caveat

Unpinned DNS for `https://vanisetu.rajyasabha.digital` in this environment returns a static Vani Setu landing page with no Vue `#app`. Pinned local Caddy resolution returns the Vue app. This network/routing split must be fixed before external UAT can use ordinary DNS.

## Recommended Fix Path

1. Network team updates public DNS/load-balancer routing so unpinned `https://vanisetu.rajyasabha.digital` reaches `vani-setu-caddy`.
2. Keep host-pinned live integration tests for this local validation environment until DNS is corrected.
3. Start Track B Parliamentary Committees as the next sprint after the routing handoff.

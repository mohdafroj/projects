# Phase 3 admin handoff — ready-to-send draft

**Status:** Drafted 2026-05-23. MCP Gmail send was blocked by Cloudflare from the NIC IP; this is the manual fallback. Copy-paste into Gmail or Slack DM to the .132 admin.

**To:** _.132 admin (fill in)_
**Subject:** Phase 3 (.17 → .132) — preflight runbook ready; 3 blockers need .132-side decisions

---

Hi,

Phase 1 (DNS cutover on .17) and Phase 2 (observability retirement) are complete. We're ready to start Phase 3 — moving Vani Setu / UAT / Reporting / Fake-Parichay runtimes from .17 Docker Compose to .132 Kubernetes — but three decisions are needed from the .132 side before execution.

## Three blockers

**1) StorageClass on .132 K3s**
Every PVC-bearing chart needs one. `kubectl get sc` to list; pick `local-path` (single-node, simpler) or `longhorn` (HA). Mark as default, or pass `--set persistence.storageClass=<name>` to each helm install.

**2) Apply the missing Prometheus IngressRoute on .132**
`prometheus.sds.local` currently returns Traefik 404 — no IngressRoute matches that host. Runbook §2.1 has a template IngressRoute YAML; adjust the backend Service name to whatever Prometheus actually deploys as on .132, then `kubectl apply`.

**3) Platform Postgres reachability for Vani**
Vani Laravel chart values default `DB_HOST` to `vani-postgres.vani-laravel.svc.cluster.local`. Confirm whether (a) a sibling Postgres chart needs deploying, or (b) values should repoint at an existing platform Postgres on .132. Latter preferred if available.

## Artefacts (on .17 at `/home/sds-dev`)

- Migration plan: `docs/MIGRATION_PHASE3_PLAN.md` (701 lines, ~7k words)
- Preflight runbook: `docs/PHASE3_PREFLIGHT_RUNBOOK.md` (4980 words, 821 lines)
- Helm charts:
  - Cluster A (Vani Laravel): `sds-monorepo/deploy/vani-setu-laravel/helm/vani-setu-laravel/`
  - Cluster C (Reporting): `sds-monorepo/deploy/sds-reporting/helm/sds-reporting/`
  - Cluster B (UAT): reuses A's chart with `values-uat.yaml` overlay
  - Cluster D (fake-parichay retirement): no chart — env repoint + container stop on .17
- Migration scripts (dry-run by default): `scripts/migration/phase3/` (14 scripts, 1612 lines)

## Execution order once you sign off

**A → C → B → D.**

## CI guard (OQ-9, must land BEFORE Cluster A)

`.gitlab-ci.yml`'s `deploy:prod` job runs `docker compose up` on .17 via the shell-runner tagged `vani-prod-deploy`. Until it's gated, the next CI run resurrects the retired stack. Runbook §7 has both diff options — gate `when: never`, or rewrite to `helm upgrade`. I'll land this on my side.

## Already resolved during planning

- ADR-VANI-5 MinIO buckets (`vani-audio-raw-rs`, `vani-ai-drafts-rs`, `vani-voiceprints-rs`, `vani-pilot-audio-rs`) verified to exist on `minio.sds.local` (probes returned 403 = exists; non-existent returns 404). No `mc mb` needed.
- `/etc/hosts` cutover on .17 covers 16 shared hostnames; backup at `/etc/hosts.bak.phase1-cutover-20260523`.
- 8 local observability containers durably removed; docker volumes preserved for rollback.

## Next steps

Once you have (a) picked a StorageClass, (b) applied the Prometheus IngressRoute, (c) answered the platform-Postgres question — I'll pause Codex on .17, gate the CI deploy job, sanity-run the dry-run scripts, and start Cluster A. Estimated per-cluster downtime is in runbook §6.

Anything else you'd like reviewed before greenlight, just shout.

Thanks,
Kushal

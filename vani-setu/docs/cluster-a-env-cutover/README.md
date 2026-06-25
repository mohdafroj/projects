# Cluster A connection-string cutover — reviewable patch set

**Audience:** Software Manager, RS SDS (Kushal Pathak)
**Author:** Claude (Opus 4.7, 1M ctx) — bootstrap host /home/sds-dev
**Date:** 2026-05-23
**Status:** DRAFT — **patches NOT applied**, review required before `patch -p1`

---

## Why this exists

Cluster A (Vani Laravel) is migrating off the `.17` Docker Compose stack onto
the `.132` Kubernetes cluster. The Helm chart at
`/home/sds-dev/sds-monorepo/deploy/vani-setu-laravel/helm/vani-setu-laravel/values.yaml`
provides the new in-cluster DNS targets:

| Service     | Old (compose-local)                | New (K8s in-cluster DNS)                                       |
|-------------|------------------------------------|----------------------------------------------------------------|
| Postgres    | `postgres`                         | `vani-postgres.vani-laravel.svc.cluster.local`                 |
| Redis       | `redis`                            | `vani-redis.vani-laravel.svc.cluster.local`                    |
| MinIO       | `minio` / `http://minio:9000`      | `https://minio.sds.local` (via Phase-1 `/etc/hosts`)           |
| ML Gateway  | `ml-gateway` / `http://ml-gateway:8000` | `http://vani-ml-gateway.vani-system.svc.cluster.local:8000` |

Once Cluster A is fully on `.132`, the `.17`-side env files become irrelevant
(those containers no longer run on `.17`). **These patches document the diff
for the cutover transition window** — when humans may need to run dev tooling
(artisan, tinker, queue replay, etc.) on `.17` pointed at the new `.132`
services.

**Do NOT apply blindly.** Review every patch with Kushal before any rewrite,
and confirm the open questions below.

---

## Patches authored

| File                       | Size  | Target file               | Keys rewritten                                                                  |
|----------------------------|-------|---------------------------|---------------------------------------------------------------------------------|
| `root-env.patch`           | 478 B | `./.env`                  | `DB_HOST`, `REDIS_HOST`                                                          |
| `root-env-example.patch`   | 494 B | `./.env.example`          | `DB_HOST`, `REDIS_HOST`                                                          |
| `src-env.patch`            | 757 B | `./src/.env`              | `DB_HOST`, `REDIS_HOST`, `SARVAM_VOICE_PIPELINE_URL` (ml-gateway hostname swap)  |
| `src-env-example.patch`    | 1.3 KB| `./src/.env.example`      | `DB_HOST`, `REDIS_HOST`, `SYNOPSIS_MODEL_URL`, `SYNOPSIS_MODEL_ALLOWED_HOSTS`, `SARVAM_VOICE_PIPELINE_URL` |
| `docker-compose.patch`     | 683 B | `./docker-compose.yml`    | `x-app-environment.DB_HOST`, `x-app-environment.REDIS_HOST` (anchor block only)  |

No patch was authored for `docker-compose.uat.yml` — see open Q3.
No patch was authored for `docker-compose.prod.yml` — it carries no inline
connection strings (image overrides only).
No patch was authored for `ml-gateway/.env` — file holds Sarvam API keys only,
no DB/Redis/MinIO references.

---

## Apply commands

Apply from the repo root (`/home/sds-dev`). All patches use `-p1` prefixes.

```bash
cd /home/sds-dev

# Dry-run first (recommended — confirms each patch will apply cleanly)
patch --dry-run -p1 < docs/cluster-a-env-cutover/root-env.patch
patch --dry-run -p1 < docs/cluster-a-env-cutover/root-env-example.patch
patch --dry-run -p1 < docs/cluster-a-env-cutover/src-env.patch
patch --dry-run -p1 < docs/cluster-a-env-cutover/src-env-example.patch
patch --dry-run -p1 < docs/cluster-a-env-cutover/docker-compose.patch

# Apply for real once dry-run succeeds
patch -p1 < docs/cluster-a-env-cutover/root-env.patch
patch -p1 < docs/cluster-a-env-cutover/root-env-example.patch
patch -p1 < docs/cluster-a-env-cutover/src-env.patch
patch -p1 < docs/cluster-a-env-cutover/src-env-example.patch
patch -p1 < docs/cluster-a-env-cutover/docker-compose.patch
```

---

## Order of application

No hard ordering requirement — the patches touch disjoint files. Suggested
order for least surprise (config-files first, compose last):

1. `root-env.patch` and `root-env-example.patch`
2. `src-env.patch` and `src-env-example.patch`
3. `docker-compose.patch`

If you intend to actually start the `.17` compose stack against the `.132`
services, you must **stop** the local `postgres` and `redis` compose services
first, otherwise the new hostnames will fail DNS resolution at compose-level
(K8s `*.svc.cluster.local` names won't resolve from `.17` unless you have a
CoreDNS forwarder or `/etc/hosts` shim — see open Q4).

---

## What is intentionally NOT changed

Per the spec, the following are public-facing identifiers or open questions
and are **deliberately left alone**:

- `REVERB_HOST=vanisetu.rajyasabha.digital` — public WebSocket ingress.
- `APP_URL=https://vanisetu.rajyasabha.digital` — public URL.
- `SESSION_DOMAIN`, `CORS_ALLOWED_ORIGINS`, `SANCTUM_STATEFUL_DOMAINS` — public-cookie scoping.
- `MEMCACHED_HOST=127.0.0.1` — Laravel default, no real memcached in stack.
- `MAIL_HOST=127.0.0.1` (`src/.env`) — see open Q1.
- `MEILISEARCH_HOST=http://meilisearch:7700` (`docker-compose.yml` anchor) — see open Q2.
- `VANI_MINIO_ENDPOINT=http://10.43.52.176:9000` (`/.env`) — numeric ClusterIP, NOT the compose-local name `minio`. See open Q5.
- `VANI_MINIO_ENDPOINT=minio.data.svc.cluster.local:9000` (`src/.env.example`) — already a K8s name (different namespace). See open Q5.
- `TIJORI_ASR_URL=http://tijori-router.tijori-system.svc.cluster.local/v1/asr` (`src/.env.example`) — already a K8s name, points at Tijori (Cluster T, not Cluster A).
- `SUPERSET_INTERNAL_URL=http://sds-reporting-superset:8088` — Garuda/Superset, outside Cluster A scope.

---

## Top 3 open questions (need Kushal's input before any rollout)

These are connection strings I could **NOT** find a clean `.132` target for —
the Helm `values.yaml` doesn't define services for them, and they need a
decision before the cutover transition window is meaningful.

### Q1. Mail relay on `.132`

`src/.env` line 67: `MAIL_HOST=127.0.0.1` with `MAIL_MAILER=log`.

There is no `mail-relay` / `postfix` / `smtp` service in the Vani Helm chart.
Three options:
- **(a)** Keep `MAIL_MAILER=log` — accept that outbound mail is logged-only in
  Cluster A. OK for the transition window if SMS-gov is the live channel.
- **(b)** Point at a Common Core mail relay service if one exists on `.132`
  (none surveyed yet).
- **(c)** Wire to NIC SMTP gateway directly (sovereignty-clean, but creds
  unknown).

**Recommendation:** option (a) for the cutover window, revisit once Common
Core mail-relay lands.

### Q2. Meilisearch + MongoDB on `.132`

- `docker-compose.yml` x-app-environment anchor: `MEILISEARCH_HOST=http://meilisearch:7700`
- `MEILISEARCH_KEY` is required (compose fails to start without it).
- No `meilisearch` service appears in `vani-setu-laravel/helm/values.yaml`.
- No `MONGO_HOST=mongodb` found in any of the surveyed env/compose files on
  `.17`, so the original spec's mongo line item is moot here.

The `src/Modules/Search/Services/SearchIndexer.php` and
`Providers/SearchServiceProvider.php` modifications in current branch suggest
Meilisearch is still wired in code. **If Meilisearch is not part of Cluster A
on `.132`, this is a functional gap that blocks reporter search.**

**Recommendation:** confirm with platform team whether Meilisearch will be
sidecar-deployed into the `vani-laravel` namespace, hosted as a shared
search-tier service in `common-core-system`, or replaced. The patch set
deliberately leaves the line alone so the app fails-loud rather than
silently-wrong.

### Q3. UAT compose file (`docker-compose.uat.yml`) target namespace

The UAT stack uses compose-local names `uat-postgres`, `uat-redis`,
`uat-meilisearch`. These are technically compose-local but the spec only
defines a `vani-laravel` namespace for prod on `.132`.

Options:
- **(a)** Leave UAT entirely on `.17` compose during the transition (likely
  intent — UAT validates the OLD path before cutover).
- **(b)** Carve a `vani-laravel-uat` namespace on `.132` and produce a
  parallel patch.

**Recommendation:** option (a). UAT remains on `.17` compose until Cluster A
prod is green on `.132`, then UAT migrates as its own follow-up. No UAT patch
authored in this set.

---

## Other notes Kushal should be aware of

### Q4. DNS resolution from `.17` after cutover

Once `DB_HOST=vani-postgres.vani-laravel.svc.cluster.local`, the `.17` host
needs to resolve that name. The K8s in-cluster DNS (CoreDNS) does **not**
respond on `.17`. You'll need one of:
- A CoreDNS forwarder + `/etc/resolv.conf` route on `.17` pointing at the
  `.132` cluster DNS.
- Static `/etc/hosts` entries on `.17` mapping the four `*.svc.cluster.local`
  names to the appropriate NodePort / ClusterIP-via-MetalLB / Ingress.

This is Phase-1-style hosts file work (the same pattern as `minio.sds.local`
already in place). Flag for platform team — without this, the patched env
files render the `.17` host non-functional against the new services.

### Q5. MinIO endpoint already migrated

`/.env` has `VANI_MINIO_ENDPOINT=http://10.43.52.176:9000` (ClusterIP form)
and `src/.env.example` has `VANI_MINIO_ENDPOINT=minio.data.svc.cluster.local:9000`
(K8s name form). Neither is the legacy `http://minio:9000` compose-local
name, so neither was rewritten. **However**, both are inconsistent with the
spec's `https://minio.sds.local` (Phase-1 /etc/hosts) target and with each
other. Worth a follow-up clean-up patch, but out of scope for the spec as
literally written ("only when value is the current compose-local name").

### Q6. `docker-compose.uat.yml` UAT-specific compose-local names

If decision Q3(b) is taken, the same sed rewrites would apply with
`uat-postgres` → `vani-postgres.vani-laravel-uat.svc.cluster.local` etc.
Patch can be regenerated on request.

---

## Sign-off

- [ ] Software Manager reviewed open Qs 1-3 above and confirmed cutover target
      decisions.
- [ ] Platform team confirmed DNS shim on `.17` (Q4) is in place OR the `.17`
      stack is shut down at cutover.
- [ ] Dry-run `patch --dry-run -p1` succeeded for all five patches.
- [ ] Patches applied (record commit SHA here): `__________`

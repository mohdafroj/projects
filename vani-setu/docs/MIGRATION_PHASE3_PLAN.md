# SDS Phase 3 Migration Plan — Compose on .17 → Kubernetes on .132

| Field | Value |
| --- | --- |
| Document ID | SDS-DEV-MIG-PHASE3 |
| Title | Phase 3 Migration Plan — Docker Compose runtimes on 10.21.217.17 to K3s on 10.21.217.132 |
| Date | 2026-05-23 |
| Author | Claude Code, on behalf of Kushal Pathak (Software Manager, Rajya Sabha SDS) |
| Audience | Software Manager; sub-agent that will execute Phase 3 against the main tree; .132 platform admin (executes the .132-side steps) |
| Status | DRAFT — awaiting Software Manager sign-off; the executing session must not start until this DRAFT is flipped to APPROVED |
| Plan boundary | **Plan only.** No state-changing operations performed in the producing session. A separate session executes the cutover. |
| Prior phases | Phase 1 (DNS cutover) CLOSED 2026-05-23 — see `/etc/hosts` + `/etc/hosts.bak.phase1-cutover-20260523`. Phase 2 (observability retired) CLOSED — 8 containers `docker rm`'d; `docker-compose.monitoring.yml` not yet hardened. |

---

## Executive summary

Phase 3 lifts four service clusters off the `/home/sds-dev` Docker Compose stack on dev host **10.21.217.17** and rehomes their stable runtimes on the shared K3s platform at **10.21.217.132**: (A) the Vani Setu stable backend (`app`/`worker`/`audit`/`reverb`/`web`/caddy edge), (B) the UAT Vani stack (`uat-app`/`uat-worker`/`uat-reverb`/`uat-web`/`uat-ml-gateway`), (C) the SDS Reporting Engine (Superset, Celery worker/beat, Gotenberg, the report-pipeline FastAPI), and (D) retirement of the .17 `sds-fake-parichay` copy in favour of the already-deployed .132 instance. Vani frontend hot reload and the full Tijori AI workbench stay on .17; the .132 Prometheus IngressRoute and SSH-from-.17 are pre-flight blockers we surface but do not solve here. For Cluster A the Software Manager has pre-approved a **transitional Laravel-monolith Helm chart** (`vani-setu-laravel`) that hosts the existing PHP runtime as-is on .132, with a documented handoff seam to the future Stage-6 native chart already drafted at `~/sds-monorepo/deploy/vani-setu/helm/vani-setu/`. All four clusters retain MinIO-only object storage and the Rajya-Sabha-only application-layer scope.

---

## Change log

| Rev | Date | Author | Notes |
| --- | --- | --- | --- |
| 0.1 | 2026-05-23 | Claude Code | Initial DRAFT. Discovery completed read-only on .17. .132-side inventory limited to ingress-edge probes; internal K3s state listed as UNVERIFIED throughout. |
| 0.2 | 2026-05-25 | Claude Code | Dedraft pass: (a) drop MongoDB from Cluster A & B scope — evidence: `vani-setu-mongodb` is orphan (not in any `docker-compose*.yml` service block; `restart: no`; zero references in `src/` Eloquent models — S2S tables `s2s_sessions`/`s2s_segments`/`s2s_outputs` live in Postgres on the default `pgsql` connection); ml-gateway has an optional `MongoArtifactStore` but `mongodb_trace_writes_enabled` defaults to `False` with no env override anywhere. OQ-7 resolved. (b) Record UAT-stack `restart: no` posture: all 11 UAT containers Exited(255) on the last host reboot; this is intentional (UAT is brought up only for acceptance windows); operator must `docker compose -f docker-compose.uat.yml up -d uat-postgres` before script `20-uat-pgdump.sh` will succeed. (c) Resolve OQ-3 with retire recommendation for `sds-dev-uat-frontend-1` (UAT stack restart:no + not resurrected since reboot = signal of inactive dev). (d) Probe OQ-1 from .17 confirmed unreachable on all candidate ports/hostnames; the .132 admin must still settle it. |

---

## 1. Scope, constraints, and ground truth captured during discovery

### 1.1 In-scope clusters

| Cluster | Containers in scope | Source compose file |
| --- | --- | --- |
| **A — Vani Setu stable** | `vani-setu-app`, `vani-setu-worker`, `vani-setu-audit`, `vani-setu-reverb`, `vani-setu-web`, `vani-setu-caddy`, supporting `vani-setu-postgres`, `vani-setu-redis`, `vani-setu-meilisearch`, `vani-setu-minio`, `vani-setu-ml-gateway`, `vani-setu-realtime-sidecar` | `/home/sds-dev/docker-compose.yml` |
| **B — UAT Vani** | `sds-dev-uat-app-1`, `sds-dev-uat-worker-1`, `sds-dev-uat-reverb-1`, `sds-dev-uat-web-1`, `sds-dev-uat-ml-gateway-1`, supporting `sds-dev-uat-postgres-1`, `sds-dev-uat-redis-1`, `sds-dev-uat-meilisearch-1`, `sds-dev-uat-realtime-sidecar-1`, `sds-dev-uat-caddy-1` | `/home/sds-dev/docker-compose.uat.yml` |
| **C — Reporting Engine** | `sds-reporting-superset`, `sds-reporting-worker`, `sds-reporting-beat`, `sds-reporting-gotenberg`, `sds-reporting-pipeline`, supporting `sds-reporting-postgres`, `sds-reporting-redis`, one-shot `sds-reporting-init` | `/home/sds-dev/sds-reporting-engine/docker-compose.yml` |
| **D — Fake Parichay (.17 retirement)** | `sds-fake-parichay` (the .17 copy only; .132 instance already running per directive) | `/home/sds-dev/sds-fake-parichay/docker-compose.yml` |

### 1.2 Out of scope (do **not** migrate)

- Vani frontend dev server `vani-setu-frontend` (Vite hot reload) — stays on .17 by directive.
- UAT frontend `sds-dev-uat-frontend-1` — stays on .17 if actively developed, else may be stopped (see OQ-3).
- Tijori AI workbench (`tijori-dev-vllm`, `tijori-dev-ollama`, `tijori-dev-whisper`, `tijori-dev-ocr-service`, `tijori-dev-indictrans2`, `tijori-dev-triton`, `tijori-dev-minio`, `tijori-dev-postgres`, `tijori-ai-litellm`, `tijori-ai-langfuse`, `tijori-ai-langfuse-db`, `tijori-ai-open-webui`, `tijori-ai-mlflow`, `tijori-ai-flowise`) — explicitly excluded.
- `vani-registry`, `sanchalan-{postgres,redis,rabbitmq}-dev`, `vani-setu-ops-remediator`, `vani-setu-{node,blackbox}-exporter` — not in directive; stay on .17.
- Local-only K3s control plane on .17 — orphaned; retirement is a separate Phase.

### 1.3 Hard constraints honoured by this plan

- **No SSH from .17 → .132** (verified: `ssh sds-dev@10.21.217.132` fails with `publickey,password`). Every `.132-side action below is enumerated as "executed by .132 admin"`; never as automation from .17.
- **`kubectl` on .17 binds only to the .17 K3s control plane** — usable for read-only inspection of orphaned .17 cluster only.
- **MinIO is the only object storage allowed** — no AWS S3 / Azure Blob / GCS. `AWS_*` env keys in `src/.env` lines 96–100 are MinIO-endpoint shims and stay MinIO.
- **Rajya-Sabha-only at the application layer** — no `lok-sabha-*` namespaces, buckets, secrets, or clients in any artefact authored under this plan.
- **.132 Prometheus IngressRoute is currently broken** (probe `https://prometheus.sds.local/` → Traefik 404). Any service that scrapes or pushes metrics into .132 Prometheus is blocked until that ingress is fixed → tracked as PRE-1.
- **Reuse the existing GitLab CI image pipeline** at `/home/sds-dev/.gitlab-ci.yml` (commits `89869fc`, `a4fa3ab`) — it already builds to `registry.gitlab.sds.local/vani/setu/{app,web,ml-gateway,realtime-sidecar}` via kaniko. The deploy stage (shell-runner tag `vani-prod-deploy`, runs `docker compose pull && up -d`) is what changes; the build stage does not.
- **Sarvam Saarika v2 is the only paid Sarvam endpoint** — `SARVAM_API_KEY` keeps its current scope; no new Sarvam product introduced by this migration.

### 1.4 Compose-file inventory under `/home/sds-dev/`

| File | Role | Touched by Phase 3? |
| --- | --- | --- |
| `docker-compose.yml` | Vani stable stack (Cluster A) | Yes — Cluster A services move; `vani-setu-frontend` block kept |
| `docker-compose.uat.yml` | UAT stack (Cluster B) | Yes — five app services move; `uat-frontend`/`uat-caddy` decisions per OQ-3 |
| `docker-compose.prod.yml` | Prod overlay used by CI `deploy:prod` | Touched — overlay must align with the post-migration compose-file (Cluster A backend removed) |
| `docker-compose.monitoring.yml` | Phase-2 observability (now `docker rm`'d but file resurrects on `up`) | Touched — harden during Phase 3 (delete or comment-out) so a re-`up` cannot resurrect retired containers |
| `docker-compose.rtsearch.yml` | Realtime search (Meili) overlay | Reviewed; not in scope unless OQ-5 |
| `docker-compose.mlgw.yml` | ml-gateway split overlay | Reviewed; folded into Cluster A move |
| `docker-compose.vault.yml` | Vault local | Out of scope (already on .132 substrate per memory) |
| `sds-reporting-engine/docker-compose.yml` | Cluster C source | Yes |
| `sds-fake-parichay/docker-compose.yml` | Cluster D source (.17 copy retired) | Yes (stop, not move) |

### 1.5 Persistent-data ground truth (sizes captured live)

| Volume | Container source | Live size |
| --- | --- | --- |
| `sds-dev_postgres_data` | `vani-setu-postgres` (db `vani_setu`) | **18 MB** (`pg_database_size`) |
| `sds-dev_postgres_uat_data` | `sds-dev-uat-postgres-1` (db `vani_setu_uat`) | **12 MB** |
| `sds-reporting_superset_db_data` | `sds-reporting-postgres` (metadata DB) | **11 MB** |
| `sds-dev_minio-data` | `vani-setu-minio` | **424 KB** (essentially empty — buckets configured but corpus tiny) |
| `sds-dev_meilisearch-data`, `sds-dev_meilisearch-uat-data` | Meili (stable + UAT) | small (rebuildable from Postgres via `scout:import`) |
| `sds-dev_mongodb-data`, `sds-dev_mongodb-uat-data` | Mongo (orphan; **not in any compose service block**) | residual on disk — leave in place on .17, do not migrate |
| `sds-dev_redis-data`, `sds-dev_redis-uat-data`, `sds-reporting_superset_redis_data` | Redis | ephemeral (queues + cache) |

All datasets are small enough that the migration window is dominated by **service-restart + DNS-cutover latency**, not by data transfer time.

### 1.6 Existing Helm-chart inventory under `/home/sds-dev/`

| Path | Status | Use in Phase 3 |
| --- | --- | --- |
| `~/sds-monorepo/deploy/vani-setu/helm/vani-setu/` (Chart v0.2.1-dev.5, appVersion 0.2.1.dev5) | Stage-6 native FastAPI chart — assumes Vault Agent, Linkerd mesh, in-cluster MinIO `minio.data:9000`, Keycloak `svc-vani`, RabbitMQ `vani-rabbitmq-app`. **Not the live PHP runtime.** | NOT used directly — handoff target. The transitional Laravel chart below feeds it the seam contract. |
| `~/sds-monorepo/deploy/vani-setu/manifests/{host-substrate-services,minio-buckets,postgres-dev}.yaml` | Substrate stubs | Reference only |
| `~/sds-monorepo/deploy/vani-setu/deployments/{dev,prod,staging,uat}/` | Stage-3/4 ratification log (`audit-log.jsonl` + dated yamls) | Reference only |
| `~/sds-tijori-setu/helm/tijori-setu/` | Tijori chart | Reference only — Vani consumes Tijori router URL post-migration |
| Reporting / UAT / fake-parichay | No Helm chart exists | **Author in Phase 3 execution** |

### 1.7 .132-side probes (from .17, via shared `/etc/hosts` resolution)

| Probe | Result | Interpretation |
| --- | --- | --- |
| `curl -k https://vanisetu.rajyasabha.digital/healthz` | **503** | IngressRoute wired on .132 Traefik; backend pod missing/unhealthy. Cutover window for Cluster A is "swap the upstream", not "create new ingress". |
| `curl -k https://tijorisetu.rajyasabha.digital/` | 307 | Tijori live on .132 |
| `curl -k https://prometheus.sds.local/` | 404 (Traefik) | **PRE-1 blocker** — ingress broken |
| `curl -k https://10.21.217.132:18443/health` | conn refused (000) | Port 18443 not reachable from .17; verify with .132 admin (OQ-1). Memory says fake-parichay is at this port; conflict surfaced. |

---

## 2. Cluster A — Vani Setu stable runtime

### 2.1 Current state on .17

**Containers in scope (running images all `vani-setu-app:local` PHP-FPM 8.3 except where noted):**

| Container | Image | Role | Command |
| --- | --- | --- | --- |
| `vani-setu-app` | `vani-setu-app:local` | PHP-FPM | `php-fpm` (Dockerfile default; `/home/sds-dev/docker/app.Dockerfile`) |
| `vani-setu-worker` | `vani-setu-app:local` | Horizon queue worker | `php artisan horizon` |
| `vani-setu-audit` | `vani-setu-app:local` | Audit queue worker | `php artisan queue:work redis --queue=audit --tries=1 --timeout=30 --sleep=1` |
| `vani-setu-reverb` | `vani-setu-app:local` | Reverb WebSocket | `php artisan reverb:start --host=0.0.0.0` (port 8080) |
| `vani-setu-web` | `vani-setu-web:local` | nginx in front of php-fpm | nginx default (`/home/sds-dev/docker/web.Dockerfile`, `/home/sds-dev/docker/nginx.conf`) |
| `vani-setu-caddy` | `caddy:2-alpine` | Edge TLS + static + proxy | mounts `caddy/Caddyfile.private` |

Supporting (not in directive but must follow): `vani-setu-postgres`, `vani-setu-redis`, `vani-setu-meilisearch`, `vani-setu-minio`, `vani-setu-ml-gateway`, `vani-setu-realtime-sidecar`. (`vani-setu-mongodb` is an orphan container from a prior compose iteration — `restart: no`, not referenced by any service block — and is **not** carried into the .132 chart.)

**Mounted volumes (from `docker inspect`):**

| Container | Host source | Mount target |
| --- | --- | --- |
| `vani-setu-app` / `-worker` / `-audit` / `-reverb` | `/home/sds-dev/src` | `/var/www/html` (RW) |
| same | `/home/sds-dev/sds-common-core-php` | `/var/www/sds-common-core-php` (RO) |
| `vani-setu-web` | `/home/sds-dev/src` | `/var/www/html` (RO) |
| `vani-setu-postgres` | `/var/lib/docker/volumes/sds-dev_postgres_data/_data` | `/var/lib/postgresql/data` |
| `vani-setu-minio` | `/var/lib/docker/volumes/sds-dev_minio-data/_data` | `/data` |
| `vani-setu-meilisearch` | `/var/lib/docker/volumes/sds-dev_meilisearch-data/_data` | `/meili_data` |
| `vani-setu-caddy` | `caddy_data`, `caddy_config`, `caddy/Caddyfile.private`, `caddy/certs`, `/opt/vanisetu/frontend/dist` | `/data`, `/config`, `/etc/caddy/Caddyfile`, `/etc/caddy/certs`, `/srv/vanisetu-frontend` |

**Environment keys (from `docker-compose.yml` lines 1–32; secret values redacted):**

`APP_ENV`, `APP_DEBUG`, `APP_KEY [REDACTED — source: src/.env line 3]`, `APP_URL` (`https://vanisetu.rajyasabha.digital`), `TRUSTED_PROXIES` (`10.42.0.0/16,172.16.0.0/12,127.0.0.1`), `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE`, `CORS_ALLOWED_ORIGINS`, `ASR_INGEST_SECRET [REDACTED — src/.env]`, `REALTIME_AUDIT_SECRET [REDACTED — src/.env]`, `DB_CONNECTION=pgsql`, `DB_HOST=postgres`, `DB_PORT=5432`, `DB_DATABASE=vani_setu`, `DB_USERNAME=vani`, `DB_PASSWORD [REDACTED — docker-compose.yml line 17 + src/.env line 29]`, `REDIS_HOST=redis`, `REDIS_PORT=6379`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=file`, `BROADCAST_CONNECTION=reverb`, `REVERB_APP_ID`, `REVERB_APP_KEY [REDACTED]`, `REVERB_APP_SECRET [REDACTED]`, `REVERB_HOST=vanisetu.rajyasabha.digital`, `REVERB_PORT=443`, `REVERB_SCHEME=https`, `REVERB_SERVER_PORT=8080`, `MEILISEARCH_HOST=http://meilisearch:7700`, `MEILISEARCH_KEY [REDACTED — MEILI_MASTER_KEY in src/.env]`.

Additional `src/.env` keys (lines 1–129) consumed by the app: `BOOTSTRAP_ADMIN_{EMAIL,PASSWORD,EMPLOYEE_ID}` (31–33), `SANCTUM_*` (46–47, 63), `MAIL_*` (65–77), `SMS_GOV_*` (84–94), `AWS_*` (96–100, MinIO-shim — endpoint TBD per OQ-4), `SARVAM_*` (127–129).

**`depends_on` chain:** `caddy` → `web` + `frontend` + `ml-gateway` + `realtime-sidecar`; `web` → `app`; `app` → `postgres` + `redis`; `worker`/`audit`/`reverb` → `app` + `postgres` + `redis`. Reverb listens on container port 8080; the public path is `wss://vanisetu.rajyasabha.digital/app/*` proxied by Caddy.

**Network:** `vani-setu` bridge (named on host as `sds-dev_vani-setu`); the Reporting Engine joins as `external` (line 215 of reporting compose) to talk to Vani's Postgres.

### 2.2 Target state on .132

| Resource | Spec |
| --- | --- |
| Namespace | `vani-laravel` (new; distinct from the future `vani-system` reserved by the Stage-6 native chart) |
| Deployments | `vani-app` (php-fpm, `replicas: 2`), `vani-web` (nginx, `replicas: 2`), `vani-worker` (Horizon, `replicas: 1`), `vani-audit` (queue worker, `replicas: 1`), `vani-reverb` (WebSocket, `replicas: 1`, sticky session via Service `sessionAffinity: ClientIP`) |
| StatefulSets | `vani-postgres` (Bitnami `postgresql:16-alpine`, `replicas: 1`, PVC 5 Gi), `vani-redis` (`replicas: 1`, PVC 1 Gi), `vani-meilisearch` (`replicas: 1`, PVC 2 Gi) — **no Mongo StatefulSet** (orphan in compose; unused at runtime) |
| Services | `vani-app:9000` (ClusterIP), `vani-web:80`, `vani-reverb:8080`, supporting services for Postgres/Redis/Meili/Mongo |
| IngressRoute (Traefik CRD) | Reuse the existing `vanisetu.rajyasabha.digital` IngressRoute on .132 (already wired, currently returning 503 to the missing backend). The cutover swaps its upstream Service from whatever it points to today → `vani-web:80`. Add a second IngressRoute (or middleware) for `/app/*` and `/apps/*` → `vani-reverb:8080` with `enable-websocket: true`. |
| Storage | StorageClass per OQ-2. Postgres/Mongo/Meili each get a PVC. Sustained on local-path SC for dev or longhorn for HA — to confirm. |
| Secrets | `vani-app-secrets` (APP_KEY, ASR_INGEST_SECRET, REALTIME_AUDIT_SECRET, REVERB_APP_KEY/SECRET, DB_PASSWORD, MEILI_MASTER_KEY, SARVAM_API_KEY, BOOTSTRAP_ADMIN_PASSWORD, SMTP_PASSWORD); `vani-minio-app` (access-key/secret-key); `vani-mail-app`; `vani-sms-gov` |
| ConfigMaps | `vani-app-env` (non-secret APP_URL, APP_ENV, TRUSTED_PROXIES, REVERB_HOST etc.); `vani-nginx-conf` (mounts the existing `docker/nginx.conf` content) |
| Migrations | Helm `Job` that runs `php artisan migrate --force` on each chart install/upgrade with `helm.sh/hook: post-install,post-upgrade` |
| ServiceAccount | `vani-laravel-sa` — minimal; no SPIFFE/Linkerd injection in the transitional chart (deferred to Stage-6 native chart) |

### 2.3 Data migration

| Dataset | Approach | Estimated downtime |
| --- | --- | --- |
| Postgres `vani_setu` (18 MB) | `docker exec vani-setu-postgres pg_dump -Fc -U vani vani_setu > /home/sds-dev/migration-artifacts/vani_setu.dump`, then scp/manually transfer to .132 admin (since no SSH); .132 admin pipes into `kubectl exec -i vani-postgres-0 -n vani-laravel -- pg_restore -d vani_setu -U vani -j 2 --clean --if-exists` | **~3 min** (dump+restore are I/O-trivial at 18 MB; bottleneck is human handoff of dump file) |
| MinIO `sds-dev_minio-data` (424 KB) | Stop writes; `docker exec vani-setu-minio mc mirror --overwrite local/ /tmp/minio-export/`; tar; hand off; .132 admin `mc mirror` into platform MinIO at `minio.sds.local`. **Confirm bucket names match Rajya-Sabha-only naming** (OQ-6) | **~2 min** |
| Meilisearch index | Rebuild from source on .132 via `php artisan scout:flush && php artisan scout:import` post-migration; do NOT migrate the binary index | **~5 min** (rebuild) |
| MongoDB | **Not in scope** — orphan container, no application data (S2S tables live in Postgres). Volume `sds-dev_mongodb-data` stays on .17 for archaeological retention only. | n/a |
| Redis | Do **not** migrate — queues drain first; sessions are file-based per env (`SESSION_DRIVER=file`); rate-limit counters and cache rebuild | n/a |

**Pre-migration write-freeze.** Drain Horizon and the audit queue on .17 before `pg_dump`: `docker exec vani-setu-worker php artisan horizon:terminate` followed by `docker exec vani-setu-app php artisan queue:size redis --queue=audit` to confirm 0. The audit queue must drain before cutover or audit chain segregation (per `docs/AUDIT_CHAIN_SEGREGATION.md`) is violated.

### 2.4 Connection-string updates

Changes targeted on the .17 side (read-only host references; the new authoritative endpoints live on .132):

| File | Lines | Today | After cutover |
| --- | --- | --- | --- |
| `/home/sds-dev/docker-compose.yml` | 13, 18 | `DB_HOST: postgres`, `REDIS_HOST: redis` (internal compose-network DNS) | n/a — entire service block deleted; only `frontend` retained |
| `/home/sds-dev/src/.env` | 25 (`DB_HOST`), 59 (`REDIS_HOST`), 96–100 (`AWS_*`/MinIO), 107 (`REVERB_HOST`) | dev-host literals | **Removed from .17.** Authority moves to ConfigMap/Secret in `vani-laravel` namespace. Keep `/home/sds-dev/src/.env.example` updated for local-only dev frontends. |
| `/home/sds-dev/.env.example` | `MEILI_MASTER_KEY`, `APP_KEY`, `REVERB_APP_*` | required for `up` | Mark as "compose-stack retired; see Helm values.yaml in sds-monorepo" |
| `/home/sds-dev/caddy/Caddyfile.private` | Vani host block | Reverse-proxies to `web:80` container | **Removed** — the .132 IngressRoute is now authoritative for `vanisetu.rajyasabha.digital`. The .17 Caddy stays for the frontend hot-reload preview only (UAT 9443, dev preview hostnames). |
| `/home/sds-dev/ml-gateway/app/config.py` | `VANI_API_URL` env | `http://web` (compose DNS) | `https://vanisetu.rajyasabha.digital` (public) OR a new `vani-app.vani-laravel:9000` if ml-gateway also moves to .132 — see OQ-8 |
| `/home/sds-dev/docker-compose.prod.yml` | full file | Production overlay used by CI | Reduce to "frontend-only" overlay (the deploy:prod CI job in `.gitlab-ci.yml` lines 148–171 still tries to `docker compose up` — must be rewritten or gated `when: never` until CI/CD plan B is in place — see OQ-9) |

Frontend Vite dev server keeps pointing at the public `https://vanisetu.rajyasabha.digital/api/*` and `wss://vanisetu.rajyasabha.digital/app/*` — no source change needed on `VITE_API_BASE_URL` for Cluster A.

### 2.5 Ingress / DNS cutover

- **Phase 1 `/etc/hosts` already routes `vanisetu.rajyasabha.digital → 10.21.217.132`** (verified, line 27 of `/etc/hosts`). No further DNS change required on .17.
- External DNS (`rajyasabha.digital` zone on GoDaddy per memory `reference_rajyasabha_dns.md`): the public A record should already point at the platform VIP — confirm via dig from off-host (OQ-10).
- The Phase 1 cutover means **the .17 Caddy is no longer serving public traffic** for `vanisetu.rajyasabha.digital` (the dev host resolves the hostname to .132). To avoid ambiguity, the post-cutover Caddyfile on .17 retains only `vanisetu.private.sds.local` / `127.0.0.1` listeners and the frontend dev preview.
- New IngressRoute on .132 must include the WebSocket upgrade headers for `/app/*` (Reverb). Verify Traefik `websocket` middleware is attached.

### 2.6 Rollback

If the .132 stack fails the post-cutover smoke test (`/healthz`, login, audit chain insert, Reverb subscribe):

```
# On .132 (executed by .132 admin):
kubectl -n vani-laravel scale deploy/vani-web --replicas=0
kubectl -n vani-laravel scale deploy/vani-app --replicas=0
kubectl -n vani-laravel scale deploy/vani-reverb --replicas=0
# Point the IngressRoute back at the previous-known-good target OR remove the IngressRoute.

# On .17 (one-line revert of Phase 1 DNS):
sudo cp /etc/hosts.bak.phase1-cutover-20260523 /etc/hosts
# Bring the local stack back:
cd /home/sds-dev && docker compose up -d app web worker audit reverb caddy postgres redis meilisearch minio realtime-sidecar
```

The Postgres `pg_dump` artefact lives under `/home/sds-dev/migration-artifacts/vani_setu.dump` and is the rollback baseline. Volumes on .17 are not destroyed during cutover (they are left in place until APPROVED-rollback-window-elapsed; see OQ-11 for retention period).

### 2.7 Pre-flight checks

| ID | Check | Status |
| --- | --- | --- |
| PF-A1 | .132 K3s has `vani-laravel` namespace created and labelled per platform policy | UNVERIFIED — needs .132 admin |
| PF-A2 | StorageClass for PVCs is identified and works for ReadWriteOnce | UNVERIFIED |
| PF-A3 | Existing `vanisetu.rajyasabha.digital` IngressRoute on .132 can be re-pointed (currently 503) | **Half-verified** — 503 confirms ingress wired; upstream Service detail UNVERIFIED |
| PF-A4 | `cert-manager` `letsencrypt-prod-dns01` ClusterIssuer is healthy on .132 (the existing Vani Helm `values.yaml` line 164 expects it) | UNVERIFIED |
| PF-A5 | Registry pull from `registry.gitlab.sds.local` works from .132 (the CI builds images here) | UNVERIFIED |
| PF-A6 | .132 Prometheus IngressRoute restored (PRE-1) — required only if metrics push is enabled in transitional chart | UNVERIFIED |
| PF-A7 | .132 MinIO bucket `vani-audio-raw-rs` exists per Vani Helm values.yaml line 75 | UNVERIFIED |
| PF-A8 | Linkerd injection is NOT enabled at namespace level on `vani-laravel` (the transitional chart does not run with mesh) | UNVERIFIED |

### 2.8 Risk register — Cluster A

| Risk | Severity | Mitigation |
| --- | --- | --- |
| Reverb WebSocket session loss on cutover | Medium | Schedule cutover window in low-usage slot; client app already auto-reconnects; advertise window in Notion Commands log |
| Postgres extension version mismatch (Vani uses pgsql with `pg_trgm`, `unaccent`, audit triggers per `src/database/migrations/2026_05_19_160001_install_audit_logs_append_only_triggers.php`) | High | Use Bitnami `bitnami/postgresql:16.x` or the same `postgres:16-alpine` tag, run `\dx` on .17 first, ensure all listed extensions exist on .132; restore with `--no-owner --no-acl` |
| Audit chain genesis row mismatch on restore (single-source-of-truth concern) | High | Drain audit queue to 0 before dump; verify chain head hash pre- and post-restore via `php artisan audit:verify-chain` |
| MinIO bucket-name collision with existing platform MinIO buckets | Medium | Confirm `vani-audio-raw-rs`, `vani-ai-drafts-rs`, `vani-voiceprints-rs`, `vani-pilot-audio-rs` are unused on `minio.sds.local`; if collision, namespace under `vani/` prefix |
| `APP_URL` already public — no double-redirect | Low | `APP_URL=https://vanisetu.rajyasabha.digital` unchanged; verify `TRUSTED_PROXIES` includes the .132 ingress controller pod CIDR |
| Horizon supervisor process model on .17 vs k8s pod model | Medium | Set `replicas: 1` for Horizon initially; tune later (OQ-12) |
| Image pulls from `registry.gitlab.sds.local` may need imagePullSecrets in `vani-laravel` namespace | Medium | Create `regcred` Secret in the chart Helm-templated; verify with `kubectl create secret docker-registry` |
| CI `deploy:prod` job (`/.gitlab-ci.yml` line 148) still tries `docker compose up` on .17 | High | **Gate this job** before Phase 3 execution begins (set `when: never` or update to Helm-upgrade flow) — see OQ-9 |

---

## 3. Cluster B — UAT Vani Stack

### 3.1 Current state on .17

**Containers in scope** (from `docker-compose.uat.yml`):

| Container | Image | Command |
| --- | --- | --- |
| `sds-dev-uat-app-1` | `vani-setu-app:local` | php-fpm |
| `sds-dev-uat-worker-1` | `vani-setu-app:local` | `php artisan horizon` |
| `sds-dev-uat-reverb-1` | `vani-setu-app:local` | `php artisan reverb:start --host=0.0.0.0` |
| `sds-dev-uat-web-1` | `vani-setu-web:local` | nginx |
| `sds-dev-uat-ml-gateway-1` | `vani-setu-ml-gateway:local` | uvicorn FastAPI |

Supporting (carried along): `sds-dev-uat-postgres-1`, `sds-dev-uat-redis-1`, `sds-dev-uat-meilisearch-1`, `sds-dev-uat-realtime-sidecar-1`, `sds-dev-uat-caddy-1`. (`sds-dev-uat-mongodb-1` is the UAT mirror of the orphan stable Mongo and is **not** carried into the chart.)

**Important — UAT-stack restart posture.** All UAT containers are declared with `restart: no` and are normally Exited after any host reboot. The operator must bring UAT up before any dump script runs:

```bash
cd /home/sds-dev
docker compose -f docker-compose.uat.yml up -d uat-postgres uat-redis uat-meilisearch
# wait ~10 s for Postgres readiness, then run /home/sds-dev/scripts/migration/phase3/20-uat-pgdump.sh --apply
```

**Mounts:** all five UAT app containers mount `/home/sds-dev/src` → `/var/www/html` (RW); UAT does **not** mount `sds-common-core-php` (verified via `docker inspect`). This is a divergence from stable — flag (OQ-13).

**Environment** (from `docker-compose.uat.yml` lines 1–31): same shape as stable but with `UAT_` env-key prefix and:

- `APP_ENV=uat`, `APP_URL=https://uat.vanisetu.rajyasabha.digital:9443` (lines 2, 5)
- DB: `DB_HOST=uat-postgres`, `DB_DATABASE=vani_setu_uat` (lines 10, 12)
- Redis with non-default DBs: `REDIS_DB=1`, `REDIS_CACHE_DB=2` (lines 17, 18)
- Reverb: `REVERB_HOST=uat.vanisetu.rajyasabha.digital`, `REVERB_PORT=9443` (lines 26, 27)
- Meili: `MEILI_MASTER_KEY` → `${UAT_MEILI_MASTER_KEY}` (line 31)
- `uat-app` is aliased on the `vani-setu-uat` network as `app` for backward-compat (lines 67–70)

**ml-gateway environment** (from live `docker inspect`): `SARVAM_API_KEY [REDACTED — ml-gateway/.env]`, `PROVIDER_PRECEDENCE=["sarvam","whisper"]`, `PROCEEDINGS_PROVIDER=sarvam`, `VANI_API_URL=http://uat-web`.

**External hostname today:** `https://uat.vanisetu.rajyasabha.digital:9443` — served by `sds-dev-uat-caddy-1` on `0.0.0.0:9443` (line 156). **Not** in Phase 1 `/etc/hosts` rewrite (Phase 1 rewrote `vanisetu.rajyasabha.digital` only). A new hostname or path on .132 is required.

### 3.2 Target state on .132

| Resource | Spec |
| --- | --- |
| Namespace | `vani-uat` |
| Deployments | `vani-uat-app`, `vani-uat-web`, `vani-uat-worker`, `vani-uat-reverb`, `vani-uat-ml-gateway` (each `replicas: 1` — UAT does not need HA) |
| StatefulSets | `vani-uat-postgres`, `vani-uat-redis`, `vani-uat-meilisearch` (each PVC `1–2 Gi`) — no Mongo |
| IngressRoute | New hostname `uat.vanisetu.rajyasabha.digital` (add to `/etc/hosts` Phase 1 file as a new row → `10.21.217.132 uat.vanisetu.rajyasabha.digital`); standard 443 — **drop the port 9443 oddity** (OQ-14) |
| Cert | cert-manager `Certificate` for `uat.vanisetu.rajyasabha.digital` (or wildcard `*.vanisetu.rajyasabha.digital` if available) |
| Helm chart | Same `vani-setu-laravel` chart as Cluster A but with `values-uat.yaml` overlay (different namespace, replicas=1, UAT secrets, UAT hostname). Chart re-use is the key efficiency. |

### 3.3 Data migration

Same approach as Cluster A, smaller scale: Postgres `vani_setu_uat` (12 MB) via `pg_dump | pg_restore`; Meilisearch rebuilt; Mongo dropped-and-recreated unless OQ-7 says otherwise; Redis ephemeral.

### 3.4 Connection-string updates

| File | Today | After |
| --- | --- | --- |
| `docker-compose.uat.yml` | All `uat-*` services | Reduce to `uat-frontend` only (kept if actively developed, dropped otherwise per OQ-3) |
| `src/.env` UAT-related keys | `UAT_*` prefixed | Moved to `vani-uat` namespace Secret/ConfigMap on .132 |
| `caddy/Caddyfile.uat` | Edge for `:9443` | Removed; .132 IngressRoute is authoritative |
| `/etc/hosts` (Phase 1 file) | `uat.vanisetu.rajyasabha.digital` not listed | Add `10.21.217.132 uat.vanisetu.rajyasabha.digital` row |

### 3.5 Ingress / DNS cutover

Two-step (Phase 1 did **not** cover UAT hostname):

1. .132 admin creates `uat.vanisetu.rajyasabha.digital` IngressRoute + Certificate.
2. .17 admin appends new row to `/etc/hosts` and updates the backup file marker.

### 3.6 Rollback

Identical pattern to Cluster A: scale-down on .132, append-revert on .17 `/etc/hosts`, `docker compose -f docker-compose.uat.yml up -d` to resurrect. Dump artefact at `/home/sds-dev/migration-artifacts/vani_setu_uat.dump`.

### 3.7 Pre-flight checks (Cluster B)

| ID | Check | Status |
| --- | --- | --- |
| PF-B1 | `vani-uat` namespace exists on .132 | UNVERIFIED |
| PF-B2 | DNS for `uat.vanisetu.rajyasabha.digital` resolves to .132 | UNVERIFIED |
| PF-B3 | Cert-manager can issue for the UAT hostname (LE staging or DNS-01) | UNVERIFIED |
| PF-B4 | UAT-specific secrets gathered: `UAT_APP_KEY`, `UAT_REVERB_APP_KEY`, `UAT_REVERB_APP_SECRET`, `UAT_MEILI_MASTER_KEY`, UAT DB password | Pending OQ-15 (regenerate or reuse?) |

### 3.8 Risk register — Cluster B

| Risk | Severity | Mitigation |
| --- | --- | --- |
| UAT frontend Vite still bound to `https://uat.vanisetu.rajyasabha.digital:9443` (port 9443) | Medium | Update `VITE_API_BASE_URL` in UAT frontend env to standard 443 once UAT moves; or keep 9443 as additional ingress entrypoint (OQ-14) |
| UAT data is acceptance-test fixtures — loss is recoverable but inconvenient | Low | Single `pg_dump` + commit fixtures to git as a fallback |
| ml-gateway also moves to .132 alongside UAT app | Low | Bundle ml-gateway Deployment in the same Helm release |
| `ASR_INGEST_SECRET`/`REALTIME_AUDIT_SECRET` are shared between stable and UAT (single source `src/.env` line 11) | Medium | Generate separate UAT secrets during migration; document in chart values |

---

## 4. Cluster C — SDS Reporting Engine

### 4.1 Current state on .17

**Containers** (from `/home/sds-dev/sds-reporting-engine/docker-compose.yml`):

| Container | Image | Role | Command |
| --- | --- | --- | --- |
| `sds-reporting-superset` | `${SUPERSET_IMAGE}` = `sds-reporting-superset:6.0.0` | Superset gunicorn | gunicorn on :8088 (line 24) |
| `sds-reporting-init` (`superset-init`) | same | One-shot DB upgrade + admin create + `superset init` | (lines 56–61) |
| `sds-reporting-worker` | same | Celery worker (`--pool=prefork --concurrency=2`) | (lines 81–87) |
| `sds-reporting-beat` | same | Celery beat (schedule at `/tmp/celerybeat-schedule`) | (lines 113–118) |
| `sds-reporting-postgres` | `postgres:16-alpine` | Superset metadata DB | (lines 124–141) |
| `sds-reporting-redis` | `redis:7-alpine` | Celery broker + cache | (lines 142–155) |
| `sds-reporting-gotenberg` | `${GOTENBERG_IMAGE}` = `gotenberg/gotenberg:8` | HTML→PDF | (lines 157–172) |
| `sds-reporting-pipeline` | `${REPORT_PIPELINE_IMAGE}` = `sds-reporting-pipeline:0.1.0` | FastAPI orchestrator | (lines 174–205) |

**Mounts:** `/home/sds-dev/sds-reporting-engine/config` → `/app/pythonpath` (RO, all four Superset containers); volume `superset_db_data` → `/var/lib/postgresql/data`; volume `superset_redis_data` → `/data`.

**Environment** (from `/home/sds-dev/sds-reporting-engine/.env`, keys only):

`COMPOSE_PROJECT_NAME`, `SUPERSET_IMAGE`, `SUPERSET_HOST_PORT`, `SUPERSET_SECRET_KEY [REDACTED]`, `SUPERSET_GUEST_TOKEN_JWT_SECRET [REDACTED]`, `SUPERSET_ADMIN_{USERNAME,FIRSTNAME,LASTNAME,EMAIL,PASSWORD} [REDACTED]`, `SUPERSET_POSTGRES_{DB,USER,PASSWORD} [REDACTED]`, `SUPERSET_METADATA_DB_URI [REDACTED]`, `SUPERSET_ALLOWED_ORIGINS`, `SUPERSET_FRAME_ANCESTORS`, `SUPERSET_SESSION_COOKIE_{SAMESITE,SECURE}`, `REPORT_PIPELINE_HOST_PORT`, `REPORT_PIPELINE_PUBLIC_BASE_URL`, `REPORT_PIPELINE_IMAGE`, `GOTENBERG_IMAGE`, `TIJORI_MINIO_{ENDPOINT,PUBLIC_ENDPOINT,ACCESS_KEY,SECRET_KEY,BUCKET,REGION,SECURE} [REDACTED]`, `MINIO_SIGNED_URL_TTL_SECONDS`.

**Networks:**

- `reporting` (internal, line 212)
- `vani-setu` (external, line 215 — name `sds-dev_vani-setu`; this is how Superset reaches the Vani Postgres for dataset queries)
- `tijori-dev` (external, line 218 — name `tijori-dev_tijori-dev-net`; the report-pipeline talks to `tijori-dev-minio`)

The `tijori-dev` dependency is **load-bearing**: report-pipeline writes generated PDFs into the Tijori dev MinIO. Since the Tijori AI workbench stays on .17, the report-pipeline either (a) stays on .17 connected to the same MinIO, or (b) moves to .132 and writes to `minio.sds.local` on the platform. Recommend (b) with a `TIJORI_MINIO_ENDPOINT` swap (OQ-16).

### 4.2 Target state on .132

| Resource | Spec |
| --- | --- |
| Namespace | `sds-reporting` |
| Deployments | `superset-web` (gunicorn), `superset-worker` (Celery), `superset-beat` (single replica — beat must be singleton), `gotenberg`, `report-pipeline` |
| StatefulSets | `superset-postgres` (PVC 5 Gi), `superset-redis` (PVC 1 Gi) |
| Job | `superset-init` (Helm post-install/upgrade hook, idempotent) |
| Services | `superset:8088`, `superset-postgres:5432`, `superset-redis:6379`, `gotenberg:3000`, `report-pipeline:8000` |
| IngressRoute | `reports.sds.local` or `sds-reporting.rajyasabha.digital` — new hostname required (OQ-17) |
| Storage | One PVC for Superset Postgres metadata; cache rebuilt |
| Secrets | `superset-app-secrets` (SECRET_KEY, GUEST_TOKEN_JWT_SECRET, ADMIN_PASSWORD, DB_PASSWORD); `tijori-minio-app` (access-key/secret-key) |
| ConfigMap | `superset-config` (mounts the contents of `/home/sds-dev/sds-reporting-engine/config/superset_config.py`) |

Helm chart: author `sds-reporting` under `~/sds-monorepo/deploy/reporting-engine/helm/sds-reporting/` — does **not** exist today; build from scratch.

### 4.3 Data migration

| Dataset | Approach | Estimated downtime |
| --- | --- | --- |
| Superset Postgres (11 MB — dashboards, datasources, slices, users) | `pg_dump -Fc -U $SUPERSET_POSTGRES_USER` → restore on .132 | **~3 min** |
| Superset Redis (cache + Celery broker) | Drop and recreate; warm cache after | n/a |
| Generated PDFs (in Tijori MinIO) | Stay in place if Tijori MinIO not migrated; or `mc mirror tijori-dev-minio/<bucket> → minio.sds.local/<bucket>` | **~5 min** (estimate; bucket size unmeasured — flag OQ-16) |

### 4.4 Connection-string updates

| File | Today | After |
| --- | --- | --- |
| `sds-reporting-engine/.env` | `SUPERSET_METADATA_DB_URI=postgresql+psycopg2://...@superset-db:5432/...` | Replaced by Secret in `sds-reporting` namespace |
| `sds-reporting-engine/.env` | `TIJORI_MINIO_ENDPOINT=tijori-dev-minio:9000` (presumed) | `minio.sds.local:443` if .132 platform MinIO; OR `<tijori-dev-host>:9000` if Tijori MinIO stays on .17 |
| `sds-reporting-engine/docker-compose.yml` line 215 | `vani-setu` network external dependency | **Removed** — Superset queries the new Vani Postgres at `vani-postgres.vani-laravel.svc.cluster.local:5432` (cluster-internal DNS) once both Vani and Reporting live on .132 |
| `sds-reporting-engine/config/superset_config.py` | Datasource entries point at `postgres:5432` (Vani) | Datasource entries point at the .132 Vani Postgres Service DNS |

### 4.5 Ingress / DNS cutover

- Today Superset is reached at `http://<.17>:${SUPERSET_HOST_PORT}` on the LAN only (no public TLS).
- Post-migration: new IngressRoute on `reports.sds.local` (add to `/etc/hosts` if access from .17 is required) or `sds-reporting.rajyasabha.digital` (public).
- Hostname choice is OQ-17.

### 4.6 Rollback

```
kubectl -n sds-reporting scale deploy/superset-web --replicas=0
# Restore on .17:
cd /home/sds-dev/sds-reporting-engine && docker compose up -d
```

### 4.7 Pre-flight checks (Cluster C)

| ID | Check | Status |
| --- | --- | --- |
| PF-C1 | `sds-reporting` namespace exists on .132 | UNVERIFIED |
| PF-C2 | The `vani-laravel` Postgres exposes a NetworkPolicy permitting `sds-reporting` → `vani-postgres:5432` | UNVERIFIED — must be authored in the Vani Helm chart |
| PF-C3 | Tijori MinIO endpoint decision (OQ-16) resolved | UNVERIFIED |
| PF-C4 | Cluster A migration completed first (Reporting depends on Vani Postgres) | Sequencing constraint |

### 4.8 Risk register — Cluster C

| Risk | Severity | Mitigation |
| --- | --- | --- |
| Superset metadata DB schema-version drift across Postgres upgrade | Low | Same `postgres:16-alpine` tag; `pg_dump -Fc` is version-tolerant within 16.x |
| Reporting depends on Vani Postgres; Cluster A must move first | High (sequencing) | Lock phase order: A → C → B → D |
| `tijori-dev-net` cross-network bridge is removed | Medium | Update `TIJORI_MINIO_ENDPOINT` either to platform MinIO or to the .17 Tijori MinIO over a routable address |
| Celery beat duplication (two beat pods would double-fire schedules) | High | Set `replicas: 1` strictly; use a PVC-backed schedule file or migrate to `django-celery-beat` DB scheduler |
| Generated-PDF presigned URLs break if MinIO endpoint changes mid-flight | Medium | Drain pending report jobs before cutover; coordinate `MINIO_PUBLIC_ENDPOINT` change with consumers |

---

## 5. Cluster D — Fake Parichay (.17 retirement)

### 5.1 Current state on .17

**Container:** `sds-fake-parichay` (image `sds-fake-parichay:0.1.0`, running 23 hours, healthy).

**Source:** `/home/sds-dev/sds-fake-parichay/docker-compose.yml`.

**Mounts:** `config/keys` → `/srv/config/keys` (RO), `config/seed-users.yaml` → `/srv/config/seed-users.yaml` (RO).

**Port binding:** `0.0.0.0:8443:8443` (host-wide). Issues OIDC tokens signed by JWK at `config/keys/`.

**Environment keys:** `FAKE_PARICHAY_{ISSUER, DEFAULT_AUDIENCE, CLIENT_ID, CLIENT_SECRET [REDACTED], KID, BANNER}`.

**Consumers on .17:** any app that resolves `https://fake-parichay.sds.local` and hits port 8443. Memory `reference_fake_parichay.md` notes the swap to real is via the `PARICHAY_BASE_URL` env var.

### 5.2 Target state

**.132 instance is already running** per directive — but the curl probe `https://10.21.217.132:18443/health` returned 000 (connection refused) from .17. This is a contradiction with the directive; surface as OQ-1.

Assuming the .132 instance is correctly addressable (after OQ-1 resolution), Cluster D is **not a migration but a retirement**:

1. Confirm .132 fake-parichay is reachable and serves the same JWK set + seed users as the .17 copy. **Critical**: keys must match or any access token issued by .132 will not validate against existing .17-cached JWKs.
2. Update every consumer of fake-parichay on .17 to point at the .132 endpoint via `PARICHAY_BASE_URL` env override.
3. Stop and remove the .17 container.

### 5.3 Data migration

None — fake-parichay is stateless. The only "data" is the JWK keyset under `sds-fake-parichay/config/keys/`. **Verify** the .132 instance is using the same keys (otherwise federated trust breaks). If not, copy keys to .132 admin OR regenerate and roll all downstream JWKs.

### 5.4 Connection-string updates

| Consumer file | Today | After |
| --- | --- | --- |
| `/home/sds-dev/src/.env` `PARICHAY_BASE_URL` (or equivalent) | `https://fake-parichay.sds.local:8443` (presumed) | `https://10.21.217.132:18443` or whatever the OQ-1 answer is |
| `/etc/hosts` | `fake-parichay.sds.local` not listed | Optional — add `10.21.217.132 fake-parichay.sds.local` row for hostname-stable client code |

### 5.5 Ingress / DNS cutover

Optional `/etc/hosts` rewrite as above; otherwise raw IP.

### 5.6 Rollback

```
cd /home/sds-dev/sds-fake-parichay && docker compose up -d
# revert consumer env to https://fake-parichay.sds.local:8443
```

### 5.7 Pre-flight checks (Cluster D)

| ID | Check | Status |
| --- | --- | --- |
| PF-D1 | .132 fake-parichay reachable from .17 on the documented port | **UNVERIFIED — probe failed**; resolve OQ-1 before proceeding |
| PF-D2 | .132 fake-parichay JWK keyset matches .17 (or all consumers rotate together) | UNVERIFIED |
| PF-D3 | `seed-users.yaml` on .132 matches .17 (6 RS-only seed users per memory) | UNVERIFIED |

### 5.8 Risk register — Cluster D

| Risk | Severity | Mitigation |
| --- | --- | --- |
| JWK mismatch — .132 tokens fail validation | High | Compare JWK fingerprints before stopping .17 container |
| Seed-user drift — different test users | Medium | Diff `seed-users.yaml` files; align both copies |
| Port 8443 vs 18443 inconsistency | Low | OQ-1 |
| Consumer code hardcodes the .17 hostname/IP | Medium | Grep for `fake-parichay` across `src/` and `ml-gateway/`; centralise via `PARICHAY_BASE_URL` |

---

## 6. Transitional Laravel chart — design (Cluster A specific)

### 6.1 Why a transitional chart

The native Stage-6 Vani chart at `~/sds-monorepo/deploy/vani-setu/helm/vani-setu/` is a FastAPI service (Python) that does **not** match what is currently running on .17, which is a **Laravel monolith** under `/home/sds-dev/src/`. Forcing the .17 PHP runtime into the FastAPI chart would either fail or require destructive rewrites. The Software Manager has approved a transitional chart that hosts the PHP runtime as-is, allowing Phase 3 cutover without blocking on Stage-6.

### 6.2 Chart layout

```
~/sds-monorepo/deploy/vani-setu/helm/vani-setu-laravel/
├── Chart.yaml                       # name: vani-setu-laravel; version: 0.1.0; appVersion: matches src/ commit SHA
├── values.yaml                      # stable defaults
├── values-uat.yaml                  # Cluster B overlay (replicas=1, vani-uat ns, UAT hostname)
└── templates/
    ├── _helpers.tpl
    ├── configmap-app-env.yaml       # non-secret APP_URL, APP_ENV, TRUSTED_PROXIES, REVERB_HOST, etc.
    ├── configmap-nginx.yaml         # content of docker/nginx.conf
    ├── secret-app.yaml              # placeholder; production uses existingSecret reference
    ├── secret-registry.yaml         # docker-registry imagePullSecret for registry.gitlab.sds.local
    ├── deployment-app.yaml          # php-fpm; mounts ConfigMap + Secret; readiness on TCP 9000
    ├── deployment-web.yaml          # nginx; mounts ConfigMap (nginx conf); upstream vani-app:9000
    ├── deployment-worker.yaml       # Horizon
    ├── deployment-audit.yaml        # queue:work --queue=audit
    ├── deployment-reverb.yaml       # reverb:start --host=0.0.0.0; Service sessionAffinity=ClientIP
    ├── statefulset-postgres.yaml    # postgres:16-alpine; PVC
    ├── statefulset-redis.yaml       # redis:7-alpine; PVC
    ├── statefulset-meilisearch.yaml # getmeili/meilisearch:v1.10; PVC
    ├── service-app.yaml             # ClusterIP 9000
    ├── service-web.yaml             # ClusterIP 80
    ├── service-reverb.yaml          # ClusterIP 8080, sessionAffinity ClientIP
    ├── service-supporting.yaml      # postgres/redis/meili headless services
    ├── ingressroute.yaml            # Traefik CRD; vanisetu.rajyasabha.digital → web; /app/* → reverb (WebSocket)
    ├── certificate.yaml             # cert-manager Certificate (gated by .values.certManager.enabled)
    ├── networkpolicy.yaml           # default-deny + allow: ingress from traefik, egress to postgres/redis/meili + sds-reporting (label)
    ├── job-migrate.yaml             # post-install + post-upgrade hook: php artisan migrate --force
    ├── job-audit-genesis.yaml       # post-install only: seed genesis row (idempotent guard)
    └── serviceaccount.yaml
```

### 6.3 values.yaml — key surface (handoff-relevant subset)

```yaml
namespace: vani-laravel            # NB: distinct from vani-system reserved by the native chart
image:
  app:
    repository: registry.gitlab.sds.local/vani/setu/app
    tag: ""                        # set to git SHA from CI
  web:
    repository: registry.gitlab.sds.local/vani/setu/web
    tag: ""
  mlGateway:
    repository: registry.gitlab.sds.local/vani/setu/ml-gateway
    tag: ""
  realtimeSidecar:
    repository: registry.gitlab.sds.local/vani/setu/realtime-sidecar
    tag: ""

replicas:
  app: 2
  web: 2
  worker: 1
  audit: 1
  reverb: 1

postgres:
  image: postgres:16-alpine
  storage: 5Gi
  database: vani_setu
  username: vani
  existingSecret: vani-app-secrets
  passwordKey: db-password

redis: { image: redis:7-alpine, storage: 1Gi }
meilisearch: { image: getmeili/meilisearch:v1.10, storage: 2Gi, existingSecret: vani-app-secrets, masterKeyKey: meili-master-key }
# mongo: removed v0.2 — orphan container with no consumer; see plan §1.5 / OQ-7

env:
  APP_ENV: production
  APP_URL: https://vanisetu.rajyasabha.digital
  TRUSTED_PROXIES: "10.42.0.0/16,172.16.0.0/12"
  REVERB_HOST: vanisetu.rajyasabha.digital
  REVERB_PORT: 443
  REVERB_SCHEME: https
  CACHE_STORE: redis
  QUEUE_CONNECTION: redis
  SESSION_DRIVER: file
  BROADCAST_CONNECTION: reverb
  MEILISEARCH_HOST: http://vani-meilisearch:7700

secrets:
  existingSecret: vani-app-secrets   # keys: app-key, db-password, redis-password (optional), meili-master-key, reverb-app-key, reverb-app-secret, asr-ingest-secret, realtime-audit-secret, sarvam-api-key, bootstrap-admin-password
  imagePullSecret: regcred

ingress:
  enabled: true
  className: traefik
  host: vanisetu.rajyasabha.digital
  tlsSecretName: vani-tls-public
  reverbPathPrefix: /app

certManager:
  enabled: true
  issuer: letsencrypt-prod-dns01

# --- Transitional-chart handoff seam (see § 6.4) ---
handoff:
  nativeChart: vani-setu
  nativeNamespace: vani-system
  migrationMode: passive    # passive | active-cutover
  reverbProtocol: laravel-reverb     # native chart will provide its own
```

### 6.4 Handoff seam to the future Stage-6 native chart

The handoff seam is the contract that lets the existing Stage-6 native chart (`vani-setu/`) replace this transitional chart cleanly when Stage-6 closes:

1. **Namespace separation.** Transitional = `vani-laravel`; native = `vani-system`. The two charts can coexist during the Stage-6 cutover; traffic is moved at the IngressRoute level, not by namespace rename.
2. **Postgres surface.** The transitional chart owns the canonical `vani_setu` Postgres database. The native chart, when introduced, must connect to the same Postgres (cross-namespace DSN `vani-postgres.vani-laravel.svc.cluster.local:5432`) **OR** migrate to a new Postgres seeded from a final `pg_dump` of the transitional one. Decision is a Stage-6 entry deliverable; not blocked by Phase 3.
3. **MinIO bucket surface.** Both charts must agree on the four-bucket ADR-VANI-5 names (`vani-audio-raw-rs`, `vani-ai-drafts-rs`, `vani-voiceprints-rs`, `vani-pilot-audio-rs`). The transitional chart points at platform MinIO with the same bucket names so the native chart inherits the corpus without copy.
4. **IngressRoute ownership.** The IngressRoute for `vanisetu.rajyasabha.digital` is templated by **whichever chart is currently serving**. Stage-6 cutover removes the transitional chart's IngressRoute and adds the native chart's (with the same TLS Secret name `vani-tls-public` to keep the cert).
5. **Secret surface.** Transitional Secret `vani-app-secrets` (Laravel keys) and native Secret `vani-postgres-app`/`vani-minio-app`/`vani-parichay-oauth` are distinct names — no collision. The values overlap (DB password, MinIO creds) and must be kept in sync via OQ-18 (single source-of-truth, e.g., Vault).
6. **Reverb retirement.** The native chart does not run Laravel Reverb (it is FastAPI). The transitional chart is the last hold-out of the WebSocket broadcast plane. Stage-6 cutover requires either re-implementation of the broadcast plane in FastAPI or a deliberate retirement decision.

The transitional chart's `Chart.yaml` records a `metadata.annotations` field linking to the native chart's `version` it expects to hand off to:

```yaml
metadata:
  annotations:
    sds.handoff-target: "vani-setu/0.2.x"
    sds.handoff-version-floor: "0.2.1-dev.5"
```

---

## 7. Phase ordering across the four clusters

### 7.1 Ordering decision

**A → C → B → D**

1. **Cluster A first** (Vani stable). It is the lighthouse runtime and the hardest cutover. Doing it first proves the transitional chart, the IngressRoute swap, the cert wiring, the registry pull, the data-migration mechanics, and the rollback plan. Everything downstream re-uses the chart and the playbook.
2. **Cluster C second** (Reporting). It has a **hard dependency** on Cluster A's Postgres being reachable cross-namespace from .132 K3s. Reporting on .17 talking to Vani Postgres on .132 across IP boundaries is technically possible but it adds a moving part that's not worth keeping during cutover; doing it after A keeps the Vani↔Reporting link cluster-internal at all times.
3. **Cluster B third** (UAT). Identical chart pattern to A; it's a "do-it-again with different values" exercise. No hard dependency on A or C, but pushing it after A captures the chart fixes that A inevitably uncovers.
4. **Cluster D last** (Fake Parichay retirement). Stateless, scoped to env-var changes in consumers; doing it last means consumers in A/B/C have already had their env updated once and a second env change is low-risk.

### 7.2 Parallelism

- **A and D can run in parallel** if a second operator is available (different consumers, no overlap). Only one operator is expected for this Phase, so serial.
- **C and B can run in parallel** after A closes, if the operator is comfortable holding two contexts.
- **PRE-1 (Prometheus IngressRoute fix)** is independent and can run any time before metrics are wired to .132. Not on the critical path.

### 7.3 Estimated window

Under the small-data finding (largest DB = 18 MB) and the IngressRoute being already wired:

| Cluster | Cutover window |
| --- | --- |
| A | 30–60 min |
| C | 20 min |
| B | 30 min |
| D | 10 min |
| Total | **~2 hours** under nominal conditions; double under contingency |

---

## 8. Open questions (require Software Manager + .132 admin)

| OQ | Question |
| --- | --- |
| OQ-1 | **STILL OPEN — needs .132 admin.** Confirmed unreachable from .17 (rev 0.2 probe 2026-05-25): ports 8443/18443 conn-refused, port 443 returns Traefik 404, candidate hostnames (`parichay.sds.local`, `fake-parichay.sds.local`, `parichay.rajyasabha.digital`, `fake-parichay.rajyasabha.digital`) do not resolve from .17. Runbook §2.5 still applies: the .132 admin must run `kubectl get pod -A \| grep -i parichay` from .132 itself and report back the working ClusterIP / IngressRoute. |
| OQ-2 | Which **StorageClass** should the three StatefulSets (Postgres, Redis, Meilisearch) bind to on .132 K3s? Local-path SC is fine for dev; longhorn is preferred for HA. |
| ~~OQ-3~~ | **RESOLVED 0.2 (RECOMMENDED) — retire `sds-dev-uat-frontend-1`.** Evidence: every UAT container has `restart: no`; the entire UAT stack (frontend + backend + supporting datastores) has been Exited (255) since the last host reboot 21h ago and was not brought back up by an operator. If UAT were actively developed, the frontend would have been resurrected by hand. UAT can still be re-cut on demand by `docker compose -f docker-compose.uat.yml up -d` until the .132 chart absorbs the backend; the frontend specifically is not migrated to .132. Override by Software Manager required only if active UAT-frontend dev work is planned within the next 7 days. |
| OQ-4 | The Laravel `AWS_*` env keys (lines 96–100 of `src/.env`) point at MinIO. Which MinIO is authoritative post-migration — `vani-setu-minio` (retired on .17) → platform `minio.sds.local`? Confirm bucket names and presigned-URL host policy. |
| OQ-5 | Is `docker-compose.rtsearch.yml` still in use (realtime-search Meili overlay), or can it be marked retired alongside `docker-compose.monitoring.yml`? |
| OQ-6 | Are the four ADR-VANI-5 bucket names (`vani-audio-raw-rs`, `vani-ai-drafts-rs`, `vani-voiceprints-rs`, `vani-pilot-audio-rs`) already provisioned on `minio.sds.local` or do they need to be created during Phase 3? |
| ~~OQ-7~~ | **RESOLVED 0.2** — Mongo is unused. S2S tables `s2s_sessions`/`s2s_segments`/`s2s_outputs` are on the default `pgsql` connection (verified in `src/app/Modules/SpeechToSpeech/Models/S2s{Session,Segment}.php`); `vani-setu-mongodb` and `sds-dev-uat-mongodb-1` are orphan containers with `restart: no` and no service block in any `docker-compose*.yml`. ml-gateway has an optional `MongoArtifactStore` but `mongodb_trace_writes_enabled` defaults to `False` with no env override. **No Mongo migration; no Mongo StatefulSet in either chart.** |
| OQ-8 | Does `ml-gateway` move to .132 (folded into the `vani-laravel` chart) or stay on .17? If it moves, `VANI_API_URL` is cluster-internal; if it stays, `VANI_API_URL` becomes the public `https://vanisetu.rajyasabha.digital`. |
| OQ-9 | The CI `deploy:prod` job (`/.gitlab-ci.yml` line 148) runs `docker compose up` on .17 via shell-runner. Once Cluster A moves, this is **dangerous** (would resurrect the retired stack). Should the job be gated `when: never`, rewritten to `helm upgrade`, or removed? |
| OQ-10 | Confirm external DNS for `vanisetu.rajyasabha.digital` (GoDaddy zone) resolves to the .132 platform VIP, not the .17 dev host. If it currently points elsewhere, the cutover is incomplete from outside the LAN. |

(Additional OQs raised inline above for completeness: OQ-11 .17-volume retention period; OQ-12 Horizon replica tuning; OQ-13 UAT divergence on `sds-common-core-php` mount; OQ-14 UAT port 9443 → 443; OQ-15 UAT secrets regenerate vs reuse; OQ-16 Tijori-MinIO endpoint for report-pipeline; OQ-17 reporting hostname; OQ-18 secret single-source-of-truth.)

---

## 9. Cross-cutting risks

| Risk | Severity | Mitigation |
| --- | --- | --- |
| **No SSH from .17 to .132** | High (operational friction) | Every .132-side step is a hand-off; the runbook for the executing session must list explicit human checkpoints |
| **`kubectl` on .17 is wrong cluster** | Medium | Operator must `unset KUBECONFIG` or use a `.132`-context kubeconfig fetched by hand |
| **Phase 1 /etc/hosts only covers existing hostnames** | Medium | Cluster B (`uat.vanisetu...`) and possibly C (`reports.sds.local`) need additions; document in the runbook |
| **`docker-compose.monitoring.yml` still resurrectable** | Medium | Harden during Phase 3 (delete file or set all services to `profiles: [retired]`) |
| **CI deploy job dangerously still active** | High | OQ-9; pre-execution gate |
| **MinIO bucket naming collision on platform MinIO** | Medium | OQ-6 |
| **Common Core PHP boundary breach** (memory `project_vani_laravel_boundary_breach.md`) | High (governance) | Out of scope for Phase 3 — surface in the post-Phase-3 brief so it does not get masked by the migration |

---

## 10. Sign-off

- [ ] Software Manager (Kushal Pathak) — DRAFT reviewed; OQ-1 through OQ-10 answered; APPROVED to execute
- [ ] .132 platform admin — Pre-flight checks PF-A1..A8, PF-B1..B4, PF-C1..C4, PF-D1..D3 attested
- [ ] Executing session (separate run) — Confirms it has both this APPROVED plan and a kubeconfig pointing at `.132`

Confidential | Rajya Sabha Secretariat | SDS programme | Phase 3 dev-host → platform migration

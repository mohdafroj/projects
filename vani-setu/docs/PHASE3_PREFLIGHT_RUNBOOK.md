# SDS Phase 3 — Pre-flight Runbook for the .132 K3s Admin

| Field | Value |
| --- | --- |
| Document ID | SDS-DEV-MIG-PHASE3-PREFLIGHT |
| Title | Phase 3 Pre-flight Runbook — .132 K3s admin playbook for the Docker-Compose → K3s cutover |
| Date | 2026-05-23 |
| Author | Claude Code (Agent 3 of 3 in the Phase 3 plan authoring run), on behalf of Kushal Pathak (Software Manager, Rajya Sabha SDS) |
| Audience | The DevOps engineer / software manager at the **10.21.217.132** keyboard who has `kubectl` against the platform K3s. Companion document for the executing-session operator on **10.21.217.17**. |
| Status | DRAFT — sequenced with `/home/sds-dev/docs/MIGRATION_PHASE3_PLAN.md` (also DRAFT). Both must be APPROVED before any cutover begins. |
| Plan boundary | Pre-flight and per-cluster execution checklist. Does **not** authorise data destruction; data is destroyed only by the explicit rollback steps documented per cluster. |
| Companion artefacts | `/home/sds-dev/scripts/migration/phase3/*.sh` (the .17-side scripts); `/home/sds-dev/sds-monorepo/deploy/vani-setu-laravel/helm/vani-setu-laravel/` (Cluster A and B chart, authored by Agent 1); `/home/sds-dev/sds-monorepo/deploy/sds-reporting/helm/sds-reporting/` (Cluster C chart, authored by Agent 2). |

---

## Change log

| Rev | Date | Author | Notes |
| --- | --- | --- | --- |
| 0.1 | 2026-05-23 | Claude Code (Agent 3) | Initial DRAFT. Bound to `MIGRATION_PHASE3_PLAN.md` rev 0.1. All `kubectl` commands assume `KUBECONFIG` points at the .132 K3s cluster. |
| 0.2 | 2026-05-25 | Claude Code | Sync with plan rev 0.2: drop Mongo from verification expected-pod list (Cluster A & B); add UAT bring-up step to §4.3 ("UAT containers have `restart: no` — operator must `docker compose -f docker-compose.uat.yml up -d uat-postgres` before script `20-uat-pgdump.sh --apply`"). OQ-1 (fake-parichay) probed from .17 — confirmed unreachable on every candidate port/hostname; §2.5 instruction to .132 admin stands unchanged. OQ-3 (UAT frontend) resolved with retire recommendation. |

---

## 1. Scope and assumptions

### 1.1 What this runbook covers

This runbook is the **.132 admin's playbook** for the Phase 3 cutover. It pairs with the .17-side shell scripts in `/home/sds-dev/scripts/migration/phase3/`. Together, the two artefacts describe a single coordinated cutover with one operator at each host.

The cutover migrates four service clusters from Docker Compose on the dev host **10.21.217.17** to K3s on the platform host **10.21.217.132**:

- **Cluster A** — Vani Setu stable backend (Laravel monolith). Transitional Helm chart `vani-setu-laravel`.
- **Cluster B** — UAT Vani stack. Reuses Cluster A's chart with `values-uat.yaml`.
- **Cluster C** — SDS Reporting Engine (Superset + Celery + Gotenberg + report-pipeline). New chart `sds-reporting`.
- **Cluster D** — Retirement of the .17 `sds-fake-parichay` copy in favour of the existing .132 instance.

Execution order is fixed: **A → C → B → D** (per plan section 7).

### 1.2 What is already done

- **Phase 1 DNS cutover** is closed. `/etc/hosts` on .17 already routes `vanisetu.rajyasabha.digital → 10.21.217.132`. Backup at `/etc/hosts.bak.phase1-cutover-20260523`.
- **Phase 2 observability decommission** is closed. The eight monitoring containers on .17 have been `docker rm`'d. `docker-compose.monitoring.yml` still exists on disk but its services are not running.
- **GitLab CI image build** is wired and producing images at `registry.gitlab.sds.local/vani/setu/{app,web,ml-gateway,realtime-sidecar}`.
- The .132 IngressRoute for `vanisetu.rajyasabha.digital` is already wired (currently returns HTTP 503 because the backend pod is missing — that is expected; this Phase fills it).

### 1.3 What is NOT covered here

- **Tijori AI workbench** (`tijori-dev-vllm`, `tijori-dev-ollama`, `tijori-dev-whisper`, `tijori-dev-ocr-service`, `tijori-dev-indictrans2`, `tijori-dev-triton`, `tijori-dev-minio`, `tijori-dev-postgres`, `tijori-ai-litellm`, `tijori-ai-langfuse`, `tijori-ai-langfuse-db`, `tijori-ai-open-webui`, `tijori-ai-mlflow`, `tijori-ai-flowise`) **stays on .17**. Do not touch.
- **Vani Vite frontend** (`vani-setu-frontend`, port 5173) stays on .17 for hot-reload dev work.
- The orphaned local K3s control plane on .17 is **not** in scope; that is a separate phase.
- Common Core PHP boundary breach (memory `project_vani_laravel_boundary_breach.md`) is **not** resolved in Phase 3 — surface to Software Manager after Phase 3 closes.

### 1.4 Operating assumptions

- `kubectl` on .132 is configured against the platform K3s and has cluster-admin or equivalent.
- `mc` (MinIO Client) is configured on the .132 admin's workstation with an alias `vani132` pointing at `minio.sds.local`.
- `helm` v3.x is on PATH on .132.
- The .17 operator hand-delivers dump files (`vani_setu.dump`, `vani_setu_uat.dump`, `sds_reporting_superset.dump`) via an out-of-band channel since SSH from .17 → .132 is blocked.
- `cert-manager` and Traefik are installed and healthy on .132.

---

## 2. Pre-flight checks the .132 admin must run

Run these **before** the .17 operator begins any data dump. Each check has an expected output and a remediation note. None of these commands changes state except where explicitly noted in section 2.3 (namespace creation).

### 2.1 Cluster substrate health

```bash
# A — kubeconfig points at .132
kubectl config current-context
# Expected: a context whose server URL is https://10.21.217.132:6443 or a cluster
# label indicating .132. Anything else (e.g. the .17 local K3s context) — STOP
# and `export KUBECONFIG=<path-to-132-kubeconfig>`.

# B — node ready
kubectl get node -o wide
# Expected: Ready, schedulable, internal IP 10.21.217.132.

# C — StorageClass present and default-marked
kubectl get sc
# Expected: at least one SC marked (default). Common choices on dev: local-path
# (Rancher), longhorn (HA). If none is default, set one:
#   kubectl patch sc <name> -p '{"metadata":{"annotations":{"storageclass.kubernetes.io/is-default-class":"true"}}}'
# This resolves OQ-2.

# D — cert-manager healthy
kubectl -n cert-manager get pod
# Expected: cert-manager, cert-manager-webhook, cert-manager-cainjector all Running.

# E — ClusterIssuer for public certs available
kubectl get clusterissuer
# Expected: letsencrypt-prod-dns01 (or similar) Ready=True.
# If missing — coordinate with platform team; transitional chart can run with
# certManager.enabled=false and a manually-loaded TLS Secret.

# F — Traefik healthy
kubectl -n kube-system get pod | grep traefik
# Expected: 1+ traefik pod Running.
# Confirm Traefik CRDs:
kubectl get crd | grep -i traefik
# Expected: ingressroutes.traefik.containo.us (or v1alpha1) present.
```

### 2.2 Required namespaces

Three namespaces must exist (or be created) before any `helm install`:

```bash
# Idempotent creates:
kubectl create namespace vani-laravel  --dry-run=client -o yaml | kubectl apply -f -
kubectl create namespace vani-uat       --dry-run=client -o yaml | kubectl apply -f -
kubectl create namespace sds-reporting  --dry-run=client -o yaml | kubectl apply -f -

# Label for platform policy (adjust labels to match your cluster's NetworkPolicy
# and Linkerd posture; the transitional Laravel chart is NOT mesh-injected):
kubectl label ns vani-laravel sds-tier=app sds-mesh=opt-out --overwrite
kubectl label ns vani-uat     sds-tier=app sds-mesh=opt-out --overwrite
kubectl label ns sds-reporting sds-tier=app sds-mesh=opt-out --overwrite
```

If your platform policy already creates these namespaces from another source of
truth (GitOps, IaC), skip these commands and verify with `kubectl get ns`.

### 2.3 In-cluster dependencies (read-only verification)

```bash
# A — Vault (the platform's secrets backend is in 'vault' namespace per memory)
kubectl -n vault get pod
# Expected: vault-0 Ready. If not, secret seeding (section 3) must use raw
# kubectl Secrets rather than Vault references.

# B — Platform Postgres operator (only if the chart pulls from a CRD-managed
# Postgres). The transitional Laravel chart provisions its own StatefulSet
# Postgres, so this check is informational only.
kubectl get crd | grep -iE 'postgres|cnpg|stackgres'
# Expected: presence of one of these = managed Postgres available. Absence is
# fine; the chart uses in-namespace StatefulSet Postgres.

# C — Registry pull-secret presence
kubectl -n vani-laravel  get secret regcred 2>&1 | head
kubectl -n vani-uat      get secret regcred 2>&1 | head
kubectl -n sds-reporting get secret regcred 2>&1 | head
# If missing, create from a docker-config (see section 3.5).

# D — Existing MinIO platform
mc alias list | grep -E 'vani132|minio.sds.local' || \
  echo "PLATFORM MINIO ALIAS NOT CONFIGURED — set it before mirror"
# Then probe:
mc ls vani132/
# Expected: a listing of platform buckets. Note which of the four
# ADR-VANI-5 buckets already exist:
for b in vani-audio-raw-rs vani-ai-drafts-rs vani-voiceprints-rs vani-pilot-audio-rs; do
  if mc ls "vani132/${b}" >/dev/null 2>&1; then
    echo "  EXISTS: $b"
  else
    echo "  MISSING: $b — create with 'mc mb vani132/$b' (resolves OQ-6)"
  fi
done
```

### 2.4 Ingress / DNS

```bash
# A — Existing IngressRoute on .132 for the Vani host
kubectl -A get ingressroute -o wide | grep -i vanisetu
# Expected: an IngressRoute (likely 'vanisetu' in some namespace) routing
# to whatever currently returns 503. Capture its name + namespace; you will
# either update its 'spec.routes[].services[]' to point at the new
# vani-web Service, or delete it so the new chart-templated IngressRoute
# takes over.

# B — TLS Secret retention
kubectl get secret -A | grep -iE 'vanisetu|vani-tls'
# Expected: an existing wildcard or vanisetu certificate Secret. Note its
# namespace; the new chart MUST reuse the same Secret name to keep the cert.

# C — Prometheus IngressRoute (PRE-1 blocker per plan section 1.3)
curl -sk -o /dev/null -w '%{http_code}\n' https://prometheus.sds.local/
# Expected: 200 (or 401 if behind auth). 404 means PRE-1 is still open.
# Remediation snippet (best-guess; adjust to your actual Prometheus Service):
cat <<'EOF' > /tmp/prometheus-ingressroute.yaml
apiVersion: traefik.containo.us/v1alpha1
kind: IngressRoute
metadata:
  name: prometheus
  namespace: monitoring
spec:
  entryPoints: [websecure]
  routes:
    - match: Host(`prometheus.sds.local`)
      kind: Rule
      services:
        - name: prometheus-kube-prometheus-prometheus
          port: 9090
  tls:
    secretName: sds-local-tls   # adjust to the actual cluster TLS Secret name
EOF
# Apply after adjusting the Service name + TLS Secret name to your cluster:
#   kubectl -n monitoring apply -f /tmp/prometheus-ingressroute.yaml
# This is required only if any chart we install needs to push metrics to .132
# Prometheus. The transitional Laravel chart does NOT — metrics are deferred to
# the Stage-6 native chart. So PRE-1 can be fixed in parallel and is not on
# the critical path.
```

### 2.5 fake-parichay reachability (Cluster D pre-flight)

This step resolves **OQ-1**.

```bash
# .132 admin runs this from .132 itself (not from .17):
kubectl get pod -A | grep -i parichay
# Expected: a 'fake-parichay' (or 'parichay') Deployment + Service.

# Capture the Service ClusterIP + ports, then:
kubectl -A get svc | grep -i parichay
# and probe from inside the cluster:
kubectl run -i --rm -q probe --image=curlimages/curl --restart=Never -- \
  curl -sk --max-time 4 https://<service-name>.<namespace>.svc:<port>/.well-known/openid-configuration | head

# Report the working URL + JWK fingerprint back to the .17 operator. If the
# JWK does NOT match `/home/sds-dev/sds-fake-parichay/config/keys/`, the
# Cluster D cutover requires a keyset alignment before stopping the .17 copy.
```

### 2.6 What to do if pre-flight checks fail

| Failure | Action |
| --- | --- |
| StorageClass missing or none default | Patch one to default, or supply `--set persistence.storageClass=<name>` to `helm install` |
| Traefik missing/CRDs absent | Stop. Phase 3 cannot proceed without Traefik IngressRoutes. Escalate. |
| Namespace already exists with conflicting labels | Inspect with `kubectl get ns vani-laravel -o yaml`; if labels conflict with platform policy, talk to platform team before relabelling |
| `regcred` missing | Create per section 3.5 |
| Prometheus IngressRoute fix fails | Continue Phase 3 without it; observability rewire is a separate work item |
| fake-parichay not found on .132 | Postpone Cluster D; the directive's assumption fails. Surface as a blocker |

---

## 3. Secret seeding (per cluster)

Secrets are seeded by hand from .17-side `.env` files. **Do not commit any
secret material to git, GitLab CE, or any persistent log.** The kubectl
commands below take literal values that the .132 admin copy-pastes from a
secure channel (e.g. an out-of-band 1Password share); the placeholders in this
runbook are intentionally not real values.

### 3.1 Common — image pull secret

The CI pipeline pushes to `registry.gitlab.sds.local`. Every namespace needs
a `regcred` Secret to pull.

```bash
# Replace <REGISTRY_USER> and <REGISTRY_TOKEN> with the GitLab deploy-token
# values from /home/sds-dev/sds-bootstrap/state/gitlab-bootstrap-pat.txt
# (per memory reference_gitlab_creds.md). Do NOT copy the file contents into
# any shared output — read it locally, paste the value here, then forget it.

for ns in vani-laravel vani-uat sds-reporting; do
  kubectl -n "$ns" create secret docker-registry regcred \
    --docker-server=registry.gitlab.sds.local \
    --docker-username=<copy from gitlab-bootstrap-pat.txt header> \
    --docker-password=<copy from gitlab-bootstrap-pat.txt body> \
    --docker-email=ops@rajyasabha.digital \
    --dry-run=client -o yaml | kubectl apply -f -
done
```

### 3.2 Cluster A — `vani-app-secrets`

All values are copied from `/home/sds-dev/src/.env` on the .17 host. **The
line numbers below are the .env lines in the .17 working tree as of plan
rev 0.1**; verify before copying.

```bash
kubectl -n vani-laravel create secret generic vani-app-secrets \
  --from-literal=app-key='<copy from src/.env line 3 (APP_KEY)>' \
  --from-literal=db-password='<copy from src/.env line 29 (DB_PASSWORD)>' \
  --from-literal=meili-master-key='<copy from src/.env (MEILI_MASTER_KEY)>' \
  --from-literal=reverb-app-key='<copy from src/.env (REVERB_APP_KEY)>' \
  --from-literal=reverb-app-secret='<copy from src/.env (REVERB_APP_SECRET)>' \
  --from-literal=asr-ingest-secret='<copy from src/.env line 11 (ASR_INGEST_SECRET)>' \
  --from-literal=realtime-audit-secret='<copy from src/.env (REALTIME_AUDIT_SECRET)>' \
  --from-literal=sarvam-api-key='<copy from src/.env line 127-129 (SARVAM_API_KEY)>' \
  --from-literal=bootstrap-admin-password='<copy from src/.env line 32 (BOOTSTRAP_ADMIN_PASSWORD)>' \
  --dry-run=client -o yaml | kubectl apply -f -
```

Additional supporting secrets for Cluster A:

```bash
# MinIO consumer creds (for the app to read/write the four ADR-VANI-5 buckets)
kubectl -n vani-laravel create secret generic vani-minio-app \
  --from-literal=access-key='<copy from src/.env (AWS_ACCESS_KEY_ID)>' \
  --from-literal=secret-key='<copy from src/.env (AWS_SECRET_ACCESS_KEY)>' \
  --dry-run=client -o yaml | kubectl apply -f -

# SMTP (only if mail flows are wired in this chart; otherwise skip)
kubectl -n vani-laravel create secret generic vani-mail-app \
  --from-literal=smtp-password='<copy from src/.env (MAIL_PASSWORD)>' \
  --dry-run=client -o yaml | kubectl apply -f -

# SMS-GOV bridge (only if SMS sending is enabled in this chart)
kubectl -n vani-laravel create secret generic vani-sms-gov \
  --from-literal=key='<copy from src/.env (SMS_GOV_KEY)>' \
  --dry-run=client -o yaml | kubectl apply -f -
```

### 3.3 Cluster B — `vani-uat-app-secrets`

UAT secrets are **distinct values** from stable (per OQ-15; recommended:
regenerate). Source is the `UAT_*`-prefixed keys in `/home/sds-dev/src/.env`.

```bash
kubectl -n vani-uat create secret generic vani-uat-app-secrets \
  --from-literal=app-key='<copy from src/.env (UAT_APP_KEY)>' \
  --from-literal=db-password='<copy from src/.env (UAT_DB_PASSWORD)>' \
  --from-literal=meili-master-key='<copy from src/.env (UAT_MEILI_MASTER_KEY)>' \
  --from-literal=reverb-app-key='<copy from src/.env (UAT_REVERB_APP_KEY)>' \
  --from-literal=reverb-app-secret='<copy from src/.env (UAT_REVERB_APP_SECRET)>' \
  --from-literal=asr-ingest-secret='<copy from src/.env (UAT_ASR_INGEST_SECRET or shared)>' \
  --from-literal=realtime-audit-secret='<copy from src/.env (UAT_REALTIME_AUDIT_SECRET or shared)>' \
  --from-literal=sarvam-api-key='<copy from ml-gateway/.env (SARVAM_API_KEY)>' \
  --dry-run=client -o yaml | kubectl apply -f -
```

### 3.4 Cluster C — `superset-app-secrets`

Source is `/home/sds-dev/sds-reporting-engine/.env`.

```bash
kubectl -n sds-reporting create secret generic superset-app-secrets \
  --from-literal=secret-key='<copy from sds-reporting-engine/.env (SUPERSET_SECRET_KEY)>' \
  --from-literal=guest-token-jwt-secret='<copy from sds-reporting-engine/.env (SUPERSET_GUEST_TOKEN_JWT_SECRET)>' \
  --from-literal=admin-password='<copy from sds-reporting-engine/.env (SUPERSET_ADMIN_PASSWORD)>' \
  --from-literal=db-password='<copy from sds-reporting-engine/.env (SUPERSET_POSTGRES_PASSWORD)>' \
  --dry-run=client -o yaml | kubectl apply -f -

# Tijori MinIO creds — only if report-pipeline writes PDFs to Tijori MinIO
# (OQ-16). If the destination is platform minio.sds.local instead, swap the
# alias name accordingly.
kubectl -n sds-reporting create secret generic tijori-minio-app \
  --from-literal=access-key='<copy from sds-reporting-engine/.env (TIJORI_MINIO_ACCESS_KEY)>' \
  --from-literal=secret-key='<copy from sds-reporting-engine/.env (TIJORI_MINIO_SECRET_KEY)>' \
  --dry-run=client -o yaml | kubectl apply -f -
```

### 3.5 Verification after secret seeding

```bash
for ns in vani-laravel vani-uat sds-reporting; do
  echo "--- $ns ---"
  kubectl -n "$ns" get secret
done
# Expected: regcred + each cluster's per-component Secret(s) listed.
# Never `kubectl get secret -o yaml` to a shared screen — values are base64
# but trivially decodable.
```

---

## 4. Per-cluster execution order

Execute strictly **A → C → B → D**. Do not start a cluster until the prior
cluster's verification (section 5) has passed.

### 4.1 Cluster A — Vani Setu stable

The `.17` operator runs the data-dump scripts; the .132 admin runs the
Helm install and the data restore.

```bash
# ---- .17 side (operator runs these) -----------------------------------
# 1. Drain the audit queue and Horizon:
docker exec vani-setu-worker php artisan horizon:terminate
# wait, then:
docker exec vani-setu-app php artisan queue:size redis --queue=audit  # must be 0

# 2. Capture pre-cutover audit chain head:
docker exec vani-setu-app php artisan audit:verify-chain  # record hash

# 3. Dump Postgres:
/home/sds-dev/scripts/migration/phase3/10-vani-pgdump.sh --apply

# 4. Mirror MinIO buckets (or skip if corpus is empty per OQ-6):
/home/sds-dev/scripts/migration/phase3/12-vani-minio-mirror.sh --apply

# 5. Hand off /tmp/vani_setu.dump to .132 admin out-of-band.

# ---- .132 side (admin runs these) -------------------------------------
# 1. Helm install the transitional chart:
helm upgrade --install vani-laravel \
  /home/sds-dev/sds-monorepo/deploy/vani-setu-laravel/helm/vani-setu-laravel/ \
  --namespace vani-laravel \
  --values /home/sds-dev/sds-monorepo/deploy/vani-setu-laravel/helm/vani-setu-laravel/values.yaml \
  --set image.app.tag=<current-CI-built-SHA> \
  --set image.web.tag=<current-CI-built-SHA> \
  --wait --timeout 10m

# 2. Wait for Postgres pod to be Ready:
kubectl -n vani-laravel rollout status statefulset/vani-postgres

# 3. Restore Postgres from the dump:
#    Option A: kubectl mode (recommended; the .132 admin streams the dump in)
kubectl cp /tmp/vani_setu.dump vani-laravel/vani-postgres-0:/tmp/vani_setu.dump
kubectl -n vani-laravel exec -i vani-postgres-0 -- \
  pg_restore --clean --if-exists --no-owner --no-acl -j 2 \
  -U vani -d vani_setu /tmp/vani_setu.dump

#    Option B: pipe in directly without copying (uses the .17 script):
/home/sds-dev/scripts/migration/phase3/11-vani-pgrestore.sh \
  --target-pod vani-postgres-0 \
  --target-namespace vani-laravel \
  --target-db vani_setu --target-user vani \
  --dump /tmp/vani_setu.dump --apply

# 4. Run migrations (chart includes a post-install Hook Job; if not, force):
kubectl -n vani-laravel rollout status deployment/vani-app
kubectl -n vani-laravel exec deploy/vani-app -- php artisan migrate --force

# 5. Reseed audit genesis row (idempotent — the chart Job 'audit-genesis'
#    should already have run). Verify chain matches pre-cutover capture:
kubectl -n vani-laravel exec deploy/vani-app -- php artisan audit:verify-chain
# Compare with the hash captured on .17. Must match.

# 6. Swap the IngressRoute upstream OR delete the old IngressRoute so the
#    chart-templated one takes over:
kubectl -A get ingressroute | grep vanisetu
# Take note. If the old IR is in a different namespace, delete it:
kubectl -n <old-ns> delete ingressroute <old-name>
# Verify the chart's IR is serving:
kubectl -n vani-laravel get ingressroute
```

### 4.2 Cluster C — SDS Reporting Engine

Hard dependency: Cluster A's Postgres must be reachable cross-namespace at
`vani-postgres.vani-laravel.svc.cluster.local:5432` (verify with a probe pod
before starting). Resolve OQ-16 (Tijori MinIO endpoint) before this step.

```bash
# ---- .17 side --------------------------------------------------------
# 1. Stop Celery beat first to avoid duplicate firings during dump:
docker stop sds-reporting-beat

# 2. Dump Superset metadata DB:
/home/sds-dev/scripts/migration/phase3/30-reporting-pgdump.sh --apply

# 3. Hand off /tmp/sds_reporting_superset.dump to .132 admin.

# ---- .132 side -------------------------------------------------------
# 1. Pre-flight: confirm Vani Postgres is reachable from sds-reporting ns:
kubectl run -i --rm -q probe -n sds-reporting --image=curlimages/curl --restart=Never -- \
  curl -sk --max-time 4 vani-postgres.vani-laravel.svc.cluster.local:5432 || true
# (TCP probe will return non-2xx; what we want is no DNS error.)

# 2. Helm install the reporting chart:
helm upgrade --install sds-reporting \
  /home/sds-dev/sds-monorepo/deploy/sds-reporting/helm/sds-reporting/ \
  --namespace sds-reporting \
  --values /home/sds-dev/sds-monorepo/deploy/sds-reporting/helm/sds-reporting/values.yaml \
  --set image.superset.tag=<pinned-tag> \
  --wait --timeout 10m

# 3. Restore Superset metadata:
kubectl cp /tmp/sds_reporting_superset.dump sds-reporting/superset-postgres-0:/tmp/dump
kubectl -n sds-reporting exec -i superset-postgres-0 -- \
  pg_restore --clean --if-exists --no-owner --no-acl -j 2 \
  -U superset -d superset /tmp/dump

# 4. Re-run Superset init (idempotent — should already be a post-install hook):
kubectl -n sds-reporting create job --from=cronjob/superset-init \
  "superset-init-$(date +%s)" 2>/dev/null || \
  kubectl -n sds-reporting exec deploy/superset-web -- superset init
```

### 4.3 Cluster B — UAT Vani

```bash
# ---- .17 side --------------------------------------------------------
# 0. UAT containers have restart: no — they are Exited(255) after host reboot.
#    Bring up the supporting datastores BEFORE dumping. Skip uat-app/-worker/-web
#    so we don't reactivate the queue while preparing the dump.
cd /home/sds-dev
docker compose -f docker-compose.uat.yml up -d uat-postgres uat-redis uat-meilisearch
# Wait ~10s for Postgres to accept connections:
docker exec sds-dev-uat-postgres-1 pg_isready -U vani -d vani_setu_uat

# 1. Drain Horizon on the UAT worker (only if uat-worker was already running):
docker exec sds-dev-uat-worker-1 php artisan horizon:terminate || true

# 2. Dump UAT Postgres:
/home/sds-dev/scripts/migration/phase3/20-uat-pgdump.sh --apply

# 3. (Optional) Mirror UAT MinIO buckets if any UAT-specific buckets exist:
/home/sds-dev/scripts/migration/phase3/22-uat-minio-mirror.sh --apply

# 4. Hand off /tmp/vani_setu_uat.dump.

# ---- .132 side -------------------------------------------------------
# 1. Add UAT hostname to your DNS / /etc/hosts on consumer machines:
#    10.21.217.132  uat.vanisetu.rajyasabha.digital
#    (The .17 operator must also add this row to /etc/hosts on .17.)

# 2. Helm install (re-use Cluster A chart with the UAT values overlay):
helm upgrade --install vani-uat \
  /home/sds-dev/sds-monorepo/deploy/vani-setu-laravel/helm/vani-setu-laravel/ \
  --namespace vani-uat \
  --values /home/sds-dev/sds-monorepo/deploy/vani-setu-laravel/helm/vani-setu-laravel/values-uat.yaml \
  --set image.app.tag=<pinned-tag> \
  --set image.web.tag=<pinned-tag> \
  --wait --timeout 10m

# 3. Restore UAT Postgres:
kubectl cp /tmp/vani_setu_uat.dump vani-uat/vani-uat-postgres-0:/tmp/dump
kubectl -n vani-uat exec -i vani-uat-postgres-0 -- \
  pg_restore --clean --if-exists --no-owner --no-acl -j 2 \
  -U vani -d vani_setu_uat /tmp/dump

# 4. cert-manager Certificate for uat.vanisetu.rajyasabha.digital should be
#    issued automatically by the chart's templated Certificate. Verify:
kubectl -n vani-uat get certificate
# Expected: Ready=True (may take 1–3 minutes for DNS-01).
```

### 4.4 Cluster D — fake-parichay retirement

Cluster D is **not a Helm install** — it is a retirement of the .17 copy
after consumers have been pointed at the .132 instance.

```bash
# ---- .132 side -------------------------------------------------------
# 1. Confirm .132 fake-parichay is up and JWK matches the .17 copy.
#    (Run section 2.5 first to capture the working URL + JWK fingerprint.)
kubectl -n <parichay-ns> get pod
kubectl -n <parichay-ns> exec deploy/fake-parichay -- \
  cat /srv/config/keys/jwks.json | jq -r '.keys[0].kid'
# Hand the kid + URL to the .17 operator.

# ---- .17 side --------------------------------------------------------
# 2. The .17 operator runs the discovery script and the env rewriter, then:
docker stop sds-fake-parichay
# 3. Optionally remove the container; the compose file is kept on disk for
#    rollback.
```

---

## 5. Verification after each cluster

Run **all** checks per cluster. Any FAIL is a stop-the-world.

### 5.1 Cluster A verification

```bash
# A — pods Ready
kubectl -n vani-laravel get pod
# Expected: vani-app, vani-web (x2), vani-worker, vani-audit, vani-reverb,
# vani-postgres-0, vani-redis-0, vani-meilisearch-0 all Running
# (worker may be CrashLoopBackOff briefly during boot — wait 2 minutes).
# NB: no vani-mongo-0 — Mongo was dropped from scope in plan rev 0.2.

# B — readiness probes
kubectl -n vani-laravel get deploy
# All Available replicas = desired.

# C — logs spot-check
kubectl -n vani-laravel logs deploy/vani-app --tail=50 | grep -iE 'error|fatal' || \
  echo "no errors in last 50 lines"
kubectl -n vani-laravel logs deploy/vani-worker --tail=50 | grep -iE 'error|fatal' || \
  echo "no errors"
kubectl -n vani-laravel logs deploy/vani-audit --tail=50 | grep -iE 'error|fatal' || \
  echo "no errors"

# D — HTTP probes from inside the cluster
kubectl run -i --rm -q probe -n vani-laravel --image=curlimages/curl --restart=Never -- \
  curl -sk --max-time 4 -o /dev/null -w '%{http_code}\n' http://vani-web/healthz
# Expected: 200

# E — HTTP probes from outside (the .132 admin from their workstation, or
#    the .17 operator):
curl -sk --max-time 4 -o /dev/null -w '%{http_code}\n' https://vanisetu.rajyasabha.digital/healthz
# Expected: 200

# F — WebSocket (Reverb)
# Quick subscribe test from a browser console at https://vanisetu.rajyasabha.digital/
# Open dev tools, watch the /app/<key> WS handshake — expect 101 Switching Protocols.

# G — audit chain integrity
kubectl -n vani-laravel exec deploy/vani-app -- php artisan audit:verify-chain
# Expected: chain head hash matches the pre-cutover capture.

# H — login smoke
# Browser: navigate to https://vanisetu.rajyasabha.digital/login
# Sign in with the BOOTSTRAP_ADMIN credentials. Confirm dashboard loads.
```

### 5.2 Cluster C verification

```bash
kubectl -n sds-reporting get pod
# Expected: superset-web, superset-worker, superset-beat (x1 only!),
# superset-postgres-0, superset-redis-0, gotenberg, report-pipeline all Running.

# HTTP probe (whichever hostname OQ-17 settles on):
curl -sk --max-time 4 -o /dev/null -w '%{http_code}\n' https://reports.sds.local/health
# Expected: 200

# Beat singleton check (CRITICAL — two beats = duplicate firings):
kubectl -n sds-reporting get deploy superset-beat -o jsonpath='{.spec.replicas}'
# Expected: 1

# Spot-check: log into Superset, confirm dashboards from .17 are present.
```

### 5.3 Cluster B verification

```bash
kubectl -n vani-uat get pod
# Expected: same shape as Cluster A, single replicas.

curl -sk --max-time 4 -o /dev/null -w '%{http_code}\n' \
  https://uat.vanisetu.rajyasabha.digital/healthz
# Expected: 200 (after the new /etc/hosts entry on the probing host).

# Certificate
kubectl -n vani-uat get certificate
# Expected: Ready=True
```

### 5.4 Cluster D verification

```bash
# All consumers updated to PARICHAY_BASE_URL=<.132 URL>:
grep -R PARICHAY_BASE_URL /home/sds-dev/src/.env
# Expected: points at the .132 endpoint, not fake-parichay.sds.local:8443.

# Functional test: drive a Parichay login flow end-to-end (from the Vani app
# now on .132). Confirm an access token is issued and validated.
docker ps | grep sds-fake-parichay || echo "OK: .17 fake-parichay stopped"
```

---

## 6. Rollback per cluster

Rollback is per-cluster, not whole-Phase. If Cluster A rolls back, Clusters
B/C/D cascade-rollback only if they had already cut over.

### 6.1 Cluster A rollback

```bash
# ---- .132 side -------------------------------------------------------
# 1. Scale down the new Vani workloads so the IngressRoute returns 503/502
#    rather than serving partial state:
kubectl -n vani-laravel scale deploy/vani-web    --replicas=0
kubectl -n vani-laravel scale deploy/vani-app    --replicas=0
kubectl -n vani-laravel scale deploy/vani-worker --replicas=0
kubectl -n vani-laravel scale deploy/vani-audit  --replicas=0
kubectl -n vani-laravel scale deploy/vani-reverb --replicas=0

# 2. Optional helm rollback to a prior revision (only if a prior revision
#    existed; first install has none):
helm history vani-laravel -n vani-laravel
helm rollback vani-laravel <REV> -n vani-laravel    # if applicable
# Or, if Cluster A is the very first install, uninstall:
helm uninstall vani-laravel -n vani-laravel
kubectl -n vani-laravel delete pvc --all   # ONLY if you're sure (destroys data)

# 3. Re-point or remove the IngressRoute so the old .17 backend serves again
#    via the Phase 1 hostname-revert (see step 4).
kubectl -n vani-laravel delete ingressroute vani-setu  # whichever name

# ---- .17 side --------------------------------------------------------
# 4. Restore Phase 1 /etc/hosts so vanisetu.rajyasabha.digital → .17 again:
/home/sds-dev/scripts/migration/phase3/90-rollback-vani.sh --apply

# 5. The dump artefact /tmp/vani_setu.dump (or /home/sds-dev/migration-artifacts/)
#    is the rollback baseline. Restore on .17 ONLY if Postgres on .17 has
#    diverged during the cutover window:
docker exec -i vani-setu-postgres pg_restore \
  --clean --if-exists --no-owner --no-acl -j 2 -U vani -d vani_setu < /tmp/vani_setu.dump

# 6. Verify audit chain head matches the pre-cutover capture:
docker exec vani-setu-app php artisan audit:verify-chain
```

### 6.2 Cluster C rollback

```bash
# ---- .132 side -------------------------------------------------------
kubectl -n sds-reporting scale deploy/superset-web    --replicas=0
kubectl -n sds-reporting scale deploy/superset-worker --replicas=0
kubectl -n sds-reporting scale deploy/superset-beat   --replicas=0
helm rollback sds-reporting <REV> -n sds-reporting || helm uninstall sds-reporting -n sds-reporting

# ---- .17 side --------------------------------------------------------
cd /home/sds-dev/sds-reporting-engine && docker compose up -d
# Dump artefact for forward-roll if needed: /tmp/sds_reporting_superset.dump
```

### 6.3 Cluster B rollback

```bash
# ---- .132 side -------------------------------------------------------
helm rollback vani-uat <REV> -n vani-uat || helm uninstall vani-uat -n vani-uat

# ---- .17 side --------------------------------------------------------
# Remove the uat.vanisetu.rajyasabha.digital row from /etc/hosts.
cd /home/sds-dev && docker compose -f docker-compose.uat.yml up -d
# Dump artefact: /tmp/vani_setu_uat.dump
```

### 6.4 Cluster D rollback

```bash
# Restart the .17 copy:
cd /home/sds-dev/sds-fake-parichay && docker compose up -d
# Revert consumer env: PARICHAY_BASE_URL=https://fake-parichay.sds.local:8443
# (or whatever pre-cutover URL applies).
```

---

## 7. CI guard (OQ-9)

The Phase 3 plan flags this as **High severity**: the GitLab CI `deploy:prod`
job at `/home/sds-dev/.gitlab-ci.yml` line 148 runs `docker compose up` on
the .17 shell-runner, which would **resurrect the retired Vani stack** the
moment the next pipeline runs.

The CI guard must land **before** Cluster A cutover begins.

### 7.1 Exact diff to apply

Open `/home/sds-dev/.gitlab-ci.yml` on .17. Locate the `deploy:prod` job
(near line 148 per the plan). Apply the following diff (the exact preceding
context will vary; the load-bearing change is the `when: never` line and the
comment, or the rewrite to `helm upgrade`).

**Option A — gate the job (fastest, no rewrite):**

```diff
 deploy:prod:
   stage: deploy
   tags:
     - vani-prod-deploy
+  # Phase 3: docker compose on .17 retired in favour of helm on .132.
+  # Gating with when:never until OQ-9 resolves with the helm-upgrade rewrite.
+  when: never
   script:
     - cd /home/sds-dev
     - docker compose pull
     - docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

**Option B — rewrite to `helm upgrade` (proper fix; requires kubeconfig on
the runner):**

```diff
 deploy:prod:
   stage: deploy
-  tags:
-    - vani-prod-deploy
+  tags:
+    - vani-132-deploy   # new shell-runner tag, registered on .132
   script:
-    - cd /home/sds-dev
-    - docker compose pull
-    - docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
+    - helm upgrade --install vani-laravel \
+        /home/sds-dev/sds-monorepo/deploy/vani-setu-laravel/helm/vani-setu-laravel/ \
+        --namespace vani-laravel \
+        --set image.app.tag=$CI_COMMIT_SHA \
+        --set image.web.tag=$CI_COMMIT_SHA \
+        --wait --timeout 10m
```

Option B requires a runner with kubectl/helm against .132. If no such runner
exists yet, **use Option A** before Cluster A cutover; promote to Option B
after Stage-6 wiring lands.

### 7.2 Verification

After committing the diff and pushing:

```bash
# Run the .17-side preflight; it has a check for OQ-9:
/home/sds-dev/scripts/migration/phase3/00-preflight.sh
# Look for: [ OK ] deploy:prod appears gated or rewritten to helm upgrade

# Or inspect the next pipeline:
# GitLab UI → Pipelines → confirm deploy:prod is 'manual' / skipped / runs helm.
```

---

## 8. Open questions still blocking

These items must be answered before, during, or in parallel with the cutover.
Numbering matches `MIGRATION_PHASE3_PLAN.md` section 8.

| OQ | Question | Owner | Blocks |
| --- | --- | --- | --- |
| OQ-1 | What is the correct host:port for fake-parichay on .132? Rev 0.2 probe from .17 confirmed unreachable on 8443/18443/443 and on every candidate hostname; only the .132 admin can settle this. | .132 admin | Cluster D |
| OQ-2 | Which StorageClass on .132 for the StatefulSet PVCs (Postgres/Redis/Meili) — local-path or longhorn? (Mongo dropped v0.2.) | .132 admin | All three StatefulSet-bearing clusters |
| ~~OQ-3~~ | **RESOLVED 0.2 — retire recommendation.** UAT stack `restart: no` + not resurrected since reboot 21h ago = signal of inactive dev. Override required only if active UAT-frontend dev work is planned within 7 days. | (resolved) | (no longer blocking) |
| OQ-4 | Which MinIO is authoritative post-migration for the Laravel `AWS_*` keys? Confirm bucket-name + presigned-URL host policy. | Software Manager | Cluster A `vani-minio-app` Secret + env-rewrite |
| OQ-6 | Are the four ADR-VANI-5 buckets already provisioned on `minio.sds.local`? | .132 admin | Cluster A `mc mirror` step |
| OQ-8 | Does `ml-gateway` move to .132 (folded into the chart) or stay on .17? Decides whether `VANI_API_URL` is cluster-internal DNS or the public hostname. | Software Manager | Cluster A chart values + Cluster B chart values |
| OQ-9 | Gate `deploy:prod`? See section 7 above. | Software Manager + .17 operator | Cluster A cutover (pre-condition) |
| OQ-10 | Confirm external DNS (`rajyasabha.digital` zone, GoDaddy) for `vanisetu.rajyasabha.digital` resolves to the .132 platform VIP — not the .17 dev host. | Software Manager | External access only; LAN-side already works via /etc/hosts |
| OQ-16 | Tijori-MinIO endpoint for the report-pipeline: stay on .17 Tijori or swap to `minio.sds.local`? | Software Manager + .132 admin | Cluster C `tijori-minio-app` Secret |
| OQ-17 | Reporting hostname: `reports.sds.local` (private) or `sds-reporting.rajyasabha.digital` (public)? | Software Manager | Cluster C IngressRoute + Certificate |

### 8.1 Pre-flight blockers the .132 admin must close before Cluster A starts

- **OQ-2** (StorageClass) — set a default SC, or capture the chosen SC name to pass via `--set persistence.storageClass=<name>`.
- **OQ-6** (bucket names on platform MinIO) — create the four ADR-VANI-5 buckets if missing.
- **OQ-9** (CI guard) — close per section 7.
- **Section 2.4 C** (Prometheus IngressRoute) — not a hard blocker for Cluster A (no metrics push from the transitional chart), but should be closed before Stage-6 lands.

---

## 9. Sign-off

- [ ] Software Manager (Kushal Pathak) — DRAFT reviewed; OQs above answered; approved to execute
- [ ] .132 platform admin — Sections 2 (pre-flight) and 3 (secret seeding) attested complete; ready to receive dump files
- [ ] .17 operator (executing session) — has both the APPROVED `MIGRATION_PHASE3_PLAN.md` and this APPROVED runbook; companion scripts located under `/home/sds-dev/scripts/migration/phase3/`
- [ ] Phase closure — every cluster's verification (section 5) ledger captured; CI guard (section 7) in place; rollback artefacts retained per OQ-11 retention window

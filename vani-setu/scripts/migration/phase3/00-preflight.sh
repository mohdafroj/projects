#!/usr/bin/env bash
#
# 00-preflight.sh
#
# Phase 3 pre-flight read-only checks on the .17 side.
# Verifies that source containers are running, persistent volumes exist,
# expected hostnames resolve, and there is enough free disk space for
# dumps under /tmp.
#
# This script is read-only by design and has NO --apply flag.
#
# Refs: docs/MIGRATION_PHASE3_PLAN.md sections 1.4, 1.5, 2.7, 3.7, 4.7, 5.7

set -euo pipefail

# --- usage -------------------------------------------------------------------
usage() {
  cat <<'EOF'
Usage: 00-preflight.sh [--help]

Runs read-only checks on the .17 (dev host) side before Phase 3 cutover.
Exits 0 if all checks pass; non-zero if a hard requirement is missing.

  --help    Show this help and exit.
EOF
}

case "${1:-}" in
  --help|-h) usage; exit 0 ;;
  "" ) ;;
  * ) echo "unknown arg: $1" >&2; usage; exit 2 ;;
esac

# --- logging ------------------------------------------------------------------
TS="$(date -u +%Y%m%dT%H%M%SZ)"
LOG_DIR="/var/log/phase3"
if ! mkdir -p "$LOG_DIR" 2>/dev/null; then
  LOG_DIR="${HOME}/phase3-logs"
  mkdir -p "$LOG_DIR"
fi
LOG_FILE="${LOG_DIR}/00-preflight-${TS}.log"
# tee everything to log
exec > >(tee -a "$LOG_FILE") 2>&1
echo "=== 00-preflight.sh started ${TS} ==="
echo "log: $LOG_FILE"

# --- check helpers ------------------------------------------------------------
fail_count=0
warn_count=0

ok()    { echo "[ OK ] $*"; }
warn()  { echo "[WARN] $*"; warn_count=$((warn_count+1)); }
fail()  { echo "[FAIL] $*"; fail_count=$((fail_count+1)); }

check_container() {
  local name="$1"
  if docker ps --format '{{.Names}}' | grep -qx "$name"; then
    ok "container running: $name"
    return
  fi
  # Container is not running; distinguish "intentionally stopped" (restart: no
  # — typical for UAT stack and the orphan Mongo) from "truly missing".
  if docker ps -a --format '{{.Names}}' | grep -qx "$name"; then
    local policy
    policy="$(docker inspect --format '{{.HostConfig.RestartPolicy.Name}}' "$name" 2>/dev/null || echo unknown)"
    if [[ "$policy" == "no" ]]; then
      warn "container stopped (restart: no, intentional): $name — \`docker compose up -d $name\` before any dump that targets it"
    else
      fail "container present but not running ($policy): $name"
    fi
  else
    fail "container missing or not running: $name"
  fi
}

check_volume() {
  local name="$1"
  if docker volume ls --format '{{.Name}}' | grep -qx "$name"; then
    ok "volume present: $name"
  else
    fail "volume missing: $name"
  fi
}

check_host_resolves() {
  local host="$1" expect="${2:-}"
  local got
  got="$(getent hosts "$host" | awk '{print $1; exit}')" || got=""
  if [[ -z "$got" ]]; then
    fail "hostname does not resolve: $host"
    return
  fi
  if [[ -n "$expect" && "$got" != "$expect" ]]; then
    warn "hostname $host resolves to $got (expected $expect)"
  else
    ok "hostname $host -> $got"
  fi
}

check_disk_free() {
  local path="$1" min_mb="$2"
  local got_mb
  got_mb="$(df -Pm "$path" | awk 'NR==2 {print $4}')"
  if [[ -z "$got_mb" ]]; then
    fail "could not measure free space at $path"
    return
  fi
  if (( got_mb < min_mb )); then
    fail "low disk at $path: ${got_mb}MB free (need ${min_mb}MB)"
  else
    ok "disk at $path: ${got_mb}MB free"
  fi
}

# --- 1. .17 side containers ---------------------------------------------------
echo ""
echo "--- containers (Cluster A: Vani stable) ---"
# Mongo dropped v0.2 — orphan with no consumer; see plan §1.5 / OQ-7 resolution.
for c in vani-setu-app vani-setu-worker vani-setu-audit vani-setu-reverb \
         vani-setu-web vani-setu-caddy vani-setu-postgres vani-setu-redis \
         vani-setu-meilisearch vani-setu-minio \
         vani-setu-ml-gateway vani-setu-realtime-sidecar; do
  check_container "$c"
done

echo ""
echo "--- containers (Cluster B: UAT) ---"
# UAT containers have restart: no — they will WARN, not FAIL, when stopped.
# Operator must `docker compose -f docker-compose.uat.yml up -d <svc>` before
# running 20-uat-pgdump.sh.
for c in sds-dev-uat-app-1 sds-dev-uat-worker-1 sds-dev-uat-reverb-1 \
         sds-dev-uat-web-1 sds-dev-uat-ml-gateway-1 sds-dev-uat-postgres-1 \
         sds-dev-uat-redis-1 sds-dev-uat-meilisearch-1 \
         sds-dev-uat-realtime-sidecar-1 sds-dev-uat-caddy-1; do
  check_container "$c"
done

echo ""
echo "--- containers (Cluster C: Reporting) ---"
for c in sds-reporting-superset sds-reporting-worker sds-reporting-beat \
         sds-reporting-postgres sds-reporting-redis sds-reporting-gotenberg \
         sds-reporting-pipeline; do
  check_container "$c"
done

echo ""
echo "--- containers (Cluster D: fake-parichay on .17) ---"
check_container sds-fake-parichay

# --- 2. persistent volumes ----------------------------------------------------
echo ""
echo "--- persistent volumes ---"
for v in sds-dev_postgres_data sds-dev_postgres_uat_data \
         sds-dev_minio-data \
         sds-dev_meilisearch-data sds-dev_meilisearch-uat-data \
         sds-dev_redis-data sds-dev_redis-uat-data \
         sds-reporting_superset_db_data sds-reporting_superset_redis_data; do
  check_volume "$v"
done
# sds-dev_mongodb-data + sds-dev_mongodb-uat-data are residual on .17 and
# are intentionally NOT checked — Mongo dropped from scope v0.2.

# --- 3. hostnames -------------------------------------------------------------
echo ""
echo "--- hostnames (Phase 1 DNS cutover should map *.rajyasabha.digital to .132) ---"
check_host_resolves vanisetu.rajyasabha.digital 10.21.217.132
check_host_resolves tijorisetu.rajyasabha.digital
check_host_resolves gitlab.sds.local
# UAT hostname is NOT yet in /etc/hosts (per plan section 3.5)
if getent hosts uat.vanisetu.rajyasabha.digital >/dev/null; then
  ok "uat.vanisetu.rajyasabha.digital already resolves (Phase 3 Cluster B step)"
else
  warn "uat.vanisetu.rajyasabha.digital does not resolve yet (expected; add in Cluster B)"
fi

# --- 4. disk space (dumps go to /tmp) ----------------------------------------
echo ""
echo "--- free disk space (dumps land in /tmp) ---"
check_disk_free /tmp 500        # 500MB safety margin for all three dumps
check_disk_free /home/sds-dev 1024

# --- 5. tooling presence ------------------------------------------------------
echo ""
echo "--- tooling ---"
for bin in docker pg_dump mc curl getent awk; do
  if command -v "$bin" >/dev/null 2>&1; then
    ok "tool present: $bin"
  else
    # pg_dump and mc may legitimately live only inside containers; downgrade
    case "$bin" in
      pg_dump|mc) warn "tool not on host PATH: $bin (will use docker exec)" ;;
      *) fail "tool missing: $bin" ;;
    esac
  fi
done

# --- 6. .17 K3s context warning (memory note: kubectl on .17 is wrong cluster) ---
echo ""
echo "--- kubectl context safety ---"
if command -v kubectl >/dev/null 2>&1; then
  ctx="$(kubectl config current-context 2>/dev/null || true)"
  if [[ -n "$ctx" ]]; then
    warn "kubectl current-context is '$ctx' on this host — .132 K3s is NOT reachable from here. Do not run kubectl commands during cutover; hand off to .132 admin."
  else
    ok "no kubectl current-context set"
  fi
else
  ok "kubectl absent — good (cutover is .132-admin-driven)"
fi

# --- 7. CI guard probe (OQ-9) -------------------------------------------------
echo ""
echo "--- CI deploy:prod guard (OQ-9) ---"
ci_file="/home/sds-dev/.gitlab-ci.yml"
if [[ -f "$ci_file" ]]; then
  if grep -qE "deploy:prod" "$ci_file"; then
    if grep -qE "when:\s*never" "$ci_file" || grep -qE "helm upgrade" "$ci_file"; then
      ok "deploy:prod appears gated or rewritten to helm upgrade"
    else
      warn "deploy:prod still appears to run 'docker compose up' on .17 — gate per OQ-9 before cutover"
    fi
  else
    ok "no deploy:prod block found in .gitlab-ci.yml"
  fi
else
  warn "$ci_file not found"
fi

# --- summary ------------------------------------------------------------------
echo ""
echo "=== SUMMARY ==="
echo "warnings: $warn_count"
echo "failures: $fail_count"
echo "log:      $LOG_FILE"

if (( fail_count > 0 )); then
  exit 1
fi
exit 0

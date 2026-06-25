#!/usr/bin/env bash
#
# 90-rollback-vani.sh
#
# Rollback the Vani Cluster A cutover. Brings the .17 docker-compose stack
# back up, restores the Phase 1 /etc/hosts backup, and documents the
# Postgres restore path.
#
# DRY-RUN BY DEFAULT.
#
# Refs: docs/MIGRATION_PHASE3_PLAN.md section 2.6

set -euo pipefail

COMPOSE_FILE="${PHASE3_COMPOSE_FILE:-/home/sds-dev/docker-compose.yml}"
HOSTS_BACKUP="${PHASE3_HOSTS_BACKUP:-/etc/hosts.bak.phase1-cutover-20260523}"
DUMP_PATH="${VANI_DUMP_IN:-/tmp/vani_setu.dump}"

# Set of services to resurrect (mirrors plan section 2.6).
# Mongo dropped v0.2 — orphan with no service block in compose; see plan §1.5.
SERVICES=(
  app web worker audit reverb caddy
  postgres redis meilisearch minio realtime-sidecar
)

APPLY=0
SKIP_HOSTS=0
SKIP_COMPOSE=0

usage() {
  cat <<EOF
Usage: 90-rollback-vani.sh [--apply] [options]

DRY-RUN BY DEFAULT.

Steps the rollback will perform with --apply:
  1. Restore /etc/hosts from $HOSTS_BACKUP (so vanisetu.rajyasabha.digital
     resolves to .17 again).
  2. docker compose -f $COMPOSE_FILE up -d ${SERVICES[*]}
  3. Print Postgres restore-from-dump instructions ($DUMP_PATH).

Options:
  --apply         actually execute
  --skip-hosts    don't restore /etc/hosts
  --skip-compose  don't run docker compose up
  --help
EOF
}

while (( $# )); do
  case "$1" in
    --apply) APPLY=1; shift ;;
    --skip-hosts)   SKIP_HOSTS=1; shift ;;
    --skip-compose) SKIP_COMPOSE=1; shift ;;
    --help|-h) usage; exit 0 ;;
    *) echo "unknown arg: $1" >&2; usage; exit 2 ;;
  esac
done

TS="$(date -u +%Y%m%dT%H%M%SZ)"
LOG_DIR="/var/log/phase3"
if ! mkdir -p "$LOG_DIR" 2>/dev/null; then
  LOG_DIR="${HOME}/phase3-logs"; mkdir -p "$LOG_DIR"
fi
LOG_FILE="${LOG_DIR}/90-rollback-vani-${TS}.log"
exec > >(tee -a "$LOG_FILE") 2>&1

echo "=== 90-rollback-vani.sh ${TS} ==="
echo "apply=$APPLY compose=$COMPOSE_FILE hosts_backup=$HOSTS_BACKUP"

# --- step 1: /etc/hosts -----------------------------------------------------
echo ""
echo "--- step 1: restore /etc/hosts ---"
if (( SKIP_HOSTS )); then
  echo "skipped (--skip-hosts)"
elif [[ ! -f "$HOSTS_BACKUP" ]]; then
  echo "WARN: backup $HOSTS_BACKUP not found; cannot restore /etc/hosts"
else
  if (( APPLY == 0 )); then
    echo "DRY-RUN — would execute: sudo cp $HOSTS_BACKUP /etc/hosts"
  else
    echo "restoring /etc/hosts from $HOSTS_BACKUP (requires sudo)"
    sudo cp "$HOSTS_BACKUP" /etc/hosts
  fi
fi

# --- step 2: docker compose up ----------------------------------------------
echo ""
echo "--- step 2: bring .17 vani stack back up ---"
CMD=(docker compose -f "$COMPOSE_FILE" up -d "${SERVICES[@]}")
if (( SKIP_COMPOSE )); then
  echo "skipped (--skip-compose)"
elif (( APPLY == 0 )); then
  echo "DRY-RUN — would execute:"
  printf '  %q ' "${CMD[@]}"
  echo
else
  "${CMD[@]}"
fi

# --- step 3: postgres restore notes -----------------------------------------
echo ""
echo "--- step 3: Postgres rollback (manual) ---"
cat <<EOF
If .132-side Postgres writes happened during the cutover window and you
need to roll the .17 Postgres forward, restore from the dump artefact:

  # If .17 Postgres data has diverged, drop+recreate then restore:
  docker exec -i vani-setu-postgres pg_restore \\
    --clean --if-exists --no-owner --no-acl -j 2 \\
    -U vani -d vani_setu < ${DUMP_PATH}

  # Then verify the audit chain head matches pre-cutover capture:
  docker exec vani-setu-app php artisan audit:verify-chain

The .17 volumes ($COMPOSE_FILE) are NOT destroyed by cutover, so a simple
"start the stack back up" generally suffices unless data on .132 advanced
during the cutover window.
EOF

echo ""
echo "rollback flow complete (dry-run=$APPLY-inverted)."

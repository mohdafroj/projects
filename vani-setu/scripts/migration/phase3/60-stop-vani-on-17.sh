#!/usr/bin/env bash
#
# 60-stop-vani-on-17.sh
#
# Stops the .17-side Vani service set after the .132 cutover is verified
# healthy. Idempotent: running twice is a no-op.
#
# DRY-RUN BY DEFAULT. With --apply, performs `docker compose stop` for the
# listed services from /home/sds-dev/docker-compose.yml.
#
# The Vani frontend (Vite hot reload) and supporting services (Postgres,
# Redis, Mongo, Meili, MinIO) are RETAINED until the rollback window
# closes. The frontend stays on .17 by directive (plan section 1.2).
#
# Refs: docs/MIGRATION_PHASE3_PLAN.md sections 2.6 (rollback), 7.3

set -euo pipefail

COMPOSE_FILE="${PHASE3_COMPOSE_FILE:-/home/sds-dev/docker-compose.yml}"

# Services to stop on cutover. Derived from plan section 2.1 (containers in
# scope) — keep frontend and ml-gateway out unless directive overrides.
SERVICES=(
  app
  worker
  audit
  reverb
  web
  caddy
)

APPLY=0
INCLUDE_ML_GATEWAY=0
INCLUDE_SIDECAR=0

usage() {
  cat <<EOF
Usage: 60-stop-vani-on-17.sh [--apply] [options]

DRY-RUN BY DEFAULT.

Stops the following docker-compose services on .17 (from $COMPOSE_FILE):
  ${SERVICES[*]}

Frontend (vani-setu-frontend) and supporting data services (postgres, redis,
meilisearch, minio) are NOT stopped — they remain available for rollback
through the OQ-11 retention window. (Mongo dropped from scope v0.2.)

Options:
  --apply               actually stop services
  --include-ml-gateway  also stop the ml-gateway service (OQ-8 dependent)
  --include-sidecar     also stop realtime-sidecar (OQ-8 dependent)
  --help
EOF
}

while (( $# )); do
  case "$1" in
    --apply) APPLY=1; shift ;;
    --include-ml-gateway) INCLUDE_ML_GATEWAY=1; shift ;;
    --include-sidecar)    INCLUDE_SIDECAR=1; shift ;;
    --help|-h) usage; exit 0 ;;
    *) echo "unknown arg: $1" >&2; usage; exit 2 ;;
  esac
done

if (( INCLUDE_ML_GATEWAY )); then SERVICES+=("ml-gateway"); fi
if (( INCLUDE_SIDECAR )); then SERVICES+=("realtime-sidecar"); fi

TS="$(date -u +%Y%m%dT%H%M%SZ)"
LOG_DIR="/var/log/phase3"
if ! mkdir -p "$LOG_DIR" 2>/dev/null; then
  LOG_DIR="${HOME}/phase3-logs"; mkdir -p "$LOG_DIR"
fi
LOG_FILE="${LOG_DIR}/60-stop-vani-on-17-${TS}.log"
exec > >(tee -a "$LOG_FILE") 2>&1

echo "=== 60-stop-vani-on-17.sh ${TS} ==="
echo "apply=$APPLY compose=$COMPOSE_FILE"
echo "services: ${SERVICES[*]}"

if [[ ! -f "$COMPOSE_FILE" ]]; then
  echo "FATAL: compose file not found: $COMPOSE_FILE" >&2; exit 1
fi

CMD=(docker compose -f "$COMPOSE_FILE" stop "${SERVICES[@]}")

if (( APPLY == 0 )); then
  echo ""
  echo "DRY-RUN — would execute:"
  printf '  %q ' "${CMD[@]}"
  echo
  echo ""
  echo "Pre-condition checklist:"
  echo "  [ ] .132 Cluster A smoke tests passed: /healthz, login, audit insert, Reverb subscribe"
  echo "  [ ] Postgres dump kept at /home/sds-dev/migration-artifacts/vani_setu.dump (rollback baseline)"
  echo "  [ ] /etc/hosts.bak.phase1-cutover-20260523 still on disk"
  exit 0
fi

echo "stopping services..."
"${CMD[@]}"

echo ""
echo "stopped. Current state:"
docker compose -f "$COMPOSE_FILE" ps "${SERVICES[@]}"

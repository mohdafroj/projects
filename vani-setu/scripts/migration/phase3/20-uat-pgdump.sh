#!/usr/bin/env bash
#
# 20-uat-pgdump.sh
#
# Dumps the UAT Vani Postgres database (Cluster B) from the .17 container
# `sds-dev-uat-postgres-1` to /tmp/vani_setu_uat.dump.
#
# DRY-RUN BY DEFAULT.
#
# Refs: docs/MIGRATION_PHASE3_PLAN.md section 3.3

set -euo pipefail

CONTAINER="${UAT_PG_CONTAINER:-sds-dev-uat-postgres-1}"
DB_USER="${UAT_DB_USER:-vani}"
DB_NAME="${UAT_DB_NAME:-vani_setu_uat}"
OUT_PATH="${UAT_DUMP_OUT:-/tmp/vani_setu_uat.dump}"
APPLY=0

usage() {
  cat <<EOF
Usage: 20-uat-pgdump.sh [--apply] [--help]

DRY-RUN BY DEFAULT. Pass --apply to actually execute pg_dump.

Env overrides:
  UAT_PG_CONTAINER  default: $CONTAINER
  UAT_DB_USER       default: $DB_USER
  UAT_DB_NAME       default: $DB_NAME
  UAT_DUMP_OUT      default: $OUT_PATH
EOF
}

while (( $# )); do
  case "$1" in
    --apply) APPLY=1; shift ;;
    --help|-h) usage; exit 0 ;;
    *) echo "unknown arg: $1" >&2; usage; exit 2 ;;
  esac
done

TS="$(date -u +%Y%m%dT%H%M%SZ)"
LOG_DIR="/var/log/phase3"
if ! mkdir -p "$LOG_DIR" 2>/dev/null; then
  LOG_DIR="${HOME}/phase3-logs"; mkdir -p "$LOG_DIR"
fi
LOG_FILE="${LOG_DIR}/20-uat-pgdump-${TS}.log"
exec > >(tee -a "$LOG_FILE") 2>&1

echo "=== 20-uat-pgdump.sh ${TS} ==="
echo "apply=$APPLY container=$CONTAINER db=$DB_NAME user=$DB_USER out=$OUT_PATH"

CMD=(docker exec "$CONTAINER" pg_dump -U "$DB_USER" -Fc -d "$DB_NAME")

if (( APPLY == 0 )); then
  echo ""
  echo "DRY-RUN — would execute:"
  printf '  %q ' "${CMD[@]}"
  echo "> $OUT_PATH"
  echo ""
  echo "UAT data is acceptance-test fixtures; loss is recoverable but inconvenient."
  echo "Re-run with --apply to execute."
  exit 0
fi

if ! docker ps --format '{{.Names}}' | grep -qx "$CONTAINER"; then
  echo "FATAL: container $CONTAINER not running" >&2
  exit 1
fi
if [[ -e "$OUT_PATH" ]]; then
  echo "FATAL: $OUT_PATH already exists — refusing to clobber" >&2
  exit 1
fi

"${CMD[@]}" > "$OUT_PATH"

size_human="$(du -h "$OUT_PATH" | awk '{print $1}')"
sha="$(sha256sum "$OUT_PATH" | awk '{print $1}')"
echo "dump complete: $OUT_PATH ($size_human)  sha256=$sha"

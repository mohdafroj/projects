#!/usr/bin/env bash
#
# 30-reporting-pgdump.sh
#
# Dumps the Superset metadata DB (Cluster C) from the .17 container
# `sds-reporting-postgres` to /tmp/sds_reporting_superset.dump.
#
# DRY-RUN BY DEFAULT.
#
# Refs: docs/MIGRATION_PHASE3_PLAN.md section 4.3

set -euo pipefail

CONTAINER="${REPORTING_PG_CONTAINER:-sds-reporting-postgres}"
ENV_FILE="${REPORTING_ENV_FILE:-/home/sds-dev/sds-reporting-engine/.env}"
DB_USER="${SUPERSET_POSTGRES_USER:-}"
DB_NAME="${SUPERSET_POSTGRES_DB:-}"
OUT_PATH="${REPORTING_DUMP_OUT:-/tmp/sds_reporting_superset.dump}"
APPLY=0

usage() {
  cat <<EOF
Usage: 30-reporting-pgdump.sh [--apply] [--help]

DRY-RUN BY DEFAULT.

DB user/name are read from \$SUPERSET_POSTGRES_USER / \$SUPERSET_POSTGRES_DB
or from \$REPORTING_ENV_FILE (default $ENV_FILE).

If you don't want secret-file reads, pass them explicitly via env or supply
the --user/--db flags.

Options:
  --user USER
  --db   DB
  --container NAME (default $CONTAINER)
  --out PATH (default $OUT_PATH)
  --apply
EOF
}

while (( $# )); do
  case "$1" in
    --user) DB_USER="$2"; shift 2 ;;
    --db)   DB_NAME="$2"; shift 2 ;;
    --container) CONTAINER="$2"; shift 2 ;;
    --out) OUT_PATH="$2"; shift 2 ;;
    --apply) APPLY=1; shift ;;
    --help|-h) usage; exit 0 ;;
    *) echo "unknown arg: $1" >&2; usage; exit 2 ;;
  esac
done

# Read user/db from env file ONLY if not supplied (we never echo secrets).
if [[ -z "$DB_USER" || -z "$DB_NAME" ]]; then
  if [[ -r "$ENV_FILE" ]]; then
    # shellcheck disable=SC1090
    set +u
    # shellcheck disable=SC1090
    source <(grep -E '^(SUPERSET_POSTGRES_USER|SUPERSET_POSTGRES_DB)=' "$ENV_FILE")
    set -u
    DB_USER="${DB_USER:-${SUPERSET_POSTGRES_USER:-superset}}"
    DB_NAME="${DB_NAME:-${SUPERSET_POSTGRES_DB:-superset}}"
  else
    DB_USER="${DB_USER:-superset}"
    DB_NAME="${DB_NAME:-superset}"
  fi
fi

TS="$(date -u +%Y%m%dT%H%M%SZ)"
LOG_DIR="/var/log/phase3"
if ! mkdir -p "$LOG_DIR" 2>/dev/null; then
  LOG_DIR="${HOME}/phase3-logs"; mkdir -p "$LOG_DIR"
fi
LOG_FILE="${LOG_DIR}/30-reporting-pgdump-${TS}.log"
exec > >(tee -a "$LOG_FILE") 2>&1

echo "=== 30-reporting-pgdump.sh ${TS} ==="
echo "apply=$APPLY container=$CONTAINER db=$DB_NAME user=$DB_USER out=$OUT_PATH"

CMD=(docker exec "$CONTAINER" pg_dump -U "$DB_USER" -Fc -d "$DB_NAME")

if (( APPLY == 0 )); then
  echo ""
  echo "DRY-RUN — would execute:"
  printf '  %q ' "${CMD[@]}"
  echo "> $OUT_PATH"
  echo ""
  echo "Pre-condition: drain Celery before dumping (stop beat first, then worker)."
  echo "  docker stop sds-reporting-beat"
  echo "  docker exec sds-reporting-worker celery -A superset.tasks.celery_app:app inspect active"
  echo "Re-run with --apply to execute."
  exit 0
fi

if ! docker ps --format '{{.Names}}' | grep -qx "$CONTAINER"; then
  echo "FATAL: container $CONTAINER not running" >&2; exit 1
fi
if [[ -e "$OUT_PATH" ]]; then
  echo "FATAL: $OUT_PATH already exists — refusing to clobber" >&2; exit 1
fi

"${CMD[@]}" > "$OUT_PATH"

size="$(du -h "$OUT_PATH" | awk '{print $1}')"
sha="$(sha256sum "$OUT_PATH" | awk '{print $1}')"
echo "dump complete: $OUT_PATH ($size)  sha256=$sha"

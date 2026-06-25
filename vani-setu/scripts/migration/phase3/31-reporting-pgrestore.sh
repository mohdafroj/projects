#!/usr/bin/env bash
#
# 31-reporting-pgrestore.sh
#
# Restore the Superset metadata DB dump onto the .132 Postgres in the
# `sds-reporting` namespace. Same shape as 11-vani-pgrestore.sh.
#
# DRY-RUN BY DEFAULT.
#
# Refs: docs/MIGRATION_PHASE3_PLAN.md section 4.3

set -euo pipefail

DUMP_PATH="${REPORTING_DUMP_IN:-/tmp/sds_reporting_superset.dump}"
TARGET_HOST=""
TARGET_PORT="5432"
TARGET_USER="superset"
TARGET_DB="superset"
TARGET_NAMESPACE="sds-reporting"
TARGET_POD=""
JOBS="2"
APPLY=0

usage() {
  cat <<EOF
Usage: 31-reporting-pgrestore.sh [--apply] [options]

DRY-RUN BY DEFAULT. See 11-vani-pgrestore.sh --help for flag semantics.

Options:
  --target-host HOST | --target-pod POD
  --target-port PORT (default $TARGET_PORT)
  --target-user USER (default $TARGET_USER)
  --target-db   DB   (default $TARGET_DB)
  --target-namespace NS (default $TARGET_NAMESPACE)
  --dump PATH (default $DUMP_PATH)
  --jobs N (default $JOBS)
  --apply
EOF
}

while (( $# )); do
  case "$1" in
    --target-host) TARGET_HOST="$2"; shift 2 ;;
    --target-port) TARGET_PORT="$2"; shift 2 ;;
    --target-user) TARGET_USER="$2"; shift 2 ;;
    --target-db)   TARGET_DB="$2";   shift 2 ;;
    --target-pod)  TARGET_POD="$2";  shift 2 ;;
    --target-namespace) TARGET_NAMESPACE="$2"; shift 2 ;;
    --dump) DUMP_PATH="$2"; shift 2 ;;
    --jobs) JOBS="$2"; shift 2 ;;
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
LOG_FILE="${LOG_DIR}/31-reporting-pgrestore-${TS}.log"
exec > >(tee -a "$LOG_FILE") 2>&1

echo "=== 31-reporting-pgrestore.sh ${TS} ==="
echo "apply=$APPLY dump=$DUMP_PATH db=$TARGET_DB user=$TARGET_USER"
if [[ -n "$TARGET_POD" ]]; then
  echo "mode=kubectl pod=$TARGET_POD namespace=$TARGET_NAMESPACE"
elif [[ -n "$TARGET_HOST" ]]; then
  echo "mode=direct host=$TARGET_HOST port=$TARGET_PORT"
else
  echo "FATAL: must supply --target-host OR --target-pod" >&2; exit 2
fi

PG_RESTORE_ARGS=(--clean --if-exists --no-owner --no-acl -j "$JOBS" -U "$TARGET_USER" -d "$TARGET_DB")

if [[ -n "$TARGET_POD" ]]; then
  KCTL_CMD=(kubectl exec -i -n "$TARGET_NAMESPACE" "$TARGET_POD" -- pg_restore "${PG_RESTORE_ARGS[@]}")
  if (( APPLY == 0 )); then
    echo ""
    echo "DRY-RUN — would execute:"
    printf '  %q ' "${KCTL_CMD[@]}"
    echo "< $DUMP_PATH"
    exit 0
  fi
  "${KCTL_CMD[@]}" < "$DUMP_PATH"
else
  PG_CMD=(pg_restore -h "$TARGET_HOST" -p "$TARGET_PORT" "${PG_RESTORE_ARGS[@]}" "$DUMP_PATH")
  if (( APPLY == 0 )); then
    echo ""
    echo "DRY-RUN — would execute (PGPASSWORD must be set):"
    printf '  %q ' "${PG_CMD[@]}"
    echo
    exit 0
  fi
  if [[ -z "${PGPASSWORD:-}" ]]; then
    echo "FATAL: PGPASSWORD not set" >&2; exit 1
  fi
  "${PG_CMD[@]}"
fi

echo "restore complete. Next: trigger superset init Job:"
echo "  kubectl -n $TARGET_NAMESPACE create job --from=cronjob/superset-init superset-init-\$(date +%s) || true"
echo "or invoke 'superset init' directly via the chart's hook."

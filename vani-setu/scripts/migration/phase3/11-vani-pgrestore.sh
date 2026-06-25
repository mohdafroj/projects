#!/usr/bin/env bash
#
# 11-vani-pgrestore.sh
#
# Restores a vani_setu pg_dump (custom format) onto the .132 Postgres.
# This script is intended to be RUN BY THE .132 ADMIN on the .132 side OR
# from a host that can reach the target Postgres directly (e.g. via
# `kubectl exec -i vani-postgres-0 -- pg_restore`).
#
# DRY-RUN BY DEFAULT.
#
# Refs: docs/MIGRATION_PHASE3_PLAN.md section 2.3, 2.6

set -euo pipefail

DUMP_PATH="${VANI_DUMP_IN:-/tmp/vani_setu.dump}"
TARGET_HOST=""
TARGET_PORT="5432"
TARGET_USER="vani"
TARGET_DB="vani_setu"
TARGET_NAMESPACE="vani-laravel"
TARGET_POD=""          # if set, use kubectl exec rather than direct pg_restore
JOBS="2"
APPLY=0

usage() {
  cat <<EOF
Usage: 11-vani-pgrestore.sh [--apply] [options]

DRY-RUN BY DEFAULT.

Options (mutually-exclusive transport modes):
  Direct mode (host can reach target Postgres on TCP):
    --target-host HOST       hostname / IP of target Postgres (required)
    --target-port PORT       default $TARGET_PORT
    --target-user USER       default $TARGET_USER
    --target-db   DB         default $TARGET_DB

  kubectl mode (target is in a K3s cluster, kubectl on PATH):
    --target-pod POD         pod name, e.g. vani-postgres-0
    --target-namespace NS    default $TARGET_NAMESPACE
    --target-user USER       default $TARGET_USER
    --target-db   DB         default $TARGET_DB

  Common:
    --dump PATH              dump file path (default $DUMP_PATH)
    --jobs N                 pg_restore -j (default $JOBS)
    --apply                  actually run; otherwise prints the command
    --help

pg_restore flags used: --clean --if-exists --no-owner --no-acl -j \$JOBS
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
    --dump)        DUMP_PATH="$2";   shift 2 ;;
    --jobs)        JOBS="$2";        shift 2 ;;
    --apply)       APPLY=1;          shift ;;
    --help|-h)     usage; exit 0 ;;
    *) echo "unknown arg: $1" >&2; usage; exit 2 ;;
  esac
done

TS="$(date -u +%Y%m%dT%H%M%SZ)"
LOG_DIR="/var/log/phase3"
if ! mkdir -p "$LOG_DIR" 2>/dev/null; then
  LOG_DIR="${HOME}/phase3-logs"; mkdir -p "$LOG_DIR"
fi
LOG_FILE="${LOG_DIR}/11-vani-pgrestore-${TS}.log"
exec > >(tee -a "$LOG_FILE") 2>&1

echo "=== 11-vani-pgrestore.sh ${TS} ==="
echo "apply=$APPLY dump=$DUMP_PATH db=$TARGET_DB user=$TARGET_USER"
if [[ -n "$TARGET_POD" ]]; then
  echo "mode=kubectl pod=$TARGET_POD namespace=$TARGET_NAMESPACE"
elif [[ -n "$TARGET_HOST" ]]; then
  echo "mode=direct host=$TARGET_HOST port=$TARGET_PORT"
else
  echo "FATAL: must supply --target-host OR --target-pod" >&2
  usage >&2
  exit 2
fi

if [[ ! -f "$DUMP_PATH" ]] && (( APPLY == 1 )); then
  echo "FATAL: dump file $DUMP_PATH not found" >&2
  exit 1
fi

PG_RESTORE_ARGS=(--clean --if-exists --no-owner --no-acl -j "$JOBS" -U "$TARGET_USER" -d "$TARGET_DB")

if [[ -n "$TARGET_POD" ]]; then
  # kubectl mode: stream dump into the pod
  KCTL_CMD=(kubectl exec -i -n "$TARGET_NAMESPACE" "$TARGET_POD" -- pg_restore "${PG_RESTORE_ARGS[@]}")
  if (( APPLY == 0 )); then
    echo ""
    echo "DRY-RUN — would execute:"
    printf '  %q ' "${KCTL_CMD[@]}"
    echo "< $DUMP_PATH"
    exit 0
  fi
  echo "streaming restore via kubectl exec..."
  "${KCTL_CMD[@]}" < "$DUMP_PATH"
else
  # Direct mode: PGHOST/PGPORT + PGPASSWORD must already be set in env
  PG_CMD=(pg_restore -h "$TARGET_HOST" -p "$TARGET_PORT" "${PG_RESTORE_ARGS[@]}" "$DUMP_PATH")
  if (( APPLY == 0 )); then
    echo ""
    echo "DRY-RUN — would execute (PGPASSWORD must be set in env):"
    printf '  %q ' "${PG_CMD[@]}"
    echo
    exit 0
  fi
  if [[ -z "${PGPASSWORD:-}" ]]; then
    echo "FATAL: PGPASSWORD env var not set for direct mode" >&2
    exit 1
  fi
  echo "running pg_restore..."
  "${PG_CMD[@]}"
fi

echo ""
echo "restore complete."
echo "post-restore: verify extensions exist (\\dx), then run php artisan audit:verify-chain on the new app pod."

#!/usr/bin/env bash
#
# 50-vani-env-rewrite.sh
#
# Generates a diff that would rewrite /home/sds-dev/src/.env to point
# DB_HOST / REDIS_HOST / MINIO_HOST at the .132-cluster service DNS names.
#
# IMPORTANT: Per the executing-session contract, this script must NOT be
# run with --apply in the script-authoring session. The future cutover
# session may run it after the Helm install on .132 is verified healthy.
#
# DRY-RUN BY DEFAULT.
#
# Refs: docs/MIGRATION_PHASE3_PLAN.md sections 2.4, 5.4

set -euo pipefail

ENV_FILE="${VANI_ENV_FILE:-/home/sds-dev/src/.env}"
BACKUP_SUFFIX=".bak.phase3-$(date -u +%Y%m%dT%H%M%SZ)"
APPLY=0

# Target endpoints (in-cluster DNS, assuming Vani consumers move to .132).
# If consumers stay on .17, the host parts become public hostnames instead.
DB_HOST_NEW="${PHASE3_DB_HOST:-vani-postgres.vani-laravel.svc.cluster.local}"
REDIS_HOST_NEW="${PHASE3_REDIS_HOST:-vani-redis.vani-laravel.svc.cluster.local}"
MINIO_HOST_NEW="${PHASE3_MINIO_HOST:-minio.sds.local}"
MINIO_ENDPOINT_NEW="${PHASE3_MINIO_ENDPOINT:-https://minio.sds.local}"
PARICHAY_BASE_URL_NEW="${PHASE3_PARICHAY_BASE_URL:-https://fake-parichay.sds.local}"
MEILI_HOST_NEW="${PHASE3_MEILI_HOST:-http://vani-meilisearch.vani-laravel.svc.cluster.local:7700}"

usage() {
  cat <<EOF
Usage: 50-vani-env-rewrite.sh [--apply] [--env-file PATH]

DRY-RUN BY DEFAULT. Prints a unified diff that would rewrite ENV keys.

With --apply, writes the new env to PATH and keeps the original at
PATH${BACKUP_SUFFIX}.

Env overrides (defaults shown):
  PHASE3_DB_HOST           = $DB_HOST_NEW
  PHASE3_REDIS_HOST        = $REDIS_HOST_NEW
  PHASE3_MINIO_HOST        = $MINIO_HOST_NEW
  PHASE3_MINIO_ENDPOINT    = $MINIO_ENDPOINT_NEW
  PHASE3_PARICHAY_BASE_URL = $PARICHAY_BASE_URL_NEW
  PHASE3_MEILI_HOST        = $MEILI_HOST_NEW

Options:
  --env-file PATH    default: $ENV_FILE
  --apply            actually rewrite (DO NOT use in script-authoring session)
EOF
}

while (( $# )); do
  case "$1" in
    --env-file) ENV_FILE="$2"; shift 2 ;;
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
LOG_FILE="${LOG_DIR}/50-vani-env-rewrite-${TS}.log"
exec > >(tee -a "$LOG_FILE") 2>&1

echo "=== 50-vani-env-rewrite.sh ${TS} ==="
echo "apply=$APPLY env_file=$ENV_FILE"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "FATAL: env file not found: $ENV_FILE" >&2; exit 1
fi

TMP_NEW="$(mktemp)"
trap 'rm -f "$TMP_NEW"' EXIT

# Use a per-key sed pipeline. Only known keys are rewritten; everything else
# is preserved byte-for-byte.
sed \
  -e "s|^DB_HOST=.*|DB_HOST=${DB_HOST_NEW}|" \
  -e "s|^REDIS_HOST=.*|REDIS_HOST=${REDIS_HOST_NEW}|" \
  -e "s|^AWS_ENDPOINT=.*|AWS_ENDPOINT=${MINIO_ENDPOINT_NEW}|" \
  -e "s|^AWS_URL=.*|AWS_URL=${MINIO_ENDPOINT_NEW}|" \
  -e "s|^MINIO_ENDPOINT=.*|MINIO_ENDPOINT=${MINIO_ENDPOINT_NEW}|" \
  -e "s|^MINIO_HOST=.*|MINIO_HOST=${MINIO_HOST_NEW}|" \
  -e "s|^MEILISEARCH_HOST=.*|MEILISEARCH_HOST=${MEILI_HOST_NEW}|" \
  -e "s|^PARICHAY_BASE_URL=.*|PARICHAY_BASE_URL=${PARICHAY_BASE_URL_NEW}|" \
  "$ENV_FILE" > "$TMP_NEW"

echo ""
echo "--- diff (old -> new) ---"
if diff -u "$ENV_FILE" "$TMP_NEW"; then
  echo "(no changes)"
fi

if (( APPLY == 0 )); then
  echo ""
  echo "DRY-RUN. Re-run with --apply to write changes."
  exit 0
fi

# Safety guard: never apply in this script-authoring session.
if [[ "${PHASE3_ALLOW_ENV_REWRITE:-}" != "yes" ]]; then
  echo "FATAL: --apply requires PHASE3_ALLOW_ENV_REWRITE=yes (set only in the cutover session)" >&2
  exit 1
fi

cp -a "$ENV_FILE" "${ENV_FILE}${BACKUP_SUFFIX}"
mv "$TMP_NEW" "$ENV_FILE"
trap - EXIT
echo "rewrite applied. Backup at ${ENV_FILE}${BACKUP_SUFFIX}"

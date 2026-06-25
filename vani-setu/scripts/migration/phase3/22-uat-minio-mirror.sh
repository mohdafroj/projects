#!/usr/bin/env bash
#
# 22-uat-minio-mirror.sh
#
# Mirror UAT MinIO buckets from the .17 UAT-side MinIO to the platform MinIO
# at minio.sds.local. Per the plan (section 3.3), UAT MinIO corpus is small
# and may not even exist as a separate endpoint — UAT can share buckets with
# stable or use a UAT-suffixed bucket set.
#
# DRY-RUN BY DEFAULT. The UAT-side bucket list is configurable; defaults are
# the stable ADR-VANI-5 names with a `-uat` suffix. ADJUST PER OQ-6.
#
# Refs: docs/MIGRATION_PHASE3_PLAN.md section 3.3

set -euo pipefail

SRC_ALIAS="${UAT_MC_SRC_ALIAS:-uat17}"
DST_ALIAS="${UAT_MC_DST_ALIAS:-uat132}"
SRC_ENDPOINT="${UAT_MC_SRC_ENDPOINT:-http://vani-setu-minio:9000}"
DST_ENDPOINT="${UAT_MC_DST_ENDPOINT:-https://minio.sds.local}"
SRC_ACCESS="${UAT_MC_SRC_ACCESS_KEY:-}"
SRC_SECRET="${UAT_MC_SRC_SECRET_KEY:-}"
DST_ACCESS="${UAT_MC_DST_ACCESS_KEY:-}"
DST_SECRET="${UAT_MC_DST_SECRET_KEY:-}"

BUCKETS=(
  vani-audio-raw-rs-uat
  vani-ai-drafts-rs-uat
  vani-voiceprints-rs-uat
  vani-pilot-audio-rs-uat
)

MC_RUNNER="${UAT_MC_RUNNER:-docker exec vani-setu-minio mc}"
APPLY=0

usage() {
  cat <<EOF
Usage: 22-uat-minio-mirror.sh [--apply] [--bucket NAME ...] [options]

Same shape as 12-vani-minio-mirror.sh. Adjust the BUCKETS list to match the
actual UAT-side bucket names — the defaults assume a -uat suffix per OQ-6.

  --apply / --bucket NAME / --src-alias / --dst-alias / --src-endpoint /
  --dst-endpoint / --help
EOF
}

ONLY=()
while (( $# )); do
  case "$1" in
    --apply) APPLY=1; shift ;;
    --bucket) ONLY+=("$2"); shift 2 ;;
    --src-alias) SRC_ALIAS="$2"; shift 2 ;;
    --dst-alias) DST_ALIAS="$2"; shift 2 ;;
    --src-endpoint) SRC_ENDPOINT="$2"; shift 2 ;;
    --dst-endpoint) DST_ENDPOINT="$2"; shift 2 ;;
    --help|-h) usage; exit 0 ;;
    *) echo "unknown arg: $1" >&2; usage; exit 2 ;;
  esac
done
if (( ${#ONLY[@]} > 0 )); then BUCKETS=("${ONLY[@]}"); fi

TS="$(date -u +%Y%m%dT%H%M%SZ)"
LOG_DIR="/var/log/phase3"
if ! mkdir -p "$LOG_DIR" 2>/dev/null; then
  LOG_DIR="${HOME}/phase3-logs"; mkdir -p "$LOG_DIR"
fi
LOG_FILE="${LOG_DIR}/22-uat-minio-mirror-${TS}.log"
exec > >(tee -a "$LOG_FILE") 2>&1

echo "=== 22-uat-minio-mirror.sh ${TS} ==="
echo "apply=$APPLY runner='$MC_RUNNER'"
echo "src=$SRC_ALIAS@$SRC_ENDPOINT  dst=$DST_ALIAS@$DST_ENDPOINT"
echo "buckets: ${BUCKETS[*]}"

if (( APPLY == 0 )); then
  echo ""
  echo "DRY-RUN — would execute (per bucket):"
  for b in "${BUCKETS[@]}"; do
    echo "  $MC_RUNNER mirror --preserve --dry-run $SRC_ALIAS/$b $DST_ALIAS/$b"
  done
  exit 0
fi

if [[ -z "$SRC_ACCESS" || -z "$SRC_SECRET" || -z "$DST_ACCESS" || -z "$DST_SECRET" ]]; then
  echo "FATAL: --apply requires UAT_MC_{SRC,DST}_{ACCESS,SECRET}_KEY env vars" >&2
  exit 1
fi
# shellcheck disable=SC2086
$MC_RUNNER alias set "$SRC_ALIAS" "$SRC_ENDPOINT" "$SRC_ACCESS" "$SRC_SECRET" >/dev/null
# shellcheck disable=SC2086
$MC_RUNNER alias set "$DST_ALIAS" "$DST_ENDPOINT" "$DST_ACCESS" "$DST_SECRET" >/dev/null

for b in "${BUCKETS[@]}"; do
  echo "--- bucket: $b ---"
  # shellcheck disable=SC2086
  if ! $MC_RUNNER ls "$DST_ALIAS/$b" >/dev/null 2>&1; then
    # shellcheck disable=SC2086
    $MC_RUNNER mb --ignore-existing "$DST_ALIAS/$b"
  fi
  # shellcheck disable=SC2086
  $MC_RUNNER mirror --preserve "$SRC_ALIAS/$b" "$DST_ALIAS/$b"
done
echo "mirror complete."

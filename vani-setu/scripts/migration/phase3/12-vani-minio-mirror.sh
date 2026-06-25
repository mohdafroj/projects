#!/usr/bin/env bash
#
# 12-vani-minio-mirror.sh
#
# Mirrors the four ADR-VANI-5 buckets from the .17 Vani MinIO to the .132
# platform MinIO at minio.sds.local.
#
# Per the plan's MinIO-only constraint and Rajya-Sabha-only naming policy,
# the bucket names are:
#   vani-audio-raw-rs
#   vani-ai-drafts-rs
#   vani-voiceprints-rs
#   vani-pilot-audio-rs
#
# DRY-RUN BY DEFAULT. `mc mirror --dry-run` is used in dry-run mode; --apply
# removes --dry-run.
#
# Refs: docs/MIGRATION_PHASE3_PLAN.md sections 2.3, 6.4, OQ-6

set -euo pipefail

# --- defaults ----------------------------------------------------------------
SRC_ALIAS="${VANI_MC_SRC_ALIAS:-vani17}"
DST_ALIAS="${VANI_MC_DST_ALIAS:-vani132}"

SRC_ENDPOINT="${VANI_MC_SRC_ENDPOINT:-http://vani-setu-minio:9000}"
DST_ENDPOINT="${VANI_MC_DST_ENDPOINT:-https://minio.sds.local}"

# Credentials should come from env. Never inline them.
SRC_ACCESS="${VANI_MC_SRC_ACCESS_KEY:-}"
SRC_SECRET="${VANI_MC_SRC_SECRET_KEY:-}"
DST_ACCESS="${VANI_MC_DST_ACCESS_KEY:-}"
DST_SECRET="${VANI_MC_DST_SECRET_KEY:-}"

BUCKETS=(
  vani-audio-raw-rs
  vani-ai-drafts-rs
  vani-voiceprints-rs
  vani-pilot-audio-rs
)

# Run mc inside the source container by default (mc may not exist on host).
MC_RUNNER="${VANI_MC_RUNNER:-docker exec vani-setu-minio mc}"

APPLY=0

usage() {
  cat <<EOF
Usage: 12-vani-minio-mirror.sh [--apply] [--bucket NAME ...] [options]

DRY-RUN BY DEFAULT. Without --apply, mc is invoked with --dry-run.

Options:
  --apply                      remove --dry-run from mc mirror
  --bucket NAME                limit to a single bucket (repeatable)
  --src-alias NAME             default $SRC_ALIAS
  --dst-alias NAME             default $DST_ALIAS
  --src-endpoint URL           default $SRC_ENDPOINT
  --dst-endpoint URL           default $DST_ENDPOINT
  --help

Required env (for mc alias set, used only when --apply):
  VANI_MC_SRC_ACCESS_KEY / VANI_MC_SRC_SECRET_KEY
  VANI_MC_DST_ACCESS_KEY / VANI_MC_DST_SECRET_KEY

Notes:
  * --apply executes mc alias set + mc mb (if missing) + mc mirror.
  * Source aliases are created against vani-setu-minio (in-container).
  * The plan says corpus is ~424 KB so this is fast; no parallelism flags set.
EOF
}

ONLY_BUCKETS=()
while (( $# )); do
  case "$1" in
    --apply) APPLY=1; shift ;;
    --bucket) ONLY_BUCKETS+=("$2"); shift 2 ;;
    --src-alias) SRC_ALIAS="$2"; shift 2 ;;
    --dst-alias) DST_ALIAS="$2"; shift 2 ;;
    --src-endpoint) SRC_ENDPOINT="$2"; shift 2 ;;
    --dst-endpoint) DST_ENDPOINT="$2"; shift 2 ;;
    --help|-h) usage; exit 0 ;;
    *) echo "unknown arg: $1" >&2; usage; exit 2 ;;
  esac
done

if (( ${#ONLY_BUCKETS[@]} > 0 )); then
  BUCKETS=("${ONLY_BUCKETS[@]}")
fi

TS="$(date -u +%Y%m%dT%H%M%SZ)"
LOG_DIR="/var/log/phase3"
if ! mkdir -p "$LOG_DIR" 2>/dev/null; then
  LOG_DIR="${HOME}/phase3-logs"; mkdir -p "$LOG_DIR"
fi
LOG_FILE="${LOG_DIR}/12-vani-minio-mirror-${TS}.log"
exec > >(tee -a "$LOG_FILE") 2>&1

echo "=== 12-vani-minio-mirror.sh ${TS} ==="
echo "apply=$APPLY runner='$MC_RUNNER'"
echo "src=$SRC_ALIAS@$SRC_ENDPOINT  dst=$DST_ALIAS@$DST_ENDPOINT"
echo "buckets: ${BUCKETS[*]}"

if (( APPLY == 0 )); then
  echo ""
  echo "DRY-RUN — would execute (per bucket):"
  for b in "${BUCKETS[@]}"; do
    echo "  $MC_RUNNER mirror --preserve --dry-run $SRC_ALIAS/$b $DST_ALIAS/$b"
  done
  echo ""
  echo "Re-run with --apply (and the four MC creds env vars set) to execute."
  exit 0
fi

# --- APPLY ------------------------------------------------------------------
if [[ -z "$SRC_ACCESS" || -z "$SRC_SECRET" || -z "$DST_ACCESS" || -z "$DST_SECRET" ]]; then
  echo "FATAL: --apply requires VANI_MC_{SRC,DST}_{ACCESS,SECRET}_KEY env vars" >&2
  exit 1
fi

echo "configuring mc aliases..."
# shellcheck disable=SC2086
$MC_RUNNER alias set "$SRC_ALIAS" "$SRC_ENDPOINT" "$SRC_ACCESS" "$SRC_SECRET" >/dev/null
# shellcheck disable=SC2086
$MC_RUNNER alias set "$DST_ALIAS" "$DST_ENDPOINT" "$DST_ACCESS" "$DST_SECRET" >/dev/null

for b in "${BUCKETS[@]}"; do
  echo ""
  echo "--- bucket: $b ---"
  # Ensure target bucket exists; ignore if already there.
  # shellcheck disable=SC2086
  if ! $MC_RUNNER ls "$DST_ALIAS/$b" >/dev/null 2>&1; then
    echo "creating destination bucket $DST_ALIAS/$b"
    # shellcheck disable=SC2086
    $MC_RUNNER mb --ignore-existing "$DST_ALIAS/$b"
  fi
  # shellcheck disable=SC2086
  $MC_RUNNER mirror --preserve "$SRC_ALIAS/$b" "$DST_ALIAS/$b"
done

echo ""
echo "mirror complete. Verify destination object counts match source:"
for b in "${BUCKETS[@]}"; do
  echo "  $MC_RUNNER ls --recursive $SRC_ALIAS/$b | wc -l"
  echo "  $MC_RUNNER ls --recursive $DST_ALIAS/$b | wc -l"
done

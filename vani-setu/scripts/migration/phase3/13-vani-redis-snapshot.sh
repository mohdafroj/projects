#!/usr/bin/env bash
#
# 13-vani-redis-snapshot.sh
#
# Optional: snapshot the Vani Redis dataset to an RDB file on the host.
#
# Per docs/MIGRATION_PHASE3_PLAN.md section 2.3:
#   "Redis: Do not migrate — queues drain first; sessions are file-based per
#    env (SESSION_DRIVER=file); rate-limit counters and cache rebuild"
#
# So this script exists for the edge case where the operator wants a
# diagnostic snapshot before stopping. Default behaviour is DRY-RUN.
# Do NOT run with --apply unless the executing-session plan explicitly
# says to preserve Redis state.
#
# Refs: docs/MIGRATION_PHASE3_PLAN.md section 2.3

set -euo pipefail

CONTAINER="${VANI_REDIS_CONTAINER:-vani-setu-redis}"
OUT_PATH="${VANI_REDIS_OUT:-/tmp/vani-redis.rdb}"
APPLY=0

usage() {
  cat <<EOF
Usage: 13-vani-redis-snapshot.sh [--apply] [--help]

DRY-RUN BY DEFAULT.

Per the plan, Redis state is mostly cache + queue and is acceptable to lose.
Only run with --apply if the executing-session plan explicitly says to
preserve Redis state.

Env overrides:
  VANI_REDIS_CONTAINER  default: $CONTAINER
  VANI_REDIS_OUT        default: $OUT_PATH
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
LOG_FILE="${LOG_DIR}/13-vani-redis-snapshot-${TS}.log"
exec > >(tee -a "$LOG_FILE") 2>&1

echo "=== 13-vani-redis-snapshot.sh ${TS} ==="
echo "apply=$APPLY container=$CONTAINER out=$OUT_PATH"

# We use BGSAVE then copy /data/dump.rdb out of the container.
if (( APPLY == 0 )); then
  echo ""
  echo "DRY-RUN — would execute:"
  echo "  docker exec $CONTAINER redis-cli BGSAVE"
  echo "  # poll: docker exec $CONTAINER redis-cli LASTSAVE"
  echo "  docker cp $CONTAINER:/data/dump.rdb $OUT_PATH"
  echo ""
  echo "Re-run with --apply only if Redis state must be preserved."
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

echo "triggering BGSAVE..."
before="$(docker exec "$CONTAINER" redis-cli LASTSAVE)"
docker exec "$CONTAINER" redis-cli BGSAVE
# wait up to 30s for LASTSAVE to advance
for _ in $(seq 1 30); do
  sleep 1
  after="$(docker exec "$CONTAINER" redis-cli LASTSAVE)"
  if [[ "$after" != "$before" ]]; then
    break
  fi
done
if [[ "${after:-}" == "$before" ]]; then
  echo "WARN: LASTSAVE did not advance within 30s; continuing anyway"
fi

echo "copying dump.rdb out of container..."
docker cp "$CONTAINER":/data/dump.rdb "$OUT_PATH"

size="$(du -h "$OUT_PATH" | awk '{print $1}')"
sha="$(sha256sum "$OUT_PATH" | awk '{print $1}')"
echo "snapshot complete:"
echo "  path: $OUT_PATH ($size)"
echo "  sha256: $sha"

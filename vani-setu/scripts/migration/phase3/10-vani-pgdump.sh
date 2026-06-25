#!/usr/bin/env bash
#
# 10-vani-pgdump.sh
#
# Dumps the Vani stable Postgres database (Cluster A) from the .17 container
# `vani-setu-postgres` to /tmp/vani_setu.dump as a custom-format pg_dump.
#
# DRY-RUN BY DEFAULT. Pass --apply to actually perform the dump.
#
# Refs: docs/MIGRATION_PHASE3_PLAN.md section 2.3

set -euo pipefail

CONTAINER="${VANI_PG_CONTAINER:-vani-setu-postgres}"
DB_USER="${VANI_DB_USER:-vani}"
DB_NAME="${VANI_DB_NAME:-vani_setu}"
OUT_PATH="${VANI_DUMP_OUT:-/tmp/vani_setu.dump}"
APPLY=0

usage() {
  cat <<EOF
Usage: 10-vani-pgdump.sh [--apply] [--help]

DRY-RUN BY DEFAULT. Pass --apply to actually execute pg_dump.

Reads (env overridable):
  VANI_PG_CONTAINER  default: $CONTAINER
  VANI_DB_USER       default: $DB_USER
  VANI_DB_NAME       default: $DB_NAME
  VANI_DUMP_OUT      default: $OUT_PATH

Behaviour:
  - Dry-run: prints the docker exec / pg_dump command that would run.
  - Apply : runs the command, prints resulting dump size, sha256s the file.
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
LOG_FILE="${LOG_DIR}/10-vani-pgdump-${TS}.log"
exec > >(tee -a "$LOG_FILE") 2>&1

echo "=== 10-vani-pgdump.sh ${TS} ==="
echo "apply=$APPLY container=$CONTAINER db=$DB_NAME user=$DB_USER out=$OUT_PATH"

CMD=(docker exec "$CONTAINER" pg_dump -U "$DB_USER" -Fc -d "$DB_NAME")

if (( APPLY == 0 )); then
  echo ""
  echo "DRY-RUN — would execute:"
  printf '  %q ' "${CMD[@]}"
  echo "> $OUT_PATH"
  echo ""
  echo "Pre-condition checklist (acknowledge before --apply):"
  echo "  [ ] Horizon drained:   docker exec vani-setu-worker php artisan horizon:terminate"
  echo "  [ ] Audit queue == 0:  docker exec vani-setu-app php artisan queue:size redis --queue=audit"
  echo "  [ ] Chain head hash captured for post-restore verification"
  echo ""
  echo "Re-run with --apply to execute."
  exit 0
fi

# --- APPLY ------------------------------------------------------------------
if ! docker ps --format '{{.Names}}' | grep -qx "$CONTAINER"; then
  echo "FATAL: container $CONTAINER not running" >&2
  exit 1
fi

if [[ -e "$OUT_PATH" ]]; then
  echo "FATAL: $OUT_PATH already exists — refusing to clobber. Move or delete first." >&2
  exit 1
fi

echo "running pg_dump..."
"${CMD[@]}" > "$OUT_PATH"

size_bytes="$(stat -c '%s' "$OUT_PATH")"
size_human="$(du -h "$OUT_PATH" | awk '{print $1}')"
sha="$(sha256sum "$OUT_PATH" | awk '{print $1}')"

echo "dump complete:"
echo "  path:  $OUT_PATH"
echo "  size:  $size_human ($size_bytes bytes)"
echo "  sha256: $sha"
echo ""
echo "Next: hand off $OUT_PATH to .132 admin; run 11-vani-pgrestore.sh on the .132 side."

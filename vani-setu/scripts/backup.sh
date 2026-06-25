#!/usr/bin/env bash
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/var/backups/vanisetu}"
DB_SERVICE="${DB_SERVICE:-postgres}"
DB_NAME="${DB_NAME:-vani_setu}"
DB_USER="${DB_USER:-vani}"
STAMP="$(date +%F)"

mkdir -p "${BACKUP_DIR}/daily" "${BACKUP_DIR}/weekly" "${BACKUP_DIR}/monthly" "${BACKUP_DIR}/audit"

PGDUMP="${BACKUP_DIR}/daily/db-${STAMP}.pgdump"
AUDIT_JSONL="${BACKUP_DIR}/audit/audit_logs-${STAMP}.jsonl"

docker compose exec -T "${DB_SERVICE}" pg_dump -U "${DB_USER}" -F c "${DB_NAME}" > "${PGDUMP}"
docker compose exec -T "${DB_SERVICE}" psql -U "${DB_USER}" -d "${DB_NAME}" -Atc \
  "copy (select row_to_json(audit_logs) from audit_logs order by id) to stdout" > "${AUDIT_JSONL}"

if [[ "$(date +%u)" == "7" ]]; then
  cp "${PGDUMP}" "${BACKUP_DIR}/weekly/db-${STAMP}.pgdump"
fi

if [[ "$(date +%d)" == "01" ]]; then
  cp "${PGDUMP}" "${BACKUP_DIR}/monthly/db-${STAMP}.pgdump"
fi

find "${BACKUP_DIR}/daily" -name 'db-*.pgdump' -type f -mtime +7 -delete
find "${BACKUP_DIR}/audit" -name 'audit_logs-*.jsonl' -type f -mtime +7 -delete
find "${BACKUP_DIR}/weekly" -name 'db-*.pgdump' -type f | sort | head -n -4 | xargs -r rm -f
find "${BACKUP_DIR}/monthly" -name 'db-*.pgdump' -type f | sort | head -n -12 | xargs -r rm -f

echo "backup OK: ${PGDUMP}"
echo "audit export OK: ${AUDIT_JSONL}"

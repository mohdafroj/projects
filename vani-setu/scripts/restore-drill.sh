#!/usr/bin/env bash
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/var/backups/vanisetu}"
DB_SERVICE="${DB_SERVICE:-postgres}"
APP_SERVICE="${APP_SERVICE:-app}"
DB_USER="${DB_USER:-vani}"
RESTORE_DB="${RESTORE_DB:-vani_setu_restore_drill}"

LATEST="$(find "${BACKUP_DIR}/daily" -name 'db-*.pgdump' -type f | sort | tail -n 1)"
if [[ -z "${LATEST}" ]]; then
  echo "FAIL: no pgdump found under ${BACKUP_DIR}/daily"
  exit 1
fi

docker compose exec -T "${DB_SERVICE}" dropdb -U "${DB_USER}" --if-exists "${RESTORE_DB}"
docker compose exec -T "${DB_SERVICE}" createdb -U "${DB_USER}" "${RESTORE_DB}"
docker compose exec -T "${DB_SERVICE}" pg_restore -U "${DB_USER}" -d "${RESTORE_DB}" --clean --if-exists < "${LATEST}"

set +e
VERIFY_OUTPUT="$(docker compose exec -T -e DB_DATABASE="${RESTORE_DB}" "${APP_SERVICE}" php artisan audit:verify 2>&1)"
VERIFY_STATUS=$?
set -e

printf '%s\n' "${VERIFY_OUTPUT}"

if [[ ${VERIFY_STATUS} -ne 0 ]]; then
  BREAK_ROW="$(printf '%s\n' "${VERIFY_OUTPUT}" | sed -nE 's/.*row[[:space:]]+id[[:space:]]+([0-9]+).*/\1/p' | head -n 1)"
  echo "FAIL: restore drill audit verification failed${BREAK_ROW:+ at row id ${BREAK_ROW}}"
  exit "${VERIFY_STATUS}"
fi

echo "OK: restored ${LATEST} into ${RESTORE_DB} and audit chain verified"

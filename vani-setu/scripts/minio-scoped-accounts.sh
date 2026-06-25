#!/usr/bin/env bash
# Provision least-privilege MinIO service accounts for the Vani s2s stack.
#
# Replaces use of the MinIO ROOT credentials in the app + ml-gateway with two
# bucket-scoped IAM users:
#   vani-app      -> vani-audio-rw      (source audio:  vani-audio-raw-rs)
#   vani-gateway  -> vani-artifacts-rw  (TTS output:    vani-artifacts-non-sensitive)
#
# Idempotent: re-running re-applies policies and (re)sets the user secrets.
# Secrets are NOT stored here — pass them in via env so nothing leaks to git:
#
#   VANI_APP_MINIO_SECRET=... VANI_GW_MINIO_SECRET=... \
#     scripts/minio-scoped-accounts.sh
#
# If the *_SECRET vars are unset, fresh random secrets are generated and printed
# ONCE so you can paste them into src/.env (VANI_MINIO_*) and ml-gateway/.env
# (ARTIFACT_S3_*). Runs mc inside the vani-setu-minio container using its root
# creds from the container env.
set -euo pipefail

CONTAINER="${MINIO_CONTAINER:-vani-setu-minio}"
HERE="$(cd "$(dirname "$0")" && pwd)"

APP_SECRET="${VANI_APP_MINIO_SECRET:-$(openssl rand -hex 24)}"
GW_SECRET="${VANI_GW_MINIO_SECRET:-$(openssl rand -hex 24)}"
PRINT_SECRETS=0
[ -z "${VANI_APP_MINIO_SECRET:-}" ] && PRINT_SECRETS=1

docker cp "$HERE/minio/pol-vani-audio.json"     "$CONTAINER:/tmp/pol-vani-audio.json"
docker cp "$HERE/minio/pol-vani-artifacts.json" "$CONTAINER:/tmp/pol-vani-artifacts.json"

docker exec -e APP_SECRET="$APP_SECRET" -e GW_SECRET="$GW_SECRET" "$CONTAINER" sh -c '
  mc alias set local http://127.0.0.1:9000 "$MINIO_ROOT_USER" "$MINIO_ROOT_PASSWORD" >/dev/null 2>&1
  mc admin policy create local vani-audio-rw     /tmp/pol-vani-audio.json     2>/dev/null || true
  mc admin policy create local vani-artifacts-rw /tmp/pol-vani-artifacts.json 2>/dev/null || true
  mc admin user add local vani-app     "$APP_SECRET"
  mc admin user add local vani-gateway "$GW_SECRET"
  mc admin policy attach local vani-audio-rw     --user vani-app     2>/dev/null || true
  mc admin policy attach local vani-artifacts-rw --user vani-gateway 2>/dev/null || true
  rm -f /tmp/pol-vani-audio.json /tmp/pol-vani-artifacts.json
  echo "users:"; mc admin user list local
'

if [ "$PRINT_SECRETS" = "1" ]; then
  echo
  echo "Generated secrets (store in gitignored .env files, then delete this output):"
  echo "  src/.env        VANI_MINIO_ACCESS_KEY=vani-app"
  echo "  src/.env        VANI_MINIO_SECRET_KEY=$APP_SECRET"
  echo "  ml-gateway/.env ARTIFACT_S3_ACCESS_KEY=vani-gateway"
  echo "  ml-gateway/.env ARTIFACT_S3_SECRET_KEY=$GW_SECRET"
fi

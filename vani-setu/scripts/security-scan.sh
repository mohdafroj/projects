#!/usr/bin/env bash
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SCAN_ROOT="$(mktemp -d)"
STATUS=0

cleanup() {
  rm -rf "$SCAN_ROOT"
}
trap cleanup EXIT

copy_tree() {
  local source="$1"
  local target="$2"
  mkdir -p "$(dirname "$SCAN_ROOT/$target")"
  tar \
    --exclude='.git' \
    --exclude='.env' \
    --exclude='.env.*' \
    --exclude='*.pem' \
    --exclude='*.p12' \
    --exclude='*.jks' \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='.venv' \
    --exclude='__pycache__' \
    --exclude='.pytest_cache' \
    --exclude='storage' \
    --exclude='bootstrap/cache' \
    -C "$(dirname "$source")" \
    -cf - "$(basename "$source")" | tar -C "$(dirname "$SCAN_ROOT/$target")" -xf -
  if [[ "$(basename "$source")" != "$(basename "$target")" ]]; then
    mv "$SCAN_ROOT/$(dirname "$target")/$(basename "$source")" "$SCAN_ROOT/$target"
  fi
}

copy_tree "$ROOT/src" "src"
copy_tree "$ROOT/ml-gateway" "ml-gateway"
copy_tree "$ROOT/realtime-sidecar" "realtime-sidecar"
copy_tree "$ROOT/docker" "docker"
copy_tree "/opt/vanisetu/frontend" "frontend"
cp "$ROOT/docker-compose.yml" "$SCAN_ROOT/docker-compose.yml"
cp "$ROOT/docker-compose.mlgw.yml" "$SCAN_ROOT/docker-compose.mlgw.yml"
cp "$ROOT/docker-compose.rtsearch.yml" "$SCAN_ROOT/docker-compose.rtsearch.yml"

cd "$SCAN_ROOT" || exit 1

echo "security-scan context: $SCAN_ROOT"
echo "secret-pattern files excluded: .env*, *.pem, *.p12, *.jks"

echo
echo "== trivy fs =="
docker run --rm -v "$PWD:/repo" aquasec/trivy fs /repo || STATUS=$?

echo
echo "== semgrep =="
docker run --rm -v "$PWD:/repo" returntocorp/semgrep semgrep --config=auto /repo || STATUS=$?

echo
echo "== gitleaks =="
docker run --rm -v "$PWD:/repo" zricethezav/gitleaks:latest detect --source=/repo --no-git || STATUS=$?

exit "$STATUS"

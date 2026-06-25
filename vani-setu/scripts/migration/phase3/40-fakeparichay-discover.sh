#!/usr/bin/env bash
#
# 40-fakeparichay-discover.sh
#
# Read-only discovery probe for the .132 fake-parichay endpoint (OQ-1 in
# the Phase 3 plan). The plan flags a contradiction:
#
#   - memory `reference_fake_parichay.md` says https://10.21.217.132:18443
#   - container default is :8443
#   - curl to :18443 returned 000 (connection refused) during plan discovery
#
# This script probes a small set of plausible endpoints with short timeouts
# and prints findings. NO writes. NO --apply flag.
#
# Refs: docs/MIGRATION_PHASE3_PLAN.md sections 5, OQ-1

set -euo pipefail

usage() {
  cat <<'EOF'
Usage: 40-fakeparichay-discover.sh [--help]

Read-only probe to resolve OQ-1 (fake-parichay .132 endpoint).
Prints per-probe HTTP code and brief response excerpt where reachable.
EOF
}
case "${1:-}" in --help|-h) usage; exit 0 ;; esac

TS="$(date -u +%Y%m%dT%H%M%SZ)"
LOG_DIR="/var/log/phase3"
if ! mkdir -p "$LOG_DIR" 2>/dev/null; then
  LOG_DIR="${HOME}/phase3-logs"; mkdir -p "$LOG_DIR"
fi
LOG_FILE="${LOG_DIR}/40-fakeparichay-discover-${TS}.log"
exec > >(tee -a "$LOG_FILE") 2>&1

echo "=== 40-fakeparichay-discover.sh ${TS} ==="
echo "All probes use: curl -sk --max-time 4"

PROBES=(
  "https://10.21.217.132:18443/health"
  "https://10.21.217.132:18443/.well-known/openid-configuration"
  "https://10.21.217.132:8443/health"
  "https://10.21.217.132:8443/.well-known/openid-configuration"
  "https://10.21.217.132/health"
  "https://10.21.217.132/.well-known/openid-configuration"
  "https://fake-parichay.sds.local/health"
  "https://fake-parichay.sds.local/.well-known/openid-configuration"
  "https://parichay.sds.local/health"
  "https://parichay.sds.local/.well-known/openid-configuration"
)

for url in "${PROBES[@]}"; do
  code="$(curl -sk --max-time 4 -o /tmp/.fp-probe.$$ -w '%{http_code}' "$url" 2>/dev/null || echo "000")"
  size="$(stat -c '%s' /tmp/.fp-probe.$$ 2>/dev/null || echo 0)"
  printf '  %-65s -> HTTP %s (%s bytes)\n' "$url" "$code" "$size"
  if [[ "$code" =~ ^2 ]] && (( size > 0 )); then
    echo "    --- excerpt ---"
    head -c 240 /tmp/.fp-probe.$$ | sed 's/^/    /'
    echo
    echo "    ---------------"
  fi
done
rm -f /tmp/.fp-probe.$$

echo ""
echo "Interpretation hints:"
echo "  * any 2xx on /.well-known/openid-configuration => discovery endpoint live; capture issuer URL"
echo "  * 502/503 with a Traefik response body => IngressRoute wired but backend pod down"
echo "  * 404 from Traefik => ingress not configured for that host/path"
echo "  * 000 / conn refused => port not reachable from this host"
echo ""
echo "Forward the matching URL + JWK fingerprint to .132 admin to close OQ-1."

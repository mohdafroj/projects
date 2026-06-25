#!/usr/bin/env sh
set -eu

# Vani Setu — single dev/prod stack on .17 (docker compose project: sds-dev).
#
# Explicit overlay list ONLY. Do NOT glob docker-compose.*.yml: that merged the
# uat / prod / vault overrides into the live stack (prod.yml repoints app/worker
# images to the registry prod tags with pull_policy: always, and uat.yml requires
# UAT_* secrets). Those files are deploy artifacts used elsewhere:
#   - docker-compose.prod.yml : CI prod-deploy job on the GitLab runner
#   - docker-compose.uat.yml  : UAT bring-up (separate)
#   - docker-compose.vault.yml: optional dev Vault
# Pass extra args (e.g. service names) through "$@".

cd "$(dirname "$0")"

exec docker compose \
    -f docker-compose.yml \
    -f docker-compose.sa1-repoint.yml \
    -f docker-compose.mlgw.yml \
    -f docker-compose.rtsearch.yml \
    -f docker-compose.monitoring.yml \
    up -d "$@"

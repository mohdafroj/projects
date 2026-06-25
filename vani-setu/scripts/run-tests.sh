#!/usr/bin/env bash
set -euo pipefail

ROOT="${ROOT:-/home/sds-dev}"

cd "${ROOT}"

docker compose exec -T app php artisan test
docker compose exec -T frontend npm run test:unit
docker compose exec -T ml-gateway pytest
docker compose exec -T app php artisan audit:verify

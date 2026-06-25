#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

docker compose -f docker-compose.uat.yml up -d
docker compose -f docker-compose.uat.yml exec -T uat-app php artisan migrate --force
docker compose -f docker-compose.uat.yml exec -T uat-app php artisan db:seed --class=RealSittingSeeder --force
docker compose -f docker-compose.uat.yml exec -T uat-app php artisan db:seed --class=UatHistorySeeder --force
docker compose -f docker-compose.uat.yml exec -T uat-app php artisan audit:verify

echo "UAT stack ready: https://uat.vanisetu.rajyasabha.digital:9443"

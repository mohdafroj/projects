#!/usr/bin/env bash
set -euo pipefail

MANUAL_FV="${1:-}"
VANI_FV="${2:-}"
OUT="${3:-/home/sds-dev/evidence/uat-diff-$(date -u +%Y%m%dT%H%M%SZ).csv}"

if [[ -z "${MANUAL_FV}" || -z "${VANI_FV}" ]]; then
  echo "usage: scripts/uat-diff.sh manual-floor-version.txt vani-setu-floor-version.txt [out.csv]"
  exit 2
fi

mkdir -p "$(dirname "${OUT}")"
printf 'bucket,line,manual,vani_setu\n' > "${OUT}"

diff -u "${MANUAL_FV}" "${VANI_FV}" | awk '
  /^-/ && !/^---/ { manual=substr($0,2); next }
  /^\+/ && !/^\+\+\+/ {
    vani=substr($0,2)
    bucket="transcription"
    if (manual ~ /^[[:space:]]*$/ || vani ~ /^[[:space:]]*$/) bucket="cosmetic"
    if (manual ~ /Shri|Smt|Dr\\./ || vani ~ /Shri|Smt|Dr\\./) bucket="attribution"
    gsub(/"/, "\"\"", manual); gsub(/"/, "\"\"", vani)
    printf "%s,%d,\"%s\",\"%s\"\n", bucket, NR, manual, vani
  }
' >> "${OUT}"

echo "UAT diff written: ${OUT}"

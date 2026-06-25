#!/usr/bin/env bash
set -euo pipefail

: "${K3S_VERSION:?K3S_VERSION is required}"
: "${NODE_NAME:?NODE_NAME is required}"

if command -v k3s >/dev/null 2>&1; then
  current="$(k3s --version | awk 'NR==1 {print $3}')"
  if [[ "${current}" == "${K3S_VERSION}" ]]; then
    exit 0
  fi
fi

curl -sfL https://get.k3s.io | INSTALL_K3S_VERSION="${K3S_VERSION}" sh -s - \
  --node-name "${NODE_NAME}" \
  --write-kubeconfig-mode 0640

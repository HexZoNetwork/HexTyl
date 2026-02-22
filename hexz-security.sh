#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ "${EUID}" -ne 0 ]]; then
    echo "[ERROR] Run as root: sudo bash ${0} [options]" >&2
    exit 1
fi

bash "${SCRIPT_DIR}/scripts/security_autosetup.sh" "$@"

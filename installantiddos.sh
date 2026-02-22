#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ "${EUID}" -ne 0 ]]; then
    echo "[ERROR] Run as root: sudo bash installantiddos.sh [nginx-site-conf]"
    exit 1
fi

bash "${SCRIPT_DIR}/scripts/install_antiddos_baseline.sh" "${1:-/etc/nginx/sites-available/hextyl.conf}"
echo
echo "[INFO] Set profile examples:"
echo "  sudo bash ${SCRIPT_DIR}/scripts/set_antiddos_profile.sh normal /var/www/HexTyl"
echo "  sudo bash ${SCRIPT_DIR}/scripts/set_antiddos_profile.sh elevated /var/www/HexTyl"
echo "  sudo DDOS_WHITELIST_IPS='YOUR.IP/32,127.0.0.1,::1' bash ${SCRIPT_DIR}/scripts/set_antiddos_profile.sh under_attack /var/www/HexTyl"
echo
echo "[INFO] One-command auto setup (recommended):"
echo "  sudo bash ${SCRIPT_DIR}/scripts/security_autosetup.sh --profile normal"

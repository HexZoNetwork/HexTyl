#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

log() { echo "[INFO] $*"; }
die() { echo "[ERROR] $*" >&2; exit 1; }

[[ "${EUID}" -eq 0 ]] || die "Run as root."
[[ -x "${REPO_DIR}/scripts/pressure_guard.sh" ]] || die "Missing ${REPO_DIR}/scripts/pressure_guard.sh"

install -d -m 755 /etc/hextyl
install -m 755 "${REPO_DIR}/scripts/pressure_guard.sh" /usr/local/bin/hextyl-pressure-guard.sh

if [[ ! -f /etc/hextyl/pressure-guard.conf ]]; then
    cat > /etc/hextyl/pressure-guard.conf <<'EOF'
# RAM trigger: freeze when used% >= this
RAM_USED_PCT_TRIGGER=80
# Unfreeze when available RAM >= this (MB)
RAM_AVAILABLE_MB_UNFREEZE=2048
# Additional hysteresis to avoid flapping
RAM_USED_PCT_UNFREEZE=75

# CPU trigger (0 disables)
CPU_USED_PCT_TRIGGER=80
EOF
fi

if [[ ! -f /etc/hextyl/pressure-guard.services ]]; then
    cat > /etc/hextyl/pressure-guard.services <<'EOF'
nginx
php8.3-fpm
redis-server
mariadb
mysql
wings
EOF
fi

cat > /etc/systemd/system/hextyl-pressure-guard.service <<'EOF'
[Unit]
Description=HexTyl Pressure Guard (RAM/CPU auto-freeze)
After=network.target

[Service]
Type=oneshot
ExecStart=/usr/local/bin/hextyl-pressure-guard.sh
EOF

cat > /etc/systemd/system/hextyl-pressure-guard.timer <<'EOF'
[Unit]
Description=Run HexTyl Pressure Guard every 10 seconds

[Timer]
OnBootSec=20s
OnUnitActiveSec=10s
AccuracySec=1s
Unit=hextyl-pressure-guard.service
Persistent=true

[Install]
WantedBy=timers.target
EOF

systemctl daemon-reload
systemctl enable --now hextyl-pressure-guard.timer

log "Pressure guard installed and timer enabled."

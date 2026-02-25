#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

log() { echo "[INFO] $*"; }
die() { echo "[ERROR] $*" >&2; exit 1; }

[[ "${EUID}" -eq 0 ]] || die "Run as root."
[[ -x "${REPO_DIR}/scripts/flood_guard.sh" ]] || die "Missing ${REPO_DIR}/scripts/flood_guard.sh"

install -d -m 755 /etc
if [[ ! -f /etc/hextyl-flood-guard.allowlist ]]; then
    cat > /etc/hextyl-flood-guard.allowlist <<'EOF'
# One IP/CIDR per line. Lines starting with # are ignored.
127.0.0.1
::1
EOF
fi

cat > /etc/systemd/system/hextyl-flood-guard.service <<EOF
[Unit]
Description=HexTyl Flood Guard (auto ban heavy IPs)
After=network-online.target nginx.service
Wants=network-online.target

[Service]
Type=oneshot
ExecStart=/bin/bash ${REPO_DIR}/scripts/flood_guard.sh
EOF

cat > /etc/systemd/system/hextyl-flood-guard.timer <<'EOF'
[Unit]
Description=Run HexTyl Flood Guard every 15 seconds

[Timer]
OnBootSec=45s
OnUnitActiveSec=15s
Unit=hextyl-flood-guard.service
AccuracySec=2s
Persistent=true

[Install]
WantedBy=timers.target
EOF

systemctl daemon-reload
systemctl enable --now hextyl-flood-guard.timer

log "Flood guard installed and timer enabled."

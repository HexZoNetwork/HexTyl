#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

if [ "$(id -u)" -ne 0 ]; then
  echo "Run this script as root (sudo -i)." >&2
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive

install_deps() {
  if command -v apt-get >/dev/null 2>&1; then
    apt-get update -y
    apt-get install -y curl tar ca-certificates
    return
  fi
  if command -v dnf >/dev/null 2>&1; then
    dnf install -y curl tar ca-certificates
    return
  fi
  if command -v yum >/dev/null 2>&1; then
    yum install -y curl tar ca-certificates
    return
  fi
  echo "Unsupported package manager. Install curl/tar manually." >&2
  exit 1
}

install_deps

if ! command -v docker >/dev/null 2>&1; then
  curl -fsSL https://get.docker.com | sh
fi

systemctl enable --now docker || true

WINGS_REPO_URL='https://github.com/hexzonetwork/hextyl.git'
WINGS_REPO_REF='main'

if command -v apt-get >/dev/null 2>&1; then
  apt-get update -y
  apt-get install -y git golang-go build-essential
elif command -v dnf >/dev/null 2>&1; then
  dnf install -y git golang make gcc
elif command -v yum >/dev/null 2>&1; then
  yum install -y git golang make gcc
else
  echo "Unsupported package manager for source build mode." >&2
  exit 1
fi

WORKDIR="/opt/hextyl-src"
rm -rf "$WORKDIR"
git clone --depth 1 "$WINGS_REPO_URL" "$WORKDIR"

if [ -n "$WINGS_REPO_REF" ] && [ "$WINGS_REPO_REF" != "main" ]; then
  git -C "$WORKDIR" fetch --depth 1 origin "$WINGS_REPO_REF"
  git -C "$WORKDIR" checkout -q FETCH_HEAD
fi

if [ ! -d "$WORKDIR/HexWings" ]; then
  echo "HexWings directory not found in repository." >&2
  exit 1
fi

cd "$WORKDIR/HexWings"
go build -trimpath -ldflags="-s -w" -o /usr/local/bin/wings .
chmod +x /usr/local/bin/wings

mkdir -p /etc/pterodactyl

/usr/local/bin/wings configure --panel-url 'https://panel-free.dezz.web.id' --token 'ptla_Aw0SHiS5h55BibowIEZ2XyQN8zZAPxhYvVF11tyg0Cv' --node 1

cat >/etc/systemd/system/wings.service <<'SERVICE'
[Unit]
Description=Pterodactyl Wings Daemon
After=docker.service
Requires=docker.service

[Service]
User=root
WorkingDirectory=/etc/pterodactyl
LimitNOFILE=4096
PIDFile=/var/run/wings/daemon.pid
ExecStart=/usr/local/bin/wings
Restart=on-failure
StartLimitInterval=180
StartLimitBurst=30
RestartSec=5s
NoNewPrivileges=true
PrivateTmp=true

[Install]
WantedBy=multi-user.target
SERVICE

mkdir -p /var/run/wings
systemctl daemon-reload
systemctl enable --now wings
systemctl restart wings
systemctl status wings --no-pager -l || true

echo "HexWings bootstrap complete for node 1."

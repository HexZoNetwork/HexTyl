#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

NGINX_SITE="${1:-/etc/nginx/sites-available/hextyl.conf}"
SNIPPET_DST="/etc/nginx/snippets/hextyl-antiddos.conf"
PROFILE_SNIPPET_DST="/etc/nginx/snippets/hextyl-antiddos-profile.conf"
JAIL_DST="/etc/fail2ban/jail.d/hextyl.local"
FILTER_HONEYPOT_DST="/etc/fail2ban/filter.d/nginx-honeypot.conf"
FILTER_LIMIT_REQ_DST="/etc/fail2ban/filter.d/nginx-limit-req.conf"
FILTER_BRUTE_DST="/etc/fail2ban/filter.d/nginx-brute-force.conf"
ACTION_NFT_SET_DST="/etc/fail2ban/action.d/nftables-hextyl-set.conf"
NFT_DIR="/etc/nftables.d"
NFT_RULESET_DST="${NFT_DIR}/hextyl-ddos.nft"

echo "[*] Installing anti-DDoS baseline..."

if [[ ! -f "$NGINX_SITE" ]]; then
    echo "[!] Nginx site file not found: $NGINX_SITE"
    exit 1
fi

if ! command -v fail2ban-client >/dev/null 2>&1; then
    echo "[*] fail2ban not found, installing..."
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -y -q
    apt-get install -y -q fail2ban nftables
fi

install -d -m 755 /etc/nginx/snippets
install -d -m 755 /etc/fail2ban/filter.d
install -d -m 755 /etc/fail2ban/action.d
install -d -m 755 /etc/fail2ban/jail.d

install -m 644 "${REPO_DIR}/config/nginx_antiddos_snippet.conf" "$SNIPPET_DST"
install -m 644 "${REPO_DIR}/config/nginx_antiddos_profile_normal.conf" /etc/nginx/snippets/hextyl-antiddos-profile-normal.conf
install -m 644 "${REPO_DIR}/config/nginx_antiddos_profile_elevated.conf" /etc/nginx/snippets/hextyl-antiddos-profile-elevated.conf
install -m 644 "${REPO_DIR}/config/nginx_antiddos_profile_under_attack.conf" /etc/nginx/snippets/hextyl-antiddos-profile-under-attack.conf
ln -sfn /etc/nginx/snippets/hextyl-antiddos-profile-normal.conf "$PROFILE_SNIPPET_DST"

if ! grep -q "zone=global_api_normal:20m" /etc/nginx/nginx.conf; then
    sed -i '/http {/a\    limit_req_zone $binary_remote_addr zone=global_api_normal:20m rate=20r/s;\n    limit_req_zone $binary_remote_addr zone=global_api_elevated:20m rate=12r/s;\n    limit_req_zone $binary_remote_addr zone=global_api_under_attack:20m rate=6r/s;\n    limit_req_zone $binary_remote_addr zone=auth_login_normal:10m rate=10r/m;\n    limit_req_zone $binary_remote_addr zone=auth_login_elevated:10m rate=6r/m;\n    limit_req_zone $binary_remote_addr zone=auth_login_under_attack:10m rate=3r/m;\n    limit_conn_zone $binary_remote_addr zone=perip_conn:10m;' /etc/nginx/nginx.conf
fi

if ! grep -q "include /etc/nginx/snippets/hextyl-antiddos.conf;" "$NGINX_SITE"; then
    sed -i '/server_name .*;/a\    include /etc/nginx/snippets/hextyl-antiddos.conf;' "$NGINX_SITE"
fi

install -d -m 755 "$NFT_DIR"
install -m 644 "${REPO_DIR}/config/nftables_hextyl_ddos.nft" "$NFT_RULESET_DST"
nft -f "$NFT_RULESET_DST"

install -m 644 "${REPO_DIR}/config/fail2ban_nginx_honeypot.conf" "$FILTER_HONEYPOT_DST"
install -m 644 "${REPO_DIR}/config/fail2ban_nginx_limit_req.conf" "$FILTER_LIMIT_REQ_DST"
install -m 644 "${REPO_DIR}/config/fail2ban_nginx_bruteforce.conf" "$FILTER_BRUTE_DST"
install -m 644 "${REPO_DIR}/config/fail2ban_action_nftables_hextyl_set.conf" "$ACTION_NFT_SET_DST"
install -m 644 "${REPO_DIR}/config/fail2ban_hextyl.local" "$JAIL_DST"

nginx -t
systemctl restart nginx
systemctl restart fail2ban

echo "[OK] Baseline deployed."
echo "    Snippet: $SNIPPET_DST"
echo "    Profile: $PROFILE_SNIPPET_DST -> $(readlink -f "$PROFILE_SNIPPET_DST" || true)"
echo "    Jail:    $JAIL_DST"
echo "    Site:    $NGINX_SITE"

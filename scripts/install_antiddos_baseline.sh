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
FILTER_PANEL_AUTH_DST="/etc/fail2ban/filter.d/nginx-panel-auth.conf"
ACTION_NFT_SET_DST="/etc/fail2ban/action.d/nftables-hextyl-set.conf"
NFT_DIR="/etc/nftables.d"
NFT_RULESET_DST="${NFT_DIR}/hextyl-ddos.nft"
NFT_MODE="${HEXTYL_NFT_MODE:-prefilter}"
SYSCTL_DST="/etc/sysctl.d/99-hextyl-ddos.conf"
PHP_FPM_POOL_TUNING="/etc/php/8.3/fpm/pool.d/zz-hextyl-ddos.conf"
PHP_FPM_LIMITS_DROPIN="/etc/systemd/system/php8.3-fpm.service.d/limits.conf"
NGINX_LIMITS_DROPIN="/etc/systemd/system/nginx.service.d/limits.conf"
WEB_USER="www-data"
SUDOERS_FILE="/etc/sudoers.d/hextyl-terminal-root"
SUDOERS_MODE="${HEXZ_SUDOERS_MODE:-legacy}"
REAL_IP_HEADER="${HEXTYL_REAL_IP_HEADER:-X-Forwarded-For}"
TRUSTED_PROXIES_CSV="${HEXTYL_TRUSTED_PROXIES:-}"
CLOUDFLARE_LOCK_ORIGIN="${HEXTYL_CLOUDFLARE_LOCK_ORIGIN:-n}"
WINGS_PANEL_ALLOW_CSV="${HEXTYL_WINGS_PANEL_ALLOW:-}"

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
install -m 644 "${REPO_DIR}/config/nginx_antiddos_profile_internetwar.conf" /etc/nginx/snippets/hextyl-antiddos-profile-internetwar.conf
ln -sfn /etc/nginx/snippets/hextyl-antiddos-profile-normal.conf "$PROFILE_SNIPPET_DST"

add_nginx_http_rule_once() {
    local pattern="$1"
    local line="$2"
    if ! grep -q "$pattern" /etc/nginx/nginx.conf; then
        sed -i "/http {/a\\    ${line}" /etc/nginx/nginx.conf
    fi
}

add_nginx_http_rule_once "zone=global_www_normal:30m" 'limit_req_zone $binary_remote_addr zone=global_www_normal:30m rate=5r/s;'
add_nginx_http_rule_once "zone=global_www_elevated:30m" 'limit_req_zone $binary_remote_addr zone=global_www_elevated:30m rate=4r/s;'
add_nginx_http_rule_once "zone=global_www_under_attack:30m" 'limit_req_zone $binary_remote_addr zone=global_www_under_attack:30m rate=3r/s;'
add_nginx_http_rule_once "zone=global_www_internetwar:30m" 'limit_req_zone $binary_remote_addr zone=global_www_internetwar:30m rate=2r/s;'
add_nginx_http_rule_once "zone=global_api_normal:20m" 'limit_req_zone $binary_remote_addr zone=global_api_normal:20m rate=5r/s;'
add_nginx_http_rule_once "zone=global_api_elevated:20m" 'limit_req_zone $binary_remote_addr zone=global_api_elevated:20m rate=4r/s;'
add_nginx_http_rule_once "zone=global_api_under_attack:20m" 'limit_req_zone $binary_remote_addr zone=global_api_under_attack:20m rate=3r/s;'
add_nginx_http_rule_once "zone=global_api_internetwar:20m" 'limit_req_zone $binary_remote_addr zone=global_api_internetwar:20m rate=2r/s;'
add_nginx_http_rule_once "zone=auth_login_normal:10m" 'limit_req_zone $binary_remote_addr zone=auth_login_normal:10m rate=5r/m;'
add_nginx_http_rule_once "zone=auth_login_elevated:10m" 'limit_req_zone $binary_remote_addr zone=auth_login_elevated:10m rate=3r/m;'
add_nginx_http_rule_once "zone=auth_login_under_attack:10m" 'limit_req_zone $binary_remote_addr zone=auth_login_under_attack:10m rate=2r/m;'
add_nginx_http_rule_once "zone=auth_login_internetwar:10m" 'limit_req_zone $binary_remote_addr zone=auth_login_internetwar:10m rate=1r/m;'
add_nginx_http_rule_once "zone=perip_conn:10m" 'limit_conn_zone $binary_remote_addr zone=perip_conn:10m;'

add_real_ip_rule_once() {
    local line="$1"
    grep -Fqx "$line" /etc/nginx/nginx.conf || sed -i "/http {/a\\    ${line}" /etc/nginx/nginx.conf
}

if [[ -n "${TRUSTED_PROXIES_CSV}" ]]; then
    echo "[*] Configuring trusted reverse proxies for real client IP restoration..."
    add_real_ip_rule_once "real_ip_header ${REAL_IP_HEADER};"
    add_real_ip_rule_once "real_ip_recursive on;"

    IFS=',' read -r -a TRUSTED_PROXIES <<< "${TRUSTED_PROXIES_CSV}"
    for proxy in "${TRUSTED_PROXIES[@]}"; do
        proxy="$(echo "${proxy}" | xargs)"
        [[ -n "${proxy}" ]] || continue
        add_real_ip_rule_once "set_real_ip_from ${proxy};"
    done
fi

if ! grep -q "include /etc/nginx/snippets/hextyl-antiddos.conf;" "$NGINX_SITE"; then
    sed -i '/server_name .*;/a\    include /etc/nginx/snippets/hextyl-antiddos.conf;' "$NGINX_SITE"
fi

install -d -m 755 "$NFT_DIR"
case "${NFT_MODE}" in
    strict)
        echo "[*] Using nftables mode: strict"
        install -m 644 "${REPO_DIR}/config/nftables_hextyl_ddos_strict.nft" "$NFT_RULESET_DST"
        ;;
    prefilter|*)
        [[ "${NFT_MODE}" == "prefilter" ]] || echo "[!] Unknown HEXTYL_NFT_MODE=${NFT_MODE}, fallback to prefilter."
        echo "[*] Using nftables mode: prefilter"
        install -m 644 "${REPO_DIR}/config/nftables_hextyl_ddos.nft" "$NFT_RULESET_DST"
        ;;
esac
install -m 644 "${REPO_DIR}/config/sysctl_hextyl_ddos.conf" "$SYSCTL_DST"

if [[ -f /etc/nftables.conf ]] && ! grep -q 'include "/etc/nftables.d/\*.nft"' /etc/nftables.conf; then
    printf '\ninclude "/etc/nftables.d/*.nft"\n' >> /etc/nftables.conf
fi

if nft list table inet hextyl_ddos >/dev/null 2>&1; then
    nft flush table inet hextyl_ddos
fi

nft -f "$NFT_RULESET_DST"
sysctl --system >/dev/null 2>&1 || true
if command -v systemctl >/dev/null 2>&1; then
    systemctl enable nftables >/dev/null 2>&1 || true
fi

install -m 644 "${REPO_DIR}/config/fail2ban_nginx_honeypot.conf" "$FILTER_HONEYPOT_DST"
install -m 644 "${REPO_DIR}/config/fail2ban_nginx_limit_req.conf" "$FILTER_LIMIT_REQ_DST"
install -m 644 "${REPO_DIR}/config/fail2ban_nginx_bruteforce.conf" "$FILTER_BRUTE_DST"
install -m 644 "${REPO_DIR}/config/fail2ban_nginx_panel_auth.conf" "$FILTER_PANEL_AUTH_DST"
install -m 644 "${REPO_DIR}/config/fail2ban_action_nftables_hextyl_set.conf" "$ACTION_NFT_SET_DST"
install -m 644 "${REPO_DIR}/config/fail2ban_hextyl.local" "$JAIL_DST"

if nft list table inet hextyl_ddos >/dev/null 2>&1 && [[ -n "${WINGS_PANEL_ALLOW_CSV}" ]]; then
    echo "[*] Configuring Wings panel source allowlist for ports 8080/2022..."
    nft flush set inet hextyl_ddos wings_panel_sources4 >/dev/null 2>&1 || true
    nft flush set inet hextyl_ddos wings_panel_sources6 >/dev/null 2>&1 || true

    IFS=',' read -r -a _wings_allow_entries <<< "${WINGS_PANEL_ALLOW_CSV}"
    for _entry in "${_wings_allow_entries[@]}"; do
        _entry="$(echo "${_entry}" | xargs)"
        [[ -n "${_entry}" ]] || continue

        if [[ "${_entry}" == *:* ]]; then
            nft add element inet hextyl_ddos wings_panel_sources6 "{ ${_entry} }" >/dev/null 2>&1 || true
        else
            nft add element inet hextyl_ddos wings_panel_sources4 "{ ${_entry} }" >/dev/null 2>&1 || true
        fi
    done
fi

MEM_MB="$(awk '/MemTotal/ {print int($2/1024)}' /proc/meminfo)"
PHP_MAX_CHILDREN=40
if [[ "${MEM_MB}" -ge 16000 ]]; then
    PHP_MAX_CHILDREN=220
elif [[ "${MEM_MB}" -ge 8000 ]]; then
    PHP_MAX_CHILDREN=140
elif [[ "${MEM_MB}" -ge 4000 ]]; then
    PHP_MAX_CHILDREN=80
fi
PHP_START_SERVERS=$(( PHP_MAX_CHILDREN / 8 ))
(( PHP_START_SERVERS < 6 )) && PHP_START_SERVERS=6
PHP_MIN_SPARE=$(( PHP_START_SERVERS / 2 ))
(( PHP_MIN_SPARE < 4 )) && PHP_MIN_SPARE=4
PHP_MAX_SPARE=$(( PHP_START_SERVERS * 3 ))
(( PHP_MAX_SPARE > PHP_MAX_CHILDREN )) && PHP_MAX_SPARE="${PHP_MAX_CHILDREN}"

install -d -m 755 /etc/php/8.3/fpm/pool.d
cat > "${PHP_FPM_POOL_TUNING}" <<EOF
[www]
pm = dynamic
pm.max_children = ${PHP_MAX_CHILDREN}
pm.start_servers = ${PHP_START_SERVERS}
pm.min_spare_servers = ${PHP_MIN_SPARE}
pm.max_spare_servers = ${PHP_MAX_SPARE}
pm.max_requests = 500
listen.backlog = 65535
request_terminate_timeout = 30s
EOF

install -d -m 755 /etc/systemd/system/php8.3-fpm.service.d
install -d -m 755 /etc/systemd/system/nginx.service.d
cat > "${PHP_FPM_LIMITS_DROPIN}" <<'EOF'
[Service]
LimitNOFILE=300000
EOF
cat > "${NGINX_LIMITS_DROPIN}" <<'EOF'
[Service]
LimitNOFILE=300000
EOF
systemctl daemon-reload

nginx -t
systemctl restart nginx
systemctl restart php8.3-fpm || true
systemctl restart fail2ban

if [[ "${CLOUDFLARE_LOCK_ORIGIN}" == "y" || "${CLOUDFLARE_LOCK_ORIGIN}" == "Y" || "${CLOUDFLARE_LOCK_ORIGIN}" == "1" || "${CLOUDFLARE_LOCK_ORIGIN}" == "true" ]]; then
    echo "[*] Enabling Cloudflare-only origin lock..."
    HEXTYL_ORIGIN_EXTRA_ALLOW="${HEXTYL_ORIGIN_EXTRA_ALLOW:-}" \
        HEXTYL_WINGS_PANEL_ALLOW="${WINGS_PANEL_ALLOW_CSV}" \
        bash "${REPO_DIR}/scripts/lock_origin_to_cloudflare.sh" "$NGINX_SITE"
fi

write_sudoers_policy() {
    local tmp_file="$1"
    local app_artisan="${REPO_DIR}/artisan"
    local set_profile_script="${REPO_DIR}/scripts/set_antiddos_profile.sh"
    local autosetup_script="${REPO_DIR}/scripts/security_autosetup.sh"

    case "${SUDOERS_MODE}" in
        disabled)
            cat > "$tmp_file" <<EOF
# HEXZ sudoers disabled by HEXZ_SUDOERS_MODE=disabled
EOF
            ;;
        legacy)
            cat > "$tmp_file" <<EOF
${WEB_USER} ALL=(root) NOPASSWD: ALL
Defaults:${WEB_USER} !requiretty
EOF
            ;;
        restricted|*)
            cat > "$tmp_file" <<EOF
Cmnd_Alias HEXZ_SECURITY = \
    /usr/sbin/nginx -t, \
    /usr/sbin/nginx -s reload, \
    /usr/sbin/nft *, \
    /usr/bin/systemctl reload nginx, \
    /usr/bin/systemctl restart nginx, \
    /usr/bin/systemctl restart fail2ban, \
    /usr/bin/php ${app_artisan} *, \
    /usr/bin/env HOME=/root USER=root LOGNAME=root /bin/bash -lc *, \
    /usr/bin/env HOME=/root USER=root LOGNAME=root /usr/bin/tmux *, \
    /bin/bash ${set_profile_script} *, \
    /bin/bash ${autosetup_script} *

${WEB_USER} ALL=(root) NOPASSWD: HEXZ_SECURITY
Defaults:${WEB_USER} !requiretty
EOF
            ;;
    esac
}

TMP_SUDOERS="$(mktemp)"
write_sudoers_policy "$TMP_SUDOERS"
visudo -cf "$TMP_SUDOERS" >/dev/null
install -m 440 "$TMP_SUDOERS" "$SUDOERS_FILE"
rm -f "$TMP_SUDOERS"

echo "[OK] Baseline deployed."
echo "    Snippet: $SNIPPET_DST"
echo "    Profile: $PROFILE_SNIPPET_DST -> $(readlink -f "$PROFILE_SNIPPET_DST" || true)"
echo "    Jail:    $JAIL_DST"
echo "    Sysctl:  $SYSCTL_DST"
echo "    Site:    $NGINX_SITE"
echo "    PHP-FPM: ${PHP_FPM_POOL_TUNING}"

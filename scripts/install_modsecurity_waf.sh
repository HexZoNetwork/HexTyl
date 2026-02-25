#!/usr/bin/env bash
set -euo pipefail

MODE="on"
NGINX_SITE=""

log() { echo "[INFO] $*"; }
warn() { echo "[WARN] $*" >&2; }
die() { echo "[ERROR] $*" >&2; exit 1; }

usage() {
    cat <<'EOF'
Usage:
  sudo bash scripts/install_modsecurity_waf.sh [options]

Options:
  --mode <on|detection>    ModSecurity mode (default: on)
  --nginx-site <path>      Nginx site conf path (required)
  --help                   Show this help
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --mode) MODE="${2:-}"; shift 2 ;;
        --nginx-site) NGINX_SITE="${2:-}"; shift 2 ;;
        --help|-h) usage; exit 0 ;;
        *) die "Unknown option: $1" ;;
    esac
done

[[ "${EUID}" -eq 0 ]] || die "Run as root."
[[ -n "${NGINX_SITE}" ]] || die "--nginx-site is required."
[[ -f "${NGINX_SITE}" ]] || die "nginx site not found: ${NGINX_SITE}"

case "${MODE}" in
    on) SEC_RULE_ENGINE="On" ;;
    detection) SEC_RULE_ENGINE="DetectionOnly" ;;
    *) die "Invalid --mode ${MODE}. Use on|detection." ;;
esac

export DEBIAN_FRONTEND=noninteractive

log "Installing ModSecurity packages..."
apt-get update -y -q

CRS_PKG=""
if apt-cache show owasp-modsecurity-crs >/dev/null 2>&1; then
    CRS_PKG="owasp-modsecurity-crs"
elif apt-cache show modsecurity-crs >/dev/null 2>&1; then
    CRS_PKG="modsecurity-crs"
else
    die "Could not find OWASP CRS package (owasp-modsecurity-crs or modsecurity-crs)."
fi

MODSEC_MODULE_PKG=""
if apt-cache show libnginx-mod-http-modsecurity >/dev/null 2>&1; then
    MODSEC_MODULE_PKG="libnginx-mod-http-modsecurity"
elif apt-cache show libnginx-mod-security2 >/dev/null 2>&1; then
    MODSEC_MODULE_PKG="libnginx-mod-security2"
else
    die "Could not find nginx ModSecurity module package."
fi

MODSEC_LIB_PKG=""
if apt-cache show libmodsecurity3t64 >/dev/null 2>&1; then
    MODSEC_LIB_PKG="libmodsecurity3t64"
elif apt-cache show libmodsecurity3 >/dev/null 2>&1; then
    MODSEC_LIB_PKG="libmodsecurity3"
else
    die "Could not find ModSecurity library package."
fi

apt-get install -y -q "${MODSEC_MODULE_PKG}" "${MODSEC_LIB_PKG}" "${CRS_PKG}"

MODSEC_MODULE="/usr/lib/nginx/modules/ngx_http_modsecurity_module.so"
if [[ ! -f "${MODSEC_MODULE}" ]]; then
    die "ModSecurity nginx module not found: ${MODSEC_MODULE}"
fi

install -d -m 755 /etc/nginx/modules-available /etc/nginx/modules-enabled /etc/nginx/modsec /etc/nginx/snippets

if ! grep -Rqs "ngx_http_modsecurity_module" /etc/nginx/modules-enabled; then
    MODULE_CONF="/etc/nginx/modules-available/modsecurity.conf"
    cat > "${MODULE_CONF}" <<EOF
load_module ${MODSEC_MODULE};
EOF
    ln -sfn "${MODULE_CONF}" /etc/nginx/modules-enabled/modsecurity.conf
else
    # Remove our legacy module link if it exists to avoid duplicate load.
    if [[ -L /etc/nginx/modules-enabled/modsecurity.conf ]]; then
        rm -f /etc/nginx/modules-enabled/modsecurity.conf
    fi
fi

MODSEC_CONF="/etc/modsecurity/modsecurity.conf"
if [[ ! -f "${MODSEC_CONF}" ]]; then
    if [[ -f /etc/modsecurity/modsecurity.conf-recommended ]]; then
        cp /etc/modsecurity/modsecurity.conf-recommended "${MODSEC_CONF}"
    else
        install -d -m 755 /etc/modsecurity /var/log/modsecurity
        cat > "${MODSEC_CONF}" <<'EOF'
SecRuleEngine On
SecRequestBodyAccess On
SecResponseBodyAccess Off
SecRequestBodyLimit 13107200
SecRequestBodyNoFilesLimit 131072
SecRequestBodyInMemoryLimit 131072
SecRequestBodyLimitAction Reject
SecPcreMatchLimit 1000
SecPcreMatchLimitRecursion 1000
SecTmpDir /tmp
SecDataDir /tmp
SecAuditEngine RelevantOnly
SecAuditLogParts ABIJDEFHZ
SecAuditLogType Serial
SecAuditLog /var/log/modsecurity/audit.log
SecDebugLogLevel 0
EOF
    fi
fi

sed -i -E "s/^SecRuleEngine\s+.*/SecRuleEngine ${SEC_RULE_ENGINE}/" "${MODSEC_CONF}"
# ModSecurity v3 no longer supports SecRequestBodyInMemoryLimit.
sed -i -E "/^SecRequestBodyInMemoryLimit\s+/d" "${MODSEC_CONF}"

CRS_SETUP=""
if [[ -f /etc/modsecurity/crs/crs-setup.conf ]]; then
    CRS_SETUP="/etc/modsecurity/crs/crs-setup.conf"
elif [[ -f /usr/share/owasp-modsecurity-crs/crs-setup.conf ]]; then
    CRS_SETUP="/usr/share/owasp-modsecurity-crs/crs-setup.conf"
elif [[ -f /usr/share/modsecurity-crs/crs-setup.conf ]]; then
    CRS_SETUP="/usr/share/modsecurity-crs/crs-setup.conf"
else
    die "OWASP CRS setup file not found."
fi

CRS_RULES_DIR=""
if [[ -d /usr/share/owasp-modsecurity-crs/rules ]]; then
    CRS_RULES_DIR="/usr/share/owasp-modsecurity-crs/rules"
elif [[ -d /usr/share/modsecurity-crs/rules ]]; then
    CRS_RULES_DIR="/usr/share/modsecurity-crs/rules"
else
    die "OWASP CRS rules directory not found."
fi

MAIN_CONF="/etc/nginx/modsec/main.conf"
cat > "${MAIN_CONF}" <<EOF
Include /etc/modsecurity/modsecurity.conf
Include ${CRS_SETUP}
Include ${CRS_RULES_DIR}/*.conf
EOF

SNIPPET="/etc/nginx/snippets/hextyl-modsecurity.conf"
cat > "${SNIPPET}" <<'EOF'
modsecurity on;
modsecurity_rules_file /etc/nginx/modsec/main.conf;
EOF

if ! grep -q "hextyl-modsecurity.conf" "${NGINX_SITE}"; then
    sed -i '/server_name .*;/a\    include /etc/nginx/snippets/hextyl-modsecurity.conf;' "${NGINX_SITE}"
fi

nginx -t
systemctl reload nginx

log "ModSecurity WAF enabled (mode: ${SEC_RULE_ENGINE})."

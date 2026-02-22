#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

PROFILE="normal"
APP_DIR=""
NGINX_SITE=""
WHITELIST="${DDOS_WHITELIST_IPS:-127.0.0.1,::1}"
FORCE_INSTALL="n"

log() { echo "[INFO] $*"; }
warn() { echo "[WARN] $*" >&2; }
die() { echo "[ERROR] $*" >&2; exit 1; }

usage() {
    cat <<EOF
Usage:
  sudo bash scripts/security_autosetup.sh [options]

Options:
  --profile <normal|elevated|under_attack>  DDoS profile to apply (default: normal)
  --app-dir <path>                           Panel app dir containing artisan
  --nginx-site <path>                        Nginx site conf path
  --whitelist <cidr_list>                    Whitelist IPs for under_attack profile
  --force-install <y|n>                      Force baseline reinstall (default: n)
  --help                                     Show this help
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --profile) PROFILE="${2:-}"; shift 2 ;;
        --app-dir) APP_DIR="${2:-}"; shift 2 ;;
        --nginx-site) NGINX_SITE="${2:-}"; shift 2 ;;
        --whitelist) WHITELIST="${2:-}"; shift 2 ;;
        --force-install) FORCE_INSTALL="${2:-}"; shift 2 ;;
        --help|-h) usage; exit 0 ;;
        *) die "Unknown option: $1" ;;
    esac
done

[[ "${EUID}" -eq 0 ]] || die "Run as root."
[[ "${PROFILE}" =~ ^(normal|elevated|under_attack)$ ]] || die "Invalid profile: ${PROFILE}"

detect_app_dir() {
    if [[ -n "${APP_DIR}" ]]; then
        return 0
    fi

    if [[ -f "${REPO_DIR}/artisan" ]]; then
        APP_DIR="${REPO_DIR}"
        return 0
    fi

    local candidate=""
    candidate="$(find /var/www /opt /srv -maxdepth 4 -type f -name artisan 2>/dev/null | head -n 1 || true)"
    [[ -n "${candidate}" ]] || die "Could not detect APP_DIR; pass --app-dir."
    APP_DIR="$(cd "$(dirname "${candidate}")" && pwd)"
}

detect_nginx_site() {
    if [[ -n "${NGINX_SITE}" ]]; then
        return 0
    fi

    local app_url=""
    local app_host=""
    if [[ -f "${APP_DIR}/.env" ]]; then
        app_url="$(grep -E '^APP_URL=' "${APP_DIR}/.env" | head -n1 | cut -d'=' -f2- | tr -d '"' || true)"
        app_host="$(printf '%s' "${app_url}" | sed -E 's#^[a-z]+://##; s#/.*$##; s#:[0-9]+$##')"
    fi

    if [[ -n "${app_host}" ]]; then
        NGINX_SITE="$(grep -Rsl "server_name .*${app_host}" /etc/nginx/sites-enabled /etc/nginx/sites-available 2>/dev/null | head -n1 || true)"
    fi

    if [[ -z "${NGINX_SITE}" && -f /etc/nginx/sites-available/hextyl.conf ]]; then
        NGINX_SITE="/etc/nginx/sites-available/hextyl.conf"
    fi
    if [[ -z "${NGINX_SITE}" ]]; then
        NGINX_SITE="$(find /etc/nginx/sites-available -maxdepth 1 -type f -name '*.conf' | head -n1 || true)"
    fi

    [[ -n "${NGINX_SITE}" ]] || die "Could not detect nginx site; pass --nginx-site."
}

ensure_baseline() {
    local installed="y"
    [[ -f /etc/nginx/snippets/hextyl-antiddos.conf ]] || installed="n"
    [[ -f /etc/nginx/snippets/hextyl-antiddos-profile-normal.conf ]] || installed="n"
    [[ -f /etc/fail2ban/jail.d/hextyl.local ]] || installed="n"
    [[ -f /etc/nftables.d/hextyl-ddos.nft ]] || installed="n"

    if [[ "${FORCE_INSTALL}" == "y" || "${installed}" == "n" ]]; then
        log "Installing anti-DDoS baseline..."
        bash "${SCRIPT_DIR}/install_antiddos_baseline.sh" "${NGINX_SITE}"
    else
        log "Baseline already present. Skipping baseline install."
    fi
}

apply_profile() {
    log "Applying profile: ${PROFILE}"
    DDOS_WHITELIST_IPS="${WHITELIST}" bash "${SCRIPT_DIR}/set_antiddos_profile.sh" "${PROFILE}" "${APP_DIR}"
}

print_status() {
    local profile_link="/etc/nginx/snippets/hextyl-antiddos-profile.conf"
    log "Done."
    echo "      APP_DIR:    ${APP_DIR}"
    echo "      NGINX_SITE: ${NGINX_SITE}"
    echo "      Profile:    $(readlink -f "${profile_link}" 2>/dev/null || echo 'missing')"
    echo "      Whitelist:  ${WHITELIST}"
}

detect_app_dir
[[ -f "${APP_DIR}/artisan" ]] || die "artisan not found in APP_DIR: ${APP_DIR}"
detect_nginx_site
[[ -f "${NGINX_SITE}" ]] || die "nginx site not found: ${NGINX_SITE}"

log "Auto security setup started."
log "APP_DIR=${APP_DIR}"
log "NGINX_SITE=${NGINX_SITE}"

ensure_baseline
apply_profile
print_status

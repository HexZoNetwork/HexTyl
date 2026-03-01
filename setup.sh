#!/usr/bin/env bash

if [ -z "${BASH_VERSION:-}" ]; then
    exec bash "$0" "$@"
fi

set -Eeuo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

APP_DIR=""
DB_NAME="hextyl"
DB_USER="hextyl"
DB_PASS=""
DOMAIN=""
USE_SSL="n"
LETSENCRYPT_EMAIL=""
BUILD_FRONTEND="y"
BUILD_FRONTEND_EXPLICIT="n"
INSTALL_WINGS="y"
INSTALL_WINGS_EXPLICIT="n"
INSTALL_ANTIDDOS="y"
INSTALL_ANTIDDOS_EXPLICIT="n"
INSTALL_WAF="y"
INSTALL_WAF_EXPLICIT="n"
INSTALL_FLOOD_GUARD="y"
INSTALL_FLOOD_GUARD_EXPLICIT="n"
INSTALL_PRESSURE_GUARD="y"
INSTALL_PRESSURE_GUARD_EXPLICIT="n"
INSTALL_IDE_WINGS="y"
INSTALL_IDE_WINGS_EXPLICIT="n"
INSTALL_IDE_GATEWAY="y"
INSTALL_IDE_GATEWAY_EXPLICIT="n"
AUTO_PTLR="y"
AUTO_PTLR_EXPLICIT="n"
NGINX_SITE_NAME=""
IDE_DOMAIN=""
BEHIND_PROXY="n"
BEHIND_PROXY_EXPLICIT="n"
PANEL_ORIGIN_DOMAIN=""
IDE_ROOT_API_TOKEN=""
IDE_NODE_MAP=""
IDE_AUTO_NODE_FQDN="y"
IDE_NODE_SCHEME="http"
IDE_NODE_SCHEME_EXPLICIT="n"
IDE_NODE_PORT="18080"
IDE_CODE_SERVER_URL="http://127.0.0.1:${IDE_NODE_PORT}"
IDE_CODE_SERVER_URL_EXPLICIT="n"
WINGS_PANEL_URL=""
WINGS_NODE_ID=""
WINGS_NODE_IDS=""
WINGS_API_TOKEN=""
WINGS_ALLOW_INSECURE="n"
WINGS_AUTO_TOKEN="y"
WINGS_AUTO_CREATE_NODE="y"
CLOUDFLARE_LOCK_ORIGIN="n"
CLOUDFLARE_LOCK_ORIGIN_EXPLICIT="n"
ORIGIN_EXTRA_ALLOW=""
WINGS_PANEL_ALLOW=""

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

log() { echo -e "${BLUE}[INFO]${NC} $*"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $*"; }
ok() { echo -e "${GREEN}[OK]${NC} $*"; }
fail() { echo -e "${RED}[ERROR]${NC} $*"; exit 1; }

extract_host_from_url_or_domain() {
    local input="${1:-}"
    local value host
    value="$(echo "${input}" | xargs)"
    [[ -n "${value}" ]] || { echo ""; return 0; }

    # Accept both raw host (ide.example.com) and full URL (https://ide.example.com/path).
    if [[ "${value}" == *"://"* ]]; then
        value="${value#*://}"
    fi
    value="${value%%/*}"
    value="${value%%\?*}"
    value="${value%%\#*}"
    value="${value##*@}"
    host="${value%%:*}"
    echo "${host,,}"
}

sanitize_localhost_host_entry() {
    local hostfile="$1"
    local domain="$2"
    [[ -f "${hostfile}" && -n "${domain}" ]] || return 0

    awk -v dom="${domain}" '
        {
            if ($1 == "127.0.0.1" || $1 == "::1") {
                n = split($0, fields, /[ \t]+/);
                if (n >= 2) {
                    changed = 0;
                    out = $1;
                    for (i = 2; i <= n; i++) {
                        if (fields[i] == dom) { changed = 1; continue; }
                        out = out " " fields[i];
                    }
                    if (changed) {
                        if (out == $1) { next; }
                        print out;
                        next;
                    }
                }
            }
            print;
        }
    ' "${hostfile}" > "${hostfile}.tmp" && mv "${hostfile}.tmp" "${hostfile}"
}

is_reserved_ide_port() {
    local port="${1:-}"
    [[ "${port}" == "8080" || "${port}" == "2022" ]]
}

port_in_use() {
    local port="${1:-}"
    ss -ltnH 2>/dev/null | awk '{print $4}' | grep -qE "[:.]${port}$"
}

pick_safe_ide_port() {
    local start="${1:-18080}"
    local end="${2:-18250}"
    local candidate

    for (( candidate=start; candidate<=end; candidate++ )); do
        if is_reserved_ide_port "${candidate}"; then
            continue
        fi
        if ! port_in_use "${candidate}"; then
            echo "${candidate}"
            return 0
        fi
    done

    return 1
}

generate_root_api_token() {
    (
        cd "${APP_DIR}"
        php <<'PHP'
<?php
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Models\User;

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$user = null;
if (Schema::hasColumn('users', 'root_admin')) {
    $user = User::query()->where('root_admin', 1)->orderBy('id')->first();
}
if (!$user) {
    $user = User::query()->orderBy('id')->first();
}
if (!$user) {
    fwrite(STDERR, "NO_USER\n");
    exit(11);
}

$token = Str::random(ApiKey::KEY_LENGTH);
$keyType = ApiKey::TYPE_ROOT;
$key = ApiKey::query()->create([
    'user_id' => $user->id,
    'key_type' => $keyType,
    'identifier' => ApiKey::generateTokenIdentifier($keyType),
    'token' => encrypt($token),
    'memo' => 'setup:auto:ide-gateway',
    'allowed_ips' => [],
    'r_servers' => 3,
    'r_nodes' => 3,
    'r_allocations' => 3,
    'r_users' => 3,
    'r_locations' => 3,
    'r_nests' => 3,
    'r_eggs' => 3,
    'r_database_hosts' => 3,
    'r_server_databases' => 3,
]);

echo $key->identifier . $token;
PHP
    )
}

generate_application_api_token() {
    (
        cd "${APP_DIR}"
        php <<'PHP'
<?php
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Models\User;

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$user = null;
if (Schema::hasColumn('users', 'root_admin')) {
    $user = User::query()->where('root_admin', 1)->orderBy('id')->first();
}
if (!$user) {
    $user = User::query()->orderBy('id')->first();
}
if (!$user) {
    fwrite(STDERR, "NO_USER\n");
    exit(11);
}

$token = Str::random(ApiKey::KEY_LENGTH);
$keyType = ApiKey::TYPE_APPLICATION;
$key = ApiKey::query()->create([
    'user_id' => $user->id,
    'key_type' => $keyType,
    'identifier' => ApiKey::generateTokenIdentifier($keyType),
    'token' => encrypt($token),
    'memo' => 'setup:auto:wings-bootstrap',
    'allowed_ips' => [],
    'r_servers' => 3,
    'r_nodes' => 3,
    'r_allocations' => 3,
    'r_users' => 0,
    'r_locations' => 0,
    'r_nests' => 0,
    'r_eggs' => 0,
    'r_database_hosts' => 0,
    'r_server_databases' => 0,
]);

echo $key->identifier . $token;
PHP
    )
}

split_csv() {
    local raw="${1:-}"
    local item
    IFS=',' read -ra _parts <<< "${raw}"
    for item in "${_parts[@]}"; do
        item="$(echo "${item}" | xargs)"
        [[ -n "${item}" ]] || continue
        echo "${item}"
    done
}

version_gte() {
    local current="$1"
    local required="$2"
    [[ "$(printf '%s\n' "${required}" "${current}" | sort -V | head -n1)" == "${required}" ]]
}

install_go_toolchain() {
    local required_version="${1:-1.24.1}"
    local current_version=""
    if command -v go >/dev/null 2>&1; then
        current_version="$(go version 2>/dev/null | awk '{print $3}' | sed 's/^go//')"
    fi

    if [[ -n "${current_version}" ]] && version_gte "${current_version}" "${required_version}"; then
        log "Go ${current_version} is already installed (required >= ${required_version})."
        return 0
    fi

    local go_arch=""
    case "$(uname -m)" in
        x86_64|amd64) go_arch="amd64" ;;
        aarch64|arm64) go_arch="arm64" ;;
        *) fail "Unsupported architecture for Go toolchain install: $(uname -m)" ;;
    esac

    local go_tar="go${required_version}.linux-${go_arch}.tar.gz"
    local go_url="https://go.dev/dl/${go_tar}"

    log "Installing Go ${required_version} from ${go_url}..."
    curl -fL -o "/tmp/${go_tar}" "${go_url}" || fail "Failed to download Go tarball: ${go_url}"
    rm -rf /usr/local/go
    tar -C /usr/local -xzf "/tmp/${go_tar}" || fail "Failed to extract Go toolchain."
    ln -sf /usr/local/go/bin/go /usr/local/bin/go
    rm -f "/tmp/${go_tar}"
    hash -r

    current_version="$(go version 2>/dev/null | awk '{print $3}' | sed 's/^go//')"
    if [[ -z "${current_version}" ]] || ! version_gte "${current_version}" "${required_version}"; then
        fail "Go installation verification failed. Found: ${current_version:-none}, required: ${required_version}+."
    fi
    ok "Installed Go ${current_version}."
}

usage() {
    cat <<'EOF'
HexTyl setup.sh

Usage:
  sudo bash setup.sh [options]

Options:
  --app-dir <path>       Panel install path (default: current setup.sh folder)
  --domain <fqdn>        Domain name, e.g. panel.example.com
  --db-name <name>       MySQL/MariaDB database name (default: hextyl)
  --db-user <user>       MySQL/MariaDB username (default: hextyl)
  --db-pass <pass>       MySQL/MariaDB password
  --ssl <y|n>            Enable HTTPS with certbot (default: n)
  --email <email>        Email for certbot registration
  --build-frontend <y|n> Build frontend assets (default: y)
  --install-wings <y|n>  Install Docker + Wings (default: y)
  --install-antiddos <y|n> Install anti-DDoS baseline (nginx + fail2ban) (default: y)
  --cloudflare-lock-origin <y|n> Lock origin so only Cloudflare can access 80/443 (default: n)
  --origin-extra-allow <cidr_list> Extra emergency allowlist for direct origin access
  --wings-panel-allow <cidr_list> Allowlist IP/CIDR for Wings ports 8080/2022
  --install-waf <y|n>    Install ModSecurity WAF (nginx module + OWASP CRS) (default: y)
  --install-flood-guard <y|n> Install flood detector + auto-ban (default: y)
  --install-pressure-guard <y|n> Install CPU/RAM pressure guard (auto-freeze) (default: y)
  --install-ide-wings <y|n> Enable Wings-native IDE flow + local code-server service (default: y)
  --install-ide-gateway <y|n> Install IDE gateway service + nginx site (default: y)
  --nginx-site-name <n>  Nginx site filename without .conf (default: app folder name, lowercase)
  --ide-domain <fqdn>    IDE gateway domain/URL (optional), e.g. ide.example.com
  --auto-ptlr <y|n>     Auto-generate PTLR root token for IDE gateway (default: y)
  --behind-proxy <y|n>   Panel is behind reverse proxy/CDN (default: n)
  --panel-origin <fqdn>  Origin domain/URL (DNS-only) for Wings/internal traffic
  --ide-root-api-token <tok> Root API token used by IDE gateway validation
  --ide-code-server-url <url> code-server upstream URL (default: http://127.0.0.1:18080)
  --ide-node-map <pairs> Optional per-node map: "node-fqdn=url,node-id=url"
  --ide-auto-node-fqdn <y|n> Auto route by node_fqdn (default: y)
  --ide-node-scheme <http|https> Auto routing scheme (default: http)
  --ide-node-port <port>  Auto routing port (default: 18080, reserved: 8080/2022)
  --wings-panel-url <url> Panel URL for non-interactive wings configure (optional)
  --wings-node-id <id>    Node ID for non-interactive wings configure (single node)
  --wings-node-ids <csv>  Multi-node IDs for bootstrap helper, e.g. "1,2,3"
  --wings-api-token <tok> Application API token for wings configure (optional)
  --wings-auto-token <y|n> Auto-generate Application API token for Wings bootstrap (default: y)
  --wings-auto-create-node <y|n> Auto-create local node if no node exists (default: y)
  --wings-allow-insecure <y|n> Pass --allow-insecure to wings configure (default: n)
  --strict-options <y|n>  Fail on unknown options (default: n, unknown options are skipped)
  --help                 Show this help
EOF
}

STRICT_OPTIONS="n"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --app-dir) APP_DIR="${2:-}"; shift 2 ;;
        --domain) DOMAIN="${2:-}"; shift 2 ;;
        --db-name) DB_NAME="${2:-}"; shift 2 ;;
        --db-user) DB_USER="${2:-}"; shift 2 ;;
        --db-pass) DB_PASS="${2:-}"; shift 2 ;;
        --ssl) USE_SSL="${2:-}"; shift 2 ;;
        --email) LETSENCRYPT_EMAIL="${2:-}"; shift 2 ;;
        --build-frontend) BUILD_FRONTEND="${2:-}"; BUILD_FRONTEND_EXPLICIT="y"; shift 2 ;;
        --install-wings) INSTALL_WINGS="${2:-}"; INSTALL_WINGS_EXPLICIT="y"; shift 2 ;;
        --install-antiddos) INSTALL_ANTIDDOS="${2:-}"; INSTALL_ANTIDDOS_EXPLICIT="y"; shift 2 ;;
        --cloudflare-lock-origin) CLOUDFLARE_LOCK_ORIGIN="${2:-}"; CLOUDFLARE_LOCK_ORIGIN_EXPLICIT="y"; shift 2 ;;
        --origin-extra-allow) ORIGIN_EXTRA_ALLOW="${2:-}"; shift 2 ;;
        --wings-panel-allow) WINGS_PANEL_ALLOW="${2:-}"; shift 2 ;;
        --install-waf) INSTALL_WAF="${2:-}"; INSTALL_WAF_EXPLICIT="y"; shift 2 ;;
        --install-flood-guard) INSTALL_FLOOD_GUARD="${2:-}"; INSTALL_FLOOD_GUARD_EXPLICIT="y"; shift 2 ;;
        --install-pressure-guard) INSTALL_PRESSURE_GUARD="${2:-}"; INSTALL_PRESSURE_GUARD_EXPLICIT="y"; shift 2 ;;
        --install-ide-wings) INSTALL_IDE_WINGS="${2:-}"; INSTALL_IDE_WINGS_EXPLICIT="y"; shift 2 ;;
        --install-ide-gateway) INSTALL_IDE_GATEWAY="${2:-}"; INSTALL_IDE_GATEWAY_EXPLICIT="y"; shift 2 ;;
        --auto-ptlr) AUTO_PTLR="${2:-}"; AUTO_PTLR_EXPLICIT="y"; shift 2 ;;
        --nginx-site-name) NGINX_SITE_NAME="${2:-}"; shift 2 ;;
        --ide-domain) IDE_DOMAIN="${2:-}"; shift 2 ;;
        --behind-proxy) BEHIND_PROXY="${2:-}"; BEHIND_PROXY_EXPLICIT="y"; shift 2 ;;
        --panel-origin) PANEL_ORIGIN_DOMAIN="${2:-}"; shift 2 ;;
        --ide-root-api-token) IDE_ROOT_API_TOKEN="${2:-}"; shift 2 ;;
        --ide-code-server-url) IDE_CODE_SERVER_URL="${2:-}"; IDE_CODE_SERVER_URL_EXPLICIT="y"; shift 2 ;;
        --ide-node-map) IDE_NODE_MAP="${2:-}"; shift 2 ;;
        --ide-auto-node-fqdn) IDE_AUTO_NODE_FQDN="${2:-}"; shift 2 ;;
        --ide-node-scheme) IDE_NODE_SCHEME="${2:-}"; IDE_NODE_SCHEME_EXPLICIT="y"; shift 2 ;;
        --ide-node-port) IDE_NODE_PORT="${2:-}"; shift 2 ;;
        --wings-panel-url) WINGS_PANEL_URL="${2:-}"; shift 2 ;;
        --wings-node-id) WINGS_NODE_ID="${2:-}"; shift 2 ;;
        --wings-node-ids) WINGS_NODE_IDS="${2:-}"; shift 2 ;;
        --wings-api-token) WINGS_API_TOKEN="${2:-}"; shift 2 ;;
        --wings-auto-token) WINGS_AUTO_TOKEN="${2:-}"; shift 2 ;;
        --wings-auto-create-node) WINGS_AUTO_CREATE_NODE="${2:-}"; shift 2 ;;
        --wings-allow-insecure) WINGS_ALLOW_INSECURE="${2:-}"; shift 2 ;;
        --strict-options) STRICT_OPTIONS="${2:-}"; shift 2 ;;
        --help|-h) usage; exit 0 ;;
        --*)
            if [[ "${STRICT_OPTIONS}" == "y" ]]; then
                fail "Unknown option: $1 (use --help)"
            fi
            warn "Unknown option ignored: $1"
            # If the next token looks like a value (not another option), skip it too.
            if [[ $# -ge 2 && "${2:-}" != --* ]]; then
                shift 2
            else
                shift 1
            fi
            ;;
        *)
            fail "Unknown argument: $1 (use --help)"
            ;;
    esac
done

if [[ -z "${APP_DIR}" ]]; then
    APP_DIR="${SCRIPT_DIR}"
fi
if [[ -z "${NGINX_SITE_NAME}" ]]; then
    NGINX_SITE_NAME="$(basename "${APP_DIR}" | tr '[:upper:]' '[:lower:]')"
fi

[[ "${EUID}" -eq 0 ]] || fail "This script must run as root."

if [[ -z "${DOMAIN}" ]]; then
    read -r -p "Domain name (e.g. panel.example.com): " DOMAIN
fi
[[ -n "${DOMAIN}" ]] || fail "Domain is required."

read -r -p "Database name [${DB_NAME}]: " _dbn || true
DB_NAME="${_dbn:-$DB_NAME}"
read -r -p "Database user [${DB_USER}]: " _dbu || true
DB_USER="${_dbu:-$DB_USER}"

if [[ -z "${DB_PASS}" ]]; then
    read -r -s -p "Database password: " DB_PASS
    echo
fi
[[ -n "${DB_PASS}" ]] || fail "Database password is required."

if [[ "${USE_SSL}" != "y" && "${USE_SSL}" != "n" ]]; then
    read -r -p "Enable SSL with Let's Encrypt? [y/N]: " _ssl || true
    USE_SSL="${_ssl:-n}"
fi

if [[ "${USE_SSL}" == "y" && -z "${LETSENCRYPT_EMAIL}" ]]; then
    read -r -p "Let's Encrypt email (optional, press Enter to skip): " LETSENCRYPT_EMAIL || true
fi

if [[ "${USE_SSL}" == "y" && "${IDE_NODE_SCHEME_EXPLICIT}" != "y" ]]; then
    IDE_NODE_SCHEME="https"
fi

if ! [[ "${IDE_NODE_PORT}" =~ ^[0-9]+$ ]] || (( IDE_NODE_PORT < 1 || IDE_NODE_PORT > 65535 )); then
    fail "--ide-node-port must be an integer between 1 and 65535."
fi
if is_reserved_ide_port "${IDE_NODE_PORT}"; then
    warn "Port ${IDE_NODE_PORT} is reserved by HexWings protocol flow (8080/2022). Selecting a safe IDE port..."
    IDE_NODE_PORT="$(pick_safe_ide_port 18080 18250)" || fail "Unable to auto-pick a safe IDE port."
    ok "Using IDE node port ${IDE_NODE_PORT}."
fi
if [[ "${INSTALL_IDE_WINGS}" == "y" ]] && port_in_use "${IDE_NODE_PORT}"; then
    warn "IDE node port ${IDE_NODE_PORT} is already in use. Selecting another safe port..."
    IDE_NODE_PORT="$(pick_safe_ide_port 18080 18250)" || fail "Unable to auto-pick a free IDE port."
    ok "Using IDE node port ${IDE_NODE_PORT}."
fi
if [[ "${IDE_CODE_SERVER_URL_EXPLICIT}" != "y" ]]; then
    IDE_CODE_SERVER_URL="http://127.0.0.1:${IDE_NODE_PORT}"
fi
if [[ "${IDE_CODE_SERVER_URL}" =~ :8080([/?#]|$) || "${IDE_CODE_SERVER_URL}" =~ :2022([/?#]|$) ]]; then
    fail "--ide-code-server-url cannot use reserved ports 8080 or 2022."
fi

if [[ "${INSTALL_WINGS_EXPLICIT}" != "y" && -t 0 ]]; then
    read -r -p "Install Docker + Wings on this machine? [Y/n]: " _wings || true
    INSTALL_WINGS="${_wings:-$INSTALL_WINGS}"
fi

if [[ "${INSTALL_ANTIDDOS_EXPLICIT}" != "y" && -t 0 ]]; then
    read -r -p "Install anti-DDoS baseline (nginx+fail2ban)? [Y/n]: " _antiddos || true
    INSTALL_ANTIDDOS="${_antiddos:-$INSTALL_ANTIDDOS}"
fi

if [[ "${INSTALL_ANTIDDOS}" == "y" && "${CLOUDFLARE_LOCK_ORIGIN_EXPLICIT}" != "y" && "${BEHIND_PROXY}" == "y" ]]; then
    CLOUDFLARE_LOCK_ORIGIN="y"
fi

if [[ "${INSTALL_WAF_EXPLICIT}" != "y" && -t 0 ]]; then
    read -r -p "Install ModSecurity WAF (nginx module + OWASP CRS)? [Y/n]: " _waf || true
    INSTALL_WAF="${_waf:-$INSTALL_WAF}"
fi

if [[ "${INSTALL_FLOOD_GUARD_EXPLICIT}" != "y" && -t 0 ]]; then
    read -r -p "Install flood detector + auto-ban (L7+L4)? [Y/n]: " _fg || true
    INSTALL_FLOOD_GUARD="${_fg:-$INSTALL_FLOOD_GUARD}"
fi

if [[ "${INSTALL_PRESSURE_GUARD_EXPLICIT}" != "y" && -t 0 ]]; then
    read -r -p "Install CPU/RAM pressure guard (auto-freeze)? [Y/n]: " _pg || true
    INSTALL_PRESSURE_GUARD="${_pg:-$INSTALL_PRESSURE_GUARD}"
fi

if [[ "${INSTALL_IDE_WINGS_EXPLICIT}" != "y" && -t 0 ]]; then
    read -r -p "Enable Wings-native IDE flow + code-server on this machine? [Y/n]: " _idew || true
    INSTALL_IDE_WINGS="${_idew:-$INSTALL_IDE_WINGS}"
fi

if [[ "${INSTALL_IDE_GATEWAY_EXPLICIT}" != "y" && -t 0 ]]; then
    read -r -p "Install IDE gateway service on this machine? [Y/n]: " _idegw || true
    INSTALL_IDE_GATEWAY="${_idegw:-$INSTALL_IDE_GATEWAY}"
fi

if [[ "${AUTO_PTLR_EXPLICIT}" != "y" && -t 0 ]]; then
    read -r -p "Auto-generate PTLR token for IDE gateway? [Y/n]: " _ptlr || true
    AUTO_PTLR="${_ptlr:-$AUTO_PTLR}"
fi

if [[ -z "${IDE_DOMAIN}" ]]; then
    if [[ "${INSTALL_IDE_GATEWAY}" == "y" ]]; then
        IDE_DOMAIN="ide.${DOMAIN}"
        warn "IDE gateway domain not provided. Using default: ${IDE_DOMAIN}"
        warn "Point DNS A/AAAA for ${IDE_DOMAIN} to this machine before enabling SSL."
    elif [[ "${INSTALL_IDE_WINGS}" == "y" && "${INSTALL_WINGS}" == "y" ]]; then
        IDE_DOMAIN=""
    else
        read -r -p "IDE gateway domain or URL (leave empty to disable IDE): " _ide || true
        IDE_DOMAIN="${_ide:-}"
    fi
fi

IDE_DOMAIN="$(echo "${IDE_DOMAIN}" | xargs)"
IDE_GATEWAY_DOMAIN=""
if [[ -n "${IDE_DOMAIN}" ]]; then
    IDE_GATEWAY_DOMAIN="$(extract_host_from_url_or_domain "${IDE_DOMAIN}")"
    [[ -n "${IDE_GATEWAY_DOMAIN}" ]] || fail "Invalid IDE domain/URL: ${IDE_DOMAIN}"
fi

if [[ "${INSTALL_IDE_GATEWAY}" == "y" ]]; then
    [[ -n "${IDE_DOMAIN}" ]] || fail "IDE domain is required when --install-ide-gateway y."
    [[ -n "${IDE_GATEWAY_DOMAIN}" ]] || fail "IDE domain/URL is invalid for gateway install."
fi
if [[ "${WINGS_ALLOW_INSECURE}" != "y" && "${WINGS_ALLOW_INSECURE}" != "n" ]]; then
    fail "--wings-allow-insecure must be y or n."
fi
if [[ "${WINGS_AUTO_CREATE_NODE}" != "y" && "${WINGS_AUTO_CREATE_NODE}" != "n" ]]; then
    fail "--wings-auto-create-node must be y or n."
fi
if [[ "${CLOUDFLARE_LOCK_ORIGIN}" != "y" && "${CLOUDFLARE_LOCK_ORIGIN}" != "n" ]]; then
    fail "--cloudflare-lock-origin must be y or n."
fi
if [[ "${WINGS_AUTO_TOKEN}" != "y" && "${WINGS_AUTO_TOKEN}" != "n" ]]; then
    fail "--wings-auto-token must be y or n."
fi
if [[ "${BEHIND_PROXY_EXPLICIT}" != "y" ]]; then
    read -r -p "Panel is behind reverse proxy/CDN? [y/N]: " _bp || true
    BEHIND_PROXY="${_bp:-n}"
fi
if [[ "${BEHIND_PROXY}" != "y" && "${BEHIND_PROXY}" != "n" ]]; then
    fail "--behind-proxy must be y or n."
fi
if [[ "${BEHIND_PROXY}" == "y" && -z "${PANEL_ORIGIN_DOMAIN}" ]]; then
    read -r -p "Origin panel domain or URL (DNS-only, for Wings/internal traffic): " _origin || true
    PANEL_ORIGIN_DOMAIN="${_origin:-}"
fi
PANEL_ORIGIN_DOMAIN="$(echo "${PANEL_ORIGIN_DOMAIN}" | xargs)"
PANEL_ORIGIN_HOST=""
if [[ -n "${PANEL_ORIGIN_DOMAIN}" ]]; then
    PANEL_ORIGIN_HOST="$(extract_host_from_url_or_domain "${PANEL_ORIGIN_DOMAIN}")"
    [[ -n "${PANEL_ORIGIN_HOST}" ]] || fail "Invalid panel origin domain/URL: ${PANEL_ORIGIN_DOMAIN}"
fi

for hosts_file in /etc/hosts /etc/cloud/templates/hosts.debian.tmpl; do
    sanitize_localhost_host_entry "${hosts_file}" "${DOMAIN}"
    sanitize_localhost_host_entry "${hosts_file}" "${IDE_GATEWAY_DOMAIN}"
    sanitize_localhost_host_entry "${hosts_file}" "${PANEL_ORIGIN_HOST}"
done

log "Starting HexTyl setup for domain: ${DOMAIN}"

export DEBIAN_FRONTEND=noninteractive
export NEEDRESTART_MODE=a

OS_ID=""
OS_LIKE=""
if [[ -f /etc/os-release ]]; then
    # shellcheck disable=SC1091
    source /etc/os-release
    OS_ID="${ID:-}"
    OS_LIKE="${ID_LIKE:-}"
fi

php_pkg_available() {
    local series="$1"
    apt-cache show "php${series}-fpm" >/dev/null 2>&1
}

PHP_SERIES="${HEXTYL_PHP_SERIES:-8.3}"

log "Installing base dependencies..."
apt-get update -y -q
apt-get install -y -q software-properties-common python3-launchpadlib curl apt-transport-https ca-certificates gnupg lsb-release rsync

if ! php_pkg_available "${PHP_SERIES}"; then
    if [[ "${OS_ID}" == "ubuntu" || "${OS_LIKE}" == *"ubuntu"* ]]; then
        if ! grep -Rqs "ondrej/php" /etc/apt/sources.list /etc/apt/sources.list.d 2>/dev/null; then
            log "Adding PPA ondrej/php..."
            if ! add-apt-repository -y ppa:ondrej/php; then
                warn "Failed to add ondrej/php PPA. Continuing with distro repositories."
            fi
        fi
        apt-get update -y -q || true
    else
        warn "Skipping ondrej/php PPA on non-Ubuntu distro (${OS_ID:-unknown})."
    fi
fi

if ! php_pkg_available "${PHP_SERIES}"; then
    for _candidate in 8.3 8.2 8.1 8.0; do
        if php_pkg_available "${_candidate}"; then
            PHP_SERIES="${_candidate}"
            warn "Requested PHP series unavailable; using php${PHP_SERIES} from current repositories."
            break
        fi
    done
fi

if ! php_pkg_available "${PHP_SERIES}"; then
    fail "No supported PHP-FPM package found (tried 8.3/8.2/8.1/8.0). Check apt repositories/network."
fi

apt-get install -y -q \
    "php${PHP_SERIES}" "php${PHP_SERIES}"-{common,cli,gd,mysql,mbstring,bcmath,xml,fpm,curl,zip,intl,redis} \
    mariadb-server nginx redis-server tar unzip git composer fail2ban nftables

PHP_FPM_SERVICE="php${PHP_SERIES}-fpm"
systemctl enable --now mariadb redis-server "${PHP_FPM_SERVICE}" nginx

log "Preparing application directory: ${APP_DIR}"
mkdir -p "${APP_DIR}"
if [[ "${SCRIPT_DIR}" != "${APP_DIR}" ]]; then
    rsync -a \
      --exclude ".git" \
      --exclude "node_modules" \
      --exclude "vendor" \
      --exclude "public/assets" \
      "${SCRIPT_DIR}/" "${APP_DIR}/"
fi
cd "${APP_DIR}"
[[ -f "artisan" ]] || fail "Laravel project not found in APP_DIR (${APP_DIR}). File artisan is missing."

log "Configuring database..."
mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`;
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

mysql -u "${DB_USER}" -p"${DB_PASS}" -h 127.0.0.1 -e "USE \`${DB_NAME}\`;" >/dev/null \
    || fail "Cannot connect to database with provided credentials."

if [[ ! -f ".env.example" ]]; then
    fail ".env.example not found in ${APP_DIR}"
fi

if [[ ! -f ".env" || ! -s ".env" ]]; then
    log "Initializing .env from .env.example..."
    cp .env.example .env
fi

log "Preparing Laravel writable/cache directories..."
mkdir -p \
    bootstrap/cache \
    storage/logs \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views
chmod -R 775 bootstrap/cache storage

set_env() {
    local key="$1"
    local value="$2"
    local escaped
    escaped="$(printf '%s' "${value}" | sed -e 's/[\/&|]/\\&/g')"
    if grep -qE "^[[:space:]]*(export[[:space:]]+)?${key}[[:space:]]*=" .env; then
        sed -i -E "s|^[[:space:]]*(export[[:space:]]+)?${key}[[:space:]]*=.*|${key}=${escaped}|g" .env
    else
        printf '%s=%s\n' "${key}" "${escaped}" >> .env
    fi
}

set_env_quoted() {
    local key="$1"
    local value="$2"
    local quoted
    quoted="\"$(printf '%s' "${value}" | sed -e 's/[\\"]/\\&/g')\""
    set_env "${key}" "${quoted}"
}

ensure_env_quoted_if_contains_spaces() {
    local key="$1"
    local current
    current="$(grep -E "^${key}=" .env | tail -n1 | cut -d= -f2- || true)"
    [[ -n "${current}" ]] || return 0

    if [[ "${current}" =~ ^\".*\"$ || "${current}" =~ ^\'.*\'$ ]]; then
        return 0
    fi

    if [[ "${current}" =~ [[:space:]] ]]; then
        set_env_quoted "${key}" "${current}"
    fi
}

quote_unquoted_env_values_with_spaces() {
    [[ -f ".env" ]] || return 0

    local line
    while IFS= read -r line; do
        local key raw_value
        key="$(printf '%s' "${line}" | sed -E 's/^[[:space:]]*(export[[:space:]]+)?([A-Za-z_][A-Za-z0-9_]*)[[:space:]]*=.*$/\2/')"
        raw_value="$(printf '%s' "${line}" | sed -E 's/^[[:space:]]*(export[[:space:]]+)?[A-Za-z_][A-Za-z0-9_]*[[:space:]]*=[[:space:]]*//')"

        [[ -n "${key}" ]] || continue
        [[ -n "${raw_value}" ]] || continue

        if [[ "${raw_value}" =~ ^\".*\"$ || "${raw_value}" =~ ^\'.*\'$ ]]; then
            continue
        fi

        if [[ "${raw_value}" =~ [[:space:]] ]]; then
            # Drop trailing inline comment marker from unquoted values, then quote safely.
            raw_value="${raw_value%% #*}"
            raw_value="$(printf '%s' "${raw_value}" | sed -E 's/[[:space:]]+$//')"
            set_env_quoted "${key}" "${raw_value}"
        fi
    done < <(grep -E '^[[:space:]]*(export[[:space:]]+)?[A-Za-z_][A-Za-z0-9_]*[[:space:]]*=.*[[:space:]].*$' .env || true)
}

sanitize_env_file() {
    [[ -f ".env" ]] || return 0

    # Normalize .env to avoid Dotenv parse errors from pasted section headers or CRLF/BOM.
    sed -i '1s/^\xEF\xBB\xBF//' .env
    sed -i 's/\r$//' .env
    sed -i -E '/^[[:space:]]*\[[^]]+\][[:space:]]*$/ s/^/# /' .env
}

ensure_app_key() {
    if grep -qE '^APP_KEY=base64:' .env; then
        return 0
    fi

    log "Generating APP_KEY directly in .env (pre-composer)..."
    local generated
    generated="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
    [[ -n "${generated}" ]] || fail "Failed to generate APP_KEY."
    set_env APP_KEY "${generated}"
}

join_csv_unique() {
    printf '%s\n' "$@" | tr ', ' '\n' | awk 'NF { if (!seen[$0]++) print $0 }' | paste -sd, -
}

collect_auto_whitelist_ips() {
    local entries=()
    entries+=("127.0.0.1" "::1")

    if command -v hostname >/dev/null 2>&1; then
        local host_ips
        host_ips="$(hostname -I 2>/dev/null || true)"
        if [[ -n "${host_ips}" ]]; then
            local ip
            for ip in ${host_ips}; do
                if [[ "${ip}" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
                    entries+=("${ip}")
                fi
            done
        fi
    fi

    if command -v ip >/dev/null 2>&1; then
        local cidr
        while read -r cidr; do
            [[ -n "${cidr}" ]] || continue
            entries+=("${cidr}")
        done < <(ip -4 route show scope link 2>/dev/null | awk '{print $1}')

        while read -r cidr; do
            [[ -n "${cidr}" ]] || continue
            entries+=("${cidr}")
        done < <(ip -4 addr show 2>/dev/null | awk '
            /inet / && /(docker0|br-[0-9a-f]+|cni0|podman|virbr|lxcbr|flannel)/ {print $2}
        ')
    fi

    join_csv_unique "${entries[@]}"
}

log "Updating .env..."
sanitize_env_file
AUTO_WHITELIST_IPS="$(collect_auto_whitelist_ips)"
normalize_wings_whitelist() {
    local raw="${1:-}"
    local out=()
    local item
    IFS=',' read -ra _parts <<< "${raw}"
    for item in "${_parts[@]}"; do
        item="$(echo "${item}" | xargs)"
        [[ -z "${item}" ]] && continue
        if [[ "${item}" == */* ]]; then
            out+=("${item}")
        elif [[ "${item}" == *:* ]]; then
            out+=("${item}/128")
        else
            out+=("${item}/32")
        fi
    done
    join_csv_unique "${out[@]}"
}
AUTO_WHITELIST_IPS_CIDR="$(normalize_wings_whitelist "${AUTO_WHITELIST_IPS}")"
set_env APP_ENV production
set_env APP_DEBUG false
if [[ "${USE_SSL}" == "y" ]]; then
    set_env APP_URL "https://${DOMAIN}"
else
    set_env APP_URL "http://${DOMAIN}"
fi
set_env DB_CONNECTION mysql
set_env DB_HOST 127.0.0.1
set_env DB_PORT 3306
set_env_quoted DB_DATABASE "${DB_NAME}"
set_env_quoted DB_USERNAME "${DB_USER}"
set_env_quoted DB_PASSWORD "${DB_PASS}"
set_env CACHE_DRIVER redis
set_env QUEUE_CONNECTION redis
set_env SESSION_DRIVER redis
set_env REDIS_HOST 127.0.0.1
set_env REDIS_PASSWORD null
set_env REDIS_PORT 6379
if [[ "${BEHIND_PROXY}" == "y" ]]; then
    set_env TRUSTED_PROXIES "*"
fi
set_env DDOS_LOCKDOWN_MODE false
set_env DDOS_SKIP_AUTHENTICATED_LIMITS true
set_env DDOS_WHITELIST_IPS "${AUTO_WHITELIST_IPS}"
set_env DDOS_CONTAINER_LATERAL_GUARD_WHITELIST_IPS "${AUTO_WHITELIST_IPS}"
set_env DDOS_RATE_WEB_PER_MINUTE 180
set_env DDOS_RATE_API_PER_MINUTE 120
set_env DDOS_RATE_LOGIN_PER_MINUTE 20
set_env DDOS_RATE_WRITE_PER_MINUTE 40
set_env DDOS_BURST_THRESHOLD_10S 150
set_env DDOS_TEMP_BLOCK_MINUTES 10
set_env REMOTE_ACTIVITY_SIGNATURE_REQUIRED true
set_env REMOTE_ACTIVITY_SIGNATURE_MAX_SKEW_SECONDS 180
set_env REMOTE_ACTIVITY_SIGNATURE_REPLAY_WINDOW_SECONDS 300
set_env WINGS_DDOS_ENABLED true
set_env WINGS_DDOS_PER_IP_PER_MINUTE 180
set_env WINGS_DDOS_PER_IP_BURST 40
set_env WINGS_DDOS_GLOBAL_PER_MINUTE 1800
set_env WINGS_DDOS_GLOBAL_BURST 180
set_env WINGS_DDOS_STRIKE_THRESHOLD 8
set_env WINGS_DDOS_BLOCK_SECONDS 900
BASE_WINGS_DDOS_WHITELIST="127.0.0.1/32,::1/128,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,100.64.0.0/10"
WINGS_DDOS_WHITELIST="$(join_csv_unique "${BASE_WINGS_DDOS_WHITELIST}" "${AUTO_WHITELIST_IPS_CIDR}")"
set_env WINGS_DDOS_WHITELIST "${WINGS_DDOS_WHITELIST}"
set_env WINGS_BOOTSTRAP_INSTALL_MODE repo_source
set_env WINGS_BOOTSTRAP_REPO_URL "https://github.com/hexzonetwork/hextyl.git"
set_env WINGS_BOOTSTRAP_REPO_REF main
set_env WINGS_BOOTSTRAP_BINARY_URL_TEMPLATE "https://github.com/hexzonetwork/HexWings/releases/latest/download/hexwings_linux_{arch}"
set_env WINGS_BOOTSTRAP_BINARY_VERSION latest
set_env WINGS_BOOTSTRAP_BINARY_SHA256_AMD64 ""
set_env WINGS_BOOTSTRAP_BINARY_SHA256_ARM64 ""
set_env WINGS_BOOTSTRAP_ALLOW_PRIVATE_TARGETS true
set_env RESOURCE_SAFETY_ENABLED true
set_env RESOURCE_SAFETY_VIOLATION_WINDOW_SECONDS 300
set_env RESOURCE_SAFETY_VIOLATION_THRESHOLD 3
set_env RESOURCE_SAFETY_CPU_PERCENT_THRESHOLD 95
set_env RESOURCE_SAFETY_CPU_SUPER_CORES_THRESHOLD_PERCENT 500
set_env RESOURCE_SAFETY_CPU_SUPER_ALL_CORES_THRESHOLD_PERCENT 900
set_env RESOURCE_SAFETY_CPU_SUPER_CONSECUTIVE_CYCLES_THRESHOLD 5
set_env RESOURCE_SAFETY_WINGS_ACTION_COOLDOWN_SECONDS 300
set_env RESOURCE_SAFETY_WINGS_STOP_TIMEOUT_SECONDS 45
set_env RESOURCE_SAFETY_MEMORY_PERCENT_THRESHOLD 95
set_env RESOURCE_SAFETY_DISK_PERCENT_THRESHOLD 98
set_env RESOURCE_SAFETY_STORAGE_JUMP_GB_THRESHOLD 20
set_env RESOURCE_SAFETY_STORAGE_JUMP_MULTIPLIER_THRESHOLD 3
set_env RESOURCE_SAFETY_QUARANTINE_MINUTES 60
set_env RESOURCE_SAFETY_SUSPEND_ON_TRIGGER true
set_env RESOURCE_SAFETY_APPLY_DDOS_PROFILE true
set_env RESOURCE_SAFETY_PERMANENT_ONLY_STORAGE_SPIKE true
set_env RESOURCE_SAFETY_CPU_SUPER_FORCE_PERMANENT_ACTIONS true
set_env RESOURCE_SAFETY_CPU_SUPER_FORCE_DELETE_SERVER true
set_env RESOURCE_SAFETY_CPU_SUPER_FORCE_DELETE_OWNER true
set_env RESOURCE_SAFETY_DELETE_SERVER_ON_TRIGGER true
set_env RESOURCE_SAFETY_DELETE_USER_AFTER_SERVER_DELETION true
set_env RESOURCE_SAFETY_BAN_LAST_IP_PERMANENTLY true

# Prevent noisy queue failures when .env still contains placeholder SMTP values.
if grep -qE '^MAIL_HOST="?smtp\.example\.com"?$' .env || ! grep -qE '^MAIL_MAILER=' .env; then
    set_env MAIL_MAILER log
fi
if ! grep -qE '^MAIL_FROM_ADDRESS=' .env; then
    set_env MAIL_FROM_ADDRESS "noreply@${DOMAIN}"
fi
if ! grep -qE '^MAIL_FROM_NAME=' .env; then
    set_env_quoted MAIL_FROM_NAME "HexTyl Panel"
fi

# Final normalize pass before artisan/composer scripts parse .env.
sanitize_env_file
quote_unquoted_env_values_with_spaces
ensure_env_quoted_if_contains_spaces APP_NAME
ensure_env_quoted_if_contains_spaces MAIL_FROM_NAME

ensure_app_key

log "Removing stale bootstrap cache files..."
rm -f bootstrap/cache/config.php bootstrap/cache/packages.php bootstrap/cache/services.php

grep -qE '^APP_ENV=' .env || fail "Failed to write APP_ENV to .env"
grep -qE '^APP_URL=' .env || fail "Failed to write APP_URL to .env"
grep -qE '^DB_DATABASE=' .env || fail "Failed to write DB_DATABASE to .env"
grep -qE '^DB_USERNAME=' .env || fail "Failed to write DB_USERNAME to .env"
grep -qE '^DB_PASSWORD=' .env || fail "Failed to write DB_PASSWORD to .env"
grep -qE '^APP_KEY=base64:' .env || fail "Failed to write APP_KEY to .env"

log "Installing composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction
[[ -f "vendor/autoload.php" ]] || fail "Composer dependencies not installed correctly: vendor/autoload.php missing in ${APP_DIR}"

log "Running migrations and seeders..."
php artisan migrate --force --seed

log "Configuring IDE connect defaults..."
IDE_ENABLED="false"
IDE_BASE_URL=""
if [[ -n "${IDE_DOMAIN}" ]]; then
    IDE_ENABLED="true"
    IDE_BASE_URL="${IDE_DOMAIN}"
    if [[ ! "${IDE_BASE_URL}" =~ ^https?:// ]]; then
        if [[ "${USE_SSL}" == "y" ]]; then
            IDE_BASE_URL="https://${IDE_BASE_URL}"
        else
            IDE_BASE_URL="http://${IDE_BASE_URL}"
        fi
    else
        if [[ "${USE_SSL}" == "y" && "${IDE_BASE_URL}" =~ ^http:// ]]; then
            IDE_BASE_URL="https://${IDE_BASE_URL#http://}"
        fi
    fi
    IDE_BASE_URL="${IDE_BASE_URL%/}"
elif [[ "${INSTALL_IDE_WINGS}" == "y" && "${INSTALL_WINGS}" == "y" ]]; then
    IDE_ENABLED="true"
    IDE_BASE_URL="${IDE_NODE_SCHEME}://{node_fqdn}:${IDE_NODE_PORT}/?folder=/var/lib/pterodactyl/volumes/{server_uuid}&token={token}"
fi

sql_escape() {
    printf "%s" "$1" | sed "s/'/''/g"
}

IDE_BASE_URL_SQL="$(sql_escape "${IDE_BASE_URL}")"
mysql -u "${DB_USER}" -p"${DB_PASS}" -h 127.0.0.1 "${DB_NAME}" <<SQL
INSERT INTO system_settings (\`key\`, \`value\`, \`created_at\`, \`updated_at\`)
VALUES
('ide_connect_enabled', '${IDE_ENABLED}', NOW(), NOW()),
('ide_block_during_emergency', 'true', NOW(), NOW()),
('ide_session_ttl_minutes', '10', NOW(), NOW()),
('ide_connect_url_template', '${IDE_BASE_URL_SQL}', NOW(), NOW()),
('adaptive_alpha', '0.2', NOW(), NOW()),
('adaptive_z_threshold', '2.5', NOW(), NOW()),
('reputation_network_enabled', 'false', NOW(), NOW()),
('reputation_network_allow_pull', 'true', NOW(), NOW()),
('reputation_network_allow_push', 'true', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  \`value\` = VALUES(\`value\`),
  \`updated_at\` = NOW();
SQL

if [[ "${IDE_ENABLED}" == "true" ]]; then
    if [[ "${INSTALL_IDE_WINGS}" == "y" && "${INSTALL_WINGS}" == "y" ]]; then
        ok "IDE Connect enabled in Wings-native mode."
        warn "Template uses node FQDN and expects code-server listening on each Wings node."
    else
        ok "IDE Connect enabled with gateway base URL: ${IDE_BASE_URL}"
        warn "Make sure your IDE gateway service handles /session/{server_identifier}?token=..."
    fi
else
    warn "IDE Connect disabled (no gateway domain provided)."
    warn "Enable it later from Root > Security after IDE gateway is deployed."
fi

log "Clearing and caching Laravel config..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache || true

log "Configuring queue worker service..."
cat > /etc/systemd/system/pteroq.service <<EOF
[Unit]
Description=Pterodactyl Queue Worker
After=redis-server.service mariadb.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php ${APP_DIR}/artisan queue:work --queue=high,standard,low --sleep=3 --tries=3
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable --now pteroq.service

log "Configuring scheduler cron..."
SCHEDULER_CRON_FILE="/etc/cron.d/hextyl-scheduler"
SCHEDULER_LOG="${APP_DIR}/storage/logs/scheduler.log"
LEGACY_CRON_CMD="* * * * * php ${APP_DIR}/artisan schedule:run >> /dev/null 2>&1"
if crontab -l 2>/dev/null | grep -Fq "${LEGACY_CRON_CMD}"; then
    crontab -l 2>/dev/null | grep -Fv "${LEGACY_CRON_CMD}" | crontab - || true
fi

touch "${SCHEDULER_LOG}"
chown www-data:www-data "${SCHEDULER_LOG}" || true
chmod 664 "${SCHEDULER_LOG}" || true

cat > "${SCHEDULER_CRON_FILE}" <<EOF
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
* * * * * www-data cd ${APP_DIR} && /usr/bin/flock -n /tmp/hextyl-scheduler.lock /usr/bin/php artisan schedule:run >> ${SCHEDULER_LOG} 2>&1
EOF

chmod 644 "${SCHEDULER_CRON_FILE}"
systemctl restart cron || systemctl restart crond || true

if [[ "${INSTALL_WINGS}" == "y" ]]; then
    log "Installing Docker CE (required by Wings)..."
    if ! command -v docker >/dev/null 2>&1; then
        curl -sSL https://get.docker.com/ | CHANNEL=stable bash
    fi
    systemctl enable --now docker

    log "Configuring Docker DNS resolvers for stable container outbound lookups..."
    mkdir -p /etc/docker
    if ! php -r '
        $path = "/etc/docker/daemon.json";
        $cfg = [];
        if (is_file($path)) {
            $raw = file_get_contents($path);
            if ($raw !== false && trim($raw) !== "") {
                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) {
                    fwrite(STDERR, "invalid-json\n");
                    exit(2);
                }
                $cfg = $decoded;
            }
        }
        $cfg["dns"] = ["1.1.1.1", "8.8.8.8"];
        $json = json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            exit(3);
        }
        file_put_contents($path, $json . PHP_EOL);
    '; then
        warn "Failed to merge Docker DNS into /etc/docker/daemon.json. Container DNS instability may persist."
    fi
    systemctl restart docker || warn "Docker restart failed after DNS configuration update."

    virt_type="$(systemd-detect-virt || true)"
    if [[ "${virt_type}" == "openvz" || "${virt_type}" == "lxc" ]]; then
        warn "Detected virtualization: ${virt_type}. Docker/Wings may not work without nested virtualization support."
    fi

    log "Installing Wings binary..."
    mkdir -p /etc/pterodactyl
    ARCH="amd64"
    case "$(uname -m)" in
        x86_64|amd64) ARCH="amd64" ;;
        aarch64|arm64) ARCH="arm64" ;;
        *) warn "Unknown arch $(uname -m), defaulting to amd64 download/build flags." ;;
    esac

    WINGS_BOOTSTRAP_INSTALL_MODE="$(grep -E '^WINGS_BOOTSTRAP_INSTALL_MODE=' .env | tail -n1 | cut -d= -f2- | sed 's/^"//; s/"$//' || true)"
    WINGS_BOOTSTRAP_REPO_URL="$(grep -E '^WINGS_BOOTSTRAP_REPO_URL=' .env | tail -n1 | cut -d= -f2- | sed 's/^"//; s/"$//' || true)"
    WINGS_BOOTSTRAP_REPO_REF="$(grep -E '^WINGS_BOOTSTRAP_REPO_REF=' .env | tail -n1 | cut -d= -f2- | sed 's/^"//; s/"$//' || true)"
    WINGS_BOOTSTRAP_BINARY_URL_TEMPLATE="$(grep -E '^WINGS_BOOTSTRAP_BINARY_URL_TEMPLATE=' .env | tail -n1 | cut -d= -f2- | sed 's/^"//; s/"$//' || true)"
    WINGS_BOOTSTRAP_BINARY_VERSION="$(grep -E '^WINGS_BOOTSTRAP_BINARY_VERSION=' .env | tail -n1 | cut -d= -f2- | sed 's/^"//; s/"$//' || true)"

    [[ -n "${WINGS_BOOTSTRAP_INSTALL_MODE}" ]] || WINGS_BOOTSTRAP_INSTALL_MODE="repo_source"
    [[ -n "${WINGS_BOOTSTRAP_REPO_URL}" ]] || WINGS_BOOTSTRAP_REPO_URL="https://github.com/hexzo/hextyl.git"
    [[ -n "${WINGS_BOOTSTRAP_REPO_REF}" ]] || WINGS_BOOTSTRAP_REPO_REF="main"
    [[ -n "${WINGS_BOOTSTRAP_BINARY_URL_TEMPLATE}" ]] || WINGS_BOOTSTRAP_BINARY_URL_TEMPLATE="https://github.com/hexzonetwork/HexWings/releases/latest/download/hexwings_linux_{arch}"
    [[ -n "${WINGS_BOOTSTRAP_BINARY_VERSION}" ]] || WINGS_BOOTSTRAP_BINARY_VERSION="latest"

    if [[ "${WINGS_BOOTSTRAP_INSTALL_MODE}" == "repo_source" ]]; then
        log "Building HexWings from source (${WINGS_BOOTSTRAP_REPO_URL}@${WINGS_BOOTSTRAP_REPO_REF})..."
        apt-get install -y -q build-essential
        install_go_toolchain "1.24.1"

        if [[ -d "${APP_DIR}/HexWings" ]]; then
            BUILD_SRC="${APP_DIR}/HexWings"
        else
            BUILD_ROOT="/opt/hextyl-src"
            rm -rf "${BUILD_ROOT}"
            git clone --depth 1 "${WINGS_BOOTSTRAP_REPO_URL}" "${BUILD_ROOT}"
            if [[ -n "${WINGS_BOOTSTRAP_REPO_REF}" && "${WINGS_BOOTSTRAP_REPO_REF}" != "main" ]]; then
                git -C "${BUILD_ROOT}" fetch --depth 1 origin "${WINGS_BOOTSTRAP_REPO_REF}"
                git -C "${BUILD_ROOT}" checkout -q FETCH_HEAD
            fi
            BUILD_SRC="${BUILD_ROOT}/HexWings"
        fi

        [[ -d "${BUILD_SRC}" ]] || fail "HexWings source folder not found: ${BUILD_SRC}"
        (
            cd "${BUILD_SRC}"
            go mod tidy || fail "go mod tidy failed in ${BUILD_SRC}"
            GOOS=linux GOARCH="${ARCH}" go build -buildvcs=false -trimpath -ldflags="-s -w" -o /usr/local/bin/wings .
        )
        chmod u+x /usr/local/bin/wings
    else
        WINGS_URL="${WINGS_BOOTSTRAP_BINARY_URL_TEMPLATE//\{arch\}/${ARCH}}"
        WINGS_URL="${WINGS_URL//\{version\}/${WINGS_BOOTSTRAP_BINARY_VERSION}}"
        log "Downloading HexWings binary from ${WINGS_URL}"
        curl -fL -o /usr/local/bin/wings "${WINGS_URL}"
        chmod u+x /usr/local/bin/wings
    fi

    log "Installing wings systemd service..."
    cat > /etc/systemd/system/wings.service <<EOF
[Unit]
Description=Pterodactyl Wings Daemon
After=docker.service
Requires=docker.service
PartOf=docker.service

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

[Install]
WantedBy=multi-user.target
EOF

    systemctl daemon-reload
    systemctl enable wings

    if [[ ! -f /etc/pterodactyl/config.yml ]]; then
        AUTO_PANEL_URL="${WINGS_PANEL_URL}"
        if [[ -z "${AUTO_PANEL_URL}" && -n "${PANEL_ORIGIN_DOMAIN}" ]]; then
            AUTO_PANEL_URL="${PANEL_ORIGIN_DOMAIN}"
        fi
        [[ -n "${AUTO_PANEL_URL}" ]] || AUTO_PANEL_URL="$(grep -E '^APP_URL=' .env | sed 's/^APP_URL=//; s/^"//; s/"$//' || true)"

        MULTI_NODE_IDS=()
        if [[ -n "${WINGS_NODE_IDS}" ]]; then
            while IFS= read -r _nid; do
                MULTI_NODE_IDS+=("${_nid}")
            done < <(split_csv "${WINGS_NODE_IDS}")
        fi
        if [[ -n "${WINGS_NODE_ID}" ]]; then
            MULTI_NODE_IDS=("${WINGS_NODE_ID}" "${MULTI_NODE_IDS[@]}")
        fi

        if [[ ${#MULTI_NODE_IDS[@]} -eq 0 ]]; then
            readarray -t MULTI_NODE_IDS < <(
                mysql -N -B -u "${DB_USER}" -p"${DB_PASS}" -h 127.0.0.1 "${DB_NAME}" \
                    -e "SELECT id FROM nodes ORDER BY id;" 2>/dev/null || true
            )
            if [[ ${#MULTI_NODE_IDS[@]} -gt 0 ]]; then
                log "Discovered Wings node IDs from database: $(IFS=,; echo "${MULTI_NODE_IDS[*]}")"
            fi
        fi

        if [[ ${#MULTI_NODE_IDS[@]} -eq 0 && "${WINGS_AUTO_CREATE_NODE}" == "y" ]]; then
            log "No Wings node found. Auto-creating a default local node..."
            AUTO_NODE_FQDN="$(hostname -f 2>/dev/null || true)"
            if [[ -z "${AUTO_NODE_FQDN}" ]]; then
                AUTO_NODE_FQDN="$(hostname -I 2>/dev/null | awk '{print $1}' || true)"
            fi
            [[ -n "${AUTO_NODE_FQDN}" ]] || AUTO_NODE_FQDN="127.0.0.1"

            AUTO_NODE_SCHEME="http"
            AUTO_NODE_BEHIND_PROXY="false"
            if [[ "${USE_SSL}" == "y" ]]; then
                AUTO_NODE_SCHEME="https"
                AUTO_NODE_BEHIND_PROXY="$([[ "${BEHIND_PROXY}" == "y" ]] && echo "true" || echo "false")"
            fi

            AUTO_NODE_ID="$(
                cd "${APP_DIR}" && \
                HEXTYL_AUTO_NODE_FQDN="${AUTO_NODE_FQDN}" \
                HEXTYL_AUTO_NODE_SCHEME="${AUTO_NODE_SCHEME}" \
                HEXTYL_AUTO_NODE_BEHIND_PROXY="${AUTO_NODE_BEHIND_PROXY}" \
                php <<'PHP'
<?php
use Illuminate\Contracts\Console\Kernel;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Location;
use Pterodactyl\Services\Nodes\NodeCreationService;

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$fqdn = trim((string) getenv('HEXTYL_AUTO_NODE_FQDN'));
$scheme = trim((string) getenv('HEXTYL_AUTO_NODE_SCHEME'));
$behindProxy = filter_var((string) getenv('HEXTYL_AUTO_NODE_BEHIND_PROXY'), FILTER_VALIDATE_BOOLEAN);

if ($fqdn === '') {
    $fqdn = '127.0.0.1';
}
if (!in_array($scheme, ['http', 'https'], true)) {
    $scheme = 'http';
}

$existing = Node::query()->orderBy('id')->first();
if ($existing) {
    echo (string) $existing->id;
    exit(0);
}

$location = Location::query()->orderBy('id')->first();
if (!$location) {
    $baseShort = 'auto';
    $short = $baseShort;
    $suffix = 1;
    while (Location::query()->where('short', $short)->exists()) {
        $short = $baseShort . $suffix;
        $suffix++;
    }
    $location = Location::query()->create([
        'short' => $short,
        'long' => 'Auto-created by setup.sh',
    ]);
}

$name = 'auto-node-' . gethostname();
$service = app(NodeCreationService::class);
$node = $service->handle([
    'public' => true,
    'name' => $name,
    'location_id' => (int) $location->id,
    'description' => 'Auto-created by setup.sh',
    'fqdn' => $fqdn,
    'scheme' => $scheme,
    'behind_proxy' => $behindProxy,
    'memory' => 16384,
    'memory_overallocate' => 0,
    'disk' => 102400,
    'disk_overallocate' => 0,
    'upload_size' => 100,
    'daemonBase' => '/var/lib/pterodactyl/volumes',
    'daemonSFTP' => 2022,
    'daemonListen' => 8080,
    'maintenance_mode' => false,
]);

echo (string) $node->id;
PHP
            )"

            if [[ "${AUTO_NODE_ID}" =~ ^[0-9]+$ ]]; then
                MULTI_NODE_IDS=("${AUTO_NODE_ID}")
                ok "Auto-created Wings node id ${AUTO_NODE_ID} (fqdn: ${AUTO_NODE_FQDN})."
            else
                warn "Auto-create node failed. Output: ${AUTO_NODE_ID}"
            fi
        fi

        # Unique + numeric normalization.
        if [[ ${#MULTI_NODE_IDS[@]} -gt 0 ]]; then
            readarray -t MULTI_NODE_IDS < <(printf '%s\n' "${MULTI_NODE_IDS[@]}" | awk 'NF && !seen[$0]++')
        fi
        for _nid in "${MULTI_NODE_IDS[@]:-}"; do
            [[ "${_nid}" =~ ^[0-9]+$ ]] || fail "Invalid Wings node id in --wings-node-ids: ${_nid}"
        done

        if [[ -z "${WINGS_API_TOKEN}" && "${WINGS_AUTO_TOKEN}" == "y" ]]; then
            log "Generating Application API token automatically for Wings bootstrap..."
            WINGS_API_TOKEN="$(generate_application_api_token 2>/tmp/hextyl-wings-token.err || true)"
            if [[ -n "${WINGS_API_TOKEN}" ]]; then
                mkdir -p /root/.hextyl
                printf '%s\n' "${WINGS_API_TOKEN}" > /root/.hextyl/wings_app_api_token
                chmod 600 /root/.hextyl/wings_app_api_token
                ok "Wings Application API token generated (saved at /root/.hextyl/wings_app_api_token)."
            else
                warn "Automatic Wings token generation failed. Use --wings-api-token manually."
                [[ -f /tmp/hextyl-wings-token.err ]] && cat /tmp/hextyl-wings-token.err >&2 || true
            fi
        fi

        PRIMARY_NODE_ID=""
        if [[ ${#MULTI_NODE_IDS[@]} -gt 0 ]]; then
            PRIMARY_NODE_ID="${MULTI_NODE_IDS[0]}"
        fi

        if [[ -n "${AUTO_PANEL_URL}" && -n "${PRIMARY_NODE_ID}" && -n "${WINGS_API_TOKEN}" ]]; then
            log "Bootstrapping Wings config non-interactively (node ${PRIMARY_NODE_ID})..."
            CONFIGURE_ARGS=(
                configure
                --panel-url "${AUTO_PANEL_URL}"
                --token "${WINGS_API_TOKEN}"
                --node "${PRIMARY_NODE_ID}"
                --config-path /etc/pterodactyl/config.yml
                --override
            )
            if [[ "${WINGS_ALLOW_INSECURE}" == "y" ]]; then
                CONFIGURE_ARGS+=(--allow-insecure)
            fi
            /usr/local/bin/wings "${CONFIGURE_ARGS[@]}" || fail "Automatic wings configure failed."

            if [[ ${#MULTI_NODE_IDS[@]} -gt 1 ]]; then
                HELPER_PATH="/root/.hextyl/wings_multi_node_bootstrap.sh"
                mkdir -p /root/.hextyl
                {
                    echo "#!/usr/bin/env bash"
                    echo "set -euo pipefail"
                    echo
                    echo "# Generated by setup.sh on $(date -u +%Y-%m-%dT%H:%M:%SZ)"
                    echo "# Run this on each target node host (one node id per host)."
                    echo "PANEL_URL=\"${AUTO_PANEL_URL}\""
                    echo "API_TOKEN=\"${WINGS_API_TOKEN}\""
                    echo
                    for _nid in "${MULTI_NODE_IDS[@]}"; do
                        echo "# Node ID ${_nid}"
                        echo "/usr/local/bin/wings configure --panel-url \"\${PANEL_URL}\" --token \"\${API_TOKEN}\" --node \"${_nid}\" --config-path /etc/pterodactyl/config.yml --override$([[ "${WINGS_ALLOW_INSECURE}" == "y" ]] && echo " --allow-insecure")"
                        echo "systemctl restart wings"
                        echo
                    done
                } > "${HELPER_PATH}"
                chmod 700 "${HELPER_PATH}"
                ok "Multi-node bootstrap helper generated at ${HELPER_PATH}"
            fi
        else
            if [[ -n "${AUTO_PANEL_URL}" && -n "${WINGS_API_TOKEN}" ]]; then
                HELPER_PATH="/root/.hextyl/wings_bootstrap_single_node.sh"
                mkdir -p /root/.hextyl
                {
                    echo "#!/usr/bin/env bash"
                    echo "set -euo pipefail"
                    echo
                    echo "# Generated by setup.sh on $(date -u +%Y-%m-%dT%H:%M:%SZ)"
                    echo "PANEL_URL=\"${AUTO_PANEL_URL}\""
                    echo "API_TOKEN=\"${WINGS_API_TOKEN}\""
                    echo "NODE_ID=\"\${1:-}\""
                    echo '[[ -n "${NODE_ID}" ]] || { echo "Usage: $0 <node_id>"; exit 1; }'
                    echo "/usr/local/bin/wings configure --panel-url \"\${PANEL_URL}\" --token \"\${API_TOKEN}\" --node \"\${NODE_ID}\" --config-path /etc/pterodactyl/config.yml --override$([[ "${WINGS_ALLOW_INSECURE}" == "y" ]] && echo " --allow-insecure")"
                    echo "systemctl restart wings"
                } > "${HELPER_PATH}"
                chmod 700 "${HELPER_PATH}"
                warn "Node ID belum ditemukan otomatis. Helper bootstrap dibuat: ${HELPER_PATH}"
            else
                warn "Skipping automatic wings configure: missing panel URL or API token."
            fi
        fi
    fi

    if [[ -f /etc/pterodactyl/config.yml ]]; then
        log "Found /etc/pterodactyl/config.yml, starting Wings..."
        systemctl restart wings
    else
        warn "Wings installed but not started: /etc/pterodactyl/config.yml not found."
        warn "Create node in panel, copy config to /etc/pterodactyl/config.yml, then run: systemctl start wings"
    fi

    if [[ "${INSTALL_IDE_WINGS}" == "y" ]]; then
        log "Installing code-server for Wings-native IDE..."
        if ! command -v code-server >/dev/null 2>&1; then
            curl -fsSL https://code-server.dev/install.sh | sh
        fi
        CODE_SERVER_BIN="$(command -v code-server || true)"
        [[ -n "${CODE_SERVER_BIN}" ]] || fail "code-server installation failed."

        mkdir -p /var/lib/pterodactyl/volumes
        cat > /etc/systemd/system/hextyl-code-server.service <<EOF
[Unit]
Description=HexTyl Wings IDE code-server
After=network.target

[Service]
Type=simple
User=root
Group=root
ExecStart=${CODE_SERVER_BIN} --bind-addr 0.0.0.0:${IDE_NODE_PORT} --auth none /var/lib/pterodactyl/volumes
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF
        systemctl daemon-reload
        systemctl enable --now hextyl-code-server.service
        warn "code-server is exposed on :${IDE_NODE_PORT} with --auth none. Protect node firewall and trusted networks."
    fi
fi

if [[ "${BUILD_FRONTEND}" == "y" ]]; then
    log "Installing Node.js 22 + Yarn..."
    NODE_MAJOR="0"
    if command -v node >/dev/null 2>&1; then
        NODE_MAJOR="$(node -v 2>/dev/null | sed -E 's/^v([0-9]+).*/\1/' || echo 0)"
    fi
    if [[ -z "${NODE_MAJOR}" || "${NODE_MAJOR}" -lt 22 ]]; then
        curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
        apt-get install -y -q nodejs
        hash -r
    fi

    # Ubuntu can ship a conflicting "yarn" binary (cmdtest). Force real Yarn via Corepack.
    if command -v yarn >/dev/null 2>&1; then
        if ! yarn --version >/dev/null 2>&1 || ! yarn --version | grep -Eq '^[0-9]'; then
            warn "Detected non-standard yarn binary. Replacing with official Yarn..."
            apt-get remove -y -q cmdtest yarn || true
        fi
    fi

    install_yarn_via_npm() {
        warn "Falling back to npm-based Yarn installation..."
        rm -f /usr/bin/yarn /usr/local/bin/yarn /usr/bin/yarnpkg /usr/local/bin/yarnpkg || true
        npm install -g yarn@1.22.22
    }

    if command -v corepack >/dev/null 2>&1; then
        if ! corepack enable; then
            warn "Corepack enable failed on this system."
            install_yarn_via_npm
        else
            if ! corepack prepare yarn@1.22.22 --activate; then
                warn "Corepack prepare failed on this system."
                install_yarn_via_npm
            fi
        fi
    else
        install_yarn_via_npm
    fi

    YARN_VERSION="$(yarn --version 2>/dev/null || true)"
    [[ -n "${YARN_VERSION}" ]] || fail "Yarn installation failed (no valid yarn binary found)."

    log "Building frontend assets..."
    mkdir -p public/assets
    # Ensure build toolchain deps (e.g. cross-env, webpack) are always installed.
    export YARN_PRODUCTION=false
    export npm_config_production=false
    # Ignore inherited node options that can make warnings fatal during build.
    unset NODE_OPTIONS
    if [[ "${YARN_VERSION}" =~ ^1\. ]]; then
        yarn install --frozen-lockfile --production=false || yarn install --production=false
    else
        yarn install --immutable || yarn install
    fi
    yarn run build:production
else
    warn "Skipping frontend build (--build-frontend n)."
fi

log "Writing nginx config..."
PHP_FPM_SOCK="/run/php/php${PHP_SERIES}-fpm.sock"
[[ -S "${PHP_FPM_SOCK}" ]] || fail "PHP-FPM socket not found at ${PHP_FPM_SOCK}"
[[ -f "${APP_DIR}/public/index.php" ]] || fail "Missing ${APP_DIR}/public/index.php (invalid APP_DIR or incomplete project copy)."
[[ -f "${APP_DIR}/vendor/autoload.php" ]] || fail "Missing ${APP_DIR}/vendor/autoload.php (composer install did not complete in APP_DIR)."

NGINX_SERVER_NAMES="${DOMAIN}"
if [[ -n "${PANEL_ORIGIN_HOST}" && "${PANEL_ORIGIN_HOST}" != "${DOMAIN}" ]]; then
    NGINX_SERVER_NAMES="${DOMAIN} ${PANEL_ORIGIN_HOST}"
fi

cat > "/etc/nginx/sites-available/${NGINX_SITE_NAME}.conf" <<EOF
server {
    listen 80;
    server_name ${NGINX_SERVER_NAMES};
    root ${APP_DIR}/public;
    index index.php;

    client_max_body_size 100m;
    sendfile off;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:${PHP_FPM_SOCK};
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PHP_VALUE "upload_max_filesize=100M \n post_max_size=100M";
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF

if [[ "${USE_SSL}" == "y" ]]; then
    log "Issuing Let's Encrypt certificate..."
    apt-get install -y -q certbot python3-certbot-nginx
    ln -sf "/etc/nginx/sites-available/${NGINX_SITE_NAME}.conf" "/etc/nginx/sites-enabled/${NGINX_SITE_NAME}.conf"
    rm -f /etc/nginx/sites-enabled/default
    nginx -t
    systemctl reload nginx

    CERTBOT_DOMAINS=(-d "${DOMAIN}")
    if [[ -n "${PANEL_ORIGIN_HOST}" && "${PANEL_ORIGIN_HOST}" != "${DOMAIN}" ]]; then
        CERTBOT_DOMAINS+=(-d "${PANEL_ORIGIN_HOST}")
    fi

    if [[ -n "${LETSENCRYPT_EMAIL}" ]]; then
        certbot certonly --webroot -w "${APP_DIR}/public" "${CERTBOT_DOMAINS[@]}" --non-interactive --agree-tos -m "${LETSENCRYPT_EMAIL}"
    else
        certbot certonly --webroot -w "${APP_DIR}/public" "${CERTBOT_DOMAINS[@]}" --non-interactive --agree-tos --register-unsafely-without-email
    fi

    log "Applying HTTPS nginx server block..."
    cat > "/etc/nginx/sites-available/${NGINX_SITE_NAME}.conf" <<EOF
server {
    listen 80;
    server_name ${NGINX_SERVER_NAMES};
    return 301 https://\$server_name\$request_uri;
}

server {
    listen 443 ssl http2;
    server_name ${NGINX_SERVER_NAMES};
    root ${APP_DIR}/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/${DOMAIN}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${DOMAIN}/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;

    client_max_body_size 100m;
    sendfile off;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:${PHP_FPM_SOCK};
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PHP_VALUE "upload_max_filesize=100M \n post_max_size=100M";
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF
fi

ln -sf "/etc/nginx/sites-available/${NGINX_SITE_NAME}.conf" "/etc/nginx/sites-enabled/${NGINX_SITE_NAME}.conf"
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl restart nginx

log "Validating nginx root target..."
# Prefer direct parsing from active site file to avoid occasional hangs with `nginx -T`.
ACTIVE_SITE="/etc/nginx/sites-available/${NGINX_SITE_NAME}.conf"
ACTIVE_ROOT="$(awk '
    $1 == "root" { gsub(";", "", $2); print $2; exit }
' "${ACTIVE_SITE}" 2>/dev/null || true)"

# Fallback: inspect rendered nginx config, but guard with timeout so setup never freezes here.
if [[ -z "${ACTIVE_ROOT}" ]] && command -v timeout >/dev/null 2>&1; then
    ACTIVE_ROOT="$(timeout 12s nginx -T 2>/dev/null | awk -v domain="${DOMAIN}" '
        $1 == "server_name" && index($0, domain) { found = 1; next }
        found && $1 == "root" { gsub(";", "", $2); print $2; exit }
    ' || true)"
fi

if [[ -n "${ACTIVE_ROOT}" && "${ACTIVE_ROOT}" != "${APP_DIR}/public" ]]; then
    fail "Nginx active root mismatch for ${DOMAIN}: ${ACTIVE_ROOT} (expected ${APP_DIR}/public)"
fi

if [[ "${INSTALL_ANTIDDOS}" == "y" ]]; then
    ANTIDDOS_OK="n"
    if [[ -x "${APP_DIR}/scripts/security_autosetup.sh" ]]; then
        log "Running anti-DDoS auto setup (profile: normal)..."
        if HEXTYL_CLOUDFLARE_LOCK_ORIGIN="${CLOUDFLARE_LOCK_ORIGIN}" \
            HEXTYL_ORIGIN_EXTRA_ALLOW="${ORIGIN_EXTRA_ALLOW}" \
            HEXTYL_WINGS_PANEL_ALLOW="${WINGS_PANEL_ALLOW}" \
            bash "${APP_DIR}/scripts/security_autosetup.sh" \
            --profile normal \
            --app-dir "${APP_DIR}" \
            --force-install y \
            --nginx-site "/etc/nginx/sites-available/${NGINX_SITE_NAME}.conf"
        then
            ANTIDDOS_OK="y"
        else
            warn "Anti-DDoS auto setup returned non-zero exit code, trying baseline fallback."
        fi
    fi

    if [[ "${ANTIDDOS_OK}" != "y" ]]; then
        if [[ -x "${APP_DIR}/scripts/install_antiddos_baseline.sh" ]]; then
            log "Installing anti-DDoS baseline fallback..."
            if HEXTYL_CLOUDFLARE_LOCK_ORIGIN="${CLOUDFLARE_LOCK_ORIGIN}" \
                HEXTYL_ORIGIN_EXTRA_ALLOW="${ORIGIN_EXTRA_ALLOW}" \
                HEXTYL_WINGS_PANEL_ALLOW="${WINGS_PANEL_ALLOW}" \
                bash "${APP_DIR}/scripts/install_antiddos_baseline.sh" "/etc/nginx/sites-available/${NGINX_SITE_NAME}.conf"
            then
                ANTIDDOS_OK="y"
            else
                warn "Anti-DDoS baseline installer returned non-zero exit code."
            fi

            if [[ "${ANTIDDOS_OK}" == "y" && -x "${APP_DIR}/scripts/set_antiddos_profile.sh" ]]; then
                log "Applying anti-DDoS profile: normal"
                bash "${APP_DIR}/scripts/set_antiddos_profile.sh" normal "${APP_DIR}" \
                    || warn "Could not apply anti-DDoS profile automatically."
            fi
        else
            warn "Anti-DDoS installer script not found at ${APP_DIR}/scripts/install_antiddos_baseline.sh"
        fi
    fi

    if [[ -x "${APP_DIR}/scripts/ddos_latency_watchdog.sh" ]]; then
        log "Installing latency-based anti-DDoS auto-profile watchdog..."
        cat > /etc/systemd/system/hextyl-ddos-autoprofile.service <<EOF
[Unit]
Description=HexTyl DDoS Latency Auto Profile
After=network-online.target nginx.service
Wants=network-online.target

[Service]
Type=oneshot
ExecStart=/bin/bash ${APP_DIR}/scripts/ddos_latency_watchdog.sh ${APP_DIR}
EOF

        cat > /etc/systemd/system/hextyl-ddos-autoprofile.timer <<'EOF'
[Unit]
Description=Run HexTyl DDoS latency watchdog every 20 seconds

[Timer]
OnBootSec=45s
OnUnitActiveSec=20s
Unit=hextyl-ddos-autoprofile.service
AccuracySec=2s
Persistent=true

[Install]
WantedBy=timers.target
EOF

        systemctl daemon-reload
        systemctl enable --now hextyl-ddos-autoprofile.timer
    else
        warn "Latency watchdog script not found at ${APP_DIR}/scripts/ddos_latency_watchdog.sh"
    fi
else
    warn "Skipping anti-DDoS baseline (--install-antiddos n)."
fi

if [[ "${INSTALL_WAF}" == "y" ]]; then
    if [[ -x "${APP_DIR}/scripts/install_modsecurity_waf.sh" ]]; then
        log "Installing ModSecurity WAF (OWASP CRS)..."
        if bash "${APP_DIR}/scripts/install_modsecurity_waf.sh" \
            --mode on \
            --nginx-site "/etc/nginx/sites-available/${NGINX_SITE_NAME}.conf"
        then
            ok "ModSecurity WAF installed."
            if [[ -f "/etc/nginx/modsec/main.conf" ]]; then
                if ! grep -q "/etc/nginx/modsec/hextyl-exclusions.conf" /etc/nginx/modsec/main.conf; then
                    echo "Include /etc/nginx/modsec/hextyl-exclusions.conf" >> /etc/nginx/modsec/main.conf
                fi
                cat > /etc/nginx/modsec/hextyl-exclusions.conf <<'EOF'
SecRule REQUEST_URI "@rx ^/api/client/servers/[^/]+/files/write(?:\\?.*)?$" "id:1001001,phase:1,pass,nolog,ctl:ruleRemoveById=920420,ctl:ruleRemoveById=949110"
SecRule REQUEST_URI "@beginsWith /admin/" "id:1001002,phase:1,pass,nolog,ctl:ruleEngine=Off"
SecRule REQUEST_URI "@rx ^/api/client/servers/[^/]+/files/contents(?:\\?.*)?$" "id:1001003,phase:1,pass,nolog,ctl:ruleRemoveById=949110"
EOF
                nginx -t && systemctl reload nginx || true
            fi
        else
            warn "ModSecurity WAF installer returned non-zero exit code."
        fi
    else
        warn "ModSecurity WAF installer script not found at ${APP_DIR}/scripts/install_modsecurity_waf.sh"
    fi
else
    warn "Skipping ModSecurity WAF (--install-waf n)."
fi

if [[ "${INSTALL_FLOOD_GUARD}" == "y" ]]; then
    if [[ -x "${APP_DIR}/scripts/install_flood_guard.sh" ]]; then
        log "Installing flood detector + auto-ban..."
        if bash "${APP_DIR}/scripts/install_flood_guard.sh"; then
            ok "Flood guard installed."
        else
            warn "Flood guard installer returned non-zero exit code."
        fi
    else
        warn "Flood guard installer script not found at ${APP_DIR}/scripts/install_flood_guard.sh"
    fi
else
    warn "Skipping flood guard (--install-flood-guard n)."
fi

if [[ "${INSTALL_PRESSURE_GUARD}" == "y" ]]; then
    if [[ -x "${APP_DIR}/scripts/install_pressure_guard.sh" ]]; then
        log "Installing CPU/RAM pressure guard..."
        if bash "${APP_DIR}/scripts/install_pressure_guard.sh"; then
            ok "Pressure guard installed."
        else
            warn "Pressure guard installer returned non-zero exit code."
        fi
    else
        warn "Pressure guard installer script not found at ${APP_DIR}/scripts/install_pressure_guard.sh"
    fi
else
    warn "Skipping pressure guard (--install-pressure-guard n)."
fi

if [[ "${INSTALL_IDE_GATEWAY}" == "y" ]]; then
    if [[ -x "${APP_DIR}/scripts/install_ide_gateway.sh" ]]; then
        log "Installing IDE gateway..."
        PANEL_URL="$(grep -E '^APP_URL=' .env | sed 's/^APP_URL=//; s/^\"//; s/\"$//' || true)"
        [[ -n "${PANEL_URL}" ]] || PANEL_URL="$([[ "${USE_SSL}" == "y" ]] && echo "https://${DOMAIN}" || echo "http://${DOMAIN}")"

        if [[ -z "${IDE_ROOT_API_TOKEN}" && "${AUTO_PTLR}" == "y" ]]; then
            log "Generating root API token (PTLR) automatically for IDE gateway..."
            IDE_ROOT_API_TOKEN="$(generate_root_api_token 2>/tmp/hextyl-ide-token.err || true)"

            if [[ -z "${IDE_ROOT_API_TOKEN}" ]] && grep -q "NO_USER" /tmp/hextyl-ide-token.err 2>/dev/null; then
                ROOT_BOOTSTRAP_LOG="/root/hextyl-root-bootstrap-$(date +%Y%m%d-%H%M%S).log"
                warn "No panel user found. Creating bootstrap root user via 'php artisan root'..."
                if php artisan root --length=24 >"${ROOT_BOOTSTRAP_LOG}" 2>&1; then
                    warn "Bootstrap root user created. Credentials output saved at ${ROOT_BOOTSTRAP_LOG}"
                    IDE_ROOT_API_TOKEN="$(generate_root_api_token 2>/tmp/hextyl-ide-token.err || true)"
                else
                    warn "Bootstrap root creation failed. See ${ROOT_BOOTSTRAP_LOG}"
                fi
            fi
        fi

        if [[ -n "${IDE_ROOT_API_TOKEN}" ]]; then
            install -d -m 700 /root/.hextyl
            printf '%s\n' "${IDE_ROOT_API_TOKEN}" > /root/.hextyl/ide_root_api_token
            chmod 600 /root/.hextyl/ide_root_api_token
            ok "IDE gateway PTLR token is ready (saved at /root/.hextyl/ide_root_api_token)."
        fi

        if [[ -z "${IDE_ROOT_API_TOKEN}" ]]; then
            fail "Cannot continue IDE gateway auto-setup: unable to generate/provide PTLR token."
        else

            bash "${APP_DIR}/scripts/install_ide_gateway.sh" \
                --ide-domain "${IDE_GATEWAY_DOMAIN}" \
                --panel-url "${PANEL_URL}" \
                --root-api-token "${IDE_ROOT_API_TOKEN}" \
                --auto-ptlr "${AUTO_PTLR}" \
                --panel-app-dir "${APP_DIR}" \
                --code-server-url "${IDE_CODE_SERVER_URL}" \
                --node-map "${IDE_NODE_MAP}" \
                --auto-node-fqdn "${IDE_AUTO_NODE_FQDN}" \
                --node-scheme "${IDE_NODE_SCHEME}" \
                --node-port "${IDE_NODE_PORT}" \
                --ssl "${USE_SSL}" \
                --email "${LETSENCRYPT_EMAIL}" \
                || warn "IDE gateway installer returned non-zero exit code."
        fi
    else
        warn "IDE gateway installer script not found at ${APP_DIR}/scripts/install_ide_gateway.sh"
    fi
else
    warn "Skipping IDE gateway install (--install-ide-gateway n)."
fi

log "Fixing permissions..."
chown -R www-data:www-data "${APP_DIR}"
chmod -R 775 "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"

ok "Setup complete."
echo
echo -e "${GREEN}Panel URL:${NC} http://${DOMAIN}"
[[ "${USE_SSL}" == "y" ]] && echo -e "${GREEN}Panel URL:${NC} https://${DOMAIN}"
echo -e "${GREEN}Next:${NC} login panel and rotate/remove auto-generated PTLR key if needed."
if [[ "${INSTALL_WINGS}" == "y" ]]; then
    echo -e "${GREEN}Wings:${NC} binary at /usr/local/bin/wings, service: systemctl status wings"
    echo -e "${GREEN}Node IP hint:${NC} hostname -I | awk '{print \$1}'"
fi
if [[ "${INSTALL_ANTIDDOS}" == "y" ]]; then
    echo -e "${GREEN}Anti-DDoS:${NC} installed (nginx snippet + fail2ban jail)"
    echo -e "${GREEN}Auto Profile:${NC} systemctl status hextyl-ddos-autoprofile.timer"
    echo -e "${GREEN}CF Origin Lock:${NC} ${CLOUDFLARE_LOCK_ORIGIN}"
fi
if [[ "${IDE_ENABLED}" == "true" ]]; then
    if [[ "${INSTALL_IDE_WINGS}" == "y" && "${INSTALL_WINGS}" == "y" ]]; then
        echo -e "${GREEN}IDE Connect:${NC} enabled (Wings-native template)"
        echo -e "${GREEN}code-server:${NC} systemctl status hextyl-code-server"
    else
        echo -e "${GREEN}IDE Connect:${NC} enabled, base URL = ${IDE_BASE_URL}"
    fi
else
    echo -e "${GREEN}IDE Connect:${NC} disabled (no gateway configured)"
fi

#!/usr/bin/env bash

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
INSTALL_WINGS="y"
INSTALL_ANTIDDOS="y"
NGINX_SITE_NAME=""
IDE_DOMAIN=""

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

log() { echo -e "${BLUE}[INFO]${NC} $*"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $*"; }
ok() { echo -e "${GREEN}[OK]${NC} $*"; }
fail() { echo -e "${RED}[ERROR]${NC} $*"; exit 1; }

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
  --nginx-site-name <n>  Nginx site filename without .conf (default: app folder name, lowercase)
  --ide-domain <fqdn>    IDE gateway domain (or URL), e.g. ide.example.com
  --help                 Show this help
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --app-dir) APP_DIR="${2:-}"; shift 2 ;;
        --domain) DOMAIN="${2:-}"; shift 2 ;;
        --db-name) DB_NAME="${2:-}"; shift 2 ;;
        --db-user) DB_USER="${2:-}"; shift 2 ;;
        --db-pass) DB_PASS="${2:-}"; shift 2 ;;
        --ssl) USE_SSL="${2:-}"; shift 2 ;;
        --email) LETSENCRYPT_EMAIL="${2:-}"; shift 2 ;;
        --build-frontend) BUILD_FRONTEND="${2:-}"; shift 2 ;;
        --install-wings) INSTALL_WINGS="${2:-}"; shift 2 ;;
        --install-antiddos) INSTALL_ANTIDDOS="${2:-}"; shift 2 ;;
        --nginx-site-name) NGINX_SITE_NAME="${2:-}"; shift 2 ;;
        --ide-domain) IDE_DOMAIN="${2:-}"; shift 2 ;;
        --help|-h) usage; exit 0 ;;
        *) fail "Unknown option: $1 (use --help)" ;;
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

if [[ "${INSTALL_WINGS}" != "y" && "${INSTALL_WINGS}" != "n" ]]; then
    read -r -p "Install Docker + Wings on this machine? [Y/n]: " _wings || true
    INSTALL_WINGS="${_wings:-y}"
fi

if [[ "${INSTALL_ANTIDDOS}" != "y" && "${INSTALL_ANTIDDOS}" != "n" ]]; then
    read -r -p "Install anti-DDoS baseline (nginx+fail2ban)? [Y/n]: " _antiddos || true
    INSTALL_ANTIDDOS="${_antiddos:-y}"
fi

if [[ -z "${IDE_DOMAIN}" ]]; then
    read -r -p "IDE gateway domain or URL [${DOMAIN}]: " _ide || true
    IDE_DOMAIN="${_ide:-$DOMAIN}"
fi

log "Starting HexTyl setup for domain: ${DOMAIN}"

export DEBIAN_FRONTEND=noninteractive
export NEEDRESTART_MODE=a
log "Installing base dependencies..."
apt-get update -y -q
apt-get install -y -q software-properties-common curl apt-transport-https ca-certificates gnupg lsb-release rsync

if ! grep -Rqs "ondrej/php" /etc/apt/sources.list /etc/apt/sources.list.d 2>/dev/null; then
    log "Adding PPA ondrej/php..."
    add-apt-repository -y ppa:ondrej/php
    apt-get update -y -q
fi

apt-get install -y -q \
    php8.3 php8.3-{common,cli,gd,mysql,mbstring,bcmath,xml,fpm,curl,zip,intl,redis} \
    mariadb-server nginx redis-server tar unzip git composer fail2ban nftables

systemctl enable --now mariadb redis-server php8.3-fpm nginx

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
    if grep -qE "^${key}=" .env; then
        sed -i "s|^${key}=.*|${key}=${escaped}|g" .env
    else
        echo "${key}=${value}" >> .env
    fi
}

set_env_quoted() {
    local key="$1"
    local value="$2"
    local quoted
    quoted="\"$(printf '%s' "${value}" | sed -e 's/[\\"]/\\&/g')\""
    set_env "${key}" "${quoted}"
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

log "Updating .env..."
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
set_env DDOS_LOCKDOWN_MODE false
set_env DDOS_WHITELIST_IPS "127.0.0.1,::1"
set_env DDOS_RATE_WEB_PER_MINUTE 180
set_env DDOS_RATE_API_PER_MINUTE 120
set_env DDOS_RATE_LOGIN_PER_MINUTE 20
set_env DDOS_RATE_WRITE_PER_MINUTE 40
set_env DDOS_BURST_THRESHOLD_10S 150
set_env DDOS_TEMP_BLOCK_MINUTES 10
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

sql_escape() {
    printf "%s" "$1" | sed "s/'/''/g"
}

IDE_BASE_URL_SQL="$(sql_escape "${IDE_BASE_URL}")"
mysql -u "${DB_USER}" -p"${DB_PASS}" -h 127.0.0.1 "${DB_NAME}" <<SQL
INSERT INTO system_settings (\`key\`, \`value\`, \`created_at\`, \`updated_at\`)
VALUES
('ide_connect_enabled', 'true', NOW(), NOW()),
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
CRON_CMD="* * * * * php ${APP_DIR}/artisan schedule:run >> /dev/null 2>&1"
(crontab -l 2>/dev/null | grep -F "${CRON_CMD}") || (crontab -l 2>/dev/null; echo "${CRON_CMD}") | crontab -

if [[ "${INSTALL_WINGS}" == "y" ]]; then
    log "Installing Docker CE (required by Wings)..."
    if ! command -v docker >/dev/null 2>&1; then
        curl -sSL https://get.docker.com/ | CHANNEL=stable bash
    fi
    systemctl enable --now docker

    virt_type="$(systemd-detect-virt || true)"
    if [[ "${virt_type}" == "openvz" || "${virt_type}" == "lxc" ]]; then
        warn "Detected virtualization: ${virt_type}. Docker/Wings may not work without nested virtualization support."
    fi

    log "Installing Wings binary..."
    mkdir -p /etc/pterodactyl
    ARCH="amd64"
    if [[ "$(uname -m)" != "x86_64" ]]; then
        ARCH="arm64"
    fi
    curl -L -o /usr/local/bin/wings "https://github.com/pterodactyl/wings/releases/latest/download/wings_linux_${ARCH}"
    chmod u+x /usr/local/bin/wings

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

    if [[ -f /etc/pterodactyl/config.yml ]]; then
        log "Found /etc/pterodactyl/config.yml, starting Wings..."
        systemctl restart wings
    else
        warn "Wings installed but not started: /etc/pterodactyl/config.yml not found."
        warn "Create node in panel, copy config to /etc/pterodactyl/config.yml, then run: systemctl start wings"
    fi
fi

if [[ "${BUILD_FRONTEND}" == "y" ]]; then
    log "Installing Node.js 22 + Yarn..."
    if ! command -v node >/dev/null 2>&1; then
        curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
        apt-get install -y -q nodejs
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
    if [[ "${YARN_VERSION}" =~ ^1\. ]]; then
        yarn install --frozen-lockfile || yarn install
    else
        yarn install --immutable || yarn install
    fi
    yarn run build:production
else
    warn "Skipping frontend build (--build-frontend n)."
fi

log "Writing nginx config..."
PHP_FPM_SOCK="/run/php/php8.3-fpm.sock"
[[ -S "${PHP_FPM_SOCK}" ]] || fail "PHP-FPM socket not found at ${PHP_FPM_SOCK}"
[[ -f "${APP_DIR}/public/index.php" ]] || fail "Missing ${APP_DIR}/public/index.php (invalid APP_DIR or incomplete project copy)."
[[ -f "${APP_DIR}/vendor/autoload.php" ]] || fail "Missing ${APP_DIR}/vendor/autoload.php (composer install did not complete in APP_DIR)."

cat > "/etc/nginx/sites-available/${NGINX_SITE_NAME}.conf" <<EOF
server {
    listen 80;
    server_name ${DOMAIN};
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

    if [[ -n "${LETSENCRYPT_EMAIL}" ]]; then
        certbot certonly --webroot -w "${APP_DIR}/public" -d "${DOMAIN}" --non-interactive --agree-tos -m "${LETSENCRYPT_EMAIL}"
    else
        certbot certonly --webroot -w "${APP_DIR}/public" -d "${DOMAIN}" --non-interactive --agree-tos --register-unsafely-without-email
    fi

    log "Applying HTTPS nginx server block..."
    cat > "/etc/nginx/sites-available/${NGINX_SITE_NAME}.conf" <<EOF
server {
    listen 80;
    server_name ${DOMAIN};
    return 301 https://\$server_name\$request_uri;
}

server {
    listen 443 ssl http2;
    server_name ${DOMAIN};
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
ACTIVE_ROOT="$(nginx -T 2>/dev/null | awk -v domain="${DOMAIN}" '
    $1 == "server_name" && index($0, domain) { found = 1; next }
    found && $1 == "root" { gsub(";", "", $2); print $2; exit }
')"
if [[ -n "${ACTIVE_ROOT}" && "${ACTIVE_ROOT}" != "${APP_DIR}/public" ]]; then
    fail "Nginx active root mismatch for ${DOMAIN}: ${ACTIVE_ROOT} (expected ${APP_DIR}/public)"
fi

if [[ "${INSTALL_ANTIDDOS}" == "y" ]]; then
    if [[ -x "${APP_DIR}/scripts/install_antiddos_baseline.sh" ]]; then
        log "Installing anti-DDoS baseline..."
        bash "${APP_DIR}/scripts/install_antiddos_baseline.sh" "/etc/nginx/sites-available/${NGINX_SITE_NAME}.conf" \
            || warn "Anti-DDoS baseline installer returned non-zero exit code."
        if [[ -x "${APP_DIR}/scripts/set_antiddos_profile.sh" ]]; then
            log "Applying anti-DDoS profile: normal"
            bash "${APP_DIR}/scripts/set_antiddos_profile.sh" normal "${APP_DIR}" \
                || warn "Could not apply anti-DDoS profile automatically."
        fi
    else
        warn "Anti-DDoS installer script not found at ${APP_DIR}/scripts/install_antiddos_baseline.sh"
    fi
else
    warn "Skipping anti-DDoS baseline (--install-antiddos n)."
fi

log "Fixing permissions..."
chown -R www-data:www-data "${APP_DIR}"
chmod -R 775 "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"

ok "Setup complete."
echo
echo -e "${GREEN}Panel URL:${NC} http://${DOMAIN}"
[[ "${USE_SSL}" == "y" ]] && echo -e "${GREEN}Panel URL:${NC} https://${DOMAIN}"
echo -e "${GREEN}Next:${NC} php artisan root"
if [[ "${INSTALL_WINGS}" == "y" ]]; then
    echo -e "${GREEN}Wings:${NC} binary at /usr/local/bin/wings, service: systemctl status wings"
    echo -e "${GREEN}Node IP hint:${NC} hostname -I | awk '{print \$1}'"
fi
if [[ "${INSTALL_ANTIDDOS}" == "y" ]]; then
    echo -e "${GREEN}Anti-DDoS:${NC} installed (nginx snippet + fail2ban jail)"
fi
echo -e "${GREEN}IDE Connect:${NC} enabled, base URL = ${IDE_BASE_URL}"

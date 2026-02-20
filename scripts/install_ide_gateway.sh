#!/usr/bin/env bash

set -Eeuo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log() { echo -e "${BLUE}[IDE][INFO]${NC} $*"; }
warn() { echo -e "${YELLOW}[IDE][WARN]${NC} $*"; }
ok() { echo -e "${GREEN}[IDE][OK]${NC} $*"; }
fail() { echo -e "${RED}[IDE][ERROR]${NC} $*"; exit 1; }

IDE_DOMAIN=""
PANEL_URL=""
ROOT_API_TOKEN=""
CODE_SERVER_URL="http://127.0.0.1:8080"
NODE_CODE_SERVER_MAP=""
USE_SSL="n"
LETSENCRYPT_EMAIL=""

usage() {
    cat <<'USAGE'
install_ide_gateway.sh

Usage:
  sudo bash scripts/install_ide_gateway.sh [options]

Options:
  --ide-domain <fqdn>      IDE domain, e.g. ide.example.com (required)
  --panel-url <url>        Panel URL, e.g. https://panel.example.com (required)
  --root-api-token <tok>   Root API token for /api/rootapplication (required)
  --code-server-url <url>  Upstream code-server URL (default: http://127.0.0.1:8080)
  --node-map <pairs>       Optional per-node upstream map:
                           "node-fqdn-1=url1,node-id-2=url2"
  --ssl <y|n>              Issue Let's Encrypt cert for IDE domain (default: n)
  --email <email>          Let's Encrypt email (optional)
  --help                   Show this help
USAGE
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --ide-domain) IDE_DOMAIN="${2:-}"; shift 2 ;;
        --panel-url) PANEL_URL="${2:-}"; shift 2 ;;
        --root-api-token) ROOT_API_TOKEN="${2:-}"; shift 2 ;;
        --code-server-url) CODE_SERVER_URL="${2:-}"; shift 2 ;;
        --node-map) NODE_CODE_SERVER_MAP="${2:-}"; shift 2 ;;
        --ssl) USE_SSL="${2:-}"; shift 2 ;;
        --email) LETSENCRYPT_EMAIL="${2:-}"; shift 2 ;;
        --help|-h) usage; exit 0 ;;
        *) fail "Unknown option: $1" ;;
    esac
done

[[ "${EUID}" -eq 0 ]] || fail "This script must run as root."
[[ -n "${IDE_DOMAIN}" ]] || fail "--ide-domain is required."
[[ -n "${PANEL_URL}" ]] || fail "--panel-url is required."
[[ -n "${ROOT_API_TOKEN}" ]] || fail "--root-api-token is required."

if [[ ! "${PANEL_URL}" =~ ^https?:// ]]; then
    fail "--panel-url must start with http:// or https://"
fi
if [[ ! "${CODE_SERVER_URL}" =~ ^https?:// ]]; then
    fail "--code-server-url must start with http:// or https://"
fi
if [[ -n "${NODE_CODE_SERVER_MAP}" ]]; then
    IFS=',' read -ra _pairs <<< "${NODE_CODE_SERVER_MAP}"
    for _pair in "${_pairs[@]}"; do
        _pair="$(echo "${_pair}" | xargs)"
        [[ -z "${_pair}" ]] && continue
        [[ "${_pair}" == *=* ]] || fail "Invalid --node-map pair '${_pair}' (expected key=url)."
        _url="${_pair#*=}"
        [[ "${_url}" =~ ^https?:// ]] || fail "Invalid --node-map URL '${_url}' in pair '${_pair}'."
    done
fi

if ! command -v node >/dev/null 2>&1; then
    log "Node.js not found, installing nodejs + npm from apt..."
    apt-get update -y -q
    apt-get install -y -q nodejs npm
fi

APP_DIR="/opt/hextyl-ide-gateway"
mkdir -p "${APP_DIR}"
cd "${APP_DIR}"

if [[ ! -f package.json ]]; then
    npm init -y >/dev/null 2>&1
fi

log "Installing gateway dependencies..."
npm install --silent --no-progress express axios cookie-parser http-proxy-middleware

cat > "${APP_DIR}/server.js" <<'JS'
const express = require('express');
const axios = require('axios');
const cookieParser = require('cookie-parser');
const crypto = require('crypto');
const { createProxyMiddleware } = require('http-proxy-middleware');

const app = express();
app.use(cookieParser());

const PANEL_URL = process.env.PANEL_URL || '';
const ROOT_API_TOKEN = process.env.ROOT_API_TOKEN || '';
const COOKIE_SECURE = process.env.COOKIE_SECURE !== 'false';
const CODE_SERVER_URL = process.env.CODE_SERVER_URL || 'http://127.0.0.1:8080';
const NODE_CODE_SERVER_MAP_RAW = process.env.NODE_CODE_SERVER_MAP || '';

if (!PANEL_URL || !ROOT_API_TOKEN) {
    throw new Error('PANEL_URL and ROOT_API_TOKEN are required');
}

const sessions = new Map();
const nodeMap = new Map();

for (const rawPair of NODE_CODE_SERVER_MAP_RAW.split(',')) {
    const pair = String(rawPair || '').trim();
    if (!pair) continue;
    const index = pair.indexOf('=');
    if (index <= 0) continue;
    const key = pair.slice(0, index).trim();
    const value = pair.slice(index + 1).trim();
    if (key && value) {
        nodeMap.set(key.toLowerCase(), value);
    }
}

function makeSid() {
    return crypto.randomBytes(24).toString('hex');
}

function normalizeToken(value) {
    return String(value || '').trim();
}

function resolveTarget(session) {
    const nodeFqdn = String(session.node_fqdn || '').trim().toLowerCase();
    const nodeId = String(session.node_id || '').trim().toLowerCase();
    if (nodeFqdn && nodeMap.has(nodeFqdn)) {
        return nodeMap.get(nodeFqdn);
    }
    if (nodeId && nodeMap.has(nodeId)) {
        return nodeMap.get(nodeId);
    }
    return CODE_SERVER_URL;
}

app.get('/health', (_req, res) => res.json({ success: true }));

app.get('/session/:serverIdentifier', async (req, res) => {
    const token = normalizeToken(req.query.token);
    const serverIdentifier = String(req.params.serverIdentifier || '').trim();

    if (!token || !serverIdentifier) {
        return res.status(400).send('Missing token or server identifier');
    }

    try {
        const response = await axios.post(
            `${PANEL_URL}/api/rootapplication/ide/sessions/validate`,
            {
                token,
                consume: true,
                server_identifier: serverIdentifier,
            },
            {
                headers: {
                    Authorization: `Bearer ${ROOT_API_TOKEN}`,
                    'Content-Type': 'application/json',
                },
                timeout: 10000,
            }
        );

        if (!response.data || response.data.success !== true || !response.data.session) {
            return res.status(403).send('Token validation failed');
        }

        const session = response.data.session;
        const sid = makeSid();
        const expiresAt = Date.parse(session.expires_at || '');

        if (!Number.isFinite(expiresAt)) {
            return res.status(403).send('Invalid token expiry');
        }

        sessions.set(sid, {
            expiresAt,
            userId: session.user_id,
            serverIdentifier: session.server_identifier,
            nodeId: session.node_id,
            nodeFqdn: session.node_fqdn,
            target: resolveTarget(session),
            terminalAllowed: !!session.terminal_allowed,
            extensionsAllowed: !!session.extensions_allowed,
        });

        res.cookie('ide_sid', sid, {
            httpOnly: true,
            secure: COOKIE_SECURE,
            sameSite: 'lax',
            path: '/',
            expires: new Date(expiresAt),
        });

        return res.redirect('/');
    } catch (_error) {
        return res.status(403).send('Token validation failed');
    }
});

app.use((req, res, next) => {
    if (req.path === '/health' || req.path.startsWith('/session/')) {
        return next();
    }

    const sid = req.cookies.ide_sid;
    if (!sid || !sessions.has(sid)) {
        return res.status(401).send('Unauthorized');
    }

    const session = sessions.get(sid);
    if (!session || Date.now() > session.expiresAt) {
        sessions.delete(sid);
        return res.status(401).send('Session expired');
    }

    return next();
});

app.use('/', (req, res, next) => {
    const sid = req.cookies.ide_sid;
    const session = sid ? sessions.get(sid) : null;
    const target = (session && session.target) ? session.target : CODE_SERVER_URL;

    return createProxyMiddleware({
        target,
        changeOrigin: true,
        ws: true,
    })(req, res, next);
});

const port = Number(process.env.PORT || 3006);
app.listen(port, '127.0.0.1', () => {
    // eslint-disable-next-line no-console
    console.log(`hextyl-ide-gateway listening on 127.0.0.1:${port}`);
});
JS

COOKIE_SECURE="false"
if [[ "${USE_SSL}" == "y" ]]; then
    COOKIE_SECURE="true"
fi

cat > /etc/systemd/system/hextyl-ide-gateway.service <<SERVICE
[Unit]
Description=HexTyl IDE Gateway
After=network.target

[Service]
Type=simple
WorkingDirectory=${APP_DIR}
Environment=PANEL_URL=${PANEL_URL}
Environment=ROOT_API_TOKEN=${ROOT_API_TOKEN}
Environment=CODE_SERVER_URL=${CODE_SERVER_URL}
Environment=NODE_CODE_SERVER_MAP=${NODE_CODE_SERVER_MAP}
Environment=COOKIE_SECURE=${COOKIE_SECURE}
Environment=PORT=3006
ExecStart=/usr/bin/node ${APP_DIR}/server.js
Restart=always
RestartSec=3
User=www-data
Group=www-data

[Install]
WantedBy=multi-user.target
SERVICE

chown -R www-data:www-data "${APP_DIR}"

cat > "/etc/nginx/sites-available/${IDE_DOMAIN}.conf" <<NGINX
server {
    listen 80;
    server_name ${IDE_DOMAIN};

    location / {
        proxy_pass http://127.0.0.1:3006;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$host;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}
NGINX

ln -sf "/etc/nginx/sites-available/${IDE_DOMAIN}.conf" "/etc/nginx/sites-enabled/${IDE_DOMAIN}.conf"

if [[ "${USE_SSL}" == "y" ]]; then
    apt-get install -y -q certbot python3-certbot-nginx

    if [[ -n "${LETSENCRYPT_EMAIL}" ]]; then
        certbot --nginx -d "${IDE_DOMAIN}" --non-interactive --agree-tos -m "${LETSENCRYPT_EMAIL}" --redirect
    else
        certbot --nginx -d "${IDE_DOMAIN}" --non-interactive --agree-tos --register-unsafely-without-email --redirect
    fi
fi

nginx -t
systemctl reload nginx
systemctl daemon-reload
systemctl enable --now hextyl-ide-gateway

ok "IDE gateway installed: http://127.0.0.1:3006"
ok "Nginx site: /etc/nginx/sites-available/${IDE_DOMAIN}.conf"
ok "Service: systemctl status hextyl-ide-gateway"

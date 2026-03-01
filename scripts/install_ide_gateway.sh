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

extract_host_from_url_or_domain() {
    local input="${1:-}"
    local value host
    value="$(echo "${input}" | xargs)"
    [[ -n "${value}" ]] || { echo ""; return 0; }

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

IDE_DOMAIN=""
PANEL_URL=""
ROOT_API_TOKEN=""
AUTO_PTLR="y"
PANEL_APP_DIR=""
CODE_SERVER_URL="http://127.0.0.1:18080"
NODE_CODE_SERVER_MAP=""
AUTO_NODE_FQDN="y"
NODE_SCHEME="http"
NODE_SCHEME_EXPLICIT="n"
NODE_PORT="18080"
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
  --root-api-token <tok>   Root API token for /api/rootapplication (optional)
  --auto-ptlr <y|n>        Auto-generate PTLR token if missing (default: y)
  --panel-app-dir <path>   Panel app dir for auto-PTLR (optional)
  --code-server-url <url>  Upstream code-server URL (default: http://127.0.0.1:18080)
  --node-map <pairs>       Optional per-node upstream map:
                           "node-fqdn-1=url1,node-id-2=url2"
  --auto-node-fqdn <y|n>   Auto route by node_fqdn from token (default: y)
  --node-scheme <http|https> Scheme for auto node routing (default: http)
  --node-port <port>       Port for auto node routing (default: 18080, reserved: 8080/2022)
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
        --auto-ptlr) AUTO_PTLR="${2:-}"; shift 2 ;;
        --panel-app-dir) PANEL_APP_DIR="${2:-}"; shift 2 ;;
        --code-server-url) CODE_SERVER_URL="${2:-}"; shift 2 ;;
        --node-map) NODE_CODE_SERVER_MAP="${2:-}"; shift 2 ;;
        --auto-node-fqdn) AUTO_NODE_FQDN="${2:-}"; shift 2 ;;
        --node-scheme) NODE_SCHEME="${2:-}"; NODE_SCHEME_EXPLICIT="y"; shift 2 ;;
        --node-port) NODE_PORT="${2:-}"; shift 2 ;;
        --ssl) USE_SSL="${2:-}"; shift 2 ;;
        --email) LETSENCRYPT_EMAIL="${2:-}"; shift 2 ;;
        --help|-h) usage; exit 0 ;;
        *) fail "Unknown option: $1" ;;
    esac
done

[[ "${EUID}" -eq 0 ]] || fail "This script must run as root."
[[ -n "${IDE_DOMAIN}" ]] || fail "--ide-domain is required."
[[ -n "${PANEL_URL}" ]] || fail "--panel-url is required."
if [[ "${AUTO_PTLR}" != "y" && "${AUTO_PTLR}" != "n" ]]; then
    fail "--auto-ptlr must be y or n"
fi

generate_root_api_token() {
    local app_dir="$1"
    [[ -n "${app_dir}" ]] || return 1
    [[ -f "${app_dir}/vendor/autoload.php" && -f "${app_dir}/artisan" ]] || return 1
    command -v php >/dev/null 2>&1 || return 1

    (
        cd "${app_dir}"
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
    $user = User::query()->where('admin', 1)->orderBy('id')->first();
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
    'memo' => 'ide-gateway:auto-ptlr',
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

if [[ -z "${ROOT_API_TOKEN}" ]]; then
    if [[ "${AUTO_PTLR}" != "y" ]]; then
        fail "--root-api-token is required (or use --auto-ptlr y with local panel app dir)."
    fi

    if [[ -z "${PANEL_APP_DIR}" ]]; then
        for candidate in /hextyl /var/www/pterodactyl /var/www/panel /var/www/html/pterodactyl /opt/pterodactyl /srv/pterodactyl; do
            if [[ -f "${candidate}/artisan" && -f "${candidate}/vendor/autoload.php" ]]; then
                PANEL_APP_DIR="${candidate}"
                break
            fi
        done
    fi

    [[ -n "${PANEL_APP_DIR}" ]] || fail "Auto-PTLR failed: panel app dir not found. Pass --panel-app-dir or --root-api-token."

    log "Auto-generating PTLR token from panel app at ${PANEL_APP_DIR}..."
    ROOT_API_TOKEN="$(generate_root_api_token "${PANEL_APP_DIR}" 2>/tmp/hextyl-ide-token.err || true)"
    if [[ -z "${ROOT_API_TOKEN}" ]]; then
        fail "Auto-PTLR failed. Provide --root-api-token."
    fi

    install -d -m 700 /root/.hextyl
    printf '%s\n' "${ROOT_API_TOKEN}" > /root/.hextyl/ide_root_api_token
    chmod 600 /root/.hextyl/ide_root_api_token
    ok "PTLR token saved at /root/.hextyl/ide_root_api_token"
fi

IDE_DOMAIN="$(echo "${IDE_DOMAIN}" | xargs)"
IDE_DOMAIN_HOST="$(extract_host_from_url_or_domain "${IDE_DOMAIN}")"
[[ -n "${IDE_DOMAIN_HOST}" ]] || fail "--ide-domain is invalid."
if [[ ! "${IDE_DOMAIN_HOST}" =~ ^[a-z0-9.-]+$ ]]; then
    fail "--ide-domain resolved host '${IDE_DOMAIN_HOST}' has unsupported characters."
fi
IDE_DOMAIN="${IDE_DOMAIN_HOST}"
PANEL_URL="${PANEL_URL%/}"

if [[ ! "${PANEL_URL}" =~ ^https?:// ]]; then
    fail "--panel-url must start with http:// or https://"
fi
if [[ ! "${CODE_SERVER_URL}" =~ ^https?:// ]]; then
    fail "--code-server-url must start with http:// or https://"
fi
if [[ "${CODE_SERVER_URL}" =~ :8080([/?#]|$) || "${CODE_SERVER_URL}" =~ :2022([/?#]|$) ]]; then
    fail "--code-server-url cannot use reserved ports 8080 or 2022."
fi
if [[ "${AUTO_NODE_FQDN}" != "y" && "${AUTO_NODE_FQDN}" != "n" ]]; then
    fail "--auto-node-fqdn must be y or n"
fi
if [[ "${USE_SSL}" == "y" && "${NODE_SCHEME_EXPLICIT}" != "y" ]]; then
    NODE_SCHEME="https"
fi
if [[ "${NODE_SCHEME}" != "http" && "${NODE_SCHEME}" != "https" ]]; then
    fail "--node-scheme must be http or https"
fi
if ! [[ "${NODE_PORT}" =~ ^[0-9]+$ ]] || (( NODE_PORT < 1 || NODE_PORT > 65535 )); then
    fail "--node-port must be an integer between 1 and 65535"
fi
if [[ "${NODE_PORT}" == "8080" || "${NODE_PORT}" == "2022" ]]; then
    fail "--node-port ${NODE_PORT} is reserved by HexWings protocol flow. Use a different port, e.g. 18080."
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
NODE_BIN="$(command -v node || true)"
[[ -n "${NODE_BIN}" ]] || fail "Node.js binary was not found after installation."

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
const { execFile } = require('child_process');
const cookieParser = require('cookie-parser');
const crypto = require('crypto');
const fs = require('fs');
const net = require('net');
const path = require('path');
const { promisify } = require('util');
const { createProxyMiddleware } = require('http-proxy-middleware');

const app = express();
app.use(cookieParser());
const execFileAsync = promisify(execFile);

const PANEL_URL = process.env.PANEL_URL || '';
const ROOT_API_TOKEN = process.env.ROOT_API_TOKEN || '';
const COOKIE_SECURE = process.env.COOKIE_SECURE !== 'false';
const COOKIE_SAMESITE = (process.env.COOKIE_SAMESITE || 'lax').toLowerCase();
const CODE_SERVER_URL = process.env.CODE_SERVER_URL || 'http://127.0.0.1:18080';
const NODE_CODE_SERVER_MAP_RAW = process.env.NODE_CODE_SERVER_MAP || '';
const AUTO_NODE_FQDN = process.env.AUTO_NODE_FQDN === 'true';
const NODE_SCHEME = process.env.NODE_SCHEME || 'http';
const NODE_PORT = String(process.env.NODE_PORT || '18080');
const VOLUME_ROOT = normalizeVolumeRoot(process.env.VOLUME_ROOT || '/var/lib/pterodactyl/volumes');
const ENFORCE_VOLUME_ROOT = process.env.ENFORCE_VOLUME_ROOT !== 'false';
const DEFAULT_TO_SERVER_ROOT = process.env.DEFAULT_TO_SERVER_ROOT !== 'false';
const IDE_MODE = (process.env.IDE_MODE || 'direct').toLowerCase();
const IDE_TRUSTED_ORIGINS = process.env.IDE_TRUSTED_ORIGINS || 'ide.srvzium.web.id';
const IDE_CONTAINER_IMAGE = process.env.IDE_CONTAINER_IMAGE || 'ghcr.io/coder/code-server:4.109.2';
const IDE_CONTAINER_PORT_RANGE = process.env.IDE_CONTAINER_PORT_RANGE || '19000-19999';
const IDE_CONTAINER_IDLE_SECONDS = Math.max(60, Number(process.env.IDE_CONTAINER_IDLE_SECONDS || 900));
const IDE_CONTAINER_MEMORY = process.env.IDE_CONTAINER_MEMORY || '';
const IDE_CONTAINER_CPU = process.env.IDE_CONTAINER_CPU || '';
const IDE_CONTAINER_PIDS = process.env.IDE_CONTAINER_PIDS || '256';
const IDE_CONTAINER_USER = process.env.IDE_CONTAINER_USER || '999:987';
const IDE_CONTAINER_NETWORK = process.env.IDE_CONTAINER_NETWORK || '';
const IDE_CONTAINER_PERSIST = (process.env.IDE_CONTAINER_PERSIST || 'none').toLowerCase();
const IDE_CONTAINER_DATA_ROOT = process.env.IDE_CONTAINER_DATA_ROOT || '/var/lib/pterodactyl/ide-data';
const IDE_CONTAINER_TMPFS = process.env.IDE_CONTAINER_TMPFS || '64m';
const IDE_CONTAINER_HOME_TMPFS = process.env.IDE_CONTAINER_HOME_TMPFS || IDE_CONTAINER_TMPFS;
const IDE_CONTAINER_WORKSPACE = process.env.IDE_CONTAINER_WORKSPACE || '/workspace';
const IDE_CONTAINER_EXTRA_ARGS = process.env.IDE_CONTAINER_EXTRA_ARGS || '';
const IDE_CONTAINER_CHECK_TTL_MS = Math.max(1000, Number(process.env.IDE_CONTAINER_CHECK_TTL_MS || 5000));
const IDE_CONTAINER_STARTUP_TIMEOUT_MS = Math.max(2000, Number(process.env.IDE_CONTAINER_STARTUP_TIMEOUT_MS || 8000));
const IDE_CONTAINER_STARTUP_INTERVAL_MS = Math.max(100, Number(process.env.IDE_CONTAINER_STARTUP_INTERVAL_MS || 250));

if (!PANEL_URL || !ROOT_API_TOKEN) {
    throw new Error('PANEL_URL and ROOT_API_TOKEN are required');
}

const sessions = new Map();
const nodeMap = new Map();
const containerByServer = new Map();
const containerInFlight = new Map();

function normalizeVolumeRoot(input) {
    const value = String(input || '').trim();
    if (!value) return '/var/lib/pterodactyl/volumes';
    const normalized = path.posix.normalize(value);
    if (!normalized.startsWith('/')) return '/var/lib/pterodactyl/volumes';
    return normalized.replace(/\/+$/, '') || '/';
}

function parseCookieHeader(rawHeader) {
    const header = String(rawHeader || '');
    if (!header) return {};
    const out = {};
    for (const part of header.split(';')) {
        const index = part.indexOf('=');
        if (index <= 0) continue;
        const key = part.slice(0, index).trim();
        const value = part.slice(index + 1).trim();
        if (!key) continue;
        try {
            out[key] = decodeURIComponent(value);
        } catch {
            out[key] = value;
        }
    }
    return out;
}

function getSessionFromRequest(req) {
    if (req && req.ideSession) return req.ideSession;
    const cookies = (req && req.cookies) ? req.cookies : parseCookieHeader(req && req.headers ? req.headers.cookie : '');
    const sid = cookies && cookies.ide_sid ? String(cookies.ide_sid) : '';
    if (!sid) return null;
    const session = sessions.get(sid);
    if (!session) return null;
    if (Date.now() > session.expiresAt) {
        sessions.delete(sid);
        return null;
    }
    if (req) {
        req.ideSession = session;
    }
    return session;
}

function normalizePathValue(value) {
    const raw = String(value || '').trim();
    if (!raw) return '';
    const normalized = path.posix.normalize(raw);
    if (!normalized.startsWith('/')) return '';
    return normalized;
}

function serverRootForSession(session) {
    if (!session || !session.serverUuid) return VOLUME_ROOT;
    return `${VOLUME_ROOT}/${session.serverUuid}`;
}

function sanitizeFolder(value, session) {
    const requested = normalizePathValue(value);
    const root = serverRootForSession(session);
    if (!ENFORCE_VOLUME_ROOT) {
        return requested || root;
    }
    if (!requested) return root;
    if (requested === root || requested.startsWith(`${root}/`)) {
        return requested;
    }
    return root;
}

function mapContainerFolder(value, session) {
    const requested = normalizePathValue(value);
    if (!requested) return IDE_CONTAINER_WORKSPACE;
    if (requested === IDE_CONTAINER_WORKSPACE || requested.startsWith(`${IDE_CONTAINER_WORKSPACE}/`)) {
        return requested;
    }
    const root = serverRootForSession(session);
    if (requested === root) {
        return IDE_CONTAINER_WORKSPACE;
    }
    if (requested.startsWith(`${root}/`)) {
        return `${IDE_CONTAINER_WORKSPACE}${requested.slice(root.length)}`;
    }
    return IDE_CONTAINER_WORKSPACE;
}

function resolveFolder(value, session) {
    const requested = normalizePathValue(value);
    if (!DEFAULT_TO_SERVER_ROOT && !requested) return '';
    if (IDE_MODE === 'container') {
        const base = requested || serverRootForSession(session);
        return mapContainerFolder(base, session);
    }
    return DEFAULT_TO_SERVER_ROOT ? sanitizeFolder(requested, session) : requested;
}

function buildRedirectUrl(folder) {
    if (!folder) return '/';
    const params = new URLSearchParams();
    params.set('folder', folder);
    return `/?${params.toString()}`;
}

function parseUserSpec(raw) {
    const value = String(raw || '').trim();
    if (!value) return { uid: 1000, gid: 1000 };
    const [uidRaw, gidRaw] = value.split(':');
    const uid = Number(uidRaw);
    const gid = Number(gidRaw || uidRaw);
    return {
        uid: Number.isFinite(uid) ? uid : 1000,
        gid: Number.isFinite(gid) ? gid : 1000,
    };
}

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

async function waitForPort(port, timeoutMs) {
    const deadline = Date.now() + timeoutMs;
    while (Date.now() < deadline) {
        try {
            await new Promise((resolve, reject) => {
                const socket = net.createConnection({ host: '127.0.0.1', port }, () => {
                    socket.end();
                    resolve();
                });
                socket.setTimeout(1000, () => {
                    socket.destroy(new Error('timeout'));
                });
                socket.on('error', reject);
            });
            return;
        } catch (error) {
            await sleep(IDE_CONTAINER_STARTUP_INTERVAL_MS);
        }
    }
    throw new Error(`IDE container did not start listening on port ${port}.`);
}

function parsePortRange(raw) {
    const value = String(raw || '').trim();
    if (!value) return { start: 19000, end: 19999 };
    const [startRaw, endRaw] = value.split('-').map((part) => part.trim());
    const start = Number(startRaw);
    const end = Number(endRaw || startRaw);
    if (!Number.isFinite(start) || !Number.isFinite(end) || start <= 0 || end <= 0 || end < start) {
        return { start: 19000, end: 19999 };
    }
    return { start, end };
}

function pickPort(range) {
    const span = range.end - range.start + 1;
    return range.start + Math.floor(Math.random() * span);
}

function parseExtraArgs(raw) {
    const value = String(raw || '').trim();
    if (!value) return [];
    return value.split(/\s+/).filter(Boolean);
}

async function docker(args, options = {}) {
    const result = await execFileAsync('docker', args, { timeout: 15000, maxBuffer: 1024 * 1024, ...options });
    return {
        stdout: String(result.stdout || '').trim(),
        stderr: String(result.stderr || '').trim(),
    };
}

async function inspectContainer(containerId) {
    const output = await docker(['inspect', containerId]);
    const data = JSON.parse(output.stdout || '[]');
    if (!Array.isArray(data) || !data[0]) return null;
    const info = data[0];
    const portInfo = info?.NetworkSettings?.Ports?.['8080/tcp'];
    const hostPort = portInfo && portInfo[0] ? Number(portInfo[0].HostPort) : null;
    return {
        id: info.Id,
        running: !!info?.State?.Running,
        hostPort: Number.isFinite(hostPort) ? hostPort : null,
    };
}

async function inspectContainerByName(name) {
    if (!name) return null;
    try {
        return await inspectContainer(name);
    } catch (error) {
        return null;
    }
}

async function findRunningContainer(serverUuid) {
    try {
        const output = await docker(['ps', '-q', '-f', `label=hextyl.ide.server_uuid=${serverUuid}`]);
        const id = output.stdout.split('\n').map((line) => line.trim()).find(Boolean);
        if (!id) return null;
        const info = await inspectContainer(id);
        if (!info || !info.running || !info.hostPort) return null;
        return { id: info.id, port: info.hostPort };
    } catch (error) {
        return null;
    }
}

function buildContainerName(serverUuid) {
    const suffix = String(serverUuid || '').replace(/[^a-z0-9]/gi, '').slice(0, 12).toLowerCase();
    return suffix ? `hextyl-ide-${suffix}` : `hextyl-ide-${Date.now()}`;
}

function ensureDataDir(directory) {
    if (!directory) return null;
    fs.mkdirSync(directory, { recursive: true, mode: 0o2775 });
    try {
        fs.chmodSync(directory, 0o2775);
    } catch (error) {
        // best-effort to keep group-writable
    }
    return directory;
}

async function startContainer(session) {
    const serverUuid = String(session.serverUuid || '').trim();
    if (!serverUuid) {
        throw new Error('Missing server UUID for IDE container.');
    }
    const serverRoot = serverRootForSession(session);
    if (!fs.existsSync(serverRoot)) {
        throw new Error(`Server volume not found at ${serverRoot}`);
    }

    const containerName = buildContainerName(serverUuid);
    const existing = await inspectContainerByName(containerName);
    if (existing && existing.running && existing.hostPort) {
        return { id: existing.id, port: existing.hostPort };
    }
    if (existing && !existing.running) {
        try {
            await docker(['rm', '-f', containerName]);
        } catch (error) {
            // ignore cleanup failures
        }
    }

    const range = parsePortRange(IDE_CONTAINER_PORT_RANGE);
    const portAttempts = Math.min(30, Math.max(5, range.end - range.start + 1));
    const baseArgs = ['run', '-d', '--rm'];
    const userSpec = parseUserSpec(IDE_CONTAINER_USER);
    let lastError = null;

    for (let attempt = 0; attempt < portAttempts; attempt += 1) {
        const hostPort = pickPort(range);
        const args = [...baseArgs];

        args.push('--name', containerName);
        args.push('--label', `hextyl.ide.server_uuid=${serverUuid}`);
        if (session.serverIdentifier) {
            args.push('--label', `hextyl.ide.server_identifier=${session.serverIdentifier}`);
        }
        if (session.nodeId) {
            args.push('--label', `hextyl.ide.node_id=${session.nodeId}`);
        }
        if (session.userId) {
            args.push('--label', `hextyl.ide.user_id=${session.userId}`);
        }

        args.push('--cap-drop=ALL');
        args.push('--security-opt', 'no-new-privileges:true');
        args.push('--pids-limit', IDE_CONTAINER_PIDS);
        if (IDE_CONTAINER_MEMORY) {
            args.push('--memory', IDE_CONTAINER_MEMORY);
            args.push('--memory-swap', IDE_CONTAINER_MEMORY);
        }
        if (IDE_CONTAINER_CPU) {
            args.push('--cpus', IDE_CONTAINER_CPU);
        }
        if (IDE_CONTAINER_NETWORK) {
            args.push('--network', IDE_CONTAINER_NETWORK);
        }

        args.push('--user', IDE_CONTAINER_USER);
        args.push('--entrypoint', '/usr/bin/code-server');
        args.push('-e', 'HOME=/home/coder');
        args.push('-e', 'XDG_CONFIG_HOME=/home/coder/.config');
        args.push('-e', 'XDG_DATA_HOME=/home/coder/.local/share');
        args.push('-e', 'XDG_CACHE_HOME=/home/coder/.cache');
        args.push('--tmpfs', `/home/coder:rw,uid=${userSpec.uid},gid=${userSpec.gid},size=${IDE_CONTAINER_HOME_TMPFS}`);
        args.push('--tmpfs', `/tmp:rw,size=${IDE_CONTAINER_TMPFS}`);

        if (IDE_CONTAINER_PERSIST === 'server' || IDE_CONTAINER_PERSIST === 'user') {
            const persistKey = IDE_CONTAINER_PERSIST === 'user'
                ? `user-${String(session.userId || 'unknown')}`
                : serverUuid;
            const dataDir = ensureDataDir(path.join(IDE_CONTAINER_DATA_ROOT, persistKey));
            if (dataDir) {
                args.push('-v', `${dataDir}:/home/coder/.local/share/code-server:rw`);
            }
        } else {
            args.push('--tmpfs', '/home/coder/.local/share/code-server:rw,size=128m');
        }

        args.push('-v', `${serverRoot}:${IDE_CONTAINER_WORKSPACE}:rw`);
        args.push('-p', `127.0.0.1:${hostPort}:8080`);

        args.push(IDE_CONTAINER_IMAGE);
        args.push(
            '--bind-addr',
            '0.0.0.0:8080',
            '--auth',
            'none',
            '--disable-telemetry',
            '--disable-update-check',
            '--disable-proxy',
            '--trusted-origins',
            IDE_TRUSTED_ORIGINS,
            '--idle-timeout-seconds',
            String(IDE_CONTAINER_IDLE_SECONDS),
            IDE_CONTAINER_WORKSPACE
        );

        const extraArgs = parseExtraArgs(IDE_CONTAINER_EXTRA_ARGS);
        if (extraArgs.length) {
            args.push(...extraArgs);
        }

        try {
            const output = await docker(args);
            const containerId = output.stdout.split('\n').map((line) => line.trim()).find(Boolean);
            if (!containerId) {
                const detail = output.stderr ? `: ${output.stderr}` : '';
                throw new Error(`Failed to start IDE container${detail}`);
            }
            try {
                await waitForPort(hostPort, IDE_CONTAINER_STARTUP_TIMEOUT_MS);
            } catch (error) {
                try {
                    await docker(['rm', '-f', containerId]);
                } catch (cleanupError) {
                    // ignore cleanup failures
                }
                throw error;
            }
            return { id: containerId, port: hostPort };
        } catch (error) {
            lastError = error;
            continue;
        }
    }

    // Fallback: let Docker assign a random host port.
    try {
        const containerName = buildContainerName(serverUuid);
        const args = [...baseArgs];

        args.push('--name', containerName);
        args.push('--label', `hextyl.ide.server_uuid=${serverUuid}`);
        if (session.serverIdentifier) {
            args.push('--label', `hextyl.ide.server_identifier=${session.serverIdentifier}`);
        }
        if (session.nodeId) {
            args.push('--label', `hextyl.ide.node_id=${session.nodeId}`);
        }
        if (session.userId) {
            args.push('--label', `hextyl.ide.user_id=${session.userId}`);
        }

        args.push('--cap-drop=ALL');
        args.push('--security-opt', 'no-new-privileges:true');
        args.push('--pids-limit', IDE_CONTAINER_PIDS);
        if (IDE_CONTAINER_MEMORY) {
            args.push('--memory', IDE_CONTAINER_MEMORY);
            args.push('--memory-swap', IDE_CONTAINER_MEMORY);
        }
        if (IDE_CONTAINER_CPU) {
            args.push('--cpus', IDE_CONTAINER_CPU);
        }
        if (IDE_CONTAINER_NETWORK) {
            args.push('--network', IDE_CONTAINER_NETWORK);
        }

        args.push('--user', IDE_CONTAINER_USER);
        args.push('--entrypoint', '/usr/bin/code-server');
        args.push('-e', 'HOME=/home/coder');
        args.push('-e', 'XDG_CONFIG_HOME=/home/coder/.config');
        args.push('-e', 'XDG_DATA_HOME=/home/coder/.local/share');
        args.push('-e', 'XDG_CACHE_HOME=/home/coder/.cache');
        args.push('--tmpfs', `/home/coder:rw,uid=${userSpec.uid},gid=${userSpec.gid},size=${IDE_CONTAINER_HOME_TMPFS}`);
        args.push('--tmpfs', `/tmp:rw,size=${IDE_CONTAINER_TMPFS}`);

        if (IDE_CONTAINER_PERSIST === 'server' || IDE_CONTAINER_PERSIST === 'user') {
            const persistKey = IDE_CONTAINER_PERSIST === 'user'
                ? `user-${String(session.userId || 'unknown')}`
                : serverUuid;
            const dataDir = ensureDataDir(path.join(IDE_CONTAINER_DATA_ROOT, persistKey));
            if (dataDir) {
                args.push('-v', `${dataDir}:/home/coder/.local/share/code-server:rw`);
            }
        } else {
            args.push('--tmpfs', '/home/coder/.local/share/code-server:rw,size=128m');
        }

        args.push('-v', `${serverRoot}:${IDE_CONTAINER_WORKSPACE}:rw`);
        args.push('-p', '127.0.0.1::8080');

        args.push(IDE_CONTAINER_IMAGE);
        args.push(
            '--bind-addr',
            '0.0.0.0:8080',
            '--auth',
            'none',
            '--disable-telemetry',
            '--disable-update-check',
            '--disable-proxy',
            '--trusted-origins',
            IDE_TRUSTED_ORIGINS,
            '--idle-timeout-seconds',
            String(IDE_CONTAINER_IDLE_SECONDS),
            IDE_CONTAINER_WORKSPACE
        );

        const extraArgs = parseExtraArgs(IDE_CONTAINER_EXTRA_ARGS);
        if (extraArgs.length) {
            args.push(...extraArgs);
        }

        const output = await docker(args);
        const containerId = output.stdout.split('\n').map((line) => line.trim()).find(Boolean);
        if (!containerId) {
            const detail = output.stderr ? `: ${output.stderr}` : '';
            throw new Error(`Failed to start IDE container${detail}`);
        }
        const info = await inspectContainer(containerId);
        if (!info || !info.hostPort) {
            throw new Error('Unable to determine IDE container port.');
        }
        try {
            await waitForPort(info.hostPort, IDE_CONTAINER_STARTUP_TIMEOUT_MS);
        } catch (error) {
            try {
                await docker(['rm', '-f', containerId]);
            } catch (cleanupError) {
                // ignore cleanup failures
            }
            throw error;
        }
        return { id: containerId, port: info.hostPort };
    } catch (error) {
        const detail = lastError ? ` Last error: ${lastError.message}` : '';
        throw new Error(`Unable to allocate IDE container port.${detail}`);
    }
}

async function ensureContainer(session) {
    if (IDE_MODE !== 'container') {
        return resolveTarget(session);
    }

    const serverUuid = String(session.serverUuid || '').trim();
    if (!serverUuid) {
        return CODE_SERVER_URL;
    }

    const cached = containerByServer.get(serverUuid);
    const now = Date.now();
    if (cached && (now - cached.lastCheck) < IDE_CONTAINER_CHECK_TTL_MS) {
        return `http://127.0.0.1:${cached.port}`;
    }

    if (containerInFlight.has(serverUuid)) {
        return containerInFlight.get(serverUuid);
    }

    const promise = (async () => {
        const running = await findRunningContainer(serverUuid);
        if (running) {
            const info = { id: running.id, port: running.port, lastCheck: Date.now() };
            containerByServer.set(serverUuid, info);
            return `http://127.0.0.1:${running.port}`;
        }

        const created = await startContainer(session);
        const info = { id: created.id, port: created.port, lastCheck: Date.now() };
        containerByServer.set(serverUuid, info);
        return `http://127.0.0.1:${created.port}`;
    })();

    containerInFlight.set(serverUuid, promise);
    try {
        return await promise;
    } finally {
        containerInFlight.delete(serverUuid);
    }
}

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
    if (AUTO_NODE_FQDN && nodeFqdn) {
        return `${NODE_SCHEME}://${nodeFqdn}:${NODE_PORT}`;
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
                consume: false,
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

        const serverUuid = String(session.server_uuid || '').trim();
        const sessionData = {
            expiresAt,
            userId: session.user_id,
            serverIdentifier: session.server_identifier,
            serverUuid,
            nodeId: session.node_id,
            nodeFqdn: session.node_fqdn,
            target: resolveTarget(session),
            terminalAllowed: !!session.terminal_allowed,
            extensionsAllowed: !!session.extensions_allowed,
        };
        sessions.set(sid, sessionData);

        res.cookie('ide_sid', sid, {
            httpOnly: true,
            secure: COOKIE_SECURE,
            sameSite: COOKIE_SAMESITE,
            path: '/',
            expires: new Date(expiresAt),
        });

        const folder = resolveFolder(req.query.folder, sessionData);
        if (IDE_MODE === 'container') {
            try {
                sessionData.target = await ensureContainer(sessionData);
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error('Failed to start IDE container', error);
                return res.status(503).send('IDE container unavailable');
            }
        }
        return res.redirect(buildRedirectUrl(folder));
    } catch (error) {
        const status = Number(error?.response?.status || 0);
        const detail = String(error?.response?.data?.message || '').trim();
        if (status === 422 && detail) {
            return res.status(403).send(`Token validation failed: ${detail}`);
        }
        return res.status(403).send('Token validation failed');
    }
});

app.use((req, res, next) => {
    if (req.path === '/health' || req.path === '/favicon.ico' || req.path.startsWith('/session/')) {
        return next();
    }

    const session = getSessionFromRequest(req);
    if (!session) {
        return res.status(401).send('Unauthorized');
    }
    return next();
});

app.use(async (req, res, next) => {
    if (req.path === '/health' || req.path === '/favicon.ico' || req.path.startsWith('/session/')) {
        return next();
    }
    const session = getSessionFromRequest(req);
    if (!session) return next();
    if (IDE_MODE !== 'container') return next();
    try {
        const target = await ensureContainer(session);
        session.target = target;
        req.ideTarget = target;
        return next();
    } catch (error) {
        // eslint-disable-next-line no-console
        console.error('IDE container unavailable', error);
        return res.status(503).send('IDE container unavailable');
    }
});

app.use((req, res, next) => {
    if (req.path === '/favicon.ico') {
        return res.status(204).end();
    }

    const session = getSessionFromRequest(req);
    if (!session) return next();
    if (req.method !== 'GET' || req.path !== '/') return next();
    if (req.query && req.query.workspace) return next();
    const requestedRaw = Array.isArray(req.query?.folder) ? req.query.folder[0] : req.query?.folder;
    const requested = normalizePathValue(requestedRaw);
    const effective = resolveFolder(requestedRaw, session);
    if (requested !== effective) {
        return res.redirect(buildRedirectUrl(effective));
    }
    return next();
});

const proxy = createProxyMiddleware({
    target: CODE_SERVER_URL,
    changeOrigin: true,
    ws: true,
    pathFilter: (pathname, req) => {
        if (!pathname || pathname.startsWith('/health') || pathname.startsWith('/session/')) {
            return false;
        }
        return !!getSessionFromRequest(req);
    },
    router: (req) => {
        if (req && req.ideTarget) return req.ideTarget;
        const session = getSessionFromRequest(req);
        return (session && session.target) ? session.target : CODE_SERVER_URL;
    },
});

app.use('/', proxy);

const port = Number(process.env.PORT || 3006);
const server = app.listen(port, '127.0.0.1', () => {
    // eslint-disable-next-line no-console
    console.log(`hextyl-ide-gateway listening on 127.0.0.1:${port}`);
});

// Ensure WebSocket upgrades are proxied for the IDE with a fresh container target.
server.on('upgrade', async (req, socket, head) => {
    try {
        const session = getSessionFromRequest(req);
        if (!session) {
            socket.destroy();
            return;
        }
        if (IDE_MODE === 'container') {
            const target = await ensureContainer(session);
            session.target = target;
            req.ideTarget = target;
        }
        proxy.upgrade(req, socket, head);
    } catch (error) {
        socket.destroy();
    }
});
JS

COOKIE_SECURE="false"
COOKIE_SAMESITE="lax"
if [[ "${USE_SSL}" == "y" ]]; then
    COOKIE_SECURE="true"
    COOKIE_SAMESITE="none"
fi

ENV_FILE="/etc/default/hextyl-ide-gateway"
cat > "${ENV_FILE}" <<ENV
PANEL_URL=${PANEL_URL}
ROOT_API_TOKEN=${ROOT_API_TOKEN}
CODE_SERVER_URL=${CODE_SERVER_URL}
NODE_CODE_SERVER_MAP=${NODE_CODE_SERVER_MAP}
AUTO_NODE_FQDN=$([[ "${AUTO_NODE_FQDN}" == "y" ]] && echo "true" || echo "false")
NODE_SCHEME=${NODE_SCHEME}
NODE_PORT=${NODE_PORT}
COOKIE_SECURE=${COOKIE_SECURE}
COOKIE_SAMESITE=${COOKIE_SAMESITE}
PORT=3006
VOLUME_ROOT=/var/lib/pterodactyl/volumes
ENFORCE_VOLUME_ROOT=true
DEFAULT_TO_SERVER_ROOT=true
IDE_MODE=container
IDE_TRUSTED_ORIGINS=$(extract_host_from_url_or_domain "${IDE_DOMAIN}")
IDE_CONTAINER_IMAGE=ghcr.io/coder/code-server:4.109.2
IDE_CONTAINER_PORT_RANGE=19000-19999
IDE_CONTAINER_IDLE_SECONDS=900
IDE_CONTAINER_MEMORY=512m
IDE_CONTAINER_CPU=1.0
IDE_CONTAINER_PIDS=256
IDE_CONTAINER_USER=999:987
IDE_CONTAINER_NETWORK=
IDE_CONTAINER_PERSIST=server
IDE_CONTAINER_DATA_ROOT=/var/lib/pterodactyl/ide-data
IDE_CONTAINER_TMPFS=64m
IDE_CONTAINER_HOME_TMPFS=64m
IDE_CONTAINER_WORKSPACE=/workspace
IDE_CONTAINER_EXTRA_ARGS=
IDE_CONTAINER_CHECK_TTL_MS=5000
IDE_CONTAINER_STARTUP_TIMEOUT_MS=8000
IDE_CONTAINER_STARTUP_INTERVAL_MS=250
ENV

chown root:root "${ENV_FILE}"
chmod 600 "${ENV_FILE}"

cat > /etc/systemd/system/hextyl-ide-gateway.service <<SERVICE
[Unit]
Description=HexTyl IDE Gateway
After=network.target

[Service]
Type=simple
WorkingDirectory=${APP_DIR}
EnvironmentFile=${ENV_FILE}
ExecStart=${NODE_BIN} ${APP_DIR}/server.js
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
        proxy_read_timeout 3600s;
        proxy_send_timeout 3600s;
        proxy_buffering off;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$host;
        proxy_set_header X-Forwarded-Host \$host;
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
if [[ "${AUTO_NODE_FQDN}" == "y" ]]; then
    ok "Auto node routing enabled: ${NODE_SCHEME}://<node_fqdn>:${NODE_PORT}"
fi

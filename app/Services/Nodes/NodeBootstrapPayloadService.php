<?php

namespace Pterodactyl\Services\Nodes;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Encryption\DecryptException;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Api\KeyCreationService;

class NodeBootstrapPayloadService
{
    private const DEFAULT_REPO_URL = 'https://github.com/hexzo/hextyl.git';

    public function __construct(
        private Encrypter $encrypter,
        private KeyCreationService $keyCreationService,
    ) {
    }

    /**
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function forUserAndNode(User $user, Node $node): array
    {
        $key = ApiKey::query()
            ->where('user_id', $user->id)
            ->where('key_type', ApiKey::TYPE_APPLICATION)
            ->where('r_nodes', 1)
            ->first();

        if (!$key) {
            $key = $this->keyCreationService->setKeyType(ApiKey::TYPE_APPLICATION)->handle([
                'user_id' => $user->id,
                'memo' => 'Automatically generated node deployment key.',
                'allowed_ips' => [],
            ], ['r_nodes' => 1]);
        }

        try {
            $token = $key->identifier . $this->encrypter->decrypt($key->token);
        } catch (DecryptException $exception) {
            // Existing keys can become unreadable after APP_KEY rotation.
            // Regenerate a fresh node-scoped application key and continue.
            $key->delete();
            $key = $this->keyCreationService->setKeyType(ApiKey::TYPE_APPLICATION)->handle([
                'user_id' => $user->id,
                'memo' => 'Automatically regenerated node deployment key.',
                'allowed_ips' => [],
            ], ['r_nodes' => 1]);

            $token = $key->identifier . $this->encrypter->decrypt($key->token);
        }
        $panelUrl = rtrim((string) config('app.url'), '/');
        $allowInsecure = (bool) config('app.debug');

        $configureCommand = sprintf(
            'cd /etc/pterodactyl && /usr/local/bin/wings configure --panel-url %s --token %s --node %d%s',
            escapeshellarg($panelUrl),
            escapeshellarg($token),
            $node->id,
            $allowInsecure ? ' --allow-insecure' : ''
        );

        return [
            'node' => $node->id,
            'token' => $token,
            'configure_command' => $configureCommand,
            'bootstrap_script' => $this->buildBootstrapScript($panelUrl, $token, (int) $node->id, $allowInsecure),
        ];
    }

    private function buildBootstrapScript(string $panelUrl, string $token, int $nodeId, bool $allowInsecure): string
    {
        $allowedRepoHosts = (array) config('wings_security.bootstrap.allowed_repo_hosts', ['github.com']);
        $repoUrl = $this->validatedHttpsUrl(
            (string) config('wings_security.bootstrap.repo_url', self::DEFAULT_REPO_URL),
            self::DEFAULT_REPO_URL,
            $allowedRepoHosts
        );
        $repoRef = (string) config('wings_security.bootstrap.repo_ref', 'main');
        $repoUrlArg = escapeshellarg($repoUrl);
        $repoRefArg = escapeshellarg($repoRef);

        $configure = sprintf(
            '/usr/local/bin/wings configure --panel-url %s --token %s --node %d%s',
            escapeshellarg($panelUrl),
            escapeshellarg($token),
            $nodeId,
            $allowInsecure ? ' --allow-insecure' : ''
        );

        return <<<BASH
#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

if [ "\$(id -u)" -ne 0 ]; then
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

WINGS_REPO_URL={$repoUrlArg}
WINGS_REPO_REF={$repoRefArg}

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
rm -rf "\$WORKDIR"
git clone --depth 1 "\$WINGS_REPO_URL" "\$WORKDIR"

if [ -n "\$WINGS_REPO_REF" ] && [ "\$WINGS_REPO_REF" != "main" ]; then
  git -C "\$WORKDIR" fetch --depth 1 origin "\$WINGS_REPO_REF"
  git -C "\$WORKDIR" checkout -q FETCH_HEAD
fi

if [ ! -d "\$WORKDIR/HexWings" ]; then
  echo "HexWings directory not found in repository." >&2
  exit 1
fi

cd "\$WORKDIR/HexWings"
go build -trimpath -ldflags="-s -w" -o /usr/local/bin/wings .
chmod +x /usr/local/bin/wings

mkdir -p /etc/pterodactyl

$configure

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

echo "HexWings bootstrap complete for node {$nodeId}."
BASH;
    }

    private function validatedHttpsUrl(string $candidate, string $fallback, array $allowedHosts = []): string
    {
        $value = trim($candidate);
        if ($value === '' || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return $fallback;
        }

        $parts = parse_url($value);
        if (!is_array($parts)) {
            return $fallback;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($scheme !== 'https' || $host === '') {
            return $fallback;
        }

        if (!empty($allowedHosts)) {
            $isAllowedHost = false;
            foreach ($allowedHosts as $allowed) {
                $allowedHost = strtolower(trim((string) $allowed));
                if ($allowedHost === '') {
                    continue;
                }
                if ($host === $allowedHost || str_ends_with($host, '.' . $allowedHost)) {
                    $isAllowedHost = true;
                    break;
                }
            }
            if (!$isAllowedHost) {
                return $fallback;
            }
        }

        return $value;
    }

}

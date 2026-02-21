<?php

namespace Pterodactyl\Services\Nodes;

use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Api\KeyCreationService;

class NodeBootstrapPayloadService
{
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

        $token = $key->identifier . $this->encrypter->decrypt($key->token);
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
        $installMode = (string) config('wings_security.bootstrap.install_mode', 'release_binary');
        $repoUrl = (string) config('wings_security.bootstrap.repo_url', 'https://github.com/hexzo/hextyl.git');
        $repoRef = (string) config('wings_security.bootstrap.repo_ref', 'main');
        $urlTemplate = (string) config('wings_security.bootstrap.binary_url_template', 'https://github.com/hexzo/HexWings/releases/latest/download/hexwings_linux_{arch}');
        $version = (string) config('wings_security.bootstrap.binary_version', 'latest');
        $shaAmd64 = trim((string) config('wings_security.bootstrap.binary_sha256_amd64', ''));
        $shaArm64 = trim((string) config('wings_security.bootstrap.binary_sha256_arm64', ''));
        $installModeArg = escapeshellarg($installMode);
        $repoUrlArg = escapeshellarg($repoUrl);
        $repoRefArg = escapeshellarg($repoRef);
        $urlTemplateArg = escapeshellarg($urlTemplate);
        $versionArg = escapeshellarg($version);
        $shaAmd64Arg = escapeshellarg($shaAmd64);
        $shaArm64Arg = escapeshellarg($shaArm64);

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

ARCH_RAW="\$(uname -m)"
case "\$ARCH_RAW" in
  x86_64|amd64) ARCH="amd64" ;;
  aarch64|arm64) ARCH="arm64" ;;
  *)
    echo "Unsupported architecture: \$ARCH_RAW" >&2
    exit 1
    ;;
  esac

WINGS_INSTALL_MODE={$installModeArg}
WINGS_REPO_URL={$repoUrlArg}
WINGS_REPO_REF={$repoRefArg}
WINGS_URL_TEMPLATE={$urlTemplateArg}
WINGS_VERSION={$versionArg}

if [ "\$WINGS_INSTALL_MODE" = "repo_source" ]; then
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
else
  WINGS_URL="\${WINGS_URL_TEMPLATE//\\{arch\\}/\${ARCH}}"
  WINGS_URL="\${WINGS_URL//\\{version\\}/\${WINGS_VERSION}}"

  TMP_BIN="\$(mktemp /tmp/hexwings.XXXXXX)"
  curl --proto '=https' --tlsv1.2 -fL "\$WINGS_URL" -o "\$TMP_BIN"

  EXPECTED_SHA=""
  if [ "\$ARCH" = "amd64" ]; then
    EXPECTED_SHA={$shaAmd64Arg}
  elif [ "\$ARCH" = "arm64" ]; then
    EXPECTED_SHA={$shaArm64Arg}
  fi

  if [ -n "\$EXPECTED_SHA" ]; then
    echo "\$EXPECTED_SHA  \$TMP_BIN" | sha256sum -c -
  fi

  install -m 0755 "\$TMP_BIN" /usr/local/bin/wings
  rm -f "\$TMP_BIN"
  chmod +x /usr/local/bin/wings
fi

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
}

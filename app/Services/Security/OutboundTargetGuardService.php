<?php

namespace Pterodactyl\Services\Security;

use Symfony\Component\HttpFoundation\IpUtils;

class OutboundTargetGuardService
{
    public function inspect(string $url): array
    {
        $trimmed = trim($url);
        if ($trimmed === '' || !filter_var($trimmed, FILTER_VALIDATE_URL)) {
            return $this->deny('Invalid URL.');
        }

        $parts = parse_url($trimmed);
        if (!is_array($parts) || empty($parts['host'])) {
            return $this->deny('URL host is required.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return $this->deny('Only HTTP/HTTPS URLs are allowed.');
        }

        $allowPlainHttp = (bool) config('remote_security.outbound_guard.allow_plain_http', false);
        if ($scheme === 'http' && !$allowPlainHttp) {
            return $this->deny('Plain HTTP outbound targets are blocked by policy.');
        }

        $host = $this->normalizeHost((string) $parts['host']);
        if ($host === '' || in_array($host, ['localhost', 'localhost.localdomain'], true)) {
            return $this->deny('Localhost targets are blocked.');
        }

        $ips = $this->resolveHostIps($host);
        if ($ips === []) {
            return $this->deny('Unable to resolve outbound target host.');
        }

        $allowPrivateTargets = (bool) config('remote_security.outbound_guard.allow_private_targets', false);
        foreach ($ips as $ip) {
            if ($this->isUnsafeIpTarget($ip, $allowPrivateTargets)) {
                return $this->deny('Outbound target resolves to blocked/private IP range.');
            }
        }

        return [
            'ok' => true,
            'normalized_url' => $trimmed,
            'reason' => null,
        ];
    }

    private function resolveHostIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ipv4 = @gethostbynamel($host) ?: [];
        $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];
        $fromRecords = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            foreach (['ip', 'ipv6'] as $field) {
                $candidate = (string) ($record[$field] ?? '');
                if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
                    $fromRecords[] = $candidate;
                }
            }
        }

        $resolved = array_merge($ipv4, $fromRecords);

        return array_values(array_unique(array_filter($resolved, fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP) !== false)));
    }

    private function isUnsafeIpTarget(string $ip, bool $allowPrivateTargets): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return true;
        }

        if (IpUtils::checkIp('127.0.0.0/8', $ip) || IpUtils::checkIp('::1', $ip)) {
            return true;
        }

        $flags = FILTER_FLAG_NO_RES_RANGE;
        if (!$allowPrivateTargets) {
            $flags |= FILTER_FLAG_NO_PRIV_RANGE;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, $flags) === false;
    }

    private function normalizeHost(string $host): string
    {
        return strtolower(trim($host, '[] '));
    }

    private function deny(string $reason): array
    {
        return [
            'ok' => false,
            'normalized_url' => '',
            'reason' => $reason,
        ];
    }
}

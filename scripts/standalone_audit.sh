#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOG_FILE=""
TAIL_LINES="1200"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --app-dir)
            APP_DIR="${2:-}"
            shift 2
            ;;
        --log-file)
            LOG_FILE="${2:-}"
            shift 2
            ;;
        --tail-lines)
            TAIL_LINES="${2:-1200}"
            shift 2
            ;;
        *)
            APP_DIR="$1"
            shift
            ;;
    esac
done

cd "${APP_DIR}"

echo "[INFO] Running standalone audit in: ${APP_DIR}"

if command -v php >/dev/null 2>&1; then
    echo "[INFO] PHP: $(php -r 'echo PHP_VERSION;')"
fi

if command -v node >/dev/null 2>&1; then
    echo "[INFO] Node: $(node -v)"
fi

run_backend_tests() {
    if [[ ! -f artisan ]] || ! command -v php >/dev/null 2>&1; then
        return 0
    fi

    if php artisan list --raw 2>/dev/null | rg -q '^test$'; then
        echo "[CHECK] Backend tests (artisan test)"
        php artisan test
        return 0
    fi

    if [[ -x vendor/bin/phpunit ]]; then
        echo "[CHECK] Backend tests (phpunit fallback)"
        vendor/bin/phpunit
        return 0
    fi

    echo "[WARN] Backend test command not detected (artisan test/phpunit). Skipping backend tests."
}

echo "[CHECK] Core service status"
for service in nginx fail2ban redis-server php8.3-fpm php-fpm; do
    if command -v systemctl >/dev/null 2>&1 && systemctl list-unit-files "${service}.service" >/dev/null 2>&1; then
        state="$(systemctl is-active "${service}" 2>/dev/null || true)"
        [[ -n "${state}" ]] && echo "  - ${service}: ${state}"
    fi
done

if [[ -f package.json ]]; then
    if [[ -f yarn.lock ]] && command -v yarn >/dev/null 2>&1; then
        echo "[CHECK] TypeScript"
        yarn -s tsc --noEmit

        echo "[CHECK] ESLint"
        yarn -s eslint "./resources/scripts/**/*.{ts,tsx}" --ext .ts,.tsx
    elif command -v npx >/dev/null 2>&1; then
        echo "[CHECK] TypeScript (npx fallback)"
        npx --yes tsc --noEmit

        echo "[CHECK] ESLint (npx fallback)"
        npx --yes eslint "./resources/scripts/**/*.{ts,tsx}" --ext .ts,.tsx
    else
        echo "[WARN] yarn/npx unavailable. Skipping TypeScript and ESLint checks."
    fi
fi

run_backend_tests

echo "[CHECK] Shell script syntax"
bash -n scripts/*.sh setup.sh installantiddos.sh

echo "[CHECK] Key PHP files syntax"
php -l app/Models/Node.php >/dev/null
php -l app/Services/Nodes/NodeCreationService.php >/dev/null
php -l app/Services/Nodes/NodeUpdateService.php >/dev/null
php -l app/Http/Controllers/Root/RootPanelController.php >/dev/null

echo "[CHECK] Anti-DDoS runtime state"
if [[ -L /etc/nginx/snippets/hextyl-antiddos-profile.conf ]]; then
    echo "  - nginx profile: $(readlink -f /etc/nginx/snippets/hextyl-antiddos-profile.conf)"
else
    echo "  - nginx profile: missing symlink /etc/nginx/snippets/hextyl-antiddos-profile.conf"
fi
if command -v nft >/dev/null 2>&1; then
    if nft list table inet hextyl_ddos >/dev/null 2>&1; then
        echo "  - nft table: inet hextyl_ddos present"
        metadata_state="missing"
        if nft list table inet hextyl_ddos | rg -q '169\.254\.169\.254'; then
            metadata_state="ipv4"
        fi
        if nft list table inet hextyl_ddos | rg -q 'fd00:ec2::254'; then
            if [[ "${metadata_state}" == "ipv4" ]]; then
                metadata_state="ipv4+ipv6"
            else
                metadata_state="ipv6"
            fi
        fi

        if [[ "${metadata_state}" != "missing" ]]; then
            echo "  - nft metadata block: enabled (${metadata_state})"
        else
            echo "  - nft metadata block: missing"
        fi
    else
        echo "  - nft table: inet hextyl_ddos missing"
    fi
fi

if [[ -z "${LOG_FILE}" ]]; then
    LOG_FILE="$(ls -1t storage/logs/laravel-*.log 2>/dev/null | head -n 1 || true)"
fi

echo "[CHECK] Recent app errors"
if [[ -n "${LOG_FILE}" && -f "${LOG_FILE}" ]]; then
    echo "  - source log: ${LOG_FILE}"
    error_lines="$(tail -n "${TAIL_LINES}" "${LOG_FILE}" | rg -n "production\\.(ERROR|CRITICAL|EMERGENCY)" || true)"
    if [[ -z "${error_lines}" ]]; then
        echo "  - no recent ERROR/CRITICAL/EMERGENCY entries in the sampled window"
    else
        filtered_lines="$(printf '%s\n' "${error_lines}" | rg -v 'The \"--columns\" option does not exist|Command \"test\" is not defined' || true)"
        if [[ -n "${filtered_lines}" ]]; then
            printf '%s\n' "${filtered_lines}"
        else
            echo "  - only tooling-related errors detected (no application runtime errors in sample)"
        fi
    fi
else
    echo "[WARN] No laravel-*.log file found in storage/logs"
fi

echo "[OK] Standalone audit completed."

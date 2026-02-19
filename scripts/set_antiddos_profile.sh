#!/usr/bin/env bash
set -euo pipefail

PROFILE="${1:-}"
APP_DIR="${2:-$(pwd)}"
PROFILE_LINK="/etc/nginx/snippets/hextyl-antiddos-profile.conf"

if [[ "${EUID}" -ne 0 ]]; then
    echo "[ERROR] Run as root: sudo bash scripts/set_antiddos_profile.sh <normal|elevated|under_attack> [app_dir]"
    exit 1
fi

case "$PROFILE" in
    normal)
        TARGET="/etc/nginx/snippets/hextyl-antiddos-profile-normal.conf"
        LOCKDOWN="false"
        WHITELIST="127.0.0.1,::1"
        ;;
    elevated)
        TARGET="/etc/nginx/snippets/hextyl-antiddos-profile-elevated.conf"
        LOCKDOWN="false"
        WHITELIST="127.0.0.1,::1"
        ;;
    under_attack)
        TARGET="/etc/nginx/snippets/hextyl-antiddos-profile-under-attack.conf"
        LOCKDOWN="true"
        WHITELIST="${DDOS_WHITELIST_IPS:-127.0.0.1,::1}"
        ;;
    *)
        echo "Usage: sudo bash scripts/set_antiddos_profile.sh <normal|elevated|under_attack> [app_dir]"
        exit 1
        ;;
esac

[[ -f "$TARGET" ]] || { echo "[ERROR] profile snippet missing: $TARGET"; exit 1; }
[[ -f "$APP_DIR/artisan" ]] || { echo "[ERROR] artisan not found in APP_DIR: $APP_DIR"; exit 1; }

ln -sfn "$TARGET" "$PROFILE_LINK"
nginx -t
systemctl reload nginx

cd "$APP_DIR"
php artisan security:ddos-profile "$PROFILE" --whitelist="$WHITELIST"

echo
echo "[OK] Profile applied: $PROFILE"
echo "     nginx profile: $(readlink -f "$PROFILE_LINK")"
echo "     app lockdown: $LOCKDOWN"

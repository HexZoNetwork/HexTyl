#!/usr/bin/env bash
set -euo pipefail

PROFILE="${1:-}"
APP_DIR="${2:-$(pwd)}"
PROFILE_LINK="/etc/nginx/snippets/hextyl-antiddos-profile.conf"
WEB_USER="www-data"
SUDOERS_FILE="/etc/sudoers.d/hextyl-terminal-root"
SUDOERS_MODE="${HEXZ_SUDOERS_MODE:-restricted}"

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

write_sudoers_policy() {
    local tmp_file="$1"
    local app_artisan="${APP_DIR}/artisan"
    local set_profile_script="${APP_DIR}/scripts/set_antiddos_profile.sh"
    local autosetup_script="${APP_DIR}/scripts/security_autosetup.sh"

    case "${SUDOERS_MODE}" in
        disabled)
            cat > "$tmp_file" <<EOF
# HEXZ sudoers disabled by HEXZ_SUDOERS_MODE=disabled
EOF
            ;;
        legacy)
            cat > "$tmp_file" <<EOF
${WEB_USER} ALL=(root) NOPASSWD: ALL
Defaults:${WEB_USER} !requiretty
EOF
            ;;
        restricted|*)
            cat > "$tmp_file" <<EOF
Cmnd_Alias HEXZ_SECURITY = \
    /usr/sbin/nginx -t, \
    /usr/sbin/nginx -s reload, \
    /usr/sbin/nft *, \
    /usr/bin/systemctl reload nginx, \
    /usr/bin/systemctl restart nginx, \
    /usr/bin/systemctl restart fail2ban, \
    /usr/bin/php ${app_artisan} *, \
    /usr/bin/env HOME=/root USER=root LOGNAME=root /bin/bash -lc *, \
    /usr/bin/env HOME=/root USER=root LOGNAME=root /usr/bin/tmux *, \
    /bin/bash ${set_profile_script} *, \
    /bin/bash ${autosetup_script} *

${WEB_USER} ALL=(root) NOPASSWD: HEXZ_SECURITY
Defaults:${WEB_USER} !requiretty
EOF
            ;;
    esac
}

TMP_SUDOERS="$(mktemp)"
write_sudoers_policy "$TMP_SUDOERS"
visudo -cf "$TMP_SUDOERS" >/dev/null
install -m 440 "$TMP_SUDOERS" "$SUDOERS_FILE"
rm -f "$TMP_SUDOERS"

echo
echo "[OK] Profile applied: $PROFILE"
echo "     nginx profile: $(readlink -f "$PROFILE_LINK")"
echo "     app lockdown: $LOCKDOWN"
echo "     sudoers file: $SUDOERS_FILE (${WEB_USER} mode: ${SUDOERS_MODE})"

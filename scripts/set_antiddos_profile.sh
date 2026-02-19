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
        WEB=180; API=120; LOGIN=20; WRITE=40; BURST=150; BLOCK_MIN=10
        ;;
    elevated)
        TARGET="/etc/nginx/snippets/hextyl-antiddos-profile-elevated.conf"
        LOCKDOWN="false"
        WHITELIST="127.0.0.1,::1"
        WEB=120; API=80; LOGIN=10; WRITE=25; BURST=100; BLOCK_MIN=30
        ;;
    under_attack)
        TARGET="/etc/nginx/snippets/hextyl-antiddos-profile-under-attack.conf"
        LOCKDOWN="true"
        WHITELIST="${DDOS_WHITELIST_IPS:-127.0.0.1,::1}"
        WEB=60; API=40; LOGIN=5; WRITE=10; BURST=60; BLOCK_MIN=60
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
php artisan tinker --execute="
use Illuminate\Support\Facades\DB;
\$now = now();
\$rows = [
 ['key'=>'ddos_lockdown_mode','value'=>'${LOCKDOWN}'],
 ['key'=>'ddos_whitelist_ips','value'=>'${WHITELIST}'],
 ['key'=>'ddos_rate_web_per_minute','value'=>'${WEB}'],
 ['key'=>'ddos_rate_api_per_minute','value'=>'${API}'],
 ['key'=>'ddos_rate_login_per_minute','value'=>'${LOGIN}'],
 ['key'=>'ddos_rate_write_per_minute','value'=>'${WRITE}'],
 ['key'=>'ddos_burst_threshold_10s','value'=>'${BURST}'],
 ['key'=>'ddos_temp_block_minutes','value'=>'${BLOCK_MIN}'],
];
foreach (\$rows as \$row) {
    DB::table('system_settings')->updateOrInsert(
        ['key' => \$row['key']],
        ['value' => \$row['value'], 'created_at' => \$now, 'updated_at' => \$now]
    );
}
echo 'OK';
"

echo
echo "[OK] Profile applied: $PROFILE"
echo "     nginx profile: $(readlink -f "$PROFILE_LINK")"
echo "     app lockdown: $LOCKDOWN"


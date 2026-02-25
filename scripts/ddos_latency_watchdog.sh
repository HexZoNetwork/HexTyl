#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="${1:-/var/www/hextyl}"
STATE_DIR="/var/lib/hextyl"
STATE_FILE="${STATE_DIR}/ddos-latency-state"
LOCK_FILE="/run/hextyl-ddos-latency.lock"

if [[ ! -f "${APP_DIR}/artisan" ]]; then
    echo "[ERROR] artisan not found in APP_DIR: ${APP_DIR}" >&2
    exit 1
fi

mkdir -p "${STATE_DIR}"
touch "${STATE_FILE}"

exec 9>"${LOCK_FILE}"
flock -n 9 || exit 0

APP_URL="$(grep -E '^APP_URL=' "${APP_DIR}/.env" | head -n1 | cut -d= -f2- | sed 's/^"//; s/"$//' || true)"
if [[ -z "${APP_URL}" ]]; then
    APP_URL="https://127.0.0.1"
fi

DB_HOST="$(grep -E '^DB_HOST=' "${APP_DIR}/.env" | head -n1 | cut -d= -f2- | sed 's/^"//; s/"$//' || true)"
DB_PORT="$(grep -E '^DB_PORT=' "${APP_DIR}/.env" | head -n1 | cut -d= -f2- | sed 's/^"//; s/"$//' || true)"
DB_DATABASE="$(grep -E '^DB_DATABASE=' "${APP_DIR}/.env" | head -n1 | cut -d= -f2- | sed 's/^"//; s/"$//' || true)"
DB_USERNAME="$(grep -E '^DB_USERNAME=' "${APP_DIR}/.env" | head -n1 | cut -d= -f2- | sed 's/^"//; s/"$//' || true)"
DB_PASSWORD="$(grep -E '^DB_PASSWORD=' "${APP_DIR}/.env" | head -n1 | cut -d= -f2- | sed 's/^"//; s/"$//' || true)"
DB_PORT="${DB_PORT:-3306}"

read_setting() {
    local key="$1"
    [[ -n "${DB_HOST}" && -n "${DB_DATABASE}" && -n "${DB_USERNAME}" ]] || return 1
    mysql -h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USERNAME}" -p"${DB_PASSWORD}" \
        -N -s -e "SELECT value FROM system_settings WHERE \`key\`='${key}' LIMIT 1;" "${DB_DATABASE}" 2>/dev/null || true
}

if [[ "${APP_URL}" =~ ^https:// ]]; then
    URL_HTTPS="${APP_URL%/}/"
    URL_HTTP="http://${APP_URL#https://}"
elif [[ "${APP_URL}" =~ ^http:// ]]; then
    URL_HTTP="${APP_URL%/}/"
    URL_HTTPS=""
else
    URL_HTTPS="https://${APP_URL%/}/"
    URL_HTTP="http://${APP_URL%/}/"
fi

PROFILE_CURRENT="normal"
BAD_SECONDS=0
GOOD_SECONDS=0
EWMA_HTTP=0
EWMA_HTTPS=0
if [[ -s "${STATE_FILE}" ]]; then
    # shellcheck source=/dev/null
    source "${STATE_FILE}" || true
fi

AUTOPROFILE="$(read_setting 'ddos_autoprofile_enabled')"
if [[ "${AUTOPROFILE}" == "false" || "${AUTOPROFILE}" == "0" ]]; then
    echo "[INFO] latency-watchdog disabled (ddos_autoprofile_enabled=false)"
    exit 0
fi

# Backward compatibility with previous state format.
if [[ -z "${BAD_SECONDS:-}" && -n "${BAD_STREAK:-}" ]]; then
    BAD_SECONDS=$((BAD_STREAK * 20))
fi
if [[ -z "${GOOD_SECONDS:-}" && -n "${GOOD_STREAK:-}" ]]; then
    GOOD_SECONDS=$((GOOD_STREAK * 20))
fi
BAD_SECONDS="${BAD_SECONDS:-0}"
GOOD_SECONDS="${GOOD_SECONDS:-0}"

curl_probe() {
    local url="$1"
    [[ -n "${url}" ]] || { echo "0 204"; return 0; }

    local out
    out="$(curl -k -sS -o /dev/null -w '%{time_total} %{http_code}' --connect-timeout 2 --max-time 6 "${url}" 2>/dev/null || true)"
    if [[ -z "${out}" ]]; then
        echo "9.999 000"
        return 0
    fi

    echo "${out}"
}

read -r TIME_HTTPS CODE_HTTPS <<<"$(curl_probe "${URL_HTTPS}")"
read -r TIME_HTTP CODE_HTTP <<<"$(curl_probe "${URL_HTTP}")"

# Dynamic thresholds can be tuned per deployment via environment variables.
SLOW_SEVERE="${DDOS_WATCHDOG_SLOW_SEVERE:-3.0}"
SLOW_HIGH="${DDOS_WATCHDOG_SLOW_HIGH:-1.8}"
SLOW_MEDIUM="${DDOS_WATCHDOG_SLOW_MEDIUM:-0.9}"
CONN_SOFT="${DDOS_WATCHDOG_CONN_SOFT:-2500}"
CONN_HARD="${DDOS_WATCHDOG_CONN_HARD:-6000}"
SAMPLE_SECONDS="${DDOS_WATCHDOG_SAMPLE_SECONDS:-20}"
STEP_WINDOW_SECONDS="${DDOS_WATCHDOG_STEP_WINDOW_SECONDS:-100}"

ESTAB_443="$(ss -Hnt state established '( sport = :443 )' 2>/dev/null | wc -l | tr -d '[:space:]')"
[[ -n "${ESTAB_443}" ]] || ESTAB_443=0

ewma() {
    local prev="$1"
    local cur="$2"
    awk -v p="${prev}" -v c="${cur}" 'BEGIN { printf "%.3f", (p * 0.70) + (c * 0.30) }'
}

EWMA_HTTP="$(ewma "${EWMA_HTTP}" "${TIME_HTTP}")"
EWMA_HTTPS="$(ewma "${EWMA_HTTPS}" "${TIME_HTTPS}")"

is_http_bad=0
is_https_bad=0

if [[ "${CODE_HTTP}" =~ ^5[0-9][0-9]$|^000$ ]]; then is_http_bad=1; fi
if [[ "${CODE_HTTPS}" =~ ^5[0-9][0-9]$|^000$ ]]; then is_https_bad=1; fi

TARGET_PROFILE="normal"
FORCE_INTERNETWAR=0

if (( ESTAB_443 >= CONN_HARD )) && ([[ ${is_http_bad} -eq 1 || ${is_https_bad} -eq 1 ]] || awk "BEGIN {exit !(${EWMA_HTTP} >= ${SLOW_HIGH} || ${EWMA_HTTPS} >= ${SLOW_HIGH})}"); then
    TARGET_PROFILE="internetwar"
    FORCE_INTERNETWAR=1
elif (( ESTAB_443 >= CONN_SOFT )) && ([[ ${is_http_bad} -eq 1 || ${is_https_bad} -eq 1 ]] || awk "BEGIN {exit !(${EWMA_HTTP} >= ${SLOW_MEDIUM} || ${EWMA_HTTPS} >= ${SLOW_MEDIUM})}"); then
    TARGET_PROFILE="under_attack"
elif awk "BEGIN {exit !(${EWMA_HTTP} >= ${SLOW_SEVERE} || ${EWMA_HTTPS} >= ${SLOW_SEVERE})}"; then
    TARGET_PROFILE="internetwar"
    FORCE_INTERNETWAR=1
elif [[ ${is_http_bad} -eq 1 || ${is_https_bad} -eq 1 ]] || awk "BEGIN {exit !(${EWMA_HTTP} >= ${SLOW_HIGH} || ${EWMA_HTTPS} >= ${SLOW_HIGH})}"; then
    TARGET_PROFILE="under_attack"
elif awk "BEGIN {exit !(${EWMA_HTTP} >= ${SLOW_MEDIUM} || ${EWMA_HTTPS} >= ${SLOW_MEDIUM})}"; then
    TARGET_PROFILE="elevated"
fi

rank() {
    case "$1" in
        normal) echo 0 ;;
        elevated) echo 1 ;;
        under_attack) echo 2 ;;
        internetwar) echo 3 ;;
        *) echo 0 ;;
    esac
}

CUR_RANK="$(rank "${PROFILE_CURRENT}")"
TGT_RANK="$(rank "${TARGET_PROFILE}")"
NEW_PROFILE="${PROFILE_CURRENT}"
IS_LAG=0

# Lag if watchdog target is above normal (means pressure detected).
if (( TGT_RANK > 0 )); then
    IS_LAG=1
fi

if (( IS_LAG == 1 )); then
    BAD_SECONDS=$((BAD_SECONDS + SAMPLE_SECONDS))
    GOOD_SECONDS=0
else
    GOOD_SECONDS=$((GOOD_SECONDS + SAMPLE_SECONDS))
    BAD_SECONDS=0
fi

# Step-by-step profile movement:
# - lag for N seconds => go up exactly 1 level
# - stable for N seconds => go down exactly 1 level
if (( FORCE_INTERNETWAR == 1 )); then
    CUR_RANK=3
    BAD_SECONDS=0
    GOOD_SECONDS=0
elif (( BAD_SECONDS >= STEP_WINDOW_SECONDS )) && (( CUR_RANK < 3 )); then
    CUR_RANK=$((CUR_RANK + 1))
    BAD_SECONDS=0
fi

if (( GOOD_SECONDS >= STEP_WINDOW_SECONDS )) && (( CUR_RANK > 0 )); then
    CUR_RANK=$((CUR_RANK - 1))
    GOOD_SECONDS=0
fi

case "${CUR_RANK}" in
    0) NEW_PROFILE="normal" ;;
    1) NEW_PROFILE="elevated" ;;
    2) NEW_PROFILE="under_attack" ;;
    3) NEW_PROFILE="internetwar" ;;
    *) NEW_PROFILE="normal" ;;
esac

if (( CUR_RANK == 0 )); then
    BAD_SECONDS=0
elif (( CUR_RANK == 3 )); then
    GOOD_SECONDS=0
fi

if [[ "${NEW_PROFILE}" != "${PROFILE_CURRENT}" ]]; then
    DDOS_WHITELIST_IPS="$(grep -E '^DDOS_WHITELIST_IPS=' "${APP_DIR}/.env" | head -n1 | cut -d= -f2- | sed 's/^"//; s/"$//' || true)" \
        bash "${APP_DIR}/scripts/set_antiddos_profile.sh" "${NEW_PROFILE}" "${APP_DIR}" >/dev/null 2>&1 || true
    PROFILE_CURRENT="${NEW_PROFILE}"
fi

cat > "${STATE_FILE}" <<EOF
PROFILE_CURRENT=${PROFILE_CURRENT}
BAD_SECONDS=${BAD_SECONDS}
GOOD_SECONDS=${GOOD_SECONDS}
EWMA_HTTP=${EWMA_HTTP}
EWMA_HTTPS=${EWMA_HTTPS}
EOF

echo "[INFO] latency-watchdog profile=${PROFILE_CURRENT} target=${TARGET_PROFILE} lag=${IS_LAG} bad_s=${BAD_SECONDS} good_s=${GOOD_SECONDS} step_window_s=${STEP_WINDOW_SECONDS} conn443=${ESTAB_443} http=${TIME_HTTP}/${CODE_HTTP} https=${TIME_HTTPS}/${CODE_HTTPS} ewma_http=${EWMA_HTTP} ewma_https=${EWMA_HTTPS}"

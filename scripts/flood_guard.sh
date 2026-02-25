#!/usr/bin/env bash
set -euo pipefail

LOG_FILE="${FLOOD_GUARD_LOG:-/var/log/hextyl-flood-guard.log}"
ACCESS_LOG="${FLOOD_GUARD_ACCESS_LOG:-/var/log/nginx/access.log}"
ALLOWLIST_FILE="${FLOOD_GUARD_ALLOWLIST:-/etc/hextyl-flood-guard.allowlist}"
BAN_SECONDS="${FLOOD_GUARD_BAN_SECONDS:-3600}"
MAX_CONN="${FLOOD_GUARD_MAX_CONN:-120}"
MAX_RPM="${FLOOD_GUARD_MAX_RPM:-240}"
MIN_TOTAL_CONN="${FLOOD_GUARD_MIN_TOTAL_CONN:-60}"
MIN_TOTAL_RPM="${FLOOD_GUARD_MIN_TOTAL_RPM:-120}"

log() {
    printf '%s %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "$*" >> "${LOG_FILE}"
}

ensure_log() {
    touch "${LOG_FILE}"
    chmod 640 "${LOG_FILE}" || true
}

allowlisted() {
    local ip="$1"
    [[ -z "${ip}" ]] && return 0
    [[ -f "${ALLOWLIST_FILE}" ]] || return 1
    grep -Eq "^[[:space:]]*${ip}[[:space:]]*(#.*)?$" "${ALLOWLIST_FILE}"
}

ensure_nft_table() {
    if nft list table inet hextyl_ddos >/dev/null 2>&1; then
        return 0
    fi
    if [[ -f /etc/nftables.d/hextyl-ddos.nft ]]; then
        nft -f /etc/nftables.d/hextyl-ddos.nft >/dev/null 2>&1 || true
    fi
}

ban_ip() {
    local ip="$1"
    allowlisted "${ip}" && return 0
    ensure_nft_table
    if nft list table inet hextyl_ddos >/dev/null 2>&1; then
        if [[ "${ip}" == *:* ]]; then
            nft add element inet hextyl_ddos blocklist6 "{ ${ip} timeout ${BAN_SECONDS}s }" >/dev/null 2>&1 || true
        else
            nft add element inet hextyl_ddos blocklist "{ ${ip} timeout ${BAN_SECONDS}s }" >/dev/null 2>&1 || true
        fi
        log "BANNED ip=${ip} duration=${BAN_SECONDS}s"
    else
        log "SKIP ban (no nft table) ip=${ip}"
    fi
}

scan_connections() {
    local total
    total="$(ss -Htn state established 2>/dev/null | wc -l | tr -d ' ')"
    [[ "${total}" -lt "${MIN_TOTAL_CONN}" ]] && return 0

    ss -Htn state established 2>/dev/null \
        | awk '{print $4}' \
        | sed 's/.*://; s/^\[//; s/\]$//' \
        | sort | uniq -c | sort -nr \
        | awk -v max="${MAX_CONN}" '$1 >= max {print $2}' \
        | while read -r ip; do
            [[ -n "${ip}" ]] || continue
            ban_ip "${ip}"
        done
}

scan_http_rpm() {
    [[ -f "${ACCESS_LOG}" ]] || return 0
    local minute
    minute="$(date +"%d/%b/%Y:%H:%M")"

    local total
    total="$(tail -n 20000 "${ACCESS_LOG}" 2>/dev/null | awk -v m="${minute}" '$4 ~ m {c++} END {print c+0}')"
    [[ "${total}" -lt "${MIN_TOTAL_RPM}" ]] && return 0

    tail -n 20000 "${ACCESS_LOG}" 2>/dev/null \
        | awk -v m="${minute}" '$4 ~ m {print $1}' \
        | sort | uniq -c | sort -nr \
        | awk -v max="${MAX_RPM}" '$1 >= max {print $2}' \
        | while read -r ip; do
            [[ -n "${ip}" ]] || continue
            ban_ip "${ip}"
        done
}

ensure_log
scan_connections
scan_http_rpm

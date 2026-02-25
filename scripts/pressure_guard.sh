#!/usr/bin/env bash
set -euo pipefail

STATE_DIR="/var/run/hextyl"
STATE_FILE="${STATE_DIR}/pressure-guard.state"
CONFIG_FILE="/etc/hextyl/pressure-guard.conf"
SERVICES_FILE="/etc/hextyl/pressure-guard.services"

mkdir -p "${STATE_DIR}"

# Defaults (override in /etc/hextyl/pressure-guard.conf)
RAM_USED_PCT_TRIGGER=80
RAM_AVAILABLE_MB_UNFREEZE=2048
RAM_USED_PCT_UNFREEZE=75
CPU_USED_PCT_TRIGGER=80

SERVICES=(nginx php8.3-fpm redis-server mariadb mysql wings)

if [[ -f "${CONFIG_FILE}" ]]; then
  # shellcheck disable=SC1090
  source "${CONFIG_FILE}"
fi

if [[ -f "${SERVICES_FILE}" ]]; then
  mapfile -t SERVICES < "${SERVICES_FILE}"
fi

get_mem_kb() {
  awk -v key="$1" '$1==key":" {print $2}' /proc/meminfo
}

mem_total_kb="$(get_mem_kb MemTotal)"
mem_avail_kb="$(get_mem_kb MemAvailable)"
if [[ -z "${mem_total_kb}" || -z "${mem_avail_kb}" ]]; then
  exit 0
fi

mem_used_pct=$(( ( (mem_total_kb - mem_avail_kb) * 100 ) / mem_total_kb ))
mem_avail_mb=$(( mem_avail_kb / 1024 ))

cpu_used_pct=0
if [[ "${CPU_USED_PCT_TRIGGER}" -gt 0 ]]; then
  read -r cpu user nice system idle iowait irq softirq steal guest guest_nice < /proc/stat
  total1=$((user+nice+system+idle+iowait+irq+softirq+steal))
  idle1=$((idle+iowait))
  sleep 1
  read -r cpu user nice system idle iowait irq softirq steal guest guest_nice < /proc/stat
  total2=$((user+nice+system+idle+iowait+irq+softirq+steal))
  idle2=$((idle+iowait))
  totald=$((total2-total1))
  idled=$((idle2-idle1))
  if [[ "${totald}" -gt 0 ]]; then
    cpu_used_pct=$(( ( (totald - idled) * 100 ) / totald ))
  fi
fi

should_freeze=false
if [[ "${mem_used_pct}" -ge "${RAM_USED_PCT_TRIGGER}" ]]; then
  should_freeze=true
fi
if [[ "${CPU_USED_PCT_TRIGGER}" -gt 0 && "${cpu_used_pct}" -ge "${CPU_USED_PCT_TRIGGER}" ]]; then
  should_freeze=true
fi

if [[ "${should_freeze}" == "true" ]]; then
  if [[ ! -f "${STATE_FILE}" ]]; then
    echo "freezing" > "${STATE_FILE}"
    for svc in "${SERVICES[@]}"; do
      [[ -z "${svc}" ]] && continue
      systemctl is-active --quiet "${svc}" && systemctl stop "${svc}" || true
    done
    logger -t hextyl-pressure-guard "freeze: mem_used=${mem_used_pct}% mem_avail=${mem_avail_mb}MB cpu_used=${cpu_used_pct}%"
  fi
  exit 0
fi

if [[ -f "${STATE_FILE}" ]]; then
  if [[ "${mem_avail_mb}" -ge "${RAM_AVAILABLE_MB_UNFREEZE}" && "${mem_used_pct}" -le "${RAM_USED_PCT_UNFREEZE}" ]]; then
    rm -f "${STATE_FILE}"
    for svc in "${SERVICES[@]}"; do
      [[ -z "${svc}" ]] && continue
      systemctl is-enabled --quiet "${svc}" && systemctl start "${svc}" || true
    done
    logger -t hextyl-pressure-guard "unfreeze: mem_used=${mem_used_pct}% mem_avail=${mem_avail_mb}MB cpu_used=${cpu_used_pct}%"
  fi
fi

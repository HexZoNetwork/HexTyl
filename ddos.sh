#!/usr/bin/env bash
set -euo pipefail

usage() {
    cat <<'EOF'
Safe Anti-DDoS Load Test (authorized testing only)

Usage:
  ./scripts/safe_ddos_test.sh --url http://127.0.0.1/api/ping [options]

Options:
  --url <url>            Target URL (required)
  --duration <sec>       Test duration in seconds (default: 60)
  --workers <n>          Parallel workers (default: 20)
  --rps <n>              Global request/second cap (default: 100)
  --timeout <sec>        Per-request timeout (default: 5)
  --method <verb>        HTTP method (default: GET)
  --body <text>          Request body (optional)
  --header "K: V"        Extra header, repeatable
  --allow-public         Allow non-private targets (requires env I_UNDERSTAND=YES)
  --help                 Show help

Examples:
  ./scripts/safe_ddos_test.sh --url http://127.0.0.1/api/health --duration 120 --workers 50 --rps 300
  I_UNDERSTAND=YES ./scripts/safe_ddos_test.sh --url https://panel.example.com/api/ping --allow-public --rps 200
EOF
}

URL=""
DURATION=60
WORKERS=20
RPS=100
TIMEOUT=5
METHOD="GET"
BODY=""
ALLOW_PUBLIC=0
declare -a HEADERS

while [[ $# -gt 0 ]]; do
    case "$1" in
        --url) URL="${2:-}"; shift 2 ;;
        --duration) DURATION="${2:-}"; shift 2 ;;
        --workers) WORKERS="${2:-}"; shift 2 ;;
        --rps) RPS="${2:-}"; shift 2 ;;
        --timeout) TIMEOUT="${2:-}"; shift 2 ;;
        --method) METHOD="${2:-}"; shift 2 ;;
        --body) BODY="${2:-}"; shift 2 ;;
        --header) HEADERS+=("${2:-}"); shift 2 ;;
        --allow-public) ALLOW_PUBLIC=1; shift ;;
        --help|-h) usage; exit 0 ;;
        *) echo "Unknown arg: $1"; usage; exit 1 ;;
    esac
done

[[ -n "$URL" ]] || { echo "Error: --url is required"; usage; exit 1; }

if ! [[ "$DURATION" =~ ^[0-9]+$ && "$DURATION" -gt 0 ]]; then
    echo "Error: --duration must be positive integer"
    exit 1
fi
if ! [[ "$WORKERS" =~ ^[0-9]+$ && "$WORKERS" -gt 0 ]]; then
    echo "Error: --workers must be positive integer"
    exit 1
fi
if ! [[ "$RPS" =~ ^[0-9]+$ && "$RPS" -gt 0 ]]; then
    echo "Error: --rps must be positive integer"
    exit 1
fi

host_from_url() {
    echo "$1" | sed -E 's#^[a-zA-Z]+://([^/:]+).*#\1#'
}

is_private_host() {
    local h="$1"
    [[ "$h" == "localhost" || "$h" == "127.0.0.1" || "$h" == "::1" ]] && return 0
    [[ "$h" =~ ^10\. ]] && return 0
    [[ "$h" =~ ^192\.168\. ]] && return 0
    [[ "$h" =~ ^172\.(1[6-9]|2[0-9]|3[0-1])\. ]] && return 0
    [[ "$h" =~ \.local$ ]] && return 0
    return 1
}

TARGET_HOST="$(host_from_url "$URL")"
if ! is_private_host "$TARGET_HOST"; then
    if [[ "$ALLOW_PUBLIC" -ne 1 || "${I_UNDERSTAND:-}" != "YES" ]]; then
        echo "Refusing public target: $TARGET_HOST"
        echo "If you are authorized, rerun with --allow-public and I_UNDERSTAND=YES"
        exit 1
    fi
fi

TMP_DIR="$(mktemp -d)"
RESULTS_FILE="${TMP_DIR}/results.log"
trap 'rm -rf "$TMP_DIR"' EXIT

PER_WORKER_RPS="$(awk -v r="$RPS" -v w="$WORKERS" 'BEGIN { printf "%.6f", r / w }')"
SLEEP_SECS="$(awk -v p="$PER_WORKER_RPS" 'BEGIN { if (p <= 0) print "1"; else printf "%.6f", 1 / p }')"
END_TS=$(( $(date +%s) + DURATION ))

build_curl_cmd() {
    local -a cmd
    cmd=(curl -sS -o /dev/null --max-time "$TIMEOUT" -X "$METHOD")
    for h in "${HEADERS[@]:-}"; do
        cmd+=(-H "$h")
    done
    if [[ -n "$BODY" ]]; then
        cmd+=(--data "$BODY")
    fi
    cmd+=(-w "%{http_code} %{time_total}")
    cmd+=("$URL")
    printf '%q ' "${cmd[@]}"
}

CMD_STR="$(build_curl_cmd)"

worker() {
    local wid="$1"
    local now out
    while true; do
        now="$(date +%s)"
        [[ "$now" -ge "$END_TS" ]] && break
        out="$(eval "$CMD_STR" 2>/dev/null || echo "000 0")"
        echo "$out" >> "$RESULTS_FILE"
        sleep "$SLEEP_SECS"
    done
    echo "worker-$wid done" >/dev/null
}

echo "[*] Target: $URL"
echo "[*] Duration: ${DURATION}s | Workers: $WORKERS | RPS cap: $RPS (~${PER_WORKER_RPS}/worker)"
echo "[*] Method: $METHOD | Timeout: ${TIMEOUT}s"
echo "[*] Running..."

for i in $(seq 1 "$WORKERS"); do
    worker "$i" &
done
wait

TOTAL="$(wc -l < "$RESULTS_FILE" | tr -d ' ')"
OK2XX="$(awk '$1 ~ /^2/ {c++} END {print c+0}' "$RESULTS_FILE")"
ERR4XX="$(awk '$1 ~ /^4/ {c++} END {print c+0}' "$RESULTS_FILE")"
ERR5XX="$(awk '$1 ~ /^5/ {c++} END {print c+0}' "$RESULTS_FILE")"
ERR000="$(awk '$1 == "000" {c++} END {print c+0}' "$RESULTS_FILE")"

P50="$(awk '{print $2}' "$RESULTS_FILE" | sort -n | awk '{a[NR]=$1} END {if (NR==0) {print 0; exit} i=int(NR*0.50); if (i<1)i=1; print a[i] }')"
P95="$(awk '{print $2}' "$RESULTS_FILE" | sort -n | awk '{a[NR]=$1} END {if (NR==0) {print 0; exit} i=int(NR*0.95); if (i<1)i=1; print a[i] }')"
AVG="$(awk '{s+=$2} END {if (NR==0) print 0; else printf "%.6f", s/NR}' "$RESULTS_FILE")"

echo
echo "=== Summary ==="
echo "Total Requests : $TOTAL"
echo "2xx            : $OK2XX"
echo "4xx            : $ERR4XX"
echo "5xx            : $ERR5XX"
echo "Timeout/Fail   : $ERR000"
echo "Latency avg    : ${AVG}s"
echo "Latency p50    : ${P50}s"
echo "Latency p95    : ${P95}s"
echo
echo "Top Status Codes:"
awk '{print $1}' "$RESULTS_FILE" | sort | uniq -c | sort -nr | head -n 10


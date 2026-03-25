#!/usr/bin/env bash
set -euo pipefail

DISPLAYFLOW_ENV_FILE="${DISPLAYFLOW_ENV_FILE:-/etc/default/displayflow}"

if [ -f "$DISPLAYFLOW_ENV_FILE" ]; then
    # shellcheck disable=SC1090
    . "$DISPLAYFLOW_ENV_FILE"
fi

DISPLAYFLOW_BASE_DIR="${DISPLAYFLOW_BASE_DIR:-/usr/local/lib/displayflow}"
DISPLAYFLOW_CONFIG_DIR="${DISPLAYFLOW_CONFIG_DIR:-/etc/displayflow}"
DISPLAYFLOW_STATE_DIR="${DISPLAYFLOW_STATE_DIR:-/var/lib/displayflow}"
DISPLAYFLOW_LOG_DIR="${DISPLAYFLOW_LOG_DIR:-/var/log/displayflow}"

DISPLAYFLOW_DEVICE_CONFIG="${DISPLAYFLOW_DEVICE_CONFIG:-${DISPLAYFLOW_CONFIG_DIR}/config.json}"
DISPLAYFLOW_STATE_FILE="${DISPLAYFLOW_STATE_FILE:-${DISPLAYFLOW_STATE_DIR}/state.json}"
DISPLAYFLOW_WPA_CONFIG="${DISPLAYFLOW_WPA_CONFIG:-${DISPLAYFLOW_CONFIG_DIR}/wpa_supplicant.conf}"
DISPLAYFLOW_SYSTEM_WPA_CONFIG="${DISPLAYFLOW_SYSTEM_WPA_CONFIG:-/etc/wpa_supplicant/wpa_supplicant.conf}"

DISPLAYFLOW_INTERFACE="${DISPLAYFLOW_INTERFACE:-wlan0}"
DISPLAYFLOW_AP_HOST="${DISPLAYFLOW_AP_HOST:-192.168.4.1}"
DISPLAYFLOW_AP_CIDR="${DISPLAYFLOW_AP_CIDR:-192.168.4.1/24}"
DISPLAYFLOW_AP_NETWORK_CIDR="${DISPLAYFLOW_AP_NETWORK_CIDR:-192.168.4.0/24}"
DISPLAYFLOW_AP_DHCP_RANGE="${DISPLAYFLOW_AP_DHCP_RANGE:-192.168.4.50,192.168.4.150,255.255.255.0,12h}"
DISPLAYFLOW_AP_CHANNEL="${DISPLAYFLOW_AP_CHANNEL:-6}"
DISPLAYFLOW_AP_COUNTRY="${DISPLAYFLOW_AP_COUNTRY:-GB}"

DISPLAYFLOW_FAILURE_THRESHOLD="${DISPLAYFLOW_FAILURE_THRESHOLD:-3}"
DISPLAYFLOW_CONNECT_TIMEOUT="${DISPLAYFLOW_CONNECT_TIMEOUT:-60}"
DISPLAYFLOW_CONNECTIVITY_URL="${DISPLAYFLOW_CONNECTIVITY_URL:-https://connectivitycheck.gstatic.com/generate_204}"

DISPLAYFLOW_API_BASE_URL="${DISPLAYFLOW_API_BASE_URL:-https://babbage-ai.co.uk/Present}"
DISPLAYFLOW_PLAYER_PORT="${DISPLAYFLOW_PLAYER_PORT:-8080}"
DISPLAYFLOW_SETUP_PORT="${DISPLAYFLOW_SETUP_PORT:-80}"
DISPLAYFLOW_REFRESH_INTERVAL="${DISPLAYFLOW_REFRESH_INTERVAL:-300}"
DISPLAYFLOW_HEARTBEAT_INTERVAL="${DISPLAYFLOW_HEARTBEAT_INTERVAL:-60}"
DISPLAYFLOW_CACHE_NAMESPACE_PREFIX="${DISPLAYFLOW_CACHE_NAMESPACE_PREFIX:-screen}"

DISPLAYFLOW_FORCE_SETUP_FLAG="${DISPLAYFLOW_FORCE_SETUP_FLAG:-${DISPLAYFLOW_CONFIG_DIR}/force-setup}"
DISPLAYFLOW_BOOT_FORCE_SETUP_FLAG_NAME="${DISPLAYFLOW_BOOT_FORCE_SETUP_FLAG_NAME:-displayflow-force-setup}"
DISPLAYFLOW_BOOT_RESET_FLAG_NAME="${DISPLAYFLOW_BOOT_RESET_FLAG_NAME:-displayflow-reset-provisioning}"

displayflow_log() {
    local message="$1"
    printf '[%s] %s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "$message"
}

displayflow_require_root() {
    if [ "$(id -u)" -ne 0 ]; then
        echo "This command must run as root." >&2
        exit 1
    fi
}

displayflow_boot_dir() {
    local candidate
    for candidate in /boot/firmware /boot; do
        if [ -d "$candidate" ]; then
            printf '%s\n' "$candidate"
            return 0
        fi
    done

    return 1
}

displayflow_get_pi_user() {
    local user_name
    user_name="$(getent passwd 1000 | cut -d: -f1 || true)"

    if [ -z "$user_name" ]; then
        user_name="${DISPLAYFLOW_PI_USER:-pi}"
    fi

    printf '%s\n' "$user_name"
}

displayflow_get_pi_home() {
    local user_name
    user_name="${1:-$(displayflow_get_pi_user)}"
    eval echo "~${user_name}"
}

displayflow_player_dir() {
    local user_name
    user_name="${1:-$(displayflow_get_pi_user)}"
    printf '%s/cloud-signage/player\n' "$(displayflow_get_pi_home "$user_name")"
}

displayflow_player_config_path() {
    local user_name
    user_name="${1:-$(displayflow_get_pi_user)}"
    printf '%s/config.json\n' "$(displayflow_player_dir "$user_name")"
}

displayflow_ensure_runtime_dirs() {
    mkdir -p "$DISPLAYFLOW_CONFIG_DIR" "$DISPLAYFLOW_STATE_DIR" "$DISPLAYFLOW_LOG_DIR"
    chmod 0755 "$DISPLAYFLOW_CONFIG_DIR" "$DISPLAYFLOW_STATE_DIR" "$DISPLAYFLOW_LOG_DIR"
}

displayflow_unique_suffix() {
    local raw
    raw="$(tr -dc 'A-F0-9' < /etc/machine-id | cut -c1-4 || true)"

    if [ -z "$raw" ]; then
        raw="$(hostname | tr -dc 'A-Za-z0-9' | tr '[:lower:]' '[:upper:]' | cut -c1-4)"
    fi

    if [ -z "$raw" ]; then
        raw="0000"
    fi

    printf '%s\n' "$raw"
}

displayflow_setup_ssid() {
    printf 'DisplayFlow-Setup-%s\n' "$(displayflow_unique_suffix)"
}

displayflow_init_state_if_missing() {
    displayflow_ensure_runtime_dirs

    if [ -f "$DISPLAYFLOW_STATE_FILE" ]; then
        return 0
    fi

    python3 - "$DISPLAYFLOW_STATE_FILE" "$(displayflow_setup_ssid)" <<'PY'
import json
import sys
from pathlib import Path

state_path = Path(sys.argv[1])
ssid = sys.argv[2]

payload = {
    "mode": "unprovisioned",
    "provisioning_status": "unprovisioned",
    "consecutive_failures": 0,
    "last_error": "",
    "last_message": "Waiting for first provisioning",
    "setup_ssid": ssid,
    "updated_at": ""
}

tmp_path = state_path.with_suffix(".tmp")
tmp_path.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")
tmp_path.chmod(0o600)
tmp_path.replace(state_path)
PY
}

displayflow_json_get() {
    local file_path="$1"
    local key="$2"

    python3 - "$file_path" "$key" <<'PY'
import json
import sys
from pathlib import Path

path = Path(sys.argv[1])
key = sys.argv[2]

if not path.exists():
    sys.exit(1)

data = json.loads(path.read_text(encoding="utf-8"))
value = data

for part in key.split("."):
    if isinstance(value, dict) and part in value:
        value = value[part]
    else:
        sys.exit(1)

if isinstance(value, bool):
    print("true" if value else "false")
elif value is None:
    print("")
else:
    print(value)
PY
}

displayflow_json_quote() {
    python3 - "$1" <<'PY'
import json
import sys

print(json.dumps(sys.argv[1]))
PY
}

displayflow_state_merge() {
    local updates_json="$1"

    python3 - "$DISPLAYFLOW_STATE_FILE" "$updates_json" <<'PY'
import json
import sys
from datetime import datetime, timezone
from pathlib import Path

state_path = Path(sys.argv[1])
updates = json.loads(sys.argv[2])

current = {}
if state_path.exists():
    current = json.loads(state_path.read_text(encoding="utf-8"))

current.update(updates)
current["setup_ssid"] = current.get("setup_ssid") or ""
current["updated_at"] = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")

tmp_path = state_path.with_suffix(".tmp")
tmp_path.write_text(json.dumps(current, indent=2) + "\n", encoding="utf-8")
tmp_path.chmod(0o600)
tmp_path.replace(state_path)
PY
}

displayflow_has_valid_config() {
    python3 - "$DISPLAYFLOW_DEVICE_CONFIG" <<'PY'
import json
import sys
from pathlib import Path

path = Path(sys.argv[1])
if not path.exists():
    sys.exit(1)

try:
    data = json.loads(path.read_text(encoding="utf-8"))
except Exception:
    sys.exit(1)

required = ["wifi_ssid", "wifi_password", "screen_code"]
for key in required:
    value = str(data.get(key, "")).strip()
    if not value:
        sys.exit(1)

screen_code = str(data.get("screen_code", "")).strip()
if len(screen_code) < 4:
    sys.exit(1)
PY
}

displayflow_install_wifi_config() {
    python3 - "$DISPLAYFLOW_DEVICE_CONFIG" "$DISPLAYFLOW_WPA_CONFIG" "$DISPLAYFLOW_AP_COUNTRY" <<'PY'
import json
import subprocess
import sys
from pathlib import Path

config_path = Path(sys.argv[1])
target_path = Path(sys.argv[2])
country = sys.argv[3]

data = json.loads(config_path.read_text(encoding="utf-8"))
ssid = str(data["wifi_ssid"])
password = str(data["wifi_password"])

result = subprocess.run(
    ["wpa_passphrase", ssid, password],
    check=True,
    capture_output=True,
    text=True,
)

content = "country={}\nctrl_interface=DIR=/var/run/wpa_supplicant GROUP=netdev\nupdate_config=1\n\n{}".format(
    country,
    result.stdout.strip(),
)

tmp_path = target_path.with_suffix(".tmp")
tmp_path.write_text(content + "\n", encoding="utf-8")
tmp_path.chmod(0o600)
tmp_path.replace(target_path)
PY

    install -m 0600 "$DISPLAYFLOW_WPA_CONFIG" "$DISPLAYFLOW_SYSTEM_WPA_CONFIG"
}

displayflow_sync_player_config() {
    local pi_user
    local player_config
    local player_dir

    pi_user="$(displayflow_get_pi_user)"
    player_dir="$(displayflow_player_dir "$pi_user")"
    player_config="$(displayflow_player_config_path "$pi_user")"

    mkdir -p "$player_dir"

    python3 - "$DISPLAYFLOW_DEVICE_CONFIG" "$player_config" "$DISPLAYFLOW_API_BASE_URL" "$DISPLAYFLOW_REFRESH_INTERVAL" "$DISPLAYFLOW_HEARTBEAT_INTERVAL" "$DISPLAYFLOW_CACHE_NAMESPACE_PREFIX" <<'PY'
import json
import re
import sys
from pathlib import Path

device_path = Path(sys.argv[1])
player_path = Path(sys.argv[2])
api_base_url = sys.argv[3].rstrip("/")
refresh_interval = int(sys.argv[4])
heartbeat_interval = int(sys.argv[5])
cache_prefix = sys.argv[6]

device = json.loads(device_path.read_text(encoding="utf-8"))
screen_code = str(device["screen_code"]).strip().upper()
cache_namespace = "{}-{}".format(
    cache_prefix,
    re.sub(r"[^A-Z0-9]+", "-", screen_code).strip("-").lower() or "default",
)

payload = {
    "api_base_url": api_base_url,
    "screen_code": screen_code,
    "refresh_interval_seconds": refresh_interval,
    "heartbeat_interval_seconds": heartbeat_interval,
    "cache_namespace": cache_namespace,
}

tmp_path = player_path.with_suffix(".tmp")
tmp_path.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")
tmp_path.chmod(0o644)
tmp_path.replace(player_path)
PY

    chown "$pi_user:$pi_user" "$player_config"
}

displayflow_wait_for_wifi() {
    local deadline
    deadline=$((SECONDS + DISPLAYFLOW_CONNECT_TIMEOUT))

    while [ "$SECONDS" -lt "$deadline" ]; do
        if iw dev "$DISPLAYFLOW_INTERFACE" link 2>/dev/null | grep -q '^Connected to '; then
            return 0
        fi

        sleep 2
    done

    return 1
}

displayflow_check_connectivity_url() {
    curl -fsSIL --max-time 10 "$DISPLAYFLOW_CONNECTIVITY_URL" >/dev/null 2>&1
}

displayflow_verify_backend() {
    local screen_code
    local api_url
    local response_file

    screen_code="$(displayflow_json_get "$DISPLAYFLOW_DEVICE_CONFIG" "screen_code" | tr '[:lower:]' '[:upper:]')"
    api_url="${DISPLAYFLOW_API_BASE_URL%/}/api/get_screen_config.php?screen=${screen_code}"
    response_file="$(mktemp)"

    if ! curl -fsS --max-time 15 "$api_url" -o "$response_file"; then
        rm -f "$response_file"
        return 1
    fi

    python3 - "$response_file" <<'PY'
import json
import sys
from pathlib import Path

payload = json.loads(Path(sys.argv[1]).read_text(encoding="utf-8"))
if payload.get("success") is not True:
    raise SystemExit(1)
PY

    rm -f "$response_file"
}

displayflow_force_setup_requested() {
    local boot_dir

    if [ -f "$DISPLAYFLOW_FORCE_SETUP_FLAG" ]; then
        return 0
    fi

    boot_dir="$(displayflow_boot_dir || true)"
    if [ -n "$boot_dir" ] && [ -f "${boot_dir}/${DISPLAYFLOW_BOOT_FORCE_SETUP_FLAG_NAME}" ]; then
        return 0
    fi

    return 1
}

displayflow_reset_requested() {
    local boot_dir

    boot_dir="$(displayflow_boot_dir || true)"
    if [ -n "$boot_dir" ] && [ -f "${boot_dir}/${DISPLAYFLOW_BOOT_RESET_FLAG_NAME}" ]; then
        return 0
    fi

    return 1
}

displayflow_clear_boot_flag() {
    local file_name="$1"
    local boot_dir

    boot_dir="$(displayflow_boot_dir || true)"
    if [ -n "$boot_dir" ] && [ -f "${boot_dir}/${file_name}" ]; then
        rm -f "${boot_dir:?}/${file_name}"
    fi
}

#!/usr/bin/env bash
set -euo pipefail

SOURCE_PATH="${BASH_SOURCE[0]}"
if command -v readlink >/dev/null 2>&1; then
    RESOLVED_PATH="$(readlink -f "$SOURCE_PATH" 2>/dev/null || true)"
    if [ -n "$RESOLVED_PATH" ]; then
        SOURCE_PATH="$RESOLVED_PATH"
    fi
fi
SCRIPT_DIR="$(cd "$(dirname "$SOURCE_PATH")" && pwd)"
# shellcheck source=displayflow-common.sh
. "${SCRIPT_DIR}/displayflow-common.sh"

displayflow_require_root
displayflow_init_state_if_missing

displayflow_log "Applying newly submitted provisioning data."
displayflow_state_merge '{"mode":"transition","provisioning_status":"applying","last_error":"","last_message":"Switching from setup hotspot to venue Wi-Fi.","setup_hotspot_ready":false}'

rm -f "$DISPLAYFLOW_FORCE_SETUP_FLAG"
systemctl stop displayflow-setup-web.service >/dev/null 2>&1 || true
systemctl stop displayflow-setup-ap.service >/dev/null 2>&1 || true
systemctl stop cloud-signage-player.service >/dev/null 2>&1 || true

displayflow_install_wifi_config
ip addr flush dev "$DISPLAYFLOW_INTERFACE" >/dev/null 2>&1 || true
rfkill unblock wifi >/dev/null 2>&1 || true

systemctl start NetworkManager.service >/dev/null 2>&1 || true
systemctl start NetworkManager-wait-online.service >/dev/null 2>&1 || true
systemctl restart wpa_supplicant.service >/dev/null 2>&1 || true
systemctl restart "wpa_supplicant@${DISPLAYFLOW_INTERFACE}.service" >/dev/null 2>&1 || true
systemctl restart dhcpcd.service >/dev/null 2>&1 || true

if ! displayflow_wait_for_wifi; then
    displayflow_log "Provisioning failed: Wi-Fi association timed out."
    displayflow_state_merge '{"mode":"setup","provisioning_status":"failed","last_error":"Could not join the venue Wi-Fi network.","last_message":"Venue Wi-Fi connection failed. Reconnect to the setup hotspot and try again.","consecutive_failures":0,"setup_hotspot_ready":false}'
    systemctl start displayflow-setup-ap.service
    systemctl start displayflow-setup-web.service
    exit 1
fi

if ! displayflow_check_connectivity_url; then
    displayflow_log "Connectivity URL check failed during provisioning. Continuing to backend verification."
fi

if ! displayflow_verify_backend; then
    displayflow_log "Provisioning failed: backend verification failed."
    displayflow_state_merge '{"mode":"setup","provisioning_status":"failed","last_error":"Venue Wi-Fi connected, but the DisplayFlow backend could not be reached.","last_message":"Cloud verification failed. Reconnect to the setup hotspot and check internet access, firewall rules, and the screen code.","consecutive_failures":0,"setup_hotspot_ready":false}'
    systemctl start displayflow-setup-ap.service
    systemctl start displayflow-setup-web.service
    exit 1
fi

python3 - "$DISPLAYFLOW_DEVICE_CONFIG" <<'PY'
import json
import sys
from datetime import datetime, timezone
from pathlib import Path

config_path = Path(sys.argv[1])
payload = json.loads(config_path.read_text(encoding="utf-8"))
timestamp = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
payload["provisioned"] = True
payload["provisioned_at"] = payload.get("provisioned_at") or timestamp
payload["last_updated_at"] = timestamp
tmp_path = config_path.with_suffix(".tmp")
tmp_path.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")
tmp_path.chmod(0o600)
tmp_path.replace(config_path)
PY

displayflow_sync_player_config
displayflow_state_merge '{"mode":"normal","provisioning_status":"provisioned","last_error":"","last_message":"Provisioning complete. Rebooting into player mode.","consecutive_failures":0,"setup_hotspot_ready":false}'
systemctl start cloud-signage-player-update.service >/dev/null 2>&1 || true
systemctl start cloud-signage-player.service

sleep 3
systemctl reboot

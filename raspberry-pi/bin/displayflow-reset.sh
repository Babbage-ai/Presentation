#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=displayflow-common.sh
. "${SCRIPT_DIR}/displayflow-common.sh"

PRESERVE_FORCE_FLAG=0

if [ "${1:-}" = "--preserve-force-flag" ]; then
    PRESERVE_FORCE_FLAG=1
fi

displayflow_require_root
displayflow_init_state_if_missing

displayflow_log "Resetting local provisioning state."

systemctl stop cloud-signage-player.service >/dev/null 2>&1 || true
systemctl stop displayflow-setup-web.service >/dev/null 2>&1 || true
systemctl stop displayflow-setup-ap.service >/dev/null 2>&1 || true

rm -f "$DISPLAYFLOW_DEVICE_CONFIG" "$DISPLAYFLOW_WPA_CONFIG" "$DISPLAYFLOW_SYSTEM_WPA_CONFIG"

if [ "$PRESERVE_FORCE_FLAG" -eq 0 ]; then
    rm -f "$DISPLAYFLOW_FORCE_SETUP_FLAG"
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
    "last_message": "Provisioning reset. Device will return to setup mode on next boot.",
    "setup_ssid": ssid,
    "setup_hotspot_ready": False,
    "updated_at": ""
}

tmp_path = state_path.with_suffix(".tmp")
tmp_path.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")
tmp_path.chmod(0o600)
tmp_path.replace(state_path)
PY

displayflow_log "Provisioning reset complete."

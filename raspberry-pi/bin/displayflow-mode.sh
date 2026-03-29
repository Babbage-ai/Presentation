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

COMMAND="${1:-boot}"

enter_setup_mode() {
    local reason="$1"

    displayflow_log "Entering setup mode: ${reason}"
    displayflow_state_merge "{\"mode\":\"setup\",\"provisioning_status\":\"needs_setup\",\"last_error\":$(displayflow_json_quote "$reason"),\"last_message\":\"Starting setup hotspot.\",\"setup_hotspot_ready\":false}"

    systemctl stop cloud-signage-player.service >/dev/null 2>&1 || true
    systemctl stop cloud-signage-player-update.service >/dev/null 2>&1 || true
    systemctl start displayflow-setup-ap.service
    systemctl start displayflow-setup-web.service
}

start_normal_mode() {
    displayflow_log "Preparing normal player mode."

    rm -f "$DISPLAYFLOW_FORCE_SETUP_FLAG"
    systemctl stop displayflow-setup-web.service >/dev/null 2>&1 || true
    systemctl stop displayflow-setup-ap.service >/dev/null 2>&1 || true

    displayflow_install_wifi_config
    rfkill unblock wifi >/dev/null 2>&1 || true
    ip addr flush dev "$DISPLAYFLOW_INTERFACE" >/dev/null 2>&1 || true

    systemctl restart wpa_supplicant.service >/dev/null 2>&1 || true
    systemctl restart "wpa_supplicant@${DISPLAYFLOW_INTERFACE}.service" >/dev/null 2>&1 || true
    systemctl restart dhcpcd.service >/dev/null 2>&1 || true

    displayflow_sync_player_config
}

boot_flow() {
    local failures
    local reason

    displayflow_require_root
    displayflow_init_state_if_missing

    if displayflow_reset_requested; then
        displayflow_log "Boot reset flag detected."
        displayflow_clear_boot_flag "$DISPLAYFLOW_BOOT_RESET_FLAG_NAME"
        "${SCRIPT_DIR}/displayflow-reset.sh" --preserve-force-flag
    fi

    if ! displayflow_has_valid_config; then
        enter_setup_mode "Missing or invalid device configuration."
        exit 0
    fi

    if displayflow_force_setup_requested; then
        displayflow_clear_boot_flag "$DISPLAYFLOW_BOOT_FORCE_SETUP_FLAG_NAME"
        enter_setup_mode "Manual setup mode requested."
        exit 0
    fi

    start_normal_mode

    if ! displayflow_wait_for_wifi; then
        reason="Wi-Fi connection failed after ${DISPLAYFLOW_CONNECT_TIMEOUT} seconds."
        displayflow_state_merge "{\"mode\":\"setup\",\"provisioning_status\":\"failed\",\"consecutive_failures\":0,\"last_error\":$(displayflow_json_quote "$reason"),\"last_message\":\"Venue Wi-Fi is unavailable. Reconnect to the DisplayFlow setup hotspot to update settings.\",\"setup_hotspot_ready\":false}"
        enter_setup_mode "Wi-Fi failed on boot. Returning to setup mode."
        exit 0
    fi

    if ! displayflow_check_connectivity_url; then
        displayflow_log "Connectivity URL check failed. Continuing to backend verification."
    fi

    if ! displayflow_verify_backend; then
        failures="$(displayflow_json_get "$DISPLAYFLOW_STATE_FILE" "consecutive_failures" 2>/dev/null || printf '0\n')"
        failures=$((failures + 1))
        reason="Cloud backend verification failed."
        displayflow_state_merge "{\"mode\":\"degraded\",\"provisioning_status\":\"provisioned\",\"consecutive_failures\":${failures},\"last_error\":$(displayflow_json_quote "$reason"),\"last_message\":$(displayflow_json_quote "$reason"),\"setup_hotspot_ready\":false}"

        if [ "$failures" -ge "$DISPLAYFLOW_FAILURE_THRESHOLD" ]; then
            enter_setup_mode "Backend verification failed repeatedly. Re-entering setup mode."
            exit 0
        fi

        displayflow_log "${reason} Starting player in degraded mode."
        systemctl start cloud-signage-player.service
        exit 0
    fi

    displayflow_state_merge '{"mode":"normal","provisioning_status":"provisioned","consecutive_failures":0,"last_error":"","last_message":"Device ready for playback.","setup_hotspot_ready":false}'
    systemctl start cloud-signage-player-update.service >/dev/null 2>&1 || true
    systemctl start cloud-signage-player.service
}

case "$COMMAND" in
    boot)
        boot_flow
        ;;
    enter-setup)
        displayflow_require_root
        displayflow_init_state_if_missing
        enter_setup_mode "${2:-Manual request}"
        ;;
    start-normal)
        displayflow_require_root
        start_normal_mode
        ;;
    sync-player-config)
        displayflow_require_root
        displayflow_sync_player_config
        ;;
    verify-backend)
        displayflow_require_root
        displayflow_verify_backend
        ;;
    *)
        echo "Unknown command: $COMMAND" >&2
        exit 1
        ;;
esac

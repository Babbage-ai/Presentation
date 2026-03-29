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

COMMAND="${1:-start}"
HOSTAPD_RUNTIME_CONF="${DISPLAYFLOW_STATE_DIR}/hostapd.conf"
DNSMASQ_RUNTIME_CONF="${DISPLAYFLOW_STATE_DIR}/dnsmasq.conf"

write_configs() {
    local ssid
    ssid="$(displayflow_setup_ssid)"

    cat > "$HOSTAPD_RUNTIME_CONF" <<EOF
interface=${DISPLAYFLOW_INTERFACE}
driver=nl80211
ssid=${ssid}
hw_mode=g
channel=${DISPLAYFLOW_AP_CHANNEL}
country_code=${DISPLAYFLOW_AP_COUNTRY}
auth_algs=1
ignore_broadcast_ssid=0
wmm_enabled=1
EOF
    chmod 0600 "$HOSTAPD_RUNTIME_CONF"

    cat > "$DNSMASQ_RUNTIME_CONF" <<EOF
interface=${DISPLAYFLOW_INTERFACE}
bind-interfaces
dhcp-range=${DISPLAYFLOW_AP_DHCP_RANGE}
domain-needed
bogus-priv
no-resolv
address=/#/${DISPLAYFLOW_AP_HOST}
dhcp-option=3,${DISPLAYFLOW_AP_HOST}
dhcp-option=6,${DISPLAYFLOW_AP_HOST}
log-dhcp
EOF
    chmod 0600 "$DNSMASQ_RUNTIME_CONF"

    displayflow_state_merge "{\"setup_ssid\":$(displayflow_json_quote "$ssid"),\"mode\":\"setup\",\"setup_hotspot_ready\":false,\"last_error\":\"\",\"last_message\":\"Starting setup hotspot.\"}"
}

wait_for_hotspot_ready() {
    local attempts

    for attempts in $(seq 1 30); do
        if hotspot_is_ready; then
            return 0
        fi

        sleep 1
    done

    return 1
}

hotspot_is_ready() {
    if ! ip -4 addr show dev "$DISPLAYFLOW_INTERFACE" | grep -Fq "$DISPLAYFLOW_AP_HOST"; then
        return 1
    fi

    if ! pgrep -x hostapd >/dev/null 2>&1 || ! pgrep -x dnsmasq >/dev/null 2>&1; then
        return 1
    fi

    if command -v hostapd_cli >/dev/null 2>&1; then
        if hostapd_cli -i "$DISPLAYFLOW_INTERFACE" status 2>/dev/null | grep -q '^state=ENABLED$'; then
            return 0
        fi

        return 1
    fi

    if iw dev "$DISPLAYFLOW_INTERFACE" info 2>/dev/null | grep -q 'type AP'; then
        return 0
    fi

    return 1
}

start_ap() {
    displayflow_require_root
    displayflow_init_state_if_missing
    write_configs

    rfkill unblock wifi >/dev/null 2>&1 || true

    systemctl stop wpa_supplicant.service >/dev/null 2>&1 || true
    systemctl stop "wpa_supplicant@${DISPLAYFLOW_INTERFACE}.service" >/dev/null 2>&1 || true
    systemctl stop dhcpcd.service >/dev/null 2>&1 || true

    ip link set "$DISPLAYFLOW_INTERFACE" down || true
    ip addr flush dev "$DISPLAYFLOW_INTERFACE" || true
    ip addr add "$DISPLAYFLOW_AP_CIDR" dev "$DISPLAYFLOW_INTERFACE"
    ip link set "$DISPLAYFLOW_INTERFACE" up

    if pgrep -x hostapd >/dev/null 2>&1; then
        pkill -x hostapd || true
    fi
    if pgrep -x dnsmasq >/dev/null 2>&1; then
        pkill -x dnsmasq || true
    fi

    hostapd -B "$HOSTAPD_RUNTIME_CONF"
    dnsmasq --conf-file="$DNSMASQ_RUNTIME_CONF"

    if wait_for_hotspot_ready; then
        displayflow_state_merge "{\"setup_ssid\":$(displayflow_json_quote "$(displayflow_setup_ssid)"),\"mode\":\"setup\",\"setup_hotspot_ready\":true,\"last_error\":\"\",\"last_message\":\"Setup hotspot active.\"}"
    else
        displayflow_state_merge "{\"setup_ssid\":$(displayflow_json_quote "$(displayflow_setup_ssid)"),\"mode\":\"setup\",\"setup_hotspot_ready\":false,\"last_error\":\"Setup hotspot did not become ready in time.\",\"last_message\":\"Still starting the setup hotspot. If the SSID is already visible, connect now and open http://${DISPLAYFLOW_AP_HOST}.\"}"
    fi

    displayflow_log "Setup hotspot started on ${DISPLAYFLOW_INTERFACE} as $(displayflow_setup_ssid)."
}

stop_ap() {
    displayflow_require_root

    pkill -x hostapd >/dev/null 2>&1 || true
    pkill -x dnsmasq >/dev/null 2>&1 || true

    ip addr flush dev "$DISPLAYFLOW_INTERFACE" >/dev/null 2>&1 || true
    ip link set "$DISPLAYFLOW_INTERFACE" up >/dev/null 2>&1 || true

    systemctl restart wpa_supplicant.service >/dev/null 2>&1 || true
    systemctl restart "wpa_supplicant@${DISPLAYFLOW_INTERFACE}.service" >/dev/null 2>&1 || true
    systemctl restart dhcpcd.service >/dev/null 2>&1 || true

    displayflow_state_merge '{"setup_hotspot_ready":false}'

    displayflow_log "Setup hotspot stopped on ${DISPLAYFLOW_INTERFACE}."
}

case "$COMMAND" in
    start)
        start_ap
        ;;
    stop)
        stop_ap
        ;;
    restart)
        stop_ap
        start_ap
        ;;
    *)
        echo "Unknown command: $COMMAND" >&2
        exit 1
        ;;
esac

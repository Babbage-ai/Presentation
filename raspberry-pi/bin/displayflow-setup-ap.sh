#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
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

    displayflow_state_merge "{\"setup_ssid\":$(displayflow_json_quote "$ssid"),\"mode\":\"setup\",\"last_message\":\"Setup hotspot active.\"}"
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

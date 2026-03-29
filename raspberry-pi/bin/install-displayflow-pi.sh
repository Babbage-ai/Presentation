#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
PI_SOURCE_ROOT="${REPO_ROOT}/raspberry-pi"
PLAYER_SOURCE_DIR="${REPO_ROOT}/player"

while [ "$#" -gt 0 ]; do
    case "$1" in
        --pi-source)
            PI_SOURCE_ROOT="$2"
            shift 2
            ;;
        --player-source)
            PLAYER_SOURCE_DIR="$2"
            shift 2
            ;;
        *)
            echo "Unknown option: $1" >&2
            exit 1
            ;;
    esac
done

# shellcheck source=displayflow-common.sh
. "${SCRIPT_DIR}/displayflow-common.sh"

displayflow_require_root
displayflow_ensure_runtime_dirs
displayflow_init_state_if_missing

PI_USER="$(displayflow_get_pi_user)"
PI_HOME="$(displayflow_get_pi_home "$PI_USER")"
INSTALL_DIR="${PI_HOME}/cloud-signage"

export DEBIAN_FRONTEND=noninteractive
apt-get update

pick_chromium_package() {
    local package
    local candidate

    for package in chromium chromium-browser; do
        candidate="$(apt-cache policy "$package" 2>/dev/null | awk '/Candidate:/ { print $2; exit }')"
        if [ -n "$candidate" ] && [ "$candidate" != "(none)" ]; then
            printf '%s\n' "$package"
            return 0
        fi
    done

    return 1
}

if CHROMIUM_PACKAGE="$(pick_chromium_package)"; then
    CHROMIUM_CMD="$CHROMIUM_PACKAGE"
else
    echo "Could not find an installable Chromium package (checked: chromium, chromium-browser)." >&2
    exit 1
fi

apt-get install -y \
    "$CHROMIUM_PACKAGE" \
    curl \
    dnsmasq \
    hostapd \
    iproute2 \
    iw \
    rfkill \
    python3 \
    wpasupplicant \
    xserver-xorg \
    x11-xserver-utils \
    unclutter

mkdir -p "$INSTALL_DIR"
rm -rf "${INSTALL_DIR}/player"

if [ ! -d "$PLAYER_SOURCE_DIR" ]; then
    cat >&2 <<EOF
Missing player source directory:
  $PLAYER_SOURCE_DIR

This installer must be run from a full DisplayFlow checkout that includes both:
  - raspberry-pi/
  - player/

If you copied only raspberry-pi/ onto the Pi, also copy the repository's player/
directory and rerun with:
  sudo bash raspberry-pi/bin/install-displayflow-pi.sh --player-source /path/to/player
EOF
    exit 1
fi

cp -R "$PLAYER_SOURCE_DIR" "${INSTALL_DIR}/player"

if [ -f "${INSTALL_DIR}/player/config.json.example" ] && [ ! -f "${INSTALL_DIR}/player/config.json" ]; then
    cp "${INSTALL_DIR}/player/config.json.example" "${INSTALL_DIR}/player/config.json"
fi

rm -rf "$DISPLAYFLOW_BASE_DIR"
mkdir -p "$DISPLAYFLOW_BASE_DIR"
cp -R "${PI_SOURCE_ROOT}/bin" "$DISPLAYFLOW_BASE_DIR/"
cp -R "${PI_SOURCE_ROOT}/setup-ui" "$DISPLAYFLOW_BASE_DIR/"
chmod 0755 "$DISPLAYFLOW_BASE_DIR"/bin/*.sh
chmod 0755 "$DISPLAYFLOW_BASE_DIR"/bin/*.py

if [ ! -f "$DISPLAYFLOW_ENV_FILE" ]; then
    install -m 0644 "${PI_SOURCE_ROOT}/etc/displayflow.env.example" "$DISPLAYFLOW_ENV_FILE"
fi
install -m 0644 "${PI_SOURCE_ROOT}/systemd/displayflow-boot.service" /etc/systemd/system/displayflow-boot.service
install -m 0644 "${PI_SOURCE_ROOT}/systemd/displayflow-kiosk.service" /etc/systemd/system/displayflow-kiosk.service
install -m 0644 "${PI_SOURCE_ROOT}/systemd/displayflow-setup-ap.service" /etc/systemd/system/displayflow-setup-ap.service
install -m 0644 "${PI_SOURCE_ROOT}/systemd/displayflow-setup-web.service" /etc/systemd/system/displayflow-setup-web.service
install -m 0644 "${PI_SOURCE_ROOT}/systemd/cloud-signage-player-update.service" /etc/systemd/system/cloud-signage-player-update.service
install -m 0644 "${PI_SOURCE_ROOT}/systemd/cloud-signage-player-update.timer" /etc/systemd/system/cloud-signage-player-update.timer

sed \
    -e "s|__PI_USER__|${PI_USER}|g" \
    -e "s|__PI_HOME__|${PI_HOME}|g" \
    -e "s|__PLAYER_PORT__|${DISPLAYFLOW_PLAYER_PORT}|g" \
    "${PI_SOURCE_ROOT}/systemd/cloud-signage-player.service" > /etc/systemd/system/cloud-signage-player.service
chmod 0644 /etc/systemd/system/cloud-signage-player.service

sed \
    -e "s|__PI_USER__|${PI_USER}|g" \
    -e "s|__PI_HOME__|${PI_HOME}|g" \
    -e "s|__CHROMIUM_CMD__|${CHROMIUM_CMD}|g" \
    "${PI_SOURCE_ROOT}/systemd/displayflow-kiosk.service" > /etc/systemd/system/displayflow-kiosk.service
chmod 0644 /etc/systemd/system/displayflow-kiosk.service

install -m 0755 "${PI_SOURCE_ROOT}/bin/cloud-signage-player-update.sh" /usr/local/bin/cloud-signage-player-update.sh
ln -sf "${DISPLAYFLOW_BASE_DIR}/bin/displayflow-reset.sh" /usr/local/bin/displayflow-reset-provisioning
ln -sf "${DISPLAYFLOW_BASE_DIR}/bin/displayflow-mode.sh" /usr/local/bin/displayflow-mode

rm -f "${PI_HOME}/.config/lxsession/LXDE-pi/autostart"
rm -f "${PI_HOME}/.config/autostart/displayflow-kiosk.desktop"

if command -v raspi-config >/dev/null 2>&1; then
    raspi-config nonint do_boot_behaviour B4 || true
fi

CMDLINE_FILE=""
if [ -f /boot/firmware/cmdline.txt ]; then
    CMDLINE_FILE="/boot/firmware/cmdline.txt"
elif [ -f /boot/cmdline.txt ]; then
    CMDLINE_FILE="/boot/cmdline.txt"
fi

if [ -n "$CMDLINE_FILE" ] && ! grep -q 'consoleblank=0' "$CMDLINE_FILE"; then
    sed -i '1 s|$| consoleblank=0|' "$CMDLINE_FILE"
fi

mkdir -p "$DISPLAYFLOW_CONFIG_DIR" "$DISPLAYFLOW_STATE_DIR" "$DISPLAYFLOW_LOG_DIR"
chmod 0755 "$DISPLAYFLOW_CONFIG_DIR" "$DISPLAYFLOW_STATE_DIR" "$DISPLAYFLOW_LOG_DIR"

touch "${DISPLAYFLOW_LOG_DIR}/boot.log" "${DISPLAYFLOW_LOG_DIR}/setup-web.log" "${DISPLAYFLOW_LOG_DIR}/setup-ap.log" "${DISPLAYFLOW_LOG_DIR}/player.log" "${DISPLAYFLOW_LOG_DIR}/kiosk.log"
chmod 0644 "${DISPLAYFLOW_LOG_DIR}/boot.log" "${DISPLAYFLOW_LOG_DIR}/setup-web.log" "${DISPLAYFLOW_LOG_DIR}/setup-ap.log" "${DISPLAYFLOW_LOG_DIR}/player.log" "${DISPLAYFLOW_LOG_DIR}/kiosk.log"

chown -R "$PI_USER:$PI_USER" "$INSTALL_DIR" "$PI_HOME/.config"

systemctl disable --now hostapd >/dev/null 2>&1 || true
systemctl disable --now dnsmasq >/dev/null 2>&1 || true
systemctl enable NetworkManager.service >/dev/null 2>&1 || true
systemctl enable NetworkManager-wait-online.service >/dev/null 2>&1 || true

systemctl daemon-reload
systemctl enable displayflow-boot.service
systemctl enable displayflow-kiosk.service
systemctl enable cloud-signage-player-update.timer

cat > "${INSTALL_DIR}/SETUP-NEXT-STEPS.txt" <<EOF
DisplayFlow Raspberry Pi installation completed.

1. Edit ${DISPLAYFLOW_ENV_FILE} if the cloud API base URL is different.
2. Reboot or run: sudo systemctl start displayflow-boot.service
3. If the Pi is unprovisioned, connect to the hotspot shown as:
   $(displayflow_setup_ssid)
4. Open http://${DISPLAYFLOW_AP_HOST}
5. Enter the venue Wi-Fi details and screen code.

Manual reset:
  sudo displayflow-reset-provisioning
  sudo touch ${DISPLAYFLOW_FORCE_SETUP_FLAG}
EOF

chown "$PI_USER:$PI_USER" "${INSTALL_DIR}/SETUP-NEXT-STEPS.txt"

displayflow_log "DisplayFlow Pi runtime installed for ${PI_USER}."

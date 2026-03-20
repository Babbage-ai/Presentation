#!/usr/bin/env bash
set -euo pipefail

exec > >(tee -a /var/log/cloud-signage-firstboot.log) 2>&1

MARKER_FILE="/var/lib/cloud-signage-firstboot.done"

if [ -f "$MARKER_FILE" ]; then
    echo "Cloud Signage first-boot provisioning already completed."
    exit 0
fi

BOOT_DIR=""
for candidate in /boot/firmware /boot; do
    if [ -d "$candidate" ]; then
        BOOT_DIR="$candidate"
        break
    fi
done

if [ -z "$BOOT_DIR" ]; then
    echo "Could not locate the Raspberry Pi boot partition."
    exit 1
fi

PLAYER_SOURCE_DIR="$BOOT_DIR/cloud-signage-player"

if [ ! -d "$PLAYER_SOURCE_DIR" ]; then
    echo "Missing player files on boot partition: $PLAYER_SOURCE_DIR"
    echo "Copy the repository's player/ directory there before first boot."
    exit 1
fi

PI_USER="$(getent passwd 1000 | cut -d: -f1 || true)"

if [ -z "$PI_USER" ]; then
    echo "Could not determine the primary desktop user (uid 1000)."
    exit 1
fi

PI_HOME="$(eval echo "~${PI_USER}")"
INSTALL_DIR="${PI_HOME}/cloud-signage"
AUTOSTART_DIR="${PI_HOME}/.config/lxsession/LXDE-pi"
AUTOSTART_FILE="${AUTOSTART_DIR}/autostart"
SERVICE_FILE="/etc/systemd/system/cloud-signage-player.service"

echo "Boot partition: $BOOT_DIR"
echo "Primary user: $PI_USER"
echo "Install directory: $INSTALL_DIR"

export DEBIAN_FRONTEND=noninteractive
apt-get update

if apt-cache show chromium-browser >/dev/null 2>&1; then
    CHROMIUM_PACKAGE="chromium-browser"
    CHROMIUM_CMD="chromium-browser"
else
    CHROMIUM_PACKAGE="chromium"
    CHROMIUM_CMD="chromium"
fi

apt-get install -y \
    "$CHROMIUM_PACKAGE" \
    xserver-xorg \
    x11-xserver-utils \
    unclutter \
    python3

mkdir -p "$INSTALL_DIR"
rm -rf "${INSTALL_DIR}/player"
cp -R "$PLAYER_SOURCE_DIR" "${INSTALL_DIR}/player"

if [ ! -f "${INSTALL_DIR}/player/config.json" ] && [ -f "${INSTALL_DIR}/player/config.json.example" ]; then
    cp "${INSTALL_DIR}/player/config.json.example" "${INSTALL_DIR}/player/config.json"
fi

mkdir -p "$AUTOSTART_DIR"
cat > "$AUTOSTART_FILE" <<EOF
@xset s off
@xset -dpms
@xset s noblank
@unclutter -idle 0.1 -root
@${CHROMIUM_CMD} --kiosk --disable-infobars --autoplay-policy=no-user-gesture-required --overscroll-history-navigation=0 http://127.0.0.1:8080/player/player.html
EOF

cat > "$SERVICE_FILE" <<EOF
[Unit]
Description=Cloud Signage Local Player Server
After=network.target

[Service]
User=${PI_USER}
WorkingDirectory=${INSTALL_DIR}
ExecStart=/usr/bin/python3 -m http.server 8080
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

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

chown -R "$PI_USER:$PI_USER" "$INSTALL_DIR" "$PI_HOME/.config"

systemctl daemon-reload
systemctl enable cloud-signage-player.service
systemctl restart cloud-signage-player.service

mkdir -p "$(dirname "$MARKER_FILE")"
cat > "$MARKER_FILE" <<EOF
Cloud Signage first-boot provisioning completed on $(date -u '+%Y-%m-%d %H:%M:%S UTC')
EOF

cat > "${INSTALL_DIR}/SETUP-NEXT-STEPS.txt" <<EOF
Edit this file after first boot:
  ${INSTALL_DIR}/player/config.json

Set:
  - api_base_url
  - screen_token

Then reboot the Pi:
  sudo reboot
EOF

chown "$PI_USER:$PI_USER" "${INSTALL_DIR}/SETUP-NEXT-STEPS.txt"

echo "Cloud Signage first-boot provisioning completed."
echo "Edit ${INSTALL_DIR}/player/config.json, then reboot."

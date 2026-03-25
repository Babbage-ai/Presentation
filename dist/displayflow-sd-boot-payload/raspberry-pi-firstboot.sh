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
PI_SOURCE_DIR="$BOOT_DIR/cloud-signage-pi"

if [ ! -d "$PLAYER_SOURCE_DIR" ]; then
    echo "Missing player files on boot partition: $PLAYER_SOURCE_DIR"
    echo "Copy the repository's player/ directory there before first boot."
    exit 1
fi

if [ ! -d "$PI_SOURCE_DIR" ]; then
    echo "Missing Raspberry Pi runtime files on boot partition: $PI_SOURCE_DIR"
    echo "Copy the repository's raspberry-pi/ directory there before first boot."
    exit 1
fi

PI_USER="$(getent passwd 1000 | cut -d: -f1 || true)"

if [ -z "$PI_USER" ]; then
    echo "Could not determine the primary desktop user (uid 1000)."
    exit 1
fi

PI_HOME="$(eval echo "~${PI_USER}")"
INSTALL_DIR="${PI_HOME}/cloud-signage"

echo "Boot partition: $BOOT_DIR"
echo "Primary user: $PI_USER"
echo "Install directory: $INSTALL_DIR"

mkdir -p "$INSTALL_DIR"
rm -rf "${INSTALL_DIR}/raspberry-pi"
cp -R "$PI_SOURCE_DIR" "${INSTALL_DIR}/raspberry-pi"

bash "${INSTALL_DIR}/raspberry-pi/bin/install-displayflow-pi.sh" \
    --pi-source "${INSTALL_DIR}/raspberry-pi" \
    --player-source "$PLAYER_SOURCE_DIR"

systemctl start displayflow-boot.service || true

mkdir -p "$(dirname "$MARKER_FILE")"
cat > "$MARKER_FILE" <<EOF
Cloud Signage first-boot provisioning completed on $(date -u '+%Y-%m-%d %H:%M:%S UTC')
EOF

cat > "${INSTALL_DIR}/SETUP-NEXT-STEPS.txt" <<EOF
DisplayFlow first-boot install completed.

If the Pi has not been provisioned yet:
  1. Connect your phone to the hotspot:
     DisplayFlow-Setup-XXXX
  2. Open:
     http://192.168.4.1
  3. Enter the venue Wi-Fi details and screen code.

If needed, edit:
  /etc/default/displayflow

Manual reset:
  sudo displayflow-reset-provisioning
  sudo touch /etc/displayflow/force-setup
EOF

chown "$PI_USER:$PI_USER" "${INSTALL_DIR}/SETUP-NEXT-STEPS.txt"

echo "Cloud Signage first-boot provisioning completed."
echo "Provision the Pi from http://192.168.4.1 if no valid config exists yet."

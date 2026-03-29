#!/bin/bash
set -e

echo "DisplayFlow boot payload is deprecated."
echo "This SD card must be cloned from a preinstalled DisplayFlow master image."
echo "A stock Raspberry Pi OS card plus dist/ will boot to desktop and will not"
echo "have the offline hotspot, setup screen, or kiosk services installed."

bash /boot/firmware/raspberry-pi-firstboot.sh || bash /boot/raspberry-pi-firstboot.sh

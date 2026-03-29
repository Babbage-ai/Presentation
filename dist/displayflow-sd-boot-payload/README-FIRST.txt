DisplayFlow SD boot payload status
=================================

This folder is kept only as a compatibility notice.

It is NOT a supported way to prepare a customer Raspberry Pi anymore.
Copying these files onto a fresh Raspberry Pi OS boot partition will not
produce the DisplayFlow hotspot, setup screen, or kiosk player on first boot.

Why:
- The offline setup hotspot depends on packages and systemd services that must
  already be installed on the Pi image.
- Those packages include Chromium, hostapd, and dnsmasq.
- A stock Raspberry Pi OS image does not have the full DisplayFlow runtime
  preinstalled, so it can still boot straight to the desktop.

Supported workflow:
1. Flash Raspberry Pi OS Desktop to a staging card.
2. Boot the Pi with temporary internet access.
3. Run: sudo bash raspberry-pi/bin/install-displayflow-pi.sh
4. Confirm the Pi shows the DisplayFlow setup hotspot while offline.
5. Clone that prepared SD card for production/customer devices.

Reference docs:
- README.md
- raspberry-pi-setup.md
- raspberry-pi-shipping.md
- raspberry-pi-firstboot.md

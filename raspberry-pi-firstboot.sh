#!/usr/bin/env bash
set -euo pipefail

cat >&2 <<'EOF'
DisplayFlow first-boot installation is deprecated.

Reason:
  The Pi setup hotspot must work before venue Wi-Fi is known. Installing the
  runtime on the customer's first boot requires apt/network access before
  hostapd, dnsmasq, Chromium, and the DisplayFlow services exist, which breaks
  the offline provisioning flow.

Use the preinstalled image workflow instead:
  1. Boot a staging Pi with temporary internet access.
  2. Run: sudo bash raspberry-pi/bin/install-displayflow-pi.sh
  3. Set /etc/default/displayflow as needed.
  4. Verify the Pi boots into DisplayFlow setup mode while offline.
  5. Clone that prepared SD card for customer devices.

See:
  - README.md
  - raspberry-pi-setup.md
  - raspberry-pi-shipping.md
  - raspberry-pi-firstboot.md
EOF

exit 1

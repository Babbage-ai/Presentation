#!/usr/bin/env bash
set -euo pipefail

cat >&2 <<'EOF'
This script is deprecated.

DisplayFlow no longer supports preparing a stock Raspberry Pi OS boot
partition and expecting the Pi to install the runtime on customer first boot.
That design requires internet before the offline setup hotspot can exist.

Use the preinstalled image workflow instead:
  1. Flash Raspberry Pi OS Desktop to a staging SD card.
  2. Boot it on a bench network with temporary internet access.
  3. Run: sudo bash raspberry-pi/bin/install-displayflow-pi.sh
  4. Configure /etc/default/displayflow.
  5. Confirm the Pi boots into DisplayFlow setup mode while offline.
  6. Clone that prepared card for production/customer devices.

See raspberry-pi-shipping.md and raspberry-pi-setup.md.
EOF

exit 1

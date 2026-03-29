#!/usr/bin/env bash
set -euo pipefail

cat >&2 <<'EOF'
This script is deprecated.

DisplayFlow no longer supports shipping a boot-partition payload that installs
the Pi runtime during customer first boot. That flow cannot guarantee the
offline setup hotspot because required packages must be installed before venue
Wi-Fi has been configured.

Use the preinstalled image workflow instead:
  1. Prepare a master DisplayFlow Pi image on a staging Pi with internet.
  2. Install the runtime with raspberry-pi/bin/install-displayflow-pi.sh.
  3. Verify offline setup mode works.
  4. Clone the prepared SD card for distribution.

See raspberry-pi-shipping.md and raspberry-pi-setup.md.
EOF

exit 1

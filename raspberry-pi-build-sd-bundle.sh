#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$SCRIPT_DIR"
DIST_DIR="${REPO_ROOT}/dist"
STAGING_DIR="${DIST_DIR}/displayflow-sd-boot-payload"
ZIP_PATH="${DIST_DIR}/displayflow-sd-boot-payload.zip"
API_BASE_URL=""

usage() {
    cat <<'EOF'
Usage:
  ./raspberry-pi-build-sd-bundle.sh [--api-base-url https://example.com/app]

What it does:
  - Builds a boot-partition payload folder under dist/
  - Copies player/ to cloud-signage-player/
  - Copies raspberry-pi/ to cloud-signage-pi/
  - Copies raspberry-pi-firstboot.sh to the bundle root
  - Creates firstrun.sh
  - Writes an optional default API base URL into cloud-signage-pi/etc/displayflow.env.example
  - Produces dist/displayflow-sd-boot-payload.zip
EOF
}

require_arg() {
    local value="${2:-}"
    if [ -z "$value" ]; then
        echo "Missing value for $1" >&2
        exit 1
    fi
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        --api-base-url)
            require_arg "$1" "${2:-}"
            API_BASE_URL="$2"
            shift 2
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown argument: $1" >&2
            usage >&2
            exit 1
            ;;
    esac
done

PLAYER_SOURCE="${REPO_ROOT}/player"
PI_SOURCE="${REPO_ROOT}/raspberry-pi"
FIRSTBOOT_SOURCE="${REPO_ROOT}/raspberry-pi-firstboot.sh"

if [ ! -d "$PLAYER_SOURCE" ] || [ ! -d "$PI_SOURCE" ] || [ ! -f "$FIRSTBOOT_SOURCE" ]; then
    echo "Run this script from the repository root, or keep it beside the repo files." >&2
    exit 1
fi

rm -rf "$STAGING_DIR" "$ZIP_PATH"
mkdir -p "$STAGING_DIR"

cp -R "$PLAYER_SOURCE" "${STAGING_DIR}/cloud-signage-player"
cp -R "$PI_SOURCE" "${STAGING_DIR}/cloud-signage-pi"
install -m 0755 "$FIRSTBOOT_SOURCE" "${STAGING_DIR}/raspberry-pi-firstboot.sh"

if [ -n "$API_BASE_URL" ]; then
    python3 - "${STAGING_DIR}/cloud-signage-pi/etc/displayflow.env.example" "$API_BASE_URL" <<'PY'
import sys
from pathlib import Path

path = Path(sys.argv[1])
api_base_url = sys.argv[2].rstrip("/")
content = path.read_text(encoding="utf-8")
lines = []
updated = False

for line in content.splitlines():
    if line.startswith("DISPLAYFLOW_API_BASE_URL="):
        lines.append(f"DISPLAYFLOW_API_BASE_URL={api_base_url}")
        updated = True
    else:
        lines.append(line)

if not updated:
    lines.append(f"DISPLAYFLOW_API_BASE_URL={api_base_url}")

path.write_text("\n".join(lines) + "\n", encoding="utf-8")
PY
fi

cat > "${STAGING_DIR}/firstrun.sh" <<'EOF'
#!/bin/bash
set -e
bash /boot/firmware/raspberry-pi-firstboot.sh || bash /boot/raspberry-pi-firstboot.sh
EOF
chmod 0755 "${STAGING_DIR}/firstrun.sh"

(
    cd "$DIST_DIR"
    zip -qr "$(basename "$ZIP_PATH")" "$(basename "$STAGING_DIR")"
)

echo "SD boot payload built successfully."
echo "Folder: $STAGING_DIR"
echo "Zip:    $ZIP_PATH"
echo
echo "Next step on your Windows PC:"
echo "1. Download the zip from Codespaces."
echo "2. Open the SD card boot partition."
echo "3. Extract the zip contents into the boot partition root."

#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$SCRIPT_DIR"

BOOT_DIR=""
API_BASE_URL=""
FORCE=0

usage() {
    cat <<'EOF'
Usage:
  ./raspberry-pi-prep-sd.sh --boot-dir /path/to/boot [--api-base-url https://example.com/app] [--force]

What it does:
  - Copies player/ to BOOT_DIR/cloud-signage-player
  - Copies raspberry-pi/ to BOOT_DIR/cloud-signage-pi
  - Copies raspberry-pi-firstboot.sh to BOOT_DIR/
  - Creates or patches BOOT_DIR/firstrun.sh so the Pi installs DisplayFlow on first boot

Options:
  --boot-dir       Mounted Raspberry Pi boot partition on this PC
  --api-base-url   Optional default cloud base URL to write into the Pi runtime env template
  --force          Overwrite existing staged files without prompting
  -h, --help       Show this help
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
        --boot-dir)
            require_arg "$1" "${2:-}"
            BOOT_DIR="$2"
            shift 2
            ;;
        --api-base-url)
            require_arg "$1" "${2:-}"
            API_BASE_URL="$2"
            shift 2
            ;;
        --force)
            FORCE=1
            shift
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

if [ -z "$BOOT_DIR" ]; then
    echo "--boot-dir is required." >&2
    usage >&2
    exit 1
fi

BOOT_DIR="$(cd "$BOOT_DIR" && pwd)"

if [ ! -d "$BOOT_DIR" ]; then
    echo "Boot directory does not exist: $BOOT_DIR" >&2
    exit 1
fi

PLAYER_SOURCE="${REPO_ROOT}/player"
PI_SOURCE="${REPO_ROOT}/raspberry-pi"
FIRSTBOOT_SOURCE="${REPO_ROOT}/raspberry-pi-firstboot.sh"

if [ ! -d "$PLAYER_SOURCE" ] || [ ! -d "$PI_SOURCE" ] || [ ! -f "$FIRSTBOOT_SOURCE" ]; then
    echo "Run this script from the repository root, or keep it beside the repo files." >&2
    exit 1
fi

TARGET_PLAYER_DIR="${BOOT_DIR}/cloud-signage-player"
TARGET_PI_DIR="${BOOT_DIR}/cloud-signage-pi"
TARGET_FIRSTBOOT="${BOOT_DIR}/raspberry-pi-firstboot.sh"
TARGET_FIRSTRUN="${BOOT_DIR}/firstrun.sh"
BOOT_ENV_TEMPLATE="${TARGET_PI_DIR}/etc/displayflow.env.example"

if [ "$FORCE" -ne 1 ]; then
    for existing in "$TARGET_PLAYER_DIR" "$TARGET_PI_DIR" "$TARGET_FIRSTBOOT"; do
        if [ -e "$existing" ]; then
            echo "Target already exists: $existing" >&2
            echo "Re-run with --force to replace staged files." >&2
            exit 1
        fi
    done
fi

rm -rf "$TARGET_PLAYER_DIR" "$TARGET_PI_DIR"
mkdir -p "$TARGET_PLAYER_DIR" "$TARGET_PI_DIR"

cp -R "${PLAYER_SOURCE}/." "$TARGET_PLAYER_DIR/"
cp -R "${PI_SOURCE}/." "$TARGET_PI_DIR/"
install -m 0755 "$FIRSTBOOT_SOURCE" "$TARGET_FIRSTBOOT"

if [ -n "$API_BASE_URL" ]; then
    python3 - "$BOOT_ENV_TEMPLATE" "$API_BASE_URL" <<'PY'
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

FIRSTBOOT_HOOK='bash /boot/firmware/raspberry-pi-firstboot.sh || bash /boot/raspberry-pi-firstboot.sh'

if [ -f "$TARGET_FIRSTRUN" ]; then
    if ! grep -Fqx "$FIRSTBOOT_HOOK" "$TARGET_FIRSTRUN"; then
        printf '\n%s\n' "$FIRSTBOOT_HOOK" >> "$TARGET_FIRSTRUN"
    fi
else
    cat > "$TARGET_FIRSTRUN" <<EOF
#!/bin/bash
set -e
$FIRSTBOOT_HOOK
EOF
    chmod 0755 "$TARGET_FIRSTRUN"
fi

echo "DisplayFlow first-boot files staged successfully."
echo "Boot partition: $BOOT_DIR"
echo "Staged directories:"
echo "  - $TARGET_PLAYER_DIR"
echo "  - $TARGET_PI_DIR"
echo "Staged scripts:"
echo "  - $TARGET_FIRSTBOOT"
echo "  - $TARGET_FIRSTRUN"

if [ -n "$API_BASE_URL" ]; then
    echo "Default API base URL written into:"
    echo "  - $BOOT_ENV_TEMPLATE"
fi

echo
echo "Next step: eject the SD card, insert it into the Pi, and boot it."

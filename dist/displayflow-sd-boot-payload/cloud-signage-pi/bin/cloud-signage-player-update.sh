#!/usr/bin/env bash
set -euo pipefail

APP_BASE_URL="${1:-https://babbage-ai.co.uk/Present}"
PI_USER="${SUDO_USER:-pi}"
PI_HOME="$(eval echo "~${PI_USER}")"
PLAYER_DIR="${PI_HOME}/cloud-signage/player"
TMP_DIR="$(mktemp -d)"

cleanup() {
    rm -rf "$TMP_DIR"
}

trap cleanup EXIT

mkdir -p "$PLAYER_DIR"

download_file() {
    local remote_name="$1"
    local local_name="$2"
    curl -fsSL "${APP_BASE_URL%/}/player/${remote_name}" -o "${TMP_DIR}/${local_name}"
}

download_file "player.html" "player.html"
download_file "player.js" "player.js"
download_file "player.css" "player.css"

UPDATED_COUNT=0

install_if_changed() {
    local file_name="$1"
    local source_path="${TMP_DIR}/${file_name}"
    local target_path="${PLAYER_DIR}/${file_name}"

    if [ -f "$target_path" ] && cmp -s "$source_path" "$target_path"; then
        return 0
    fi

    install -m 0644 "$source_path" "$target_path"
    chown "${PI_USER}:${PI_USER}" "$target_path"
    UPDATED_COUNT=$((UPDATED_COUNT + 1))
}

install_if_changed "player.html"
install_if_changed "player.js"
install_if_changed "player.css"

if [ "$UPDATED_COUNT" -gt 0 ]; then
    echo "Player files updated from ${APP_BASE_URL%/}/player/ (${UPDATED_COUNT} file(s) changed)."
else
    echo "Player files checked from ${APP_BASE_URL%/}/player/ (no changes)."
fi

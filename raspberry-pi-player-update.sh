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

install -m 0644 "${TMP_DIR}/player.html" "${PLAYER_DIR}/player.html"
install -m 0644 "${TMP_DIR}/player.js" "${PLAYER_DIR}/player.js"
install -m 0644 "${TMP_DIR}/player.css" "${PLAYER_DIR}/player.css"

chown "${PI_USER}:${PI_USER}" \
    "${PLAYER_DIR}/player.html" \
    "${PLAYER_DIR}/player.js" \
    "${PLAYER_DIR}/player.css"

echo "Player files updated from ${APP_BASE_URL%/}/player/"

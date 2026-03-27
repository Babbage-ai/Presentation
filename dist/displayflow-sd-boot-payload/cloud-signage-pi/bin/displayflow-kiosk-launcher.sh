#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=displayflow-common.sh
. "${SCRIPT_DIR}/displayflow-common.sh"

CHROMIUM_CMD="${1:-chromium-browser}"
PLAYER_URL="http://127.0.0.1:${DISPLAYFLOW_PLAYER_PORT}/player/player.html"
# The Pi's own Chromium should use localhost for the setup UI.
# Phones and laptops still reach the same service via the hotspot address.
SETUP_URL="http://127.0.0.1:${DISPLAYFLOW_SETUP_PORT}/screen"
WAIT_SECONDS=30

pick_url() {
    local state_mode=""

    if [ -f "$DISPLAYFLOW_STATE_FILE" ]; then
        state_mode="$(displayflow_json_get "$DISPLAYFLOW_STATE_FILE" "mode" 2>/dev/null || true)"
    fi

    if displayflow_force_setup_requested; then
        printf '%s\n' "$SETUP_URL"
        return 0
    fi

    if [ "$state_mode" = "setup" ] || [ "$state_mode" = "transition" ] || ! displayflow_has_valid_config; then
        printf '%s\n' "$SETUP_URL"
        return 0
    fi

    printf '%s\n' "$PLAYER_URL"
}

wait_for_url() {
    local url="$1"
    local deadline

    deadline=$((SECONDS + WAIT_SECONDS))

    while [ "$SECONDS" -lt "$deadline" ]; do
        if curl -fsS --max-time 2 "$url" >/dev/null 2>&1; then
            return 0
        fi
        sleep 2
    done

    return 1
}

main() {
    local url

    sleep 8
    url="$(pick_url)"
    wait_for_url "$url" || true

    exec "$CHROMIUM_CMD" \
        --kiosk \
        --disable-infobars \
        --autoplay-policy=no-user-gesture-required \
        --overscroll-history-navigation=0 \
        --no-first-run \
        --disable-session-crashed-bubble \
        "$url"
}

main "$@"

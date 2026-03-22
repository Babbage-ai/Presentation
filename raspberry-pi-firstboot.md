# Raspberry Pi SD Card First-Boot Setup

This workflow is for a brand-new Raspberry Pi image while the SD card is still in your PC.

It does not fully install the player from the PC alone. Instead, it prepares the boot partition so the Pi installs the kiosk stack automatically during its first boot.

## What This Does

- copies the signage player files from the SD card boot partition into the Pi user's home directory
- installs Chromium and the local web server dependencies
- configures Chromium kiosk autostart
- creates the `cloud-signage-player.service` local HTTP service
- disables display blanking
- leaves `player/config.json` ready for you to fill in with the real cloud server URL and screen code

## Requirements

- Raspberry Pi OS with Desktop
- Raspberry Pi Imager advanced options used to preconfigure:
  - username/password
  - Wi-Fi if needed
  - SSH if you want remote access after boot

## SD Card Preparation On Your PC

After flashing the card, open the boot partition. Depending on your OS, it may be shown as `bootfs`, `boot`, or `system-boot`.

Copy these items from this repository onto the SD card boot partition:

1. Copy the repository's [`player`](/workspaces/Presentation/player) directory to:
   - `cloud-signage-player`

   Result on the boot partition:

```text
cloud-signage-player/
  config.json.example
  player.css
  player.html
  player.js
  sync-notes.md
```

2. Copy [`raspberry-pi-firstboot.sh`](/workspaces/Presentation/raspberry-pi-firstboot.sh) to the boot partition root.

3. Open the boot partition's existing `firstrun.sh` file and append this line near the end, before any final cleanup or reboot logic:

```bash
bash /boot/firmware/raspberry-pi-firstboot.sh || bash /boot/raspberry-pi-firstboot.sh
```

If your image does not already contain `firstrun.sh`, create it in the boot partition root with:

```bash
#!/bin/bash
set -e
bash /boot/firmware/raspberry-pi-firstboot.sh || bash /boot/raspberry-pi-firstboot.sh
```

## First Boot

Insert the SD card into the Pi and power it on.

On first boot, the Pi will:

- finish normal Raspberry Pi OS first-run tasks
- run [`raspberry-pi-firstboot.sh`](/workspaces/Presentation/raspberry-pi-firstboot.sh)
- install the local signage player stack

This can take several minutes depending on package download speed.

## After The Pi Boots

Log into the Pi locally or over SSH and edit:

- `/home/pi/cloud-signage/player/config.json`

Set:

- `api_base_url`
- `screen_code`

Example:

```json
{
    "api_base_url": "https://your-domain.example",
    "screen_code": "ABC123",
    "refresh_interval_seconds": 300,
    "heartbeat_interval_seconds": 60,
    "cache_namespace": "screen-main"
}
```

Then reboot:

```bash
sudo reboot
```

## Notes

- The script detects either `/boot/firmware` or `/boot` on the Pi.
- The script uses the first normal user account with uid `1000`.
- The local player is served from:
  - `http://127.0.0.1:8080/player/player.html`
- The service log for the provisioning run is written to:
  - `/var/log/cloud-signage-firstboot.log`

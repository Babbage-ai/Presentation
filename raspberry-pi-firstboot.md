# Raspberry Pi SD Card First-Boot Setup

This workflow is for a brand-new Raspberry Pi image while the SD card is still in your PC.

It prepares the boot partition so the Pi installs the kiosk stack, the provisioning services, and the headless setup hotspot automatically during its first boot.

## What This Does

- copies the signage player files from the SD card boot partition into the Pi user's home directory
- installs Chromium, hostapd, dnsmasq, and the local setup/runtime dependencies
- configures Chromium kiosk autostart
- creates the `cloud-signage-player.service` local HTTP service
- installs the `displayflow-boot.service`, setup AP service, and setup web service
- disables display blanking
- leaves the Pi ready to enter setup mode automatically if no valid device config exists

## Requirements

- Raspberry Pi OS with Desktop
- Raspberry Pi Imager advanced options used to preconfigure:
  - username/password
  - SSH if you want remote access after boot

## SD Card Preparation On Your PC

After flashing the card, open the boot partition. Depending on your OS, it may be shown as `bootfs`, `boot`, or `system-boot`.

The easiest option is to use the single prep script from this repo root:

```bash
chmod +x raspberry-pi-prep-sd.sh
./raspberry-pi-prep-sd.sh --boot-dir /media/your-user/bootfs --api-base-url https://your-domain.example/cloud-signage
```

That script copies the required files and creates or patches `firstrun.sh` automatically.

If you prefer to do it manually, use the steps below.

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

2. Copy the repository's [`raspberry-pi`](/workspaces/Presentation/raspberry-pi) directory to:
   - `cloud-signage-pi`

3. Copy [`raspberry-pi-firstboot.sh`](/workspaces/Presentation/raspberry-pi-firstboot.sh) to the boot partition root.

4. Open the boot partition's existing `firstrun.sh` file and append this line near the end, before any final cleanup or reboot logic:

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
- install the DisplayFlow setup hotspot and setup web app

This can take several minutes depending on package download speed.

## After The Pi Boots

If the Pi has no valid device config yet, it will start a hotspot such as `DisplayFlow-Setup-AB12`.

From a phone:

1. Join the hotspot.
2. Open `http://192.168.4.1`.
3. Enter the venue Wi-Fi SSID, password, and screen code.
4. Wait for the Pi to test connectivity and reboot into player mode.

If your deployment uses a different cloud base URL, edit `/etc/default/displayflow` before provisioning and set `DISPLAYFLOW_API_BASE_URL`.

## Notes

- The script detects either `/boot/firmware` or `/boot` on the Pi.
- The script uses the first normal user account with uid `1000`.
- The local player is served from:
  - `http://127.0.0.1:8080/player/player.html`
- The setup UI is served from:
  - `http://192.168.4.1`
- The service log for the provisioning run is written to:
  - `/var/log/cloud-signage-firstboot.log`

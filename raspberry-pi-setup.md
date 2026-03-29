# Raspberry Pi Setup Guide

## Architecture Summary

- `displayflow-boot.service` decides whether the Pi should run in normal player mode or setup mode.
- Device provisioning lives in `/etc/displayflow/config.json`.
- The player still reads `/home/pi/cloud-signage/player/config.json`, but that file is generated from the protected device config.
- Setup mode starts a temporary Wi-Fi hotspot with `hostapd` + `dnsmasq` and serves a mobile setup UI at `http://192.168.4.1`.
- Repeated Wi-Fi or backend failures automatically push the Pi back into setup mode.
- The Pi image must already contain the DisplayFlow runtime and required OS packages before the customer ever powers it on.

## 1. Prepare A Master Image

1. Flash Raspberry Pi OS Desktop using Raspberry Pi Imager.
2. Use Raspberry Pi Imager advanced options to pre-create a username and password.
3. Boot the Pi on a bench network with temporary internet access.
4. Update packages:

```bash
sudo apt update
sudo apt upgrade -y
```

## 2. Install The DisplayFlow Pi Runtime

From a checkout of this repository on the staging Pi:

```bash
sudo bash raspberry-pi/bin/install-displayflow-pi.sh
```

The installer:

- installs Chromium, `hostapd`, `dnsmasq`, and the Pi runtime dependencies
- copies the player files to `/home/pi/cloud-signage/player`
- installs the Pi scripts to `/usr/local/lib/displayflow`
- installs the systemd units
- configures Chromium kiosk autostart

If your cloud base URL differs from the default, edit:

```bash
sudo nano /etc/default/displayflow
```

Set `DISPLAYFLOW_API_BASE_URL` to the correct backend base URL.

This installation step is part of image preparation, not customer first boot.

## 3. Leave The Master Image Unprovisioned

Do not pre-fill `/etc/displayflow/config.json` unless the destination venue Wi-Fi and screen code are already known.

The normal shipping state is:

- runtime installed
- services enabled
- Chromium kiosk autostart installed
- no saved venue Wi-Fi
- no saved screen code

That state makes every cloned device boot into DisplayFlow setup mode automatically.

## 4. Customer Boot And Provisioning

On each boot, the Pi checks `/etc/displayflow/config.json` for:

- `wifi_ssid`
- `wifi_password`
- `screen_code`

If any are missing or invalid, or setup mode is forced, it starts a temporary hotspot such as `DisplayFlow-Setup-AB12`.

From the installer’s phone:

1. Join the setup hotspot.
2. Open `http://192.168.4.1`.
3. Enter the venue Wi-Fi name, venue Wi-Fi password, and screen code.
4. Submit the form.
5. The Pi saves the config atomically, tests venue Wi-Fi, verifies the cloud backend, then reboots into player mode.

If the Wi-Fi or backend check fails, the Pi returns to setup mode and brings the hotspot back automatically.

## 5. Services Installed

The runtime installs these services:

- `displayflow-boot.service`
- `displayflow-setup-ap.service`
- `displayflow-setup-web.service`
- `cloud-signage-player.service`
- `cloud-signage-player-update.service`
- `cloud-signage-player-update.timer`

Useful checks:

```bash
systemctl status displayflow-boot.service
systemctl status displayflow-setup-web.service
systemctl status cloud-signage-player.service
```

## 6. Keep The Display Awake

The autostart file disables screen blanking and DPMS power saving. If HDMI still sleeps on some hardware, add this to `/boot/firmware/cmdline.txt` or `/boot/cmdline.txt`:

```text
consoleblank=0
```

## 7. Operational Notes

- Create one screen record per Pi in the admin panel.
- Give the installer the matching screen code.
- Heartbeats update the screen’s `last_seen`, resolution, IP, and player version.
- Cached media remains available in Chromium’s local profile even if WAN access drops.
- If Wi-Fi or backend verification fails repeatedly on future boots, the Pi falls back into setup mode automatically.
- After you validate one master SD card, clone it for additional customer devices instead of relying on per-device first-boot installation.

## 8. Manual Reset And Forced Setup Mode

Wipe the saved Wi-Fi and pairing details completely:

```bash
sudo displayflow-reset-provisioning
sudo reboot
```

Force setup mode without deleting the saved config:

```bash
sudo touch /etc/displayflow/force-setup
sudo reboot
```

If you still have access to the boot partition, either of these flag files also works:

- `/boot/displayflow-force-setup`
- `/boot/displayflow-reset-provisioning`

## 9. Troubleshooting

Logs and state:

```bash
sudo journalctl -u displayflow-boot.service -b
sudo journalctl -u displayflow-setup-web.service -b
sudo journalctl -u cloud-signage-player.service -b
sudo cat /var/lib/displayflow/state.json
```

Common checks:

- Verify `/etc/default/displayflow` contains the correct `DISPLAYFLOW_API_BASE_URL`.
- Verify `/etc/displayflow/config.json` contains the expected screen code.
- Verify `wlan0` is the correct wireless interface on the Pi image.
- Verify the screen code exists in the cloud admin.
- Verify the SD card was cloned from a preinstalled master image rather than a stock Raspberry Pi OS image.

## 10. Shipping Workflow

Recommended process:

1. Build one master DisplayFlow SD card on a staging Pi with temporary internet access.
2. Install and configure the runtime.
3. Boot that card offline and confirm the setup hotspot appears.
4. Clone the card for production/customer devices.

The old boot-partition first-boot workflow in [`raspberry-pi-firstboot.md`](/workspaces/Presentation/raspberry-pi-firstboot.md) is deprecated because it cannot guarantee an offline setup hotspot on a fresh device.

## 11. Auto-Update Player On Boot And Daily

If you want each Pi to refresh its local player files from the live website before Chromium launches, and also check daily for player code updates:

1. Copy [`raspberry-pi-player-update.sh`](/workspaces/Presentation/raspberry-pi-player-update.sh) to the Pi:

```bash
sudo cp raspberry-pi-player-update.sh /usr/local/bin/cloud-signage-player-update.sh
sudo chmod 755 /usr/local/bin/cloud-signage-player-update.sh
```

2. Install the shipped unit files:

```bash
sudo cp raspberry-pi/systemd/cloud-signage-player-update.service /etc/systemd/system/cloud-signage-player-update.service
sudo cp raspberry-pi/systemd/cloud-signage-player-update.timer /etc/systemd/system/cloud-signage-player-update.timer
```

3. Edit `/etc/default/displayflow` if the API base URL differs from the default.

4. Enable the timer:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now cloud-signage-player-update.timer
```

This updates `player.html`, `player.js`, and `player.css` on each boot and then checks again daily. It does not overwrite the Pi’s local provisioning data.

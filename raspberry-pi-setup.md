# Raspberry Pi Setup Guide

## Architecture Summary

- `displayflow-boot.service` decides whether the Pi should run in normal player mode or setup mode.
- Device provisioning lives in `/etc/displayflow/config.json`.
- The player still reads `/home/pi/cloud-signage/player/config.json`, but that file is generated from the protected device config.
- Setup mode starts a temporary Wi-Fi hotspot with `hostapd` + `dnsmasq` and serves a mobile setup UI at `http://192.168.4.1`.
- Repeated Wi-Fi or backend failures automatically push the Pi back into setup mode.

## 1. Install Raspberry Pi OS

1. Flash Raspberry Pi OS Lite or Desktop using Raspberry Pi Imager.
2. Use Raspberry Pi Imager advanced options to pre-create a username and password.
3. Boot the Pi and update packages:

```bash
sudo apt update
sudo apt upgrade -y
```

## 2. Install The DisplayFlow Pi Runtime

From a checkout of this repository on the Pi:

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

## 3. First Boot And Provisioning

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

## 4. Services Installed

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

## 5. Keep The Display Awake

The autostart file disables screen blanking and DPMS power saving. If HDMI still sleeps on some hardware, add this to `/boot/firmware/cmdline.txt` or `/boot/cmdline.txt`:

```text
consoleblank=0
```

## 6. Operational Notes

- Create one screen record per Pi in the admin panel.
- Give the installer the matching screen code.
- Heartbeats update the screen’s `last_seen`, resolution, IP, and player version.
- Cached media remains available in Chromium’s local profile even if WAN access drops.
- If Wi-Fi or backend verification fails repeatedly on future boots, the Pi falls back into setup mode automatically.

## 7. Manual Reset And Forced Setup Mode

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

## 8. Troubleshooting

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

## 9. Fresh SD Card Workflow

If the Pi has not booted yet and the SD card is still in your PC, use the first-boot method documented in [`raspberry-pi-firstboot.md`](/workspaces/Presentation/raspberry-pi-firstboot.md).

That workflow lets you place both the player files and the Pi runtime onto the boot partition so the Pi installs the full setup stack automatically during its initial boot.

## 10. Auto-Update Player On Boot And Daily

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

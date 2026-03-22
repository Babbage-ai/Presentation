# Raspberry Pi Setup Guide

## 1. Install Raspberry Pi OS

1. Flash Raspberry Pi OS Lite or Desktop using Raspberry Pi Imager.
2. Boot the Pi and complete the basic setup.
3. Update packages:

```bash
sudo apt update
sudo apt upgrade -y
```

## 2. Install Chromium And Utilities

```bash
sudo apt install -y chromium-browser xserver-xorg x11-xserver-utils unclutter python3
```

On some Raspberry Pi OS versions the package name is `chromium` instead of `chromium-browser`.

## 3. Copy The Player Files

Copy the repository's `/player` folder to the Pi, for example:

```bash
mkdir -p /home/pi/cloud-signage
cp -R player /home/pi/cloud-signage/
```

Create the runtime config:

```bash
cp /home/pi/cloud-signage/player/config.json.example /home/pi/cloud-signage/player/config.json
nano /home/pi/cloud-signage/player/config.json
```

Set:

- `api_base_url`
- `screen_token`
- optional refresh and heartbeat intervals

## 4. Serve The Player Locally

The player should be served from a local HTTP URL instead of `file://` so Chromium can use IndexedDB reliably for cached media.

Quick option:

```bash
cd /home/pi/cloud-signage
python3 -m http.server 8080
```

Player URL:

```text
http://127.0.0.1:8080/player/player.html
```

For production, run that command under `systemd` or replace it with another lightweight static web server.

## 5. Autostart Chromium In Kiosk Mode

Create an autostart file:

```bash
mkdir -p /home/pi/.config/lxsession/LXDE-pi
nano /home/pi/.config/lxsession/LXDE-pi/autostart
```

Add:

```text
@xset s off
@xset -dpms
@xset s noblank
@unclutter -idle 0.1 -root
@chromium-browser --kiosk --disable-infobars --autoplay-policy=no-user-gesture-required --overscroll-history-navigation=0 http://127.0.0.1:8080/player/player.html
```

If your distribution uses `chromium` instead, replace `chromium-browser` with `chromium`.

## 6. Keep The Display Awake

The `xset` commands above disable screen blanking and DPMS power saving. If HDMI still sleeps on some hardware, add this to `/boot/firmware/cmdline.txt` or `/boot/cmdline.txt` as needed for the Pi image:

```text
consoleblank=0
```

## 7. Auto-Launch On Boot With systemd

Create a small local web server service:

```bash
sudo nano /etc/systemd/system/cloud-signage-player.service
```

Use:

```ini
[Unit]
Description=Cloud Signage Local Player Server
After=network.target

[Service]
User=pi
WorkingDirectory=/home/pi/cloud-signage
ExecStart=/usr/bin/python3 -m http.server 8080
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

Enable it:

```bash
sudo systemctl daemon-reload
sudo systemctl enable cloud-signage-player.service
sudo systemctl start cloud-signage-player.service
```

## 8. Operational Notes

- Create one screen record per Pi in the admin panel.
- Paste the matching token into `player/config.json`.
- Heartbeats update the screen's `last_seen`, `resolution`, IP, and player version.
- Cached media remains available in Chromium's local profile even if the WAN connection drops.

## 9. Fresh SD Card Workflow

If the Pi has not booted yet and the SD card is still in your PC, use the first-boot method documented in [`raspberry-pi-firstboot.md`](/workspaces/Presentation/raspberry-pi-firstboot.md).

That workflow lets you place the player files and provisioning script onto the boot partition first, then have the Pi install the kiosk stack automatically during its initial boot.

## 10. Auto-Update Player On Boot And Daily

If you want each Pi to refresh its local player files from the live website before Chromium launches, and also check daily for player code updates:

1. Copy [`raspberry-pi-player-update.sh`](/workspaces/Presentation/raspberry-pi-player-update.sh) to the Pi, for example:

```bash
sudo cp raspberry-pi-player-update.sh /usr/local/bin/cloud-signage-player-update.sh
sudo chmod 755 /usr/local/bin/cloud-signage-player-update.sh
```

2. Install the example `systemd` unit files:

```bash
sudo cp raspberry-pi-player-update.service.example /etc/systemd/system/cloud-signage-player-update.service
sudo cp raspberry-pi-player-update.timer.example /etc/systemd/system/cloud-signage-player-update.timer
```

If you need a different live URL, edit the service file and change the `ExecStart` value.

3. The service file should look like this:

```bash
sudo nano /etc/systemd/system/cloud-signage-player-update.service
```

Use:

```ini
[Unit]
Description=Update Cloud Signage Player Files
After=network-online.target
Wants=network-online.target
Before=cloud-signage-player.service

[Service]
Type=oneshot
User=root
ExecStart=/usr/local/bin/cloud-signage-player-update.sh https://babbage-ai.co.uk/Present

[Install]
WantedBy=multi-user.target
```

4. The timer file should look like this:

```bash
sudo nano /etc/systemd/system/cloud-signage-player-update.timer
```

Use:

```ini
[Unit]
Description=Daily Cloud Signage Player Update Check

[Timer]
OnBootSec=10min
OnUnitActiveSec=1d
RandomizedDelaySec=30min
Persistent=true
Unit=cloud-signage-player-update.service

[Install]
WantedBy=timers.target
```

5. Enable the boot-time refresh and the daily timer:

```bash
sudo systemctl daemon-reload
sudo systemctl enable cloud-signage-player-update.service
sudo systemctl enable --now cloud-signage-player-update.timer
```

This updates `player.html`, `player.js`, and `player.css` on each boot, then checks again daily. It does not overwrite the Pi's local `config.json`.

The updater script now skips unchanged files, so the daily check is safe to leave running permanently.

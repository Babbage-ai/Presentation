# Raspberry Pi Shipping Notes

Use this workflow when preparing Pis for customer sites where the installer only has power, HDMI, and a mobile phone.

## Recommended Pre-Ship State

- Build a master SD card on a staging Pi with temporary internet access.
- Flash Raspberry Pi OS Desktop and create the normal Pi username/password with Raspberry Pi Imager advanced options.
- Install the DisplayFlow runtime with [`raspberry-pi/bin/install-displayflow-pi.sh`](/workspaces/Presentation/raspberry-pi/bin/install-displayflow-pi.sh).
- Set the correct cloud base URL in `/etc/default/displayflow`.
- Do not pre-fill `/etc/displayflow/config.json` unless the customer venue Wi-Fi and screen code are already known.
- Leave the Pi unprovisioned so it boots into setup mode automatically on-site.
- Disconnect temporary internet and test that the setup hotspot appears and that `http://192.168.4.1` loads from a phone.
- Clone that prepared SD card for shipment instead of relying on a first-boot installer.

## Before Packing

- Boot once and confirm the setup SSID format is visible in `/var/lib/displayflow/state.json`.
- Confirm `displayflow-boot.service` is enabled.
- Confirm Chromium kiosk autostart and `cloud-signage-player.service` are installed.
- Confirm the shipped card came from the prepared master image, not a stock Raspberry Pi OS image.
- Label the device externally with:
  - the screen code to assign
  - the setup URL `http://192.168.4.1`
  - a support contact path

## Unsupported Workflow

Do not ship a stock Raspberry Pi OS card with files copied only onto the boot partition and expect the Pi to install DisplayFlow during customer first boot.

That workflow is intentionally deprecated because the offline setup hotspot depends on packages and services that must already be installed before the customer can enter venue Wi-Fi details.

## Support-Safe Defaults

- Provisioning data is stored outside the player code in `/etc/displayflow/config.json`.
- Player code updates only replace `player.html`, `player.js`, and `player.css`.
- A remote support engineer can force setup mode with:

```bash
sudo touch /etc/displayflow/force-setup
sudo reboot
```

- A full reset is:

```bash
sudo displayflow-reset-provisioning
sudo reboot
```

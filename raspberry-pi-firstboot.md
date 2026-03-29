# Raspberry Pi First-Boot Installer Status

## Architecture Summary

- DisplayFlow no longer treats customer first boot as the place where the Pi runtime is installed.
- The setup hotspot must work before venue Wi-Fi is known.
- Because `hostapd`, `dnsmasq`, Chromium, and the systemd units must already exist for offline provisioning, the Pi image must be preinstalled before shipment.
- `raspberry-pi-firstboot.sh`, `raspberry-pi-prep-sd.sh`, and `raspberry-pi-build-sd-bundle.sh` are deprecated and now exit with a warning instead of attempting installation.

## Why The Old Flow Was Wrong

The deprecated flow copied files onto the boot partition and relied on Raspberry Pi OS first-boot hooks to run the installer.

That design was not reliable for two reasons:

1. Raspberry Pi OS does not guarantee those hooks will run on every image or already-booted card.
2. The installer needs packages such as `hostapd`, `dnsmasq`, and Chromium before the Pi can expose the DisplayFlow setup hotspot, which means the customer device would need internet before the offline setup path exists.

That dependency order is backwards for a field-deployable signage device.

## Supported Replacement

Use the preinstalled image workflow described in:

- [`raspberry-pi-setup.md`](/workspaces/Presentation/raspberry-pi-setup.md)
- [`raspberry-pi-shipping.md`](/workspaces/Presentation/raspberry-pi-shipping.md)

In short:

1. Flash Raspberry Pi OS Desktop to a staging card.
2. Boot the Pi on a bench network with temporary internet access.
3. Run [`raspberry-pi/bin/install-displayflow-pi.sh`](/workspaces/Presentation/raspberry-pi/bin/install-displayflow-pi.sh).
4. Set `/etc/default/displayflow`.
5. Leave `/etc/displayflow/config.json` empty so the Pi boots into setup mode.
6. Verify the setup hotspot works while offline.
7. Clone the prepared card for shipped devices.

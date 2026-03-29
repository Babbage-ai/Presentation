# Cloud Signage MVP

## Project Overview

This repository contains a deployable Phase 1 MVP for a cloud-managed digital signage platform built with PHP 8, MySQL/MariaDB, and a local Raspberry Pi HTML/JavaScript player. The cloud side provides an admin panel for media, playlists, and screen assignment. The player side fetches playlist metadata, sends heartbeat updates, and caches media locally in the browser for resilient playback.

## Architecture Summary

- Cloud app: PHP admin and JSON API under `/admin`, `/api`, and `/includes`.
- Browser player: static files under `/player`, served locally on the Pi over `http://127.0.0.1:8080`.
- Pi runtime: provisioning scripts, setup UI, and systemd units under `/raspberry-pi`.
- Protected Pi config: `/etc/displayflow/config.json` on the device, with generated player config synced into `/home/pi/cloud-signage/player/config.json`.
- Boot controller: `displayflow-boot.service` decides between normal playback and setup hotspot mode, and falls back to setup mode after repeated connectivity failures.

## Features

- Admin login with password hashing and session-based auth
- Per-admin isolated presentation systems so each login only sees its own media, playlists, and screens
- Media upload for JPG, JPEG, PNG, WEBP, and MP4
- Quiz question management with countdown and answer reveal
- Playlist creation and item ordering
- Mixed playlists containing media, fixed quiz items, and random quiz markers
- Screen registration with unique 6-character codes
- Screen-to-playlist assignment
- Admin-triggered screen update push on the next player heartbeat
- Heartbeat logging and online/offline visibility
- JSON APIs for screen config, playlist retrieval, heartbeat, and controlled media download
- Raspberry Pi kiosk player with local cache fallback
- Optional Raspberry Pi boot-time and daily player code refresh

## File Tree

```text
admin/
api/
assets/
includes/
player/
raspberry-pi/
  bin/
  etc/
  setup-ui/
  systemd/
sql/
uploads/
```

## Installation Steps

1. Point your web root at this project directory.
2. Ensure PHP 8+ with `mysqli`, `fileinfo`, and session support is enabled.
3. Create a MySQL or MariaDB database.
4. Import [`sql/schema.sql`](/workspaces/Presentation/sql/schema.sql).
5. Import [`sql/seed_admin.sql`](/workspaces/Presentation/sql/seed_admin.sql).
6. Create the upload directory if it does not already exist: `/uploads/media`.
7. Make `/uploads/media` writable by the web server.
8. Set environment variables for database access:

```bash
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_NAME=cloud_signage_present
export DB_USER=your_db_user
export DB_PASS=your_db_password
export APP_URL=https://displayflow.co.uk
export APP_BASE_PATH=/cloud-signage
```

Set `APP_BASE_PATH` to an empty value or omit it if the project is deployed at the domain root. Use a subdirectory value such as `/cloud-signage` on standard shared hosting when the app is not mounted at `/`.

For a `displayflow.co.uk` style deployment where `/` acts as the marketing homepage, keep `index.php` as the public landing page and decide where the application lives:

- Root application install: keep the existing `/admin`, `/api`, and `/player` paths and set `APP_BASE_PATH` to an empty string.
- Subdirectory application install: move or expose the app under a path such as `/app`, then set `APP_URL=https://displayflow.co.uk` and `APP_BASE_PATH=/app`.

If your host does not provide environment variables easily, copy [`includes/config.local.php.example`](/workspaces/Presentation/includes/config.local.php.example) to `includes/config.local.php` and set the database and URL values there.

## Quick Docker Test

If you want a disposable PHP 8.3 + MariaDB test stack:

```bash
docker-compose up -d --build
```

Then open:

```text
http://127.0.0.1:8088/
```

Default seeded admin credentials:

- Username: `admin`
- Password: `ChangeMe123!`

## Database Import Steps

```bash
mysql -u your_db_user -p < sql/schema.sql
mysql -u your_db_user -p < sql/seed_admin.sql
```

For existing installs upgrading to per-admin isolated presentation systems, run:

```bash
mysql -u your_db_user -p < sql/migrations/20260319_add_admin_ownership.sql
```

This migration assigns existing media, playlists, and screens to the oldest admin account already in `admins`.

To add quiz-question support to an existing install, also run:

```bash
mysql -u your_db_user -p < sql/migrations/20260320_add_quiz_questions.sql
```

To add admin-pushed screen updates and random quiz markers to an existing install, also run:

```bash
mysql -u your_db_user -p < sql/migrations/20260320_add_screen_sync_and_random_quiz_markers.sql
```

To add dedicated 6-character screen codes for existing installs, also run:

```bash
mysql -u your_db_user -p < sql/migrations/20260322_add_screen_codes.sql
```

## Default Admin Creation

The seed SQL creates:

- Username: `admin`
- Password: `ChangeMe123!`

Change the password immediately after first login by generating a new hash:

```bash
php -r "echo password_hash('YourNewStrongPassword', PASSWORD_DEFAULT), PHP_EOL;"
```

Then update the `admins.password_hash` value in MySQL.

To create additional isolated presentation systems, insert more rows into `admins`. Each admin account automatically gets its own separate media library, playlists, and screens:

```sql
INSERT INTO admins (username, password_hash, created_at)
VALUES ('customer_a', '$2y$10$replace_with_password_hash', UTC_TIMESTAMP());
```

Generate the hash with:

```bash
php -r "echo password_hash('StrongPasswordHere', PASSWORD_DEFAULT), PHP_EOL;"
```

## Upload Folder Permissions

Example for Debian or Ubuntu with Apache:

```bash
sudo mkdir -p /path/to/project/uploads/media
sudo chown -R www-data:www-data /path/to/project/uploads
sudo chmod -R 755 /path/to/project/uploads
```

## How Raspberry Pi Devices Connect

1. Create a screen in the admin panel.
2. Copy the generated 6-character `screen code`.
3. Prepare a preinstalled DisplayFlow Pi image on a staging Pi with temporary internet access.
4. Install the Pi runtime with [`install-displayflow-pi.sh`](/workspaces/Presentation/raspberry-pi/bin/install-displayflow-pi.sh).
5. Clone that prepared SD card for customer devices.
6. Ship devices with no saved venue Wi-Fi so they boot into setup mode automatically.
4. If the Pi is unprovisioned, connect to the temporary `DisplayFlow-Setup-XXXX` hotspot and open `http://192.168.4.1`.
5. Enter the venue Wi-Fi SSID, password, and screen code.
6. The Pi saves the protected config to `/etc/displayflow/config.json`, verifies the backend, generates the player config, and boots into normal playback mode.

If the app is deployed in a subdirectory, include that in `api_base_url`, for example `https://example.com/cloud-signage`.

The player calls:

- [`api/get_screen_config.php`](/workspaces/Presentation/api/get_screen_config.php)
- [`api/get_playlist.php`](/workspaces/Presentation/api/get_playlist.php)
- [`api/heartbeat.php`](/workspaces/Presentation/api/heartbeat.php)
- [`api/download.php`](/workspaces/Presentation/api/download.php)

## API Overview

All API responses use this JSON shape:

```json
{
  "success": true,
  "message": "Human readable status",
  "data": {}
}
```

Endpoints:

- `GET /api/get_screen_config.php?screen=...`
- `GET /api/get_playlist.php?screen=...`
- `POST /api/heartbeat.php`
- `GET /api/download.php?screen=...&media_id=...`

## Notes On Media Delivery

The project includes `download.php` rather than relying only on direct static file URLs. This makes the player-side fetch flow simpler because:

- screen codes are validated before download
- CORS headers can be sent consistently from PHP
- the player can cache fetched blobs locally without extra web server rules

The API still exposes `full_url` for each item if direct access is useful later.

## Raspberry Pi Provisioning And Recovery

The Pi runtime adds:

- boot-time setup-mode decision logic
- a temporary Wi-Fi hotspot using `hostapd` + `dnsmasq`
- a local mobile setup UI at `http://192.168.4.1`
- atomic config writes to `/etc/displayflow/config.json`
- automatic fallback into setup mode after repeated Wi-Fi or backend failures
- manual recovery via `/etc/displayflow/force-setup` or `displayflow-reset-provisioning`

The player updater still refreshes only the player assets, so automatic updates do not wipe local provisioning data.

## Raspberry Pi Deployment Model

DisplayFlow now uses a preinstalled-image model for Raspberry Pi deployments.

- Install the runtime on a staging Pi while that staging device has temporary internet access.
- Do not rely on customer first boot to install OS packages or systemd units.
- Leave `/etc/displayflow/config.json` empty on shipped devices so the Pi boots into setup mode automatically on-site.
- After validation, clone the prepared SD card for production devices.

The deprecated boot-partition first-boot installer scripts now exit with a warning because that design could not guarantee an offline setup hotspot on a fresh customer device.

## Raspberry Pi Install Commands

From a checkout of this repo on the Pi:

```bash
sudo bash raspberry-pi/bin/install-displayflow-pi.sh
sudo nano /etc/default/displayflow
sudo systemctl start displayflow-boot.service
sudo reboot
```

Force setup mode again:

```bash
sudo touch /etc/displayflow/force-setup
sudo reboot
```

Reset provisioning completely:

```bash
sudo displayflow-reset-provisioning
sudo reboot
```

For customer-device preparation, see [`raspberry-pi-shipping.md`](/workspaces/Presentation/raspberry-pi-shipping.md).

The old boot-partition helper scripts [`raspberry-pi-prep-sd.sh`](/workspaces/Presentation/raspberry-pi-prep-sd.sh), [`raspberry-pi-build-sd-bundle.sh`](/workspaces/Presentation/raspberry-pi-build-sd-bundle.sh), and [`raspberry-pi-firstboot.sh`](/workspaces/Presentation/raspberry-pi-firstboot.sh) are deprecated and intentionally no longer perform installation.

## Next-Phase Improvement Ideas

- Admin password change page and forced first-login rotation
- API request signing or per-device secrets in headers
- Media thumbnails and duration extraction for videos
- Audit trail UI for screen logs
- Playlist scheduling and time windows
- Device-side filesystem sync daemon for large offline libraries

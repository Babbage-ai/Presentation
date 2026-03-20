# Cloud Signage MVP

## Project Overview

This repository contains a deployable Phase 1 MVP for a cloud-managed digital signage platform built with PHP 8, MySQL/MariaDB, and a local Raspberry Pi HTML/JavaScript player. The cloud side provides an admin panel for media, playlists, and screen assignment. The player side fetches playlist metadata, sends heartbeat updates, and caches media locally in the browser for resilient playback.

## Features

- Admin login with password hashing and session-based auth
- Per-admin isolated presentation systems so each login only sees its own media, playlists, and screens
- Media upload for JPG, JPEG, PNG, WEBP, and MP4
- Quiz question management with countdown and answer reveal
- Playlist creation and item ordering
- Mixed playlists containing media, fixed quiz items, and random quiz markers
- Screen registration with unique tokens
- Screen-to-playlist assignment
- Admin-triggered screen update push on the next player heartbeat
- Heartbeat logging and online/offline visibility
- JSON APIs for screen config, playlist retrieval, heartbeat, and controlled media download
- Raspberry Pi kiosk player with local cache fallback

## Folder Structure

```text
/admin      Admin UI pages
/api        JSON API endpoints
/includes   Shared DB, auth, layout, and helper functions
/player     Raspberry Pi player files
/sql        Schema and seed SQL
/uploads    Uploaded media storage
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
export DB_NAME=cloud_signage
export DB_USER=your_db_user
export DB_PASS=your_db_password
export APP_URL=https://your-domain.example
export APP_BASE_PATH=/cloud-signage
```

Set `APP_BASE_PATH` to an empty value or omit it if the project is deployed at the domain root. Use a subdirectory value such as `/cloud-signage` on standard shared hosting when the app is not mounted at `/`.

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
2. Copy the generated `screen_token`.
3. Copy [`player/config.json.example`](/workspaces/Presentation/player/config.json.example) to `player/config.json`.
4. Set `api_base_url` and `screen_token`.
5. Serve the player locally on the Pi.
6. Launch Chromium in kiosk mode against the local player URL.

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

- `GET /api/get_screen_config.php?token=...`
- `GET /api/get_playlist.php?token=...`
- `POST /api/heartbeat.php`
- `GET /api/download.php?token=...&media_id=...`

## Notes On Media Delivery

The project includes `download.php` rather than relying only on direct static file URLs. This makes the player-side fetch flow simpler because:

- screen tokens are validated before download
- CORS headers can be sent consistently from PHP
- the player can cache fetched blobs locally without extra web server rules

The API still exposes `full_url` for each item if direct access is useful later.

## Next-Phase Improvement Ideas

- Admin password change page and forced first-login rotation
- API request signing or per-device secrets in headers
- Media thumbnails and duration extraction for videos
- Audit trail UI for screen logs
- Playlist scheduling and time windows
- Device-side filesystem sync daemon for large offline libraries

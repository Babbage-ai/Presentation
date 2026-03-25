# Player Sync Notes

## Practical cache approach

This Phase 1 player uses Chromium's local IndexedDB storage to cache downloaded media blobs on the Raspberry Pi. That keeps the implementation deployable with plain HTML and JavaScript, without requiring a native helper daemon or Node backend on the device.

Because of that:

- Serve the `/player` folder from a small local web server on the Pi such as `python3 -m http.server 8080`.
- Open `http://127.0.0.1:8080/player/player.html` in kiosk mode instead of using a `file://` URL.
- The `cache_namespace` value in `config.json` separates caches when a Pi is reassigned to a new screen code or environment.
- You can also pass `screen` and `api_base_url` on the player URL, which avoids editing `config.json` for normal screen changes.

Example:

```text
http://127.0.0.1:8080/player/player.html?screen=ABC123&api_base_url=https://babbage-ai.co.uk/Present
```

## Sync behavior

- The player fetches the playlist from `/api/get_playlist.php`.
- Each item includes a `download_url` that validates the screen code before serving the file.
- The player stores successful downloads locally in IndexedDB.
- Playback prefers cached blobs when available.
- If an item is not cached yet, the player falls back to the remote `download_url`.
- If the network drops after media was cached, playback continues from the cached blobs.
- Broken files are skipped and the loop continues.

## Operational notes

- IndexedDB cache persistence is browser-managed. Keep the Pi on a stable Chromium profile and avoid aggressive profile cleanup.
- If you need filesystem-level caching later, add a small local sync daemon in Phase 2 and have the player read from a local static media folder.

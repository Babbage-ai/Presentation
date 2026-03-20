<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

set_api_headers();

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
if ($token === '') {
    json_response(false, 'Missing screen token.', [], 400);
}

$db = get_db();
sync_screen_statuses($db);
$screen = find_screen_by_token($db, $token);

if (!$screen) {
    json_response(false, 'Invalid screen token.', [], 401);
}

$playlistSummary = null;
if (!empty($screen['playlist_id'])) {
    $items = fetch_playlist_items($db, (int) $screen['playlist_id']);
    $playlistSummary = [
        'id' => (int) $screen['playlist_id'],
        'name' => $screen['playlist_name'],
        'active' => (int) ($screen['playlist_active'] ?? 0) === 1,
        'item_count' => count($items),
    ];
}

json_response(true, 'Screen configuration loaded.', [
    'screen' => [
        'id' => (int) $screen['id'],
        'name' => $screen['name'],
        'location' => $screen['location'],
        'status' => screen_is_online($screen['last_seen']) ? 'online' : 'offline',
        'last_seen' => $screen['last_seen'],
        'resolution' => $screen['resolution'],
        'player_version' => $screen['player_version'],
        'sync_revision' => (int) $screen['sync_revision'],
    ],
    'playlist' => $playlistSummary,
]);

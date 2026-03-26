<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

set_api_headers();

$token = trim((string) ($_GET['screen'] ?? $_GET['token'] ?? $_POST['screen'] ?? $_POST['token'] ?? ''));
if ($token === '') {
    json_response(false, 'Missing screen code.', [], 400);
}

$db = get_db();
sync_screen_statuses($db);
$screen = find_screen_by_token($db, $token);

if (!$screen) {
    json_response(false, 'Invalid screen code.', [], 401);
}

if ((int) ($screen['active'] ?? 1) !== 1) {
    json_response(false, 'Screen is inactive.', [], 403);
}

$assignment = resolve_screen_playlist_assignment($db, $screen);

$playlistSummary = null;
if (!empty($assignment['playlist_id'])) {
    $items = fetch_playlist_items($db, (int) $assignment['playlist_id']);
    $playlistSummary = [
        'id' => (int) $assignment['playlist_id'],
        'name' => $assignment['playlist_name'],
        'active' => (int) ($screen['playlist_active'] ?? 0) === 1,
        'item_count' => count($items),
        'source' => $assignment['source'],
    ];
}

json_response(true, 'Screen configuration loaded.', [
    'screen' => [
        'id' => (int) $screen['id'],
        'name' => $screen['name'],
        'location' => $screen['location'],
        'active' => (int) ($screen['active'] ?? 1) === 1,
        'status' => screen_is_online($screen['last_seen']) ? 'online' : 'offline',
        'last_seen' => $screen['last_seen'],
        'resolution' => $screen['resolution'],
        'player_version' => $screen['player_version'],
        'sync_revision' => (int) $screen['sync_revision'],
        'reload_revision' => (int) ($screen['reload_revision'] ?? 0),
        'schedule_timezone' => $assignment['schedule_timezone'],
        'schedule_rule' => $assignment['schedule_rule'],
    ],
    'playlist' => $playlistSummary,
]);

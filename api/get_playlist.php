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

if (empty($screen['playlist_id'])) {
    json_response(true, 'No playlist assigned to this screen.', [
        'screen' => [
            'id' => (int) $screen['id'],
            'name' => $screen['name'],
            'location' => $screen['location'],
        ],
        'playlist' => null,
        'items' => [],
    ]);
}

$items = fetch_playlist_items($db, (int) $screen['playlist_id']);
$formattedItems = [];

foreach ($items as $item) {
    $formattedItems[] = [
        'media_id' => (int) $item['media_id'],
        'title' => $item['title'],
        'type' => $item['media_type'],
        'filename' => $item['filename'],
        'full_url' => $item['full_url'],
        'download_url' => absolute_url('api/download.php?token=' . rawurlencode($token) . '&media_id=' . (int) $item['media_id']),
        'duration' => $item['media_type'] === 'image' ? max(1, (int) $item['image_duration']) : null,
        'sort_order' => (int) $item['sort_order'],
    ];
}

json_response(true, 'Playlist loaded.', [
    'screen' => [
        'id' => (int) $screen['id'],
        'name' => $screen['name'],
        'location' => $screen['location'],
    ],
    'playlist' => [
        'id' => (int) $screen['playlist_id'],
        'name' => $screen['playlist_name'],
    ],
    'items' => $formattedItems,
]);

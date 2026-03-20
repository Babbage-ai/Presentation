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
            'sync_revision' => (int) $screen['sync_revision'],
        ],
        'playlist' => null,
        'items' => [],
    ]);
}

$items = fetch_playlist_items($db, (int) $screen['playlist_id'], (int) $screen['id']);
$formattedItems = [];

foreach ($items as $item) {
    if ($item['item_type'] === 'quiz') {
        $formattedItems[] = [
            'playlist_item_id' => (int) $item['id'],
            'quiz_question_id' => (int) $item['quiz_question_id'],
            'quiz_selection_mode' => $item['quiz_selection_mode'],
            'title' => $item['question_text'],
            'type' => 'quiz',
            'question' => $item['question_text'],
            'answers' => [
                ['key' => 'A', 'text' => $item['option_a']],
                ['key' => 'B', 'text' => $item['option_b']],
                ['key' => 'C', 'text' => $item['option_c']],
                ['key' => 'D', 'text' => $item['option_d']],
            ],
            'correct_answer' => $item['correct_option'],
            'countdown_seconds' => max(1, (int) $item['countdown_seconds']),
            'reveal_duration' => max(1, (int) $item['reveal_duration']),
            'sort_order' => (int) $item['sort_order'],
        ];
        continue;
    }

    $formattedItems[] = [
        'playlist_item_id' => (int) $item['id'],
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

foreach ($formattedItems as $item) {
    if (($item['type'] ?? '') === 'quiz' && ($item['quiz_selection_mode'] ?? '') === 'random' && !empty($item['quiz_question_id'])) {
        log_screen_event($db, (int) $screen['id'], 'random_quiz_served', (string) (int) $item['quiz_question_id']);
    }
}

json_response(true, 'Playlist loaded.', [
    'screen' => [
        'id' => (int) $screen['id'],
        'name' => $screen['name'],
        'location' => $screen['location'],
        'sync_revision' => (int) $screen['sync_revision'],
    ],
    'playlist' => [
        'id' => (int) $screen['playlist_id'],
        'name' => $screen['playlist_name'],
    ],
    'items' => $formattedItems,
]);

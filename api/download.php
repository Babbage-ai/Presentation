<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$token = trim((string) ($_GET['token'] ?? ''));
$mediaId = (int) ($_GET['media_id'] ?? 0);

if ($token === '' || $mediaId < 1) {
    http_response_code(400);
    exit('Missing token or media id.');
}

$db = get_db();
$screen = find_screen_by_token($db, $token);
if (!$screen) {
    http_response_code(401);
    exit('Invalid screen token.');
}

$sql = "SELECT m.filename, m.mime_type
        FROM media m
        INNER JOIN playlist_items pi ON pi.media_id = m.id AND pi.active = 1
        INNER JOIN screens s ON s.playlist_id = pi.playlist_id
        WHERE s.id = ? AND m.id = ? AND m.active = 1
        LIMIT 1";
$statement = $db->prepare($sql);
$statement->bind_param('ii', $screen['id'], $mediaId);
$statement->execute();
$media = $statement->get_result()->fetch_assoc();
$statement->close();

if (!$media) {
    http_response_code(404);
    exit('Media not found for this screen.');
}

$filePath = media_upload_dir() . '/' . $media['filename'];
if (!is_file($filePath)) {
    http_response_code(404);
    exit('Media file is missing on the server.');
}

header('Content-Type: ' . $media['mime_type']);
header('Content-Length: ' . (string) filesize($filePath));
header('Cache-Control: public, max-age=300');
header('Content-Disposition: inline; filename="' . basename($media['filename']) . '"');
readfile($filePath);
exit;

<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

set_api_headers();

$input = get_json_input();
$token = trim((string) ($input['token'] ?? $_POST['token'] ?? ''));
$resolution = trim((string) ($input['resolution'] ?? $_POST['resolution'] ?? ''));
$playerVersion = trim((string) ($input['player_version'] ?? $_POST['player_version'] ?? ''));
$reportedIp = trim((string) ($input['ip'] ?? $_POST['ip'] ?? ''));
$ip = $reportedIp !== '' ? $reportedIp : (string) ($_SERVER['REMOTE_ADDR'] ?? '');

if ($token === '') {
    json_response(false, 'Missing screen token.', [], 400);
}

$db = get_db();
$screen = find_screen_by_token($db, $token);

if (!$screen) {
    json_response(false, 'Invalid screen token.', [], 401);
}

$status = 'online';
$statement = $db->prepare("UPDATE screens
                           SET last_seen = UTC_TIMESTAMP(),
                               last_ip = ?,
                               resolution = ?,
                               player_version = ?,
                               status = ?
                           WHERE id = ?");
$statement->bind_param('ssssi', $ip, $resolution, $playerVersion, $status, $screen['id']);
$statement->execute();
$statement->close();

log_screen_event($db, (int) $screen['id'], 'heartbeat', 'Heartbeat received from player.');

json_response(true, 'Heartbeat recorded.', [
    'screen_id' => (int) $screen['id'],
    'last_seen' => gmdate('Y-m-d H:i:s'),
    'status' => 'online',
    'sync_revision' => (int) $screen['sync_revision'],
]);

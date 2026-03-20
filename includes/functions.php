<?php
declare(strict_types=1);

if (!defined('APP_CONFIG_LOADED')) {
    $localConfig = __DIR__ . '/config.local.php';
    if (is_file($localConfig)) {
        require_once $localConfig;
    }
    define('APP_CONFIG_LOADED', true);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . app_path($path));
    exit;
}

function is_post_request(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function set_flash(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $_SESSION['flash'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function get_flash_messages(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $posted = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';

    return is_string($posted) && is_string($stored) && $posted !== '' && hash_equals($stored, $posted);
}

function require_valid_csrf(): void
{
    if (!verify_csrf()) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }
}

function ini_size_to_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $number = (float) $value;

    switch ($unit) {
        case 'g':
            $number *= 1024;
        case 'm':
            $number *= 1024;
        case 'k':
            $number *= 1024;
            break;
    }

    return (int) round($number);
}

function upload_limit_bytes(): int
{
    $uploadMaxFilesize = ini_size_to_bytes((string) ini_get('upload_max_filesize'));
    $postMaxSize = ini_size_to_bytes((string) ini_get('post_max_size'));

    if ($uploadMaxFilesize > 0 && $postMaxSize > 0) {
        return min($uploadMaxFilesize, $postMaxSize);
    }

    return max($uploadMaxFilesize, $postMaxSize);
}

function upload_limit_summary(): string
{
    $limit = upload_limit_bytes();
    if ($limit > 0) {
        return format_bytes($limit);
    }

    return 'the server limit';
}

function request_content_length(): int
{
    return max(0, (int) ($_SERVER['CONTENT_LENGTH'] ?? 0));
}

function exceeds_post_max_size(): bool
{
    $postMaxSize = ini_size_to_bytes((string) ini_get('post_max_size'));
    return $postMaxSize > 0 && request_content_length() > $postMaxSize;
}

function is_ajax_request(): bool
{
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

    return $requestedWith === 'xmlhttprequest' || str_contains($accept, 'application/json');
}

function app_base_path(): string
{
    if (defined('APP_BASE_PATH') && is_string(APP_BASE_PATH) && APP_BASE_PATH !== '') {
        $normalized = '/' . trim(APP_BASE_PATH, '/');
        return $normalized === '/' ? '' : $normalized;
    }

    $configured = getenv('APP_BASE_PATH');
    if (is_string($configured) && $configured !== '') {
        $normalized = '/' . trim($configured, '/');
        return $normalized === '/' ? '' : $normalized;
    }

    return '';
}

function app_path(string $path = ''): string
{
    $basePath = app_base_path();
    $normalizedPath = '/' . ltrim($path, '/');

    if ($normalizedPath === '/') {
        return $basePath !== '' ? $basePath . '/' : '/';
    }

    return ($basePath !== '' ? $basePath : '') . $normalizedPath;
}

function app_url(): string
{
    if (defined('APP_URL') && is_string(APP_URL) && APP_URL !== '') {
        return rtrim(APP_URL, '/');
    }

    $configured = getenv('APP_URL');
    if (is_string($configured) && $configured !== '') {
        return rtrim($configured, '/');
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host;
}

function absolute_url(string $path): string
{
    return app_url() . app_path($path);
}

function application_base_url(): string
{
    return rtrim(app_url() . app_path('/'), '/');
}

function format_bytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $size = $bytes;
    $power = 0;

    while ($size >= 1024 && $power < count($units) - 1) {
        $size /= 1024;
        $power++;
    }

    return number_format($size, $power === 0 ? 0 : 2) . ' ' . $units[$power];
}

function format_datetime(?string $datetime): string
{
    if (!$datetime) {
        return 'Never';
    }

    try {
        $date = new DateTimeImmutable($datetime);
        return $date->format('Y-m-d H:i:s');
    } catch (Throwable $exception) {
        return $datetime;
    }
}

function generate_screen_token(int $bytes = 24): string
{
    return bin2hex(random_bytes($bytes));
}

function media_upload_dir(): string
{
    return dirname(__DIR__) . '/uploads/media';
}

function ensure_media_upload_dir(): void
{
    $directory = media_upload_dir();
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
}

function allowed_media_map(): array
{
    return [
        'jpg' => ['mime' => ['image/jpeg'], 'type' => 'image'],
        'jpeg' => ['mime' => ['image/jpeg'], 'type' => 'image'],
        'png' => ['mime' => ['image/png'], 'type' => 'image'],
        'webp' => ['mime' => ['image/webp'], 'type' => 'image'],
        'mp4' => ['mime' => ['video/mp4'], 'type' => 'video'],
    ];
}

function sanitize_upload_filename(string $originalName): string
{
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $baseName = pathinfo($originalName, PATHINFO_FILENAME);
    $baseName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $baseName) ?? 'media';
    $baseName = trim($baseName, '-_');

    if ($baseName === '') {
        $baseName = 'media';
    }

    return $baseName . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
}

function validate_uploaded_media(array $file): array
{
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
            return [false, 'Upload failed because the file is larger than the server limit of ' . upload_limit_summary() . '.', null, null];
        }

        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            return [false, 'No file was uploaded.', null, null];
        }

        return [false, 'Upload failed.', null, null];
    }

    $originalName = (string) ($file['name'] ?? '');
    $tmpName = (string) ($file['tmp_name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = allowed_media_map();

    if (!isset($allowed[$extension])) {
        return [false, 'Unsupported file extension.', null, null];
    }

    if ($tmpName === '' || !is_file($tmpName)) {
        return [false, 'Uploaded file was not received correctly.', null, null];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = (string) $finfo->file($tmpName);

    if (!in_array($mimeType, $allowed[$extension]['mime'], true)) {
        return [false, 'Uploaded file MIME type does not match the allowed extension.', null, null];
    }

    $blockedExtensions = ['php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'pl', 'py', 'sh', 'exe', 'js', 'cgi'];
    if (in_array($extension, $blockedExtensions, true)) {
        return [false, 'Executable uploads are not allowed.', null, null];
    }

    return [true, '', $mimeType, $allowed[$extension]['type']];
}

function media_file_url(string $filename): string
{
    return absolute_url('uploads/media/' . rawurlencode($filename));
}

function media_file_exists(string $filename): bool
{
    return is_file(media_upload_dir() . '/' . $filename);
}

function player_launch_url(string $screenToken): string
{
    return application_base_url()
        . '/player/player.html?token=' . rawurlencode($screenToken)
        . '&api_base_url=' . rawurlencode(application_base_url());
}

function set_api_headers(): void
{
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function json_response(bool $success, string $message, array $data = [], int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

function get_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function sync_screen_statuses(mysqli $db, int $onlineThresholdSeconds = 120): void
{
    $cutoff = gmdate('Y-m-d H:i:s', time() - $onlineThresholdSeconds);
    $sql = "UPDATE screens
            SET status = CASE
                WHEN last_seen IS NOT NULL AND last_seen >= ? THEN 'online'
                ELSE 'offline'
            END";

    $statement = $db->prepare($sql);
    $statement->bind_param('s', $cutoff);
    $statement->execute();
    $statement->close();
}

function find_screen_by_token(mysqli $db, string $token): ?array
{
    $sql = "SELECT s.*, p.name AS playlist_name, p.active AS playlist_active
            FROM screens s
            LEFT JOIN playlists p ON p.id = s.playlist_id AND p.owner_admin_id = s.owner_admin_id
            WHERE s.screen_token = ?
            LIMIT 1";
    $statement = $db->prepare($sql);
    $statement->bind_param('s', $token);
    $statement->execute();
    $result = $statement->get_result();
    $row = $result->fetch_assoc() ?: null;
    $statement->close();

    return $row;
}

function screen_is_online(?string $lastSeen, int $onlineThresholdSeconds = 120): bool
{
    if (!$lastSeen) {
        return false;
    }

    try {
        $lastSeenTime = new DateTimeImmutable($lastSeen, new DateTimeZone('UTC'));
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        return ($now->getTimestamp() - $lastSeenTime->getTimestamp()) <= $onlineThresholdSeconds;
    } catch (Throwable $exception) {
        return false;
    }
}

function fetch_playlist_items(mysqli $db, int $playlistId): array
{
    $sql = "SELECT
                pi.id,
                pi.playlist_id,
                pi.item_type,
                pi.media_id,
                pi.quiz_question_id,
                pi.sort_order,
                pi.image_duration,
                pi.active,
                m.title AS media_title,
                m.filename,
                m.mime_type,
                m.media_type,
                m.file_size,
                q.question_text,
                q.option_a,
                q.option_b,
                q.option_c,
                q.option_d,
                q.correct_option,
                q.countdown_seconds,
                q.reveal_duration
            FROM playlist_items pi
            INNER JOIN playlists p ON p.id = pi.playlist_id
            LEFT JOIN media m ON m.id = pi.media_id AND m.owner_admin_id = p.owner_admin_id
            LEFT JOIN quiz_questions q ON q.id = pi.quiz_question_id AND q.owner_admin_id = p.owner_admin_id
            WHERE pi.playlist_id = ?
              AND pi.active = 1
              AND (
                    (pi.item_type = 'media' AND m.id IS NOT NULL AND m.active = 1)
                    OR
                    (pi.item_type = 'quiz' AND q.id IS NOT NULL AND q.active = 1)
                  )
            ORDER BY pi.sort_order ASC, pi.id ASC";

    $statement = $db->prepare($sql);
    $statement->bind_param('i', $playlistId);
    $statement->execute();
    $result = $statement->get_result();
    $items = [];

    while ($row = $result->fetch_assoc()) {
        $row['media_id'] = $row['media_id'] !== null ? (int) $row['media_id'] : null;
        $row['quiz_question_id'] = $row['quiz_question_id'] !== null ? (int) $row['quiz_question_id'] : null;
        $row['sort_order'] = (int) $row['sort_order'];
        $row['image_duration'] = (int) $row['image_duration'];
        $row['countdown_seconds'] = isset($row['countdown_seconds']) ? (int) $row['countdown_seconds'] : 0;
        $row['reveal_duration'] = isset($row['reveal_duration']) ? (int) $row['reveal_duration'] : 0;
        $row['file_size'] = $row['file_size'] !== null ? (int) $row['file_size'] : 0;
        $row['title'] = $row['item_type'] === 'quiz'
            ? $row['question_text']
            : (string) ($row['media_title'] ?? '');
        $row['full_url'] = $row['item_type'] === 'media' && !empty($row['filename'])
            ? media_file_url($row['filename'])
            : null;
        $items[] = $row;
    }

    $statement->close();

    return $items;
}

function log_screen_event(mysqli $db, int $screenId, string $logType, string $message): void
{
    $sql = "INSERT INTO screen_logs (screen_id, log_type, message, created_at)
            VALUES (?, ?, ?, UTC_TIMESTAMP())";
    $statement = $db->prepare($sql);
    $statement->bind_param('iss', $screenId, $logType, $message);
    $statement->execute();
    $statement->close();
}

function normalize_int(?string $value, int $default = 0): int
{
    if ($value === null || $value === '') {
        return $default;
    }

    return max(0, (int) $value);
}

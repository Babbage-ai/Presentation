<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$db = get_db();
sync_screen_statuses($db);
$adminId = current_admin_id();

function screen_playlist_exists(mysqli $db, int $adminId, int $playlistId): bool
{
    if ($playlistId < 1) {
        return true;
    }

    $statement = $db->prepare("SELECT COUNT(*) AS total FROM playlists WHERE id = ? AND owner_admin_id = ? AND active = 1");
    $statement->bind_param('ii', $playlistId, $adminId);
    $statement->execute();
    $exists = (int) ($statement->get_result()->fetch_assoc()['total'] ?? 0) === 1;
    $statement->close();

    return $exists;
}

function screen_schedule_exists(mysqli $db, int $adminId, int $scheduleId): bool
{
    if ($scheduleId < 1) {
        return false;
    }

    $statement = $db->prepare("SELECT COUNT(*) AS total FROM schedules WHERE id = ? AND owner_admin_id = ? AND active = 1");
    $statement->bind_param('ii', $scheduleId, $adminId);
    $statement->execute();
    $exists = (int) ($statement->get_result()->fetch_assoc()['total'] ?? 0) === 1;
    $statement->close();

    return $exists;
}

function screen_ticker_exists(mysqli $db, int $adminId, int $tickerId): bool
{
    if ($tickerId < 1) {
        return true;
    }

    $statement = $db->prepare("SELECT COUNT(*) AS total FROM ticker_messages WHERE id = ? AND owner_admin_id = ? AND active = 1");
    $statement->bind_param('ii', $tickerId, $adminId);
    $statement->execute();
    $exists = (int) ($statement->get_result()->fetch_assoc()['total'] ?? 0) === 1;
    $statement->close();

    return $exists;
}

function parse_screen_assignment_value(string $value): array
{
    $value = trim($value);

    if ($value === '' || $value === 'none') {
        return ['playlist_id' => 0, 'schedule_id' => 0];
    }

    if (preg_match('/^playlist:(\d+)$/', $value, $matches) === 1) {
        return ['playlist_id' => (int) $matches[1], 'schedule_id' => 0];
    }

    if (preg_match('/^schedule:(\d+)$/', $value, $matches) === 1) {
        return ['playlist_id' => 0, 'schedule_id' => (int) $matches[1]];
    }

    return ['playlist_id' => 0, 'schedule_id' => 0];
}

function screen_assignment_value(array $screen): string
{
    $scheduleId = (int) ($screen['schedule_id'] ?? 0);
    if ($scheduleId > 0) {
        return 'schedule:' . $scheduleId;
    }

    $playlistId = (int) ($screen['playlist_id'] ?? 0);
    if ($playlistId > 0) {
        return 'playlist:' . $playlistId;
    }

    return 'none';
}

function fetch_screen_counts(mysqli $db, int $adminId): array
{
    $statement = $db->prepare("SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END) AS online_count,
            SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) AS active_count,
            SUM(CASE WHEN playlist_id IS NULL AND schedule_id IS NULL THEN 1 ELSE 0 END) AS unassigned_count
        FROM screens
        WHERE owner_admin_id = ?");
    $statement->bind_param('i', $adminId);
    $statement->execute();
    $counts = $statement->get_result()->fetch_assoc() ?: [];
    $statement->close();

    return [
        'total' => (int) ($counts['total'] ?? 0),
        'online' => (int) ($counts['online_count'] ?? 0),
        'active' => (int) ($counts['active_count'] ?? 0),
        'unassigned' => (int) ($counts['unassigned_count'] ?? 0),
    ];
}

function fetch_screen_row(mysqli $db, int $adminId, int $screenId): ?array
{
    $statement = $db->prepare("SELECT s.*, p.name AS playlist_name, sc.name AS schedule_name, tm.name AS ticker_name
        FROM screens s
        LEFT JOIN playlists p ON p.id = s.playlist_id AND p.owner_admin_id = s.owner_admin_id
        LEFT JOIN schedules sc ON sc.id = s.schedule_id AND sc.owner_admin_id = s.owner_admin_id
        LEFT JOIN ticker_messages tm ON tm.id = s.ticker_message_id AND tm.owner_admin_id = s.owner_admin_id
        WHERE s.id = ? AND s.owner_admin_id = ?
        LIMIT 1");
    $statement->bind_param('ii', $screenId, $adminId);
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc() ?: null;
    $statement->close();

    if ($row) {
        $row['screen_code'] = ensure_screen_code($db, $row);
    }

    return $row;
}

function screen_admin_payload(mysqli $db, array $screen): array
{
    $screenCode = ensure_screen_code($db, $screen);
    $online = screen_is_online($screen['last_seen'] ?? null);

    return [
        'id' => (int) $screen['id'],
        'name' => (string) $screen['name'],
        'location' => (string) ($screen['location'] ?? ''),
        'playlist_id' => (int) ($screen['playlist_id'] ?? 0),
        'playlist_name' => (string) ($screen['playlist_name'] ?? ''),
        'schedule_id' => (int) ($screen['schedule_id'] ?? 0),
        'schedule_name' => (string) ($screen['schedule_name'] ?? ''),
        'ticker_id' => (int) ($screen['ticker_message_id'] ?? 0),
        'ticker_name' => (string) ($screen['ticker_name'] ?? ''),
        'assignment_value' => screen_assignment_value($screen),
        'screen_code' => $screenCode,
        'last_seen_display' => format_datetime($screen['last_seen'] ?? null),
        'active' => (int) ($screen['active'] ?? 1),
        'online' => $online,
        'status_label' => $online ? 'Online' : 'Offline',
        'view_url' => player_browser_test_url($screenCode),
        'reload_revision' => (int) ($screen['reload_revision'] ?? 0),
    ];
}

function screen_state_payload(array $screen): array
{
    return [
        'name' => (string) $screen['name'],
        'location' => (string) ($screen['location'] ?? ''),
        'playlist_id' => (int) ($screen['playlist_id'] ?? 0),
        'schedule_id' => (int) ($screen['schedule_id'] ?? 0),
        'ticker_id' => (int) ($screen['ticker_message_id'] ?? 0),
        'assignment_value' => screen_assignment_value($screen),
        'active' => (int) ($screen['active'] ?? 1),
    ];
}

if (is_post_request()) {
    require_valid_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_screen') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));
        $assignment = parse_screen_assignment_value((string) ($_POST['assignment'] ?? 'none'));
        $playlistId = $assignment['playlist_id'];
        $scheduleId = $assignment['schedule_id'];
        $tickerId = max(0, (int) ($_POST['ticker_id'] ?? 0));
        $active = isset($_POST['active']) ? 1 : 0;
        $screenCode = generate_unique_screen_code($db);
        $token = generate_screen_token();

        if ($name === '') {
            set_flash('danger', 'Screen name is required.');
            redirect('/admin/screens.php');
        }

        if ($playlistId > 0 && !screen_playlist_exists($db, $adminId, $playlistId)) {
            set_flash('danger', 'Selected playlist was not found in your presentation system.');
            redirect('/admin/screens.php');
        }

        if ($scheduleId > 0 && !screen_schedule_exists($db, $adminId, $scheduleId)) {
            set_flash('danger', 'Selected schedule was not found in your presentation system.');
            redirect('/admin/screens.php');
        }

        if ($tickerId > 0 && !screen_ticker_exists($db, $adminId, $tickerId)) {
            set_flash('danger', 'Selected ticker was not found in your presentation system.');
            redirect('/admin/screens.php');
        }

        $statement = $db->prepare("INSERT INTO screens
            (owner_admin_id, name, screen_code, screen_token, location, playlist_id, schedule_id, ticker_message_id, active, resolution, last_seen, last_ip, status, player_version, created_at)
            VALUES (?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), ?, NULL, NULL, NULL, 'offline', NULL, UTC_TIMESTAMP())");
        $statement->bind_param('issssiiii', $adminId, $name, $screenCode, $token, $location, $playlistId, $scheduleId, $tickerId, $active);
        $statement->execute();
        $statement->close();

        set_flash('success', 'Screen created.');
        redirect('/admin/screens.php');
    }

    if ($action === 'update_inline_screen') {
        $screenId = (int) ($_POST['screen_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));
        $assignment = parse_screen_assignment_value((string) ($_POST['assignment'] ?? 'none'));
        $playlistId = $assignment['playlist_id'];
        $scheduleId = $assignment['schedule_id'];
        $tickerId = max(0, (int) ($_POST['ticker_id'] ?? 0));
        $active = isset($_POST['active']) && (string) $_POST['active'] === '1' ? 1 : 0;

        if ($screenId < 1 || $name === '') {
            json_response(false, 'Screen name is required.', [], 422);
        }

        if ($playlistId > 0 && !screen_playlist_exists($db, $adminId, $playlistId)) {
            json_response(false, 'Selected playlist was not found in your presentation system.', [], 422);
        }

        if ($scheduleId > 0 && !screen_schedule_exists($db, $adminId, $scheduleId)) {
            json_response(false, 'Selected schedule was not found in your presentation system.', [], 422);
        }

        if ($tickerId > 0 && !screen_ticker_exists($db, $adminId, $tickerId)) {
            json_response(false, 'Selected ticker was not found in your presentation system.', [], 422);
        }

        $existingScreen = fetch_screen_row($db, $adminId, $screenId);
        if (!$existingScreen) {
            json_response(false, 'Screen not found.', [], 404);
        }

        $statement = $db->prepare("UPDATE screens
            SET name = ?, location = ?, playlist_id = NULLIF(?, 0), schedule_id = NULLIF(?, 0), ticker_message_id = NULLIF(?, 0), active = ?
            WHERE id = ? AND owner_admin_id = ?");
        $statement->bind_param('ssiiiiii', $name, $location, $playlistId, $scheduleId, $tickerId, $active, $screenId, $adminId);
        $statement->execute();
        $updated = $statement->affected_rows > 0;
        $statement->close();

        if ($updated) {
            bump_screen_sync_revision($db, $screenId, $adminId);
            log_screen_event($db, $screenId, 'screen_update', 'Screen settings updated from admin.');
        }

        $screen = fetch_screen_row($db, $adminId, $screenId);
        if (!$screen) {
            json_response(false, 'Screen not found after update.', [], 404);
        }

        json_response(true, 'Screen saved.', [
            'screen' => screen_admin_payload($db, $screen),
            'counts' => fetch_screen_counts($db, $adminId),
        ]);
    }

    if ($action === 'delete_screen') {
        $screenId = (int) ($_POST['screen_id'] ?? 0);

        if ($screenId < 1) {
            json_response(false, 'Screen not found.', [], 404);
        }

        $statement = $db->prepare("DELETE FROM screens WHERE id = ? AND owner_admin_id = ?");
        $statement->bind_param('ii', $screenId, $adminId);
        $statement->execute();
        $deleted = $statement->affected_rows > 0;
        $statement->close();

        if (!$deleted) {
            json_response(false, 'Screen not found.', [], 404);
        }

        json_response(true, 'Screen deleted.', [
            'screen_id' => $screenId,
            'counts' => fetch_screen_counts($db, $adminId),
        ]);
    }

    if ($action === 'force_reload_screen') {
        $screenId = (int) ($_POST['screen_id'] ?? 0);

        if ($screenId < 1) {
            json_response(false, 'Screen not found.', [], 404);
        }

        $screen = fetch_screen_row($db, $adminId, $screenId);
        if (!$screen) {
            json_response(false, 'Screen not found.', [], 404);
        }

        if (!bump_screen_reload_revision($db, $screenId, $adminId)) {
            json_response(false, 'Could not queue a player reload.', [], 500);
        }

        log_screen_event($db, $screenId, 'player_reload_requested', 'Player reload requested from admin.');
        $screen = fetch_screen_row($db, $adminId, $screenId);

        json_response(true, 'Player reload queued. The Pi will hard refresh on its next heartbeat.', [
            'screen' => $screen ? screen_admin_payload($db, $screen) : null,
            'counts' => fetch_screen_counts($db, $adminId),
        ]);
    }
}

$playlists = [];
$statement = $db->prepare("SELECT id, name FROM playlists WHERE owner_admin_id = ? AND active = 1 ORDER BY name ASC");
$statement->bind_param('i', $adminId);
$statement->execute();
$result = $statement->get_result();
while ($row = $result->fetch_assoc()) {
    $playlists[] = $row;
}
$statement->close();

$schedules = [];
$statement = $db->prepare("SELECT id, name FROM schedules WHERE owner_admin_id = ? AND active = 1 ORDER BY name ASC");
$statement->bind_param('i', $adminId);
$statement->execute();
$result = $statement->get_result();
while ($row = $result->fetch_assoc()) {
    $schedules[] = $row;
}
$statement->close();

$tickers = [];
$statement = $db->prepare("SELECT id, name FROM ticker_messages WHERE owner_admin_id = ? AND active = 1 ORDER BY priority ASC, name ASC, id ASC");
$statement->bind_param('i', $adminId);
$statement->execute();
$result = $statement->get_result();
while ($row = $result->fetch_assoc()) {
    $tickers[] = $row;
}
$statement->close();

$screens = [];
$statement = $db->prepare("SELECT s.*, p.name AS playlist_name, sc.name AS schedule_name, tm.name AS ticker_name
    FROM screens s
    LEFT JOIN playlists p ON p.id = s.playlist_id AND p.owner_admin_id = s.owner_admin_id
    LEFT JOIN schedules sc ON sc.id = s.schedule_id AND sc.owner_admin_id = s.owner_admin_id
    LEFT JOIN ticker_messages tm ON tm.id = s.ticker_message_id AND tm.owner_admin_id = s.owner_admin_id
    WHERE s.owner_admin_id = ?
    ORDER BY s.status = 'online' DESC, s.name ASC, s.id DESC");
$statement->bind_param('i', $adminId);
$statement->execute();
$result = $statement->get_result();
while ($row = $result->fetch_assoc()) {
    $row['screen_code'] = ensure_screen_code($db, $row);
    $screens[] = $row;
}
$statement->close();

$screenCounts = fetch_screen_counts($db, $adminId);

$pageTitle = 'Screens';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
    .screen-admin-page { display: grid; gap: 0.7rem; }
    .screen-admin-page .section-heading { margin-bottom: 0; }
    .screen-admin-page .section-subtitle { max-width: 34rem; }
    .screen-admin-page .stat-card .card-body { padding: 0.62rem 0.72rem 0.66rem; }
    .screen-admin-page .stat-number-box { min-width: 4rem; padding: 0.34rem 0.72rem; margin-top: 0.28rem; }
    .screen-admin-page .stat-meta { margin-top: 0.24rem; line-height: 1.15; }
    .screen-admin-page .table-card .card-header { padding: 0.68rem 0.82rem; }
    .screen-admin-page .table-card .card-body { padding: 0; }
    .screen-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 0.55rem; padding: 0.48rem 0.62rem; border-bottom: 1px solid var(--admin-border); background: rgba(248, 250, 252, 0.9); }
    .screen-filters { display: flex; align-items: center; gap: 0.5rem; flex-wrap: nowrap; white-space: nowrap; }
    .screen-filter-group { display: flex; align-items: center; gap: 0.35rem; }
    .screen-filter-label { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--admin-text-soft); }
    .screen-filter-select { width: 6.4rem; min-width: 6.4rem; min-height: 1.8rem; font-size: 0.8rem; padding: 0.16rem 1.7rem 0.16rem 0.52rem; border-radius: 0.8rem; }
    .screen-filter-summary { color: var(--admin-text-soft); font-size: 0.8rem; white-space: nowrap; }
    .screen-admin-table { min-width: 1080px; }
    .screen-admin-table .form-control,
    .screen-admin-table .form-select { min-width: 140px; min-height: 2.1rem; padding-top: 0.34rem; padding-bottom: 0.34rem; }
    .screen-admin-table thead th { padding: 0.52rem 0.65rem; }
    .screen-admin-table tbody td { padding: 0.48rem 0.55rem; }
    .screen-cell-stack { display: grid; gap: 0.16rem; }
    .screen-name-stack { display: grid; gap: 0.3rem; }
    .screen-controls { display: inline-flex; align-items: center; gap: 0.5rem; flex-wrap: nowrap; }
    .screen-code-link { display: inline-flex; align-items: center; justify-content: center; min-height: 2.1rem; min-width: 6.6rem; padding: 0.34rem 0.75rem; border-radius: 0.78rem; border: 1px solid rgba(15, 23, 42, 0.1); background: rgba(248, 250, 252, 0.98); color: #0f172a; text-decoration: none; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 0.88rem; font-weight: 700; letter-spacing: 0.04em; line-height: 1; white-space: nowrap; }
    .screen-code-link:hover { background: #eef2f7; color: #0f172a; }
    .screen-meta-inline { display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap; }
    .screen-meta-inline .badge { font-size: 0.69rem; }
    .screen-last-seen { font-size: 0.76rem; color: var(--admin-text-soft); line-height: 1.2; }
    .screen-save-note { min-height: 0.95rem; color: var(--admin-text-soft); font-size: 0.74rem; }
    .screen-save-note.is-error { color: #b42318; }
    .screen-save-note.is-success { color: #198754; }
    .screen-action-cell { white-space: nowrap; width: 1%; }
    .screen-controls-cell { white-space: nowrap; width: 1%; }
    .screen-inline-toggle { display: flex; justify-content: center; }
    .screen-inline-toggle .form-check-input { margin-top: 0; }
    .screen-row.is-saving { opacity: 0.72; }
    .screen-row.is-filtered-out { display: none !important; }
    .modal-content { border-radius: 1rem; }
    .screen-row td[data-label="Last Seen"] { min-width: 8.5rem; }
    @media (max-width: 767px) {
        .screen-admin-page { gap: 0.48rem; }
        .screen-admin-page .section-subtitle { line-height: 1.2; }
        .screen-admin-page .row.g-3 { --bs-gutter-x: 0.5rem; --bs-gutter-y: 0.5rem; }
        .screen-admin-page .stat-card .card-body { padding: 0.56rem 0.62rem 0.58rem; }
        .screen-admin-page .stat-label { font-size: 0.64rem; letter-spacing: 0.09em; }
        .screen-admin-page .stat-number-box { min-width: 3.5rem; padding: 0.28rem 0.62rem; margin-top: 0.22rem; }
        .screen-admin-page .stat-meta { font-size: 0.72rem; margin-top: 0.18rem; }
        .screen-toolbar { align-items: center; flex-direction: row; flex-wrap: nowrap; overflow-x: auto; gap: 0.45rem; padding: 0.42rem 0.5rem; }
        .screen-filters { flex-wrap: nowrap; }
        .screen-filter-summary { display: none; }
        .screen-filter-group { gap: 0.28rem; }
        .screen-filter-label { font-size: 0.64rem; }
        .screen-filter-select { width: 5.6rem; min-width: 5.6rem; min-height: 1.72rem; font-size: 0.76rem; padding: 0.12rem 1.55rem 0.12rem 0.46rem; }
        .screen-admin-page .table-responsive { overflow: visible; }
        .screen-admin-table { min-width: 0; }
        .screen-admin-table thead { display: none; }
        .screen-admin-table,
        .screen-admin-table tbody,
        .screen-admin-table td { display: block; width: 100%; }
        .screen-admin-table tbody { padding: 0.55rem; }
        .screen-admin-table tr.screen-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 0.2rem 0.45rem;
            margin-bottom: 0.55rem;
            padding: 0.68rem;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 0.9rem;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.96));
            box-shadow: 0 0.25rem 0.8rem rgba(15, 23, 42, 0.05);
        }
        .screen-admin-table tr.screen-row:last-child { margin-bottom: 0; }
        .screen-admin-table tbody td {
            border: 0;
            padding: 0;
            margin: 0;
        }
        .screen-admin-table tbody td::before {
            content: attr(data-label);
            display: block;
            margin-bottom: 0.12rem;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--admin-text-soft);
            line-height: 1;
        }
        .screen-admin-table tbody td[data-label="Screen Name"],
        .screen-admin-table tbody td[data-label="Screen Location"],
        .screen-admin-table tbody td[data-label="Assignment"] { grid-column: 1 / -1; }
        .screen-admin-table tbody td[data-label="Last Seen"] { grid-column: 1 / 2; }
        .screen-admin-table tbody td.screen-controls-cell { grid-column: 2 / 3; align-self: end; }
        .screen-admin-table .form-control,
        .screen-admin-table .form-select { min-width: 0; width: 100%; }
        .screen-admin-table .form-control,
        .screen-admin-table .form-select { min-height: 1.9rem; }
        .screen-save-note { min-height: 0; }
        .screen-inline-toggle { justify-content: flex-start; }
        .screen-last-seen { font-size: 0.74rem; line-height: 1.1; }
        .screen-cell-stack { gap: 0.04rem; }
        .screen-controls-cell {
            width: auto !important;
            display: block !important;
        }
        .screen-controls-cell::before { content: none !important; }
        .screen-controls { width: auto; justify-content: flex-start; gap: 0.24rem; }
        .screen-code-link { min-height: 1.7rem; min-width: 4.6rem; padding: 0.2rem 0.46rem; font-size: 0.72rem; }
        .screen-controls .icon-btn-sm { width: 1.72rem; height: 1.72rem; }
        #screensEmptyRow { padding: 0.8rem !important; border-radius: 0.8rem; background: rgba(248, 250, 252, 0.94); }
    }
</style>

<div class="page-shell screen-admin-page">
<div class="section-heading">
    <div>
        <h1 class="h3">Screens</h1>
        <div class="section-subtitle">Edit screens in one list, save changes instantly, and sync playlist changes straight to the player.</div>
    </div>
    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#addScreenModal">
        <i class="bi bi-plus-circle"></i>
        <span class="ms-1">Add Screen</span>
    </button>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Total</div>
                <div class="stat-number-box"><div class="stat-value" id="screenStatTotal"><?= $screenCounts['total'] ?></div></div>
                <div class="stat-meta">Registered screens</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Online</div>
                <div class="stat-number-box"><div class="stat-value" id="screenStatOnline"><?= $screenCounts['online'] ?></div></div>
                <div class="stat-meta">Seen recently</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Active</div>
                <div class="stat-number-box"><div class="stat-value" id="screenStatActive"><?= $screenCounts['active'] ?></div></div>
                <div class="stat-meta">Enabled for playback</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Unassigned</div>
                <div class="stat-number-box"><div class="stat-value" id="screenStatUnassigned"><?= $screenCounts['unassigned'] ?></div></div>
                <div class="stat-meta">No direct feed selected</div>
            </div>
        </div>
    </div>
</div>

<div class="card table-card">
    <div class="card-header"><h2 class="h5 mb-0">Screens List</h2></div>
    <div class="screen-toolbar">
        <div class="screen-filters">
            <div class="screen-filter-group">
                <label class="screen-filter-label" for="screenFilterActive">Active</label>
                <select class="form-select screen-filter-select" id="screenFilterActive">
                    <option value="all">All</option>
                    <option value="active">Active only</option>
                    <option value="inactive">Inactive only</option>
                </select>
            </div>
            <div class="screen-filter-group">
                <label class="screen-filter-label" for="screenFilterOnline">Online</label>
                <select class="form-select screen-filter-select" id="screenFilterOnline">
                    <option value="all">All</option>
                    <option value="online">Online only</option>
                    <option value="offline">Offline only</option>
                </select>
            </div>
        </div>
        <div class="screen-filter-summary" id="screenFilterSummary">Showing all screens</div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table page-table mb-0 screen-admin-table">
                <thead>
                    <tr>
                        <th>Screen Name</th>
                        <th>Screen Location</th>
                        <th>Assignment</th>
                        <th>Ticker</th>
                        <th>Last Seen</th>
                        <th>Controls</th>
                    </tr>
                </thead>
                <tbody id="screensTableBody">
                    <?php if (!$screens): ?>
                        <tr id="screensEmptyRow">
                            <td colspan="6" class="text-muted p-3">No screens created yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($screens as $screen): ?>
                            <?php $screenPayload = screen_admin_payload($db, $screen); ?>
                            <tr
                                class="screen-row"
                                data-screen-id="<?= (int) $screen['id'] ?>"
                                data-online="<?= $screenPayload['online'] ? '1' : '0' ?>"
                                data-active="<?= (int) $screenPayload['active'] ?>"
                                data-state="<?= e(json_encode(screen_state_payload($screen), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>"
                            >
                                <td data-label="Screen Name">
                                    <div class="screen-name-stack">
                                        <input class="form-control js-screen-field" type="text" name="name" value="<?= e($screen['name']) ?>" required>
                                        <div class="screen-save-note" data-role="save-note"></div>
                                    </div>
                                </td>
                                <td data-label="Screen Location">
                                    <input class="form-control js-screen-field" type="text" name="location" value="<?= e($screen['location']) ?>">
                                </td>
                                <td data-label="Assignment">
                                    <select class="form-select js-screen-field" name="assignment">
                                        <option value="none">Unassigned</option>
                                        <?php if ($playlists): ?>
                                            <optgroup label="Direct Playlists">
                                                <?php foreach ($playlists as $playlist): ?>
                                                    <option value="playlist:<?= (int) $playlist['id'] ?>" <?= $screenPayload['assignment_value'] === 'playlist:' . (int) $playlist['id'] ? 'selected' : '' ?>><?= e($playlist['name']) ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                        <?php if ($schedules): ?>
                                            <optgroup label="Named Schedules">
                                                <?php foreach ($schedules as $schedule): ?>
                                                    <option value="schedule:<?= (int) $schedule['id'] ?>" <?= $screenPayload['assignment_value'] === 'schedule:' . (int) $schedule['id'] ? 'selected' : '' ?>><?= e($schedule['name']) ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                    </select>
                                </td>
                                <td data-label="Ticker">
                                    <select class="form-select js-screen-field" name="ticker_id">
                                        <option value="0">No direct ticker</option>
                                        <?php foreach ($tickers as $ticker): ?>
                                            <option value="<?= (int) $ticker['id'] ?>" <?= (int) ($screenPayload['ticker_id'] ?? 0) === (int) $ticker['id'] ? 'selected' : '' ?>><?= e($ticker['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td data-label="Last Seen">
                                    <div class="screen-cell-stack">
                                        <div class="screen-meta-inline">
                                            <span class="badge <?= $screenPayload['online'] ? 'text-bg-success' : 'text-bg-secondary' ?>" data-role="status-badge"><?= e($screenPayload['status_label']) ?></span>
                                        </div>
                                        <div class="screen-last-seen" data-role="last-seen"><?= e($screenPayload['last_seen_display']) ?></div>
                                    </div>
                                </td>
                                <td class="screen-controls-cell" data-label="Controls">
                                    <div class="screen-controls">
                                        <a class="screen-code-link" href="<?= e($screenPayload['view_url']) ?>" target="_blank" rel="noopener noreferrer" title="View screen by code" data-role="code-link"><?= e($screenPayload['screen_code']) ?></a>
                                        <button class="btn btn-outline-secondary btn-sm icon-btn icon-btn-sm js-screen-reload" type="button" title="Force player reload">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
                                        <label class="screen-inline-toggle screen-toggle-cell" title="Active">
                                            <input class="form-check-input js-screen-field" type="checkbox" name="active" value="1" <?= (int) $screen['active'] === 1 ? 'checked' : '' ?>>
                                        </label>
                                        <button class="btn btn-outline-danger btn-sm icon-btn icon-btn-sm js-screen-delete" type="button" title="Delete screen">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="addScreenModal" tabindex="-1" aria-labelledby="addScreenModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 mb-0" id="addScreenModalLabel">Add Screen</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form class="dense-form" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_screen">
                    <div class="mb-3">
                        <label class="form-label" for="create_screen_name">Screen Name</label>
                        <input class="form-control" id="create_screen_name" name="name" type="text" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="create_screen_location">Screen Location</label>
                        <input class="form-control" id="create_screen_location" name="location" type="text">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="create_screen_assignment">Assignment</label>
                        <select class="form-select" id="create_screen_assignment" name="assignment">
                            <option value="none">Unassigned</option>
                            <?php if ($playlists): ?>
                                <optgroup label="Direct Playlists">
                                    <?php foreach ($playlists as $playlist): ?>
                                        <option value="playlist:<?= (int) $playlist['id'] ?>"><?= e($playlist['name']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                            <?php if ($schedules): ?>
                                <optgroup label="Named Schedules">
                                    <?php foreach ($schedules as $schedule): ?>
                                        <option value="schedule:<?= (int) $schedule['id'] ?>"><?= e($schedule['name']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="create_screen_ticker">Ticker</label>
                        <select class="form-select" id="create_screen_ticker" name="ticker_id">
                            <option value="0">No direct ticker</option>
                            <?php foreach ($tickers as $ticker): ?>
                                <option value="<?= (int) $ticker['id'] ?>"><?= e($ticker['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" id="create_screen_active" name="active" type="checkbox" checked>
                        <label class="form-check-label" for="create_screen_active">Screen active</label>
                    </div>
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-plus-circle"></i>
                        <span class="ms-1">Create Screen</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
window.addEventListener('load', () => {
const tableBody = document.getElementById('screensTableBody');
const screenFilterActive = document.getElementById('screenFilterActive');
const screenFilterOnline = document.getElementById('screenFilterOnline');
const screenFilterSummary = document.getElementById('screenFilterSummary');
const csrfToken = <?= json_encode(csrf_token()) ?>;
const stats = {
    total: document.getElementById('screenStatTotal'),
    online: document.getElementById('screenStatOnline'),
    active: document.getElementById('screenStatActive'),
    unassigned: document.getElementById('screenStatUnassigned'),
};

function parseRowState(row) {
    try {
        return JSON.parse(row.dataset.state || '{}');
    } catch (error) {
        return {};
    }
}

function currentRowState(row) {
    const nameField = row.querySelector('[name="name"]');
    const locationField = row.querySelector('[name="location"]');
    const assignmentField = row.querySelector('[name="assignment"]');
    const activeField = row.querySelector('[name="active"]');

    return {
        name: nameField ? nameField.value.trim() : '',
        location: locationField ? locationField.value.trim() : '',
        assignment: assignmentField ? assignmentField.value : 'none',
        ticker_id: row.querySelector('[name="ticker_id"]') ? row.querySelector('[name="ticker_id"]').value : '0',
        active: activeField && activeField.checked ? 1 : 0,
    };
}

function applyRowState(row, state) {
    const normalizedState = {
        name: state.name || '',
        location: state.location || '',
        assignment: state.assignment || 'none',
        ticker_id: String(state.ticker_id || '0'),
        active: Number.parseInt(state.active, 10) === 1 ? 1 : 0,
    };

    const nameField = row.querySelector('[name="name"]');
    const locationField = row.querySelector('[name="location"]');
    const assignmentField = row.querySelector('[name="assignment"]');
    const activeField = row.querySelector('[name="active"]');
    const tickerField = row.querySelector('[name="ticker_id"]');

    if (nameField) {
        nameField.value = normalizedState.name;
    }
    if (locationField) {
        locationField.value = normalizedState.location;
    }
    if (assignmentField) {
        assignmentField.value = normalizedState.assignment;
    }
    if (tickerField) {
        tickerField.value = normalizedState.ticker_id;
    }
    if (activeField) {
        activeField.checked = normalizedState.active === 1;
    }

    row.dataset.state = JSON.stringify(normalizedState);
}

function stateChanged(row) {
    const current = currentRowState(row);
    const stored = parseRowState(row);

    return current.name !== (stored.name || '')
        || current.location !== (stored.location || '')
        || current.assignment !== (stored.assignment || 'none')
        || current.ticker_id !== String(stored.ticker_id || '0')
        || current.active !== (Number.parseInt(stored.active, 10) === 1 ? 1 : 0);
}

function setRowBusy(row, busy) {
    row.dataset.busy = busy ? '1' : '0';
    row.classList.toggle('is-saving', busy);

    row.querySelectorAll('input, select, button').forEach((element) => {
        if (element.classList.contains('js-screen-delete') || element.classList.contains('js-screen-reload') || element.classList.contains('js-screen-field')) {
            element.disabled = busy;
        }
    });
}

function setRowMessage(row, message, type = '') {
    const note = row.querySelector('[data-role="save-note"]');
    if (!note) {
        return;
    }

    note.textContent = message || '';
    note.classList.toggle('is-error', type === 'error');
    note.classList.toggle('is-success', type === 'success');
}

function updateCounts(counts) {
    if (!counts) {
        return;
    }

    if (stats.total) {
        stats.total.textContent = String(counts.total ?? 0);
    }
    if (stats.online) {
        stats.online.textContent = String(counts.online ?? 0);
    }
    if (stats.active) {
        stats.active.textContent = String(counts.active ?? 0);
    }
    if (stats.unassigned) {
        stats.unassigned.textContent = String(counts.unassigned ?? 0);
    }
}

function allRows() {
    return tableBody ? Array.from(tableBody.querySelectorAll('.screen-row')) : [];
}

function updateFilterSummary(visibleCount, totalCount) {
    if (!screenFilterSummary) {
        return;
    }

    if (totalCount === 0) {
        screenFilterSummary.textContent = 'No screens available';
        return;
    }

    if (visibleCount === totalCount) {
        screenFilterSummary.textContent = `Showing all ${totalCount} screens`;
        return;
    }

    screenFilterSummary.textContent = `Showing ${visibleCount} of ${totalCount} screens`;
}

function applyFilters() {
    const rows = allRows();
    const activeFilter = screenFilterActive ? screenFilterActive.value : 'all';
    const onlineFilter = screenFilterOnline ? screenFilterOnline.value : 'all';
    let visibleCount = 0;

    rows.forEach((row) => {
        const isActive = row.dataset.active === '1';
        const isOnline = row.dataset.online === '1';
        const matchesActive = activeFilter === 'all'
            || (activeFilter === 'active' && isActive)
            || (activeFilter === 'inactive' && !isActive);
        const matchesOnline = onlineFilter === 'all'
            || (onlineFilter === 'online' && isOnline)
            || (onlineFilter === 'offline' && !isOnline);
        const visible = matchesActive && matchesOnline;

        row.classList.toggle('is-filtered-out', !visible);
        if (visible) {
            visibleCount++;
        }
    });

    updateFilterSummary(visibleCount, rows.length);
}

function updateRowFromPayload(row, screen, message) {
    if (!row || !screen) {
        return;
    }

    applyRowState(row, screen);

    const statusBadge = row.querySelector('[data-role="status-badge"]');
    const lastSeen = row.querySelector('[data-role="last-seen"]');
    const codeLink = row.querySelector('[data-role="code-link"]');

    if (statusBadge) {
        statusBadge.textContent = screen.status_label || 'Offline';
        statusBadge.classList.toggle('text-bg-success', Boolean(screen.online));
        statusBadge.classList.toggle('text-bg-secondary', !screen.online);
    }
    if (lastSeen) {
        lastSeen.textContent = screen.last_seen_display || 'Never';
    }
    if (codeLink) {
        codeLink.href = screen.view_url || '#';
        codeLink.textContent = screen.screen_code || '';
    }

    row.dataset.online = Boolean(screen.online) ? '1' : '0';
    row.dataset.active = Number(screen.active) === 1 ? '1' : '0';

    setRowMessage(row, message || 'Saved', 'success');
    window.setTimeout(() => {
        if (row.isConnected) {
            setRowMessage(row, '', '');
        }
    }, 1800);

    applyFilters();
}

async function postScreenAction(body) {
    const response = await fetch(<?= json_encode(app_path('/admin/screens.php')) ?>, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        body: new URLSearchParams(body),
    });

    const data = await response.json().catch(() => ({
        success: false,
        message: 'Request failed.',
    }));

    if (!response.ok || !data.success) {
        throw new Error(data.message || 'Request failed.');
    }

    return data.data || {};
}

async function saveRow(row, revertOnError = false) {
    if (!row || row.dataset.busy === '1') {
        if (row) {
            row.dataset.pending = '1';
        }
        return;
    }

    if (!stateChanged(row)) {
        return;
    }

    const previousState = parseRowState(row);
    const nextState = currentRowState(row);

    if (!nextState.name) {
        setRowMessage(row, 'Screen name is required.', 'error');
        if (revertOnError) {
            applyRowState(row, previousState);
        }
        return;
    }

    setRowBusy(row, true);
    setRowMessage(row, 'Saving...');

    try {
        const data = await postScreenAction({
            action: 'update_inline_screen',
            csrf_token: csrfToken,
            screen_id: row.dataset.screenId || '',
            name: nextState.name,
            location: nextState.location,
            assignment: nextState.assignment,
            ticker_id: nextState.ticker_id,
            active: String(nextState.active),
        });

        updateRowFromPayload(row, data.screen || null, data.message || 'Saved');
        updateCounts(data.counts || null);
    } catch (error) {
        if (revertOnError) {
            applyRowState(row, previousState);
        }
        setRowMessage(row, error.message || 'Save failed.', 'error');
    } finally {
        setRowBusy(row, false);

        if (row.dataset.pending === '1') {
            row.dataset.pending = '0';
            if (stateChanged(row)) {
                saveRow(row);
            }
        }
    }
}

function queueSave(row, delay = 550) {
    if (!row) {
        return;
    }

    if (row._saveTimer) {
        window.clearTimeout(row._saveTimer);
    }

    row._saveTimer = window.setTimeout(() => {
        row._saveTimer = null;
        saveRow(row);
    }, delay);
}

async function deleteRow(row) {
    if (!row) {
        return;
    }

    const confirmed = window.confirm('Delete this screen? The player using its code will stop working until it is re-created.');
    if (!confirmed) {
        return;
    }

    setRowBusy(row, true);
    setRowMessage(row, 'Deleting...');

    try {
        const data = await postScreenAction({
            action: 'delete_screen',
            csrf_token: csrfToken,
            screen_id: row.dataset.screenId || '',
        });

        row.remove();
        updateCounts(data.counts || null);

        if (tableBody && !tableBody.querySelector('.screen-row')) {
            const emptyRow = document.createElement('tr');
            emptyRow.id = 'screensEmptyRow';
            emptyRow.innerHTML = '<td colspan="6" class="text-muted p-3">No screens created yet.</td>';
            tableBody.appendChild(emptyRow);
        }

        applyFilters();
    } catch (error) {
        setRowBusy(row, false);
        setRowMessage(row, error.message || 'Delete failed.', 'error');
    }
}

async function reloadRow(row) {
    if (!row || row.dataset.busy === '1') {
        return;
    }

    setRowBusy(row, true);
    setRowMessage(row, 'Queueing reload...');

    try {
        const data = await postScreenAction({
            action: 'force_reload_screen',
            csrf_token: csrfToken,
            screen_id: row.dataset.screenId || '',
        });

        if (data.screen) {
            updateRowFromPayload(row, data.screen, data.message || 'Player reload queued');
        } else {
            setRowMessage(row, data.message || 'Player reload queued', 'success');
        }
        updateCounts(data.counts || null);
    } catch (error) {
        setRowMessage(row, error.message || 'Reload request failed.', 'error');
    } finally {
        setRowBusy(row, false);
    }
}

if (!tableBody) {
    return;
}

if (screenFilterActive) {
    screenFilterActive.addEventListener('change', applyFilters);
}

if (screenFilterOnline) {
    screenFilterOnline.addEventListener('change', applyFilters);
}

tableBody.addEventListener('input', (event) => {
    const field = event.target;
    if (!(field instanceof HTMLElement) || !field.classList.contains('js-screen-field')) {
        return;
    }

    if (field.getAttribute('type') === 'text') {
        queueSave(field.closest('.screen-row'));
    }
});

tableBody.addEventListener('blur', (event) => {
    const field = event.target;
    if (!(field instanceof HTMLElement) || !field.classList.contains('js-screen-field')) {
        return;
    }

    if (field.getAttribute('type') === 'text') {
        saveRow(field.closest('.screen-row'));
    }
}, true);

tableBody.addEventListener('change', (event) => {
    const field = event.target;
    if (!(field instanceof HTMLElement)) {
        return;
    }

    if (field.classList.contains('js-screen-field') && field.getAttribute('type') !== 'text') {
        saveRow(field.closest('.screen-row'), true);
        return;
    }

    if (field.classList.contains('js-screen-delete')) {
        deleteRow(field.closest('.screen-row'));
    }
});

tableBody.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) {
        return;
    }

    const reloadButton = target.closest('.js-screen-reload');
    if (reloadButton) {
        reloadRow(reloadButton.closest('.screen-row'));
        return;
    }

    const deleteButton = target.closest('.js-screen-delete');
    if (deleteButton) {
        deleteRow(deleteButton.closest('.screen-row'));
    }
});

applyFilters();
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

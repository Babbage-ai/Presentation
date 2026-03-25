<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$db = get_db();
sync_screen_statuses($db);
$adminId = current_admin_id();

function admin_ticker_name_exists(mysqli $db, int $adminId, string $name, int $excludeId = 0): bool
{
    $statement = $db->prepare("SELECT COUNT(*) AS total
        FROM ticker_messages
        WHERE owner_admin_id = ?
          AND name = ?
          AND id <> ?");
    $statement->bind_param('isi', $adminId, $name, $excludeId);
    $statement->execute();
    $exists = (int) ($statement->get_result()->fetch_assoc()['total'] ?? 0) > 0;
    $statement->close();

    return $exists;
}

function find_admin_ticker(mysqli $db, int $adminId, int $tickerId): ?array
{
    $statement = $db->prepare("SELECT tm.*,
            (SELECT COUNT(*) FROM ticker_message_screens tms WHERE tms.ticker_message_id = tm.id) AS screen_count
        FROM ticker_messages tm
        WHERE tm.owner_admin_id = ? AND tm.id = ?
        LIMIT 1");
    $statement->bind_param('ii', $adminId, $tickerId);
    $statement->execute();
    $ticker = $statement->get_result()->fetch_assoc() ?: null;
    $statement->close();

    return $ticker;
}

function fetch_admin_tickers(mysqli $db, int $adminId): array
{
    $statement = $db->prepare("SELECT tm.*,
            (SELECT COUNT(*) FROM ticker_message_screens tms WHERE tms.ticker_message_id = tm.id) AS screen_count
        FROM ticker_messages tm
        WHERE tm.owner_admin_id = ?
        ORDER BY tm.priority ASC, tm.updated_at DESC, tm.id DESC");
    $statement->bind_param('i', $adminId);
    $statement->execute();
    $result = $statement->get_result();
    $tickers = [];

    while ($row = $result->fetch_assoc()) {
        $tickers[] = $row;
    }

    $statement->close();

    return $tickers;
}

function fetch_ticker_screen_ids(mysqli $db, int $tickerId): array
{
    $statement = $db->prepare("SELECT screen_id
        FROM ticker_message_screens
        WHERE ticker_message_id = ?
        ORDER BY screen_id ASC");
    $statement->bind_param('i', $tickerId);
    $statement->execute();
    $result = $statement->get_result();
    $screenIds = [];

    while ($row = $result->fetch_assoc()) {
        $screenIds[] = (int) $row['screen_id'];
    }

    $statement->close();

    return $screenIds;
}

function filter_valid_screen_ids(array $screenIds, array $screens): array
{
    $validLookup = [];
    foreach ($screens as $screen) {
        $validLookup[(int) $screen['id']] = true;
    }

    $normalized = [];
    foreach ($screenIds as $screenId) {
        $screenId = (int) $screenId;
        if ($screenId > 0 && isset($validLookup[$screenId])) {
            $normalized[$screenId] = $screenId;
        }
    }

    return array_values($normalized);
}

function save_ticker_screen_assignments(mysqli $db, int $tickerId, array $screenIds): void
{
    $statement = $db->prepare("DELETE FROM ticker_message_screens WHERE ticker_message_id = ?");
    $statement->bind_param('i', $tickerId);
    $statement->execute();
    $statement->close();

    if (!$screenIds) {
        return;
    }

    $statement = $db->prepare("INSERT INTO ticker_message_screens (ticker_message_id, screen_id, created_at)
        VALUES (?, ?, UTC_TIMESTAMP())");

    foreach ($screenIds as $screenId) {
        $screenId = (int) $screenId;
        $statement->bind_param('ii', $tickerId, $screenId);
        $statement->execute();
    }

    $statement->close();
}

function bump_ticker_sync_revisions(mysqli $db, int $adminId, bool $allScreens, array $screenIds): void
{
    if ($allScreens) {
        $statement = $db->prepare("UPDATE screens
            SET sync_revision = sync_revision + 1
            WHERE owner_admin_id = ?");
        $statement->bind_param('i', $adminId);
        $statement->execute();
        $statement->close();
        return;
    }

    if (!$screenIds) {
        return;
    }

    $screenIds = array_values(array_unique(array_map('intval', $screenIds)));
    $placeholders = implode(',', array_fill(0, count($screenIds), '?'));
    $types = str_repeat('i', count($screenIds) + 1);
    $params = array_merge([$adminId], $screenIds);

    $statement = $db->prepare("UPDATE screens
        SET sync_revision = sync_revision + 1
        WHERE owner_admin_id = ?
          AND id IN ($placeholders)");
    $statement->bind_param($types, ...$params);
    $statement->execute();
    $statement->close();
}

function ticker_list_excerpt(string $text, int $limit = 86): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    if (strlen($text) <= $limit) {
        return $text;
    }

    return rtrim(substr($text, 0, $limit - 3)) . '...';
}

$screens = fetch_admin_screens_basic($db, $adminId);

if (is_post_request()) {
    require_valid_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_ticker' || $action === 'update_ticker') {
        $tickerId = (int) ($_POST['ticker_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $messageText = trim((string) ($_POST['message_text'] ?? ''));
        $dayMask = build_schedule_day_mask(array_map('intval', (array) ($_POST['days'] ?? [])));
        $startTime = trim((string) ($_POST['start_time'] ?? '00:00'));
        $endTime = trim((string) ($_POST['end_time'] ?? '23:59'));
        $startsAt = trim((string) ($_POST['starts_at'] ?? ''));
        $endsAt = trim((string) ($_POST['ends_at'] ?? ''));
        $position = (string) ($_POST['position'] ?? 'bottom') === 'top' ? 'top' : 'bottom';
        $heightPx = max(40, min(220, normalize_int((string) ($_POST['height_px'] ?? '72'), 72)));
        $speedSeconds = max(10, normalize_int((string) ($_POST['speed_seconds'] ?? '28'), 28));
        $priority = max(1, normalize_int((string) ($_POST['priority'] ?? '1'), 1));
        $active = isset($_POST['active']) ? 1 : 0;
        $appliesToAllScreens = isset($_POST['applies_to_all_screens']) ? 1 : 0;
        $selectedScreenIds = filter_valid_screen_ids((array) ($_POST['screen_ids'] ?? []), $screens);

        if ($name === '') {
            set_flash('danger', 'Ticker name is required.');
            redirect('/admin/tickers.php' . ($tickerId > 0 ? '?ticker_id=' . $tickerId : ''));
        }

        if ($messageText === '') {
            set_flash('danger', 'Ticker message text is required.');
            redirect('/admin/tickers.php' . ($tickerId > 0 ? '?ticker_id=' . $tickerId : ''));
        }

        if ($dayMask === 0) {
            set_flash('danger', 'Choose at least one day.');
            redirect('/admin/tickers.php' . ($tickerId > 0 ? '?ticker_id=' . $tickerId : ''));
        }

        if (!preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            set_flash('danger', 'Enter valid start and end times.');
            redirect('/admin/tickers.php' . ($tickerId > 0 ? '?ticker_id=' . $tickerId : ''));
        }

        if ($startsAt !== '' && strtotime($startsAt) === false) {
            set_flash('danger', 'Enter a valid schedule start date and time.');
            redirect('/admin/tickers.php' . ($tickerId > 0 ? '?ticker_id=' . $tickerId : ''));
        }

        if ($endsAt !== '' && strtotime($endsAt) === false) {
            set_flash('danger', 'Enter a valid schedule end date and time.');
            redirect('/admin/tickers.php' . ($tickerId > 0 ? '?ticker_id=' . $tickerId : ''));
        }

        if ($startsAt !== '' && $endsAt !== '' && strtotime($startsAt) > strtotime($endsAt)) {
            set_flash('danger', 'Schedule end must be after the schedule start.');
            redirect('/admin/tickers.php' . ($tickerId > 0 ? '?ticker_id=' . $tickerId : ''));
        }

        if (!$appliesToAllScreens && !$selectedScreenIds) {
            set_flash('danger', 'Choose at least one screen or enable all screens.');
            redirect('/admin/tickers.php' . ($tickerId > 0 ? '?ticker_id=' . $tickerId : ''));
        }

        if (admin_ticker_name_exists($db, $adminId, $name, $tickerId)) {
            set_flash('danger', 'That ticker name already exists.');
            redirect('/admin/tickers.php' . ($tickerId > 0 ? '?ticker_id=' . $tickerId : ''));
        }

        $startTime .= ':00';
        $endTime .= ':00';
        $startsAt = $startsAt !== '' ? date('Y-m-d H:i:s', strtotime($startsAt)) : null;
        $endsAt = $endsAt !== '' ? date('Y-m-d H:i:s', strtotime($endsAt)) : null;

        $previousTicker = $tickerId > 0 ? find_admin_ticker($db, $adminId, $tickerId) : null;
        $previousScreenIds = $previousTicker ? fetch_ticker_screen_ids($db, $tickerId) : [];
        $previousAllScreens = (int) ($previousTicker['applies_to_all_screens'] ?? 0) === 1;

        if ($action === 'create_ticker') {
            $statement = $db->prepare("INSERT INTO ticker_messages
                (owner_admin_id, name, message_text, day_mask, start_time, end_time, starts_at, ends_at, position, height_px, speed_seconds, priority, active, applies_to_all_screens, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
            $statement->bind_param(
                'ississsssiiiii',
                $adminId,
                $name,
                $messageText,
                $dayMask,
                $startTime,
                $endTime,
                $startsAt,
                $endsAt,
                $position,
                $heightPx,
                $speedSeconds,
                $priority,
                $active,
                $appliesToAllScreens
            );
            $statement->execute();
            $tickerId = (int) $statement->insert_id;
            $statement->close();

            save_ticker_screen_assignments($db, $tickerId, $appliesToAllScreens ? [] : $selectedScreenIds);
            bump_ticker_sync_revisions($db, $adminId, $appliesToAllScreens === 1, $selectedScreenIds);
            set_flash('success', 'Ticker created.');
            redirect('/admin/tickers.php?ticker_id=' . $tickerId);
        }

        if (!$previousTicker) {
            set_flash('danger', 'Ticker not found.');
            redirect('/admin/tickers.php');
        }

        $statement = $db->prepare("UPDATE ticker_messages
            SET name = ?, message_text = ?, day_mask = ?, start_time = ?, end_time = ?, starts_at = ?, ends_at = ?, position = ?, height_px = ?, speed_seconds = ?, priority = ?, active = ?, applies_to_all_screens = ?, updated_at = UTC_TIMESTAMP()
            WHERE id = ? AND owner_admin_id = ?");
        $statement->bind_param(
            'ssisssssiiiiiii',
            $name,
            $messageText,
            $dayMask,
            $startTime,
            $endTime,
            $startsAt,
            $endsAt,
            $position,
            $heightPx,
            $speedSeconds,
            $priority,
            $active,
            $appliesToAllScreens,
            $tickerId,
            $adminId
        );
        $statement->execute();
        $statement->close();

        save_ticker_screen_assignments($db, $tickerId, $appliesToAllScreens ? [] : $selectedScreenIds);

        $affectedScreenIds = array_values(array_unique(array_merge($previousScreenIds, $selectedScreenIds)));
        bump_ticker_sync_revisions($db, $adminId, $previousAllScreens || $appliesToAllScreens === 1, $affectedScreenIds);
        set_flash('success', 'Ticker updated.');
        redirect('/admin/tickers.php?ticker_id=' . $tickerId);
    }

    if ($action === 'delete_ticker') {
        $tickerId = (int) ($_POST['ticker_id'] ?? 0);
        $ticker = find_admin_ticker($db, $adminId, $tickerId);

        if (!$ticker) {
            set_flash('danger', 'Ticker not found.');
            redirect('/admin/tickers.php');
        }

        $assignedScreenIds = fetch_ticker_screen_ids($db, $tickerId);
        $allScreens = (int) ($ticker['applies_to_all_screens'] ?? 0) === 1;

        $statement = $db->prepare("DELETE FROM ticker_messages WHERE id = ? AND owner_admin_id = ?");
        $statement->bind_param('ii', $tickerId, $adminId);
        $statement->execute();
        $statement->close();

        bump_ticker_sync_revisions($db, $adminId, $allScreens, $assignedScreenIds);
        set_flash('success', 'Ticker deleted.');
        redirect('/admin/tickers.php');
    }
}

$tickers = fetch_admin_tickers($db, $adminId);
$selectedTickerId = (int) ($_GET['ticker_id'] ?? ($tickers[0]['id'] ?? 0));
$selectedTicker = $selectedTickerId > 0 ? find_admin_ticker($db, $adminId, $selectedTickerId) : null;
$selectedTickerScreenIds = $selectedTicker ? fetch_ticker_screen_ids($db, $selectedTickerId) : [];

$tickerCount = count($tickers);
$activeTickerCount = 0;
$assignedTickerCount = 0;
foreach ($tickers as $ticker) {
    $activeTickerCount += (int) ($ticker['active'] ?? 0) === 1 ? 1 : 0;
    $assignedTickerCount += (int) ($ticker['screen_count'] ?? 0);
}

$pageTitle = 'Tickers';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
    .ticker-page { display: grid; gap: 0.75rem; }
    .ticker-layout { display: grid; grid-template-columns: minmax(0, 19rem) minmax(0, 1fr); gap: 0.75rem; align-items: start; }
    .ticker-list { display: grid; gap: 0.5rem; }
    .ticker-link { display: grid; gap: 0.2rem; padding: 0.72rem 0.8rem; border-radius: 0.9rem; border: 1px solid rgba(15, 23, 42, 0.08); background: rgba(248, 250, 252, 0.92); color: inherit; text-decoration: none; }
    .ticker-link.is-active { background: #0f172a; color: #fff; }
    .ticker-link .small { color: inherit; opacity: 0.84; }
    .ticker-grid { display: grid; gap: 0.75rem; grid-template-columns: repeat(12, minmax(0, 1fr)); }
    .ticker-span-12 { grid-column: span 12; }
    .ticker-span-8 { grid-column: span 8; }
    .ticker-span-6 { grid-column: span 6; }
    .ticker-span-4 { grid-column: span 4; }
    .ticker-days-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 0.35rem; }
    .ticker-day-chip { display: flex; align-items: center; justify-content: center; min-height: 2rem; border: 1px solid rgba(15, 23, 42, 0.1); border-radius: 0.72rem; background: rgba(248, 250, 252, 0.95); font-size: 0.76rem; }
    .ticker-day-chip input { display: none; }
    .ticker-day-chip span { font-weight: 700; color: #64748b; }
    .ticker-day-chip input:checked + span { color: #0f172a; }
    .ticker-day-chip:has(input:checked) { background: #e2e8f0; border-color: rgba(15, 23, 42, 0.18); }
    .ticker-screen-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.45rem; max-height: 18rem; overflow-y: auto; padding: 0.15rem; }
    .ticker-screen-chip { display: flex; align-items: flex-start; gap: 0.5rem; padding: 0.56rem 0.62rem; border: 1px solid rgba(15, 23, 42, 0.08); border-radius: 0.8rem; background: rgba(248, 250, 252, 0.92); }
    .ticker-screen-chip .form-check-input { margin-top: 0.18rem; }
    .ticker-screen-copy { display: grid; gap: 0.1rem; min-width: 0; }
    .ticker-preview { min-height: 4.5rem; padding: 0.72rem 0.85rem; border-radius: 0.9rem; background: linear-gradient(90deg, rgba(10, 10, 10, 0.94), rgba(24, 24, 24, 0.92)); border-top: 2px solid rgba(255, 166, 0, 0.42); color: #fff4dc; box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04); }
    .ticker-preview-label { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: rgba(255, 208, 140, 0.88); }
    .ticker-preview-text { margin-top: 0.28rem; font-size: 0.96rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ticker-summary-grid { display: grid; gap: 0.55rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ticker-summary-card { padding: 0.7rem 0.76rem; border-radius: 0.82rem; background: rgba(248, 250, 252, 0.92); border: 1px solid rgba(15, 23, 42, 0.06); }
    .ticker-summary-card strong { display: block; font-size: 0.78rem; letter-spacing: 0.06em; text-transform: uppercase; color: var(--admin-text-soft); }
    .ticker-summary-card span { display: block; margin-top: 0.18rem; font-size: 0.9rem; font-weight: 600; }
    @media (max-width: 991px) {
        .ticker-layout { grid-template-columns: 1fr; }
        .ticker-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); }
        .ticker-span-8,
        .ticker-span-6,
        .ticker-span-4 { grid-column: span 6; }
        .ticker-screen-grid,
        .ticker-summary-grid { grid-template-columns: 1fr; }
        .ticker-days-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    }
</style>

<div class="ticker-page">
    <div class="section-heading">
        <div>
            <h1 class="h3">Tickers</h1>
            <div class="section-subtitle">Create a reusable library of bottom-bar messages, schedule them, and assign them to one screen or many.</div>
        </div>
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#createTickerModal">
            <i class="bi bi-plus-circle"></i>
            <span class="ms-1">Add Ticker</span>
        </button>
    </div>

    <div class="row g-3">
        <div class="col-6 col-xl-3"><div class="card stat-card"><div class="card-body"><div class="stat-label">Tickers</div><div class="stat-number-box"><div class="stat-value"><?= $tickerCount ?></div></div><div class="stat-meta">Saved library items</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card stat-card"><div class="card-body"><div class="stat-label">Active</div><div class="stat-number-box"><div class="stat-value"><?= $activeTickerCount ?></div></div><div class="stat-meta">Currently enabled entries</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card stat-card"><div class="card-body"><div class="stat-label">Screen Links</div><div class="stat-number-box"><div class="stat-value"><?= $assignedTickerCount ?></div></div><div class="stat-meta">Direct screen assignments</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card stat-card"><div class="card-body"><div class="stat-label">Timezone</div><div class="stat-number-box"><div class="small fw-semibold"><?= e(app_timezone_name()) ?></div></div><div class="stat-meta">Used for ticker scheduling</div></div></div></div>
    </div>

    <div class="ticker-layout">
        <div class="card list-card">
            <div class="card-header"><h2 class="h5 mb-0">Ticker Library</h2></div>
            <div class="card-body">
                <div class="ticker-list">
                    <?php if (!$tickers): ?>
                        <div class="text-muted">No ticker messages created yet.</div>
                    <?php else: ?>
                        <?php foreach ($tickers as $ticker): ?>
                            <a class="ticker-link <?= (int) $ticker['id'] === $selectedTickerId ? 'is-active' : '' ?>" href="<?= e(app_path('/admin/tickers.php?ticker_id=' . (int) $ticker['id'])) ?>">
                                <strong><?= e((string) $ticker['name']) ?></strong>
                                <span class="small">
                                    Priority <?= (int) $ticker['priority'] ?>
                                    · <?= (int) ($ticker['applies_to_all_screens'] ?? 0) === 1 ? 'All screens' : (int) ($ticker['screen_count'] ?? 0) . ' screen(s)' ?>
                                </span>
                                <span class="small"><?= e(ticker_list_excerpt((string) $ticker['message_text'])) ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card section-card">
            <div class="card-header"><h2 class="h5 mb-0"><?= $selectedTicker ? 'Edit Ticker' : 'Ticker Details' ?></h2></div>
            <div class="card-body">
                <?php if (!$selectedTicker): ?>
                    <div class="text-muted">Select a ticker to edit it, or create a new one.</div>
                <?php else: ?>
                    <form method="post" class="dense-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_ticker">
                        <input type="hidden" name="ticker_id" value="<?= (int) $selectedTicker['id'] ?>">

                        <div class="ticker-grid">
                            <div class="ticker-span-8">
                                <label class="form-label" for="ticker_name">Ticker Name</label>
                                <input class="form-control" id="ticker_name" name="name" type="text" value="<?= e((string) $selectedTicker['name']) ?>" required>
                            </div>
                            <div class="ticker-span-4">
                                <label class="form-label" for="ticker_priority">Priority</label>
                                <input class="form-control" id="ticker_priority" name="priority" type="number" min="1" value="<?= (int) $selectedTicker['priority'] ?>" required>
                            </div>

                            <div class="ticker-span-12">
                                <label class="form-label" for="ticker_message_text">Ticker Text</label>
                                <textarea class="form-control" id="ticker_message_text" name="message_text" rows="4" required><?= e((string) $selectedTicker['message_text']) ?></textarea>
                            </div>

                            <div class="ticker-span-12">
                                <div class="ticker-preview">
                                    <div class="ticker-preview-label">Preview</div>
                                    <div class="ticker-preview-text"><?= e((string) $selectedTicker['message_text']) ?></div>
                                </div>
                            </div>

                            <div class="ticker-span-4">
                                <label class="form-label" for="ticker_position">Placement</label>
                                <select class="form-select" id="ticker_position" name="position">
                                    <option value="bottom" <?= (($selectedTicker['position'] ?? 'bottom') === 'bottom') ? 'selected' : '' ?>>Bottom</option>
                                    <option value="top" <?= (($selectedTicker['position'] ?? 'bottom') === 'top') ? 'selected' : '' ?>>Top</option>
                                </select>
                            </div>
                            <div class="ticker-span-4">
                                <label class="form-label" for="ticker_speed_seconds">Scroll Speed Seconds</label>
                                <input class="form-control" id="ticker_speed_seconds" name="speed_seconds" type="number" min="10" value="<?= (int) $selectedTicker['speed_seconds'] ?>" required>
                            </div>
                            <div class="ticker-span-4">
                                <label class="form-label" for="ticker_height_px">Ticker Height (px)</label>
                                <input class="form-control" id="ticker_height_px" name="height_px" type="number" min="40" max="220" value="<?= (int) ($selectedTicker['height_px'] ?? 72) ?>" required>
                            </div>
                            <div class="ticker-span-4">
                                <label class="form-label" for="ticker_start_time">Daily Start Time</label>
                                <input class="form-control" id="ticker_start_time" name="start_time" type="time" value="<?= e(substr((string) $selectedTicker['start_time'], 0, 5)) ?>" required>
                            </div>
                            <div class="ticker-span-4">
                                <label class="form-label" for="ticker_end_time">Daily End Time</label>
                                <input class="form-control" id="ticker_end_time" name="end_time" type="time" value="<?= e(substr((string) $selectedTicker['end_time'], 0, 5)) ?>" required>
                            </div>

                            <div class="ticker-span-6">
                                <label class="form-label" for="ticker_starts_at">Schedule Start</label>
                                <input class="form-control" id="ticker_starts_at" name="starts_at" type="datetime-local" value="<?= e(!empty($selectedTicker['starts_at']) ? date('Y-m-d\TH:i', strtotime((string) $selectedTicker['starts_at'])) : '') ?>">
                            </div>
                            <div class="ticker-span-6">
                                <label class="form-label" for="ticker_ends_at">Schedule End</label>
                                <input class="form-control" id="ticker_ends_at" name="ends_at" type="datetime-local" value="<?= e(!empty($selectedTicker['ends_at']) ? date('Y-m-d\TH:i', strtotime((string) $selectedTicker['ends_at'])) : '') ?>">
                            </div>

                            <div class="ticker-span-12">
                                <label class="form-label">Days</label>
                                <div class="ticker-days-grid">
                                    <?php foreach (schedule_day_names() as $dayIndex => $dayLabel): ?>
                                        <label class="ticker-day-chip">
                                            <input type="checkbox" name="days[]" value="<?= $dayIndex ?>" <?= in_array($dayIndex, schedule_day_mask_days((int) $selectedTicker['day_mask']), true) ? 'checked' : '' ?>>
                                            <span><?= e($dayLabel) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="ticker-span-12">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" id="ticker_active" name="active" type="checkbox" value="1" <?= (int) $selectedTicker['active'] === 1 ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="ticker_active">Ticker is active</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" id="ticker_all_screens" name="applies_to_all_screens" type="checkbox" value="1" <?= (int) $selectedTicker['applies_to_all_screens'] === 1 ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="ticker_all_screens">Apply to all screens</label>
                                </div>
                            </div>

                            <div class="ticker-span-12">
                                <label class="form-label">Screen Assignments</label>
                                <div class="ticker-screen-grid">
                                    <?php foreach ($screens as $screen): ?>
                                        <label class="ticker-screen-chip">
                                            <input class="form-check-input" type="checkbox" name="screen_ids[]" value="<?= (int) $screen['id'] ?>" <?= in_array((int) $screen['id'], $selectedTickerScreenIds, true) ? 'checked' : '' ?>>
                                            <span class="ticker-screen-copy">
                                                <strong><?= e((string) $screen['name']) ?></strong>
                                                <span class="small text-muted"><?= e((string) ($screen['location'] ?: 'No location')) ?> · <?= e(ucfirst((string) $screen['status'])) ?></span>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">Assignments are ignored when “Apply to all screens” is enabled.</div>
                            </div>

                            <div class="ticker-span-12">
                                <div class="ticker-summary-grid">
                                    <div class="ticker-summary-card"><strong>Days</strong><span><?= e(schedule_day_mask_summary((int) $selectedTicker['day_mask'])) ?></span></div>
                                    <div class="ticker-summary-card"><strong>Time Window</strong><span><?= e(schedule_time_label((string) $selectedTicker['start_time']) . ' - ' . schedule_time_label((string) $selectedTicker['end_time'])) ?></span></div>
                                    <div class="ticker-summary-card"><strong>Assignment</strong><span><?= (int) $selectedTicker['applies_to_all_screens'] === 1 ? 'All screens' : count($selectedTickerScreenIds) . ' selected screen(s)' ?></span></div>
                                    <div class="ticker-summary-card"><strong>Status</strong><span><?= (int) $selectedTicker['active'] === 1 ? 'Active' : 'Inactive' ?></span></div>
                                    <div class="ticker-summary-card"><strong>Placement</strong><span><?= e(ucfirst((string) (($selectedTicker['position'] ?? 'bottom') === 'top' ? 'top' : 'bottom'))) ?></span></div>
                                    <div class="ticker-summary-card"><strong>Height</strong><span><?= (int) ($selectedTicker['height_px'] ?? 72) ?>px</span></div>
                                </div>
                            </div>
                        </div>

                        <div class="compact-form-actions mt-3">
                            <button class="btn btn-primary" type="submit">Save Ticker</button>
                            <button class="btn btn-outline-danger" type="submit" form="deleteTickerForm" onclick="return confirm('Delete this ticker?');">Delete</button>
                        </div>
                    </form>

                    <form id="deleteTickerForm" method="post" class="d-none">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_ticker">
                        <input type="hidden" name="ticker_id" value="<?= (int) $selectedTicker['id'] ?>">
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="createTickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h2 class="modal-title fs-5">Create Ticker</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_ticker">
                    <div class="ticker-grid">
                        <div class="ticker-span-8">
                            <label class="form-label" for="create_ticker_name">Ticker Name</label>
                            <input class="form-control" id="create_ticker_name" name="name" type="text" required>
                        </div>
                        <div class="ticker-span-4">
                            <label class="form-label" for="create_ticker_priority">Priority</label>
                            <input class="form-control" id="create_ticker_priority" name="priority" type="number" min="1" value="1" required>
                        </div>
                        <div class="ticker-span-12">
                            <label class="form-label" for="create_ticker_text">Ticker Text</label>
                            <textarea class="form-control" id="create_ticker_text" name="message_text" rows="4" required></textarea>
                        </div>
                        <div class="ticker-span-4">
                            <label class="form-label" for="create_ticker_position">Placement</label>
                            <select class="form-select" id="create_ticker_position" name="position">
                                <option value="bottom" selected>Bottom</option>
                                <option value="top">Top</option>
                            </select>
                        </div>
                        <div class="ticker-span-4">
                            <label class="form-label" for="create_ticker_speed">Scroll Speed Seconds</label>
                            <input class="form-control" id="create_ticker_speed" name="speed_seconds" type="number" min="10" value="28" required>
                        </div>
                        <div class="ticker-span-4">
                            <label class="form-label" for="create_ticker_height">Ticker Height (px)</label>
                            <input class="form-control" id="create_ticker_height" name="height_px" type="number" min="40" max="220" value="72" required>
                        </div>
                        <div class="ticker-span-4">
                            <label class="form-label" for="create_ticker_start_time">Daily Start Time</label>
                            <input class="form-control" id="create_ticker_start_time" name="start_time" type="time" value="00:00" required>
                        </div>
                        <div class="ticker-span-4">
                            <label class="form-label" for="create_ticker_end_time">Daily End Time</label>
                            <input class="form-control" id="create_ticker_end_time" name="end_time" type="time" value="23:59" required>
                        </div>
                        <div class="ticker-span-6">
                            <label class="form-label" for="create_ticker_starts_at">Schedule Start</label>
                            <input class="form-control" id="create_ticker_starts_at" name="starts_at" type="datetime-local">
                        </div>
                        <div class="ticker-span-6">
                            <label class="form-label" for="create_ticker_ends_at">Schedule End</label>
                            <input class="form-control" id="create_ticker_ends_at" name="ends_at" type="datetime-local">
                        </div>
                        <div class="ticker-span-12">
                            <label class="form-label">Days</label>
                            <div class="ticker-days-grid">
                                <?php foreach (schedule_day_names() as $dayIndex => $dayLabel): ?>
                                    <label class="ticker-day-chip">
                                        <input type="checkbox" name="days[]" value="<?= $dayIndex ?>" checked>
                                        <span><?= e($dayLabel) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="ticker-span-12">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" id="create_ticker_active" name="active" type="checkbox" value="1" checked>
                                <label class="form-check-label" for="create_ticker_active">Ticker is active</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" id="create_ticker_all_screens" name="applies_to_all_screens" type="checkbox" value="1">
                                <label class="form-check-label" for="create_ticker_all_screens">Apply to all screens</label>
                            </div>
                        </div>
                        <div class="ticker-span-12">
                            <label class="form-label">Screen Assignments</label>
                            <div class="ticker-screen-grid">
                                <?php foreach ($screens as $screen): ?>
                                    <label class="ticker-screen-chip">
                                        <input class="form-check-input" type="checkbox" name="screen_ids[]" value="<?= (int) $screen['id'] ?>">
                                        <span class="ticker-screen-copy">
                                            <strong><?= e((string) $screen['name']) ?></strong>
                                            <span class="small text-muted"><?= e((string) ($screen['location'] ?: 'No location')) ?> · <?= e(ucfirst((string) $screen['status'])) ?></span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" type="submit">Create Ticker</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

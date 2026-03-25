<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$db = get_db();
sync_screen_statuses($db);
$adminId = current_admin_id();

function admin_playlist_exists(mysqli $db, int $playlistId, int $adminId): bool
{
    if ($playlistId < 1) {
        return false;
    }

    $statement = $db->prepare("SELECT COUNT(*) AS total
        FROM playlists
        WHERE id = ? AND owner_admin_id = ?");
    $statement->bind_param('ii', $playlistId, $adminId);
    $statement->execute();
    $exists = (int) ($statement->get_result()->fetch_assoc()['total'] ?? 0) === 1;
    $statement->close();

    return $exists;
}

function admin_schedule_name_exists(mysqli $db, int $adminId, string $name, int $excludeId = 0): bool
{
    $statement = $db->prepare("SELECT COUNT(*) AS total
        FROM schedules
        WHERE owner_admin_id = ?
          AND name = ?
          AND id <> ?");
    $statement->bind_param('isi', $adminId, $name, $excludeId);
    $statement->execute();
    $exists = (int) ($statement->get_result()->fetch_assoc()['total'] ?? 0) > 0;
    $statement->close();

    return $exists;
}

function find_admin_schedule(mysqli $db, int $scheduleId, int $adminId): ?array
{
    $statement = $db->prepare("SELECT sc.*,
            (SELECT COUNT(*) FROM schedule_rules sr WHERE sr.schedule_id = sc.id) AS rule_count,
            (SELECT COUNT(*) FROM screens s WHERE s.schedule_id = sc.id AND s.owner_admin_id = sc.owner_admin_id) AS screen_count
        FROM schedules sc
        WHERE sc.id = ? AND sc.owner_admin_id = ?
        LIMIT 1");
    $statement->bind_param('ii', $scheduleId, $adminId);
    $statement->execute();
    $schedule = $statement->get_result()->fetch_assoc() ?: null;
    $statement->close();

    return $schedule;
}

function fetch_admin_schedules(mysqli $db, int $adminId): array
{
    $statement = $db->prepare("SELECT sc.*,
            (SELECT COUNT(*) FROM schedule_rules sr WHERE sr.schedule_id = sc.id) AS rule_count,
            (SELECT COUNT(*) FROM screens s WHERE s.schedule_id = sc.id AND s.owner_admin_id = sc.owner_admin_id) AS screen_count
        FROM schedules sc
        WHERE sc.owner_admin_id = ?
        ORDER BY sc.name ASC, sc.id ASC");
    $statement->bind_param('i', $adminId);
    $statement->execute();
    $result = $statement->get_result();
    $schedules = [];
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
    $statement->close();

    return $schedules;
}

function fetch_schedule_screens(mysqli $db, int $scheduleId, int $adminId): array
{
    $statement = $db->prepare("SELECT id, name, location, active, status
        FROM screens
        WHERE owner_admin_id = ? AND schedule_id = ?
        ORDER BY status = 'online' DESC, name ASC, id ASC");
    $statement->bind_param('ii', $adminId, $scheduleId);
    $statement->execute();
    $result = $statement->get_result();
    $screens = [];
    while ($row = $result->fetch_assoc()) {
        $screens[] = $row;
    }
    $statement->close();

    return $screens;
}

if (is_post_request()) {
    require_valid_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_schedule') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $active = isset($_POST['active']) ? 1 : 0;

        if ($name === '') {
            set_flash('danger', 'Schedule name is required.');
            redirect('/admin/schedules.php');
        }

        if (admin_schedule_name_exists($db, $adminId, $name)) {
            set_flash('danger', 'That schedule name already exists.');
            redirect('/admin/schedules.php');
        }

        $statement = $db->prepare("INSERT INTO schedules
            (owner_admin_id, name, active, created_at, updated_at)
            VALUES (?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
        $statement->bind_param('isi', $adminId, $name, $active);
        $statement->execute();
        $scheduleId = (int) $statement->insert_id;
        $statement->close();

        set_flash('success', 'Schedule created.');
        redirect('/admin/schedules.php?schedule_id=' . $scheduleId);
    }

    $scheduleId = (int) ($_POST['schedule_id'] ?? 0);
    $schedule = find_admin_schedule($db, $scheduleId, $adminId);

    if (!$schedule) {
        set_flash('danger', 'Schedule not found.');
        redirect('/admin/schedules.php');
    }

    if ($action === 'update_schedule') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $active = isset($_POST['active']) ? 1 : 0;

        if ($name === '') {
            set_flash('danger', 'Schedule name is required.');
            redirect('/admin/schedules.php?schedule_id=' . $scheduleId);
        }

        if (admin_schedule_name_exists($db, $adminId, $name, $scheduleId)) {
            set_flash('danger', 'That schedule name already exists.');
            redirect('/admin/schedules.php?schedule_id=' . $scheduleId);
        }

        $statement = $db->prepare("UPDATE schedules
            SET name = ?, active = ?, updated_at = UTC_TIMESTAMP()
            WHERE id = ? AND owner_admin_id = ?");
        $statement->bind_param('siii', $name, $active, $scheduleId, $adminId);
        $statement->execute();
        $statement->close();

        bump_schedule_screen_sync_revision($db, $scheduleId, $adminId);
        set_flash('success', 'Schedule updated.');
        redirect('/admin/schedules.php?schedule_id=' . $scheduleId);
    }

    if ($action === 'delete_schedule') {
        $statement = $db->prepare("UPDATE screens
            SET schedule_id = NULL, sync_revision = sync_revision + 1
            WHERE owner_admin_id = ? AND schedule_id = ?");
        $statement->bind_param('ii', $adminId, $scheduleId);
        $statement->execute();
        $statement->close();

        $statement = $db->prepare("DELETE FROM schedules WHERE id = ? AND owner_admin_id = ?");
        $statement->bind_param('ii', $scheduleId, $adminId);
        $statement->execute();
        $statement->close();

        set_flash('success', 'Schedule deleted. Screens now fall back to their direct playlist.');
        redirect('/admin/schedules.php');
    }

    if ($action === 'create_schedule_rule' || $action === 'update_schedule_rule') {
        $playlistId = (int) ($_POST['playlist_id'] ?? 0);
        $label = trim((string) ($_POST['label'] ?? ''));
        $dayMask = build_schedule_day_mask(array_map('intval', (array) ($_POST['days'] ?? [])));
        $startTime = trim((string) ($_POST['start_time'] ?? ''));
        $endTime = trim((string) ($_POST['end_time'] ?? ''));
        $priority = max(1, normalize_int($_POST['priority'] ?? null, 1));
        $active = isset($_POST['active']) ? 1 : 0;

        if (!admin_playlist_exists($db, $playlistId, $adminId)) {
            set_flash('danger', 'Choose a valid playlist.');
            redirect('/admin/schedules.php?schedule_id=' . $scheduleId);
        }

        if ($dayMask === 0) {
            set_flash('danger', 'Choose at least one day.');
            redirect('/admin/schedules.php?schedule_id=' . $scheduleId);
        }

        if (!preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            set_flash('danger', 'Enter valid start and end times.');
            redirect('/admin/schedules.php?schedule_id=' . $scheduleId);
        }

        $startTime .= ':00';
        $endTime .= ':00';

        if ($action === 'create_schedule_rule') {
            $statement = $db->prepare("INSERT INTO schedule_rules
                (schedule_id, playlist_id, label, day_mask, start_time, end_time, priority, active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
            $statement->bind_param('iisissii', $scheduleId, $playlistId, $label, $dayMask, $startTime, $endTime, $priority, $active);
            $statement->execute();
            $statement->close();
            bump_schedule_screen_sync_revision($db, $scheduleId, $adminId);
            set_flash('success', 'Rule added.');
            redirect('/admin/schedules.php?schedule_id=' . $scheduleId);
        }

        $ruleId = (int) ($_POST['rule_id'] ?? 0);
        $statement = $db->prepare("UPDATE schedule_rules sr
            INNER JOIN schedules sc ON sc.id = sr.schedule_id
            SET sr.playlist_id = ?, sr.label = ?, sr.day_mask = ?, sr.start_time = ?, sr.end_time = ?, sr.priority = ?, sr.active = ?, sr.updated_at = UTC_TIMESTAMP()
            WHERE sr.id = ? AND sr.schedule_id = ? AND sc.owner_admin_id = ?");
        $statement->bind_param('isissiiiii', $playlistId, $label, $dayMask, $startTime, $endTime, $priority, $active, $ruleId, $scheduleId, $adminId);
        $statement->execute();
        $statement->close();

        bump_schedule_screen_sync_revision($db, $scheduleId, $adminId);
        set_flash('success', 'Rule updated.');
        redirect('/admin/schedules.php?schedule_id=' . $scheduleId);
    }

    if ($action === 'delete_schedule_rule') {
        $ruleId = (int) ($_POST['rule_id'] ?? 0);
        $statement = $db->prepare("DELETE sr
            FROM schedule_rules sr
            INNER JOIN schedules sc ON sc.id = sr.schedule_id
            WHERE sr.id = ? AND sr.schedule_id = ? AND sc.owner_admin_id = ?");
        $statement->bind_param('iii', $ruleId, $scheduleId, $adminId);
        $statement->execute();
        $statement->close();

        bump_schedule_screen_sync_revision($db, $scheduleId, $adminId);
        set_flash('success', 'Rule deleted.');
        redirect('/admin/schedules.php?schedule_id=' . $scheduleId);
    }
}

$schedules = fetch_admin_schedules($db, $adminId);
$selectedScheduleId = (int) ($_GET['schedule_id'] ?? ($schedules[0]['id'] ?? 0));
$selectedSchedule = $selectedScheduleId > 0 ? find_admin_schedule($db, $selectedScheduleId, $adminId) : null;
$selectedRules = $selectedSchedule ? fetch_schedule_rules($db, (int) $selectedSchedule['id'], $adminId) : [];
$selectedScreens = $selectedSchedule ? fetch_schedule_screens($db, (int) $selectedSchedule['id'], $adminId) : [];

$playlists = [];
$statement = $db->prepare("SELECT id, name, active
    FROM playlists
    WHERE owner_admin_id = ?
    ORDER BY name ASC");
$statement->bind_param('i', $adminId);
$statement->execute();
$result = $statement->get_result();
while ($row = $result->fetch_assoc()) {
    $playlists[] = $row;
}
$statement->close();

$ruleCount = 0;
$linkedScreenCount = 0;
foreach ($schedules as $schedule) {
    $ruleCount += (int) ($schedule['rule_count'] ?? 0);
    $linkedScreenCount += (int) ($schedule['screen_count'] ?? 0);
}

$pageTitle = 'Schedules';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
    .schedule-page { display: grid; gap: 0.75rem; }
    .schedule-page .section-heading { margin-bottom: 0; }
    .schedule-layout { display: grid; grid-template-columns: minmax(0, 18rem) minmax(0, 1fr); gap: 0.75rem; align-items: start; }
    .schedule-list-card .card-body,
    .schedule-main-card .card-body,
    .schedule-rule-card { padding: 0.8rem; }
    .schedule-list { display: grid; gap: 0.5rem; }
    .schedule-list-link { display: grid; gap: 0.18rem; padding: 0.68rem 0.76rem; border-radius: 0.85rem; border: 1px solid rgba(15, 23, 42, 0.08); background: rgba(248, 250, 252, 0.92); color: inherit; text-decoration: none; }
    .schedule-list-link.is-active { background: #0f172a; color: #fff; }
    .schedule-list-link .small { opacity: 0.8; color: inherit; }
    .schedule-main-grid { display: grid; gap: 0.75rem; }
    .schedule-form-grid { display: grid; gap: 0.75rem; grid-template-columns: repeat(12, minmax(0, 1fr)); }
    .schedule-span-12 { grid-column: span 12; }
    .schedule-span-8 { grid-column: span 8; }
    .schedule-span-6 { grid-column: span 6; }
    .schedule-span-4 { grid-column: span 4; }
    .schedule-days-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 0.35rem; }
    .schedule-day-chip { display: flex; align-items: center; justify-content: center; min-height: 2rem; border: 1px solid rgba(15, 23, 42, 0.1); border-radius: 0.72rem; background: rgba(248, 250, 252, 0.95); font-size: 0.76rem; }
    .schedule-day-chip input { display: none; }
    .schedule-day-chip span { font-weight: 700; color: #64748b; }
    .schedule-day-chip input:checked + span { color: #0f172a; }
    .schedule-day-chip:has(input:checked) { background: #e2e8f0; border-color: rgba(15, 23, 42, 0.18); }
    .schedule-summary { display: grid; gap: 0.45rem; }
    .schedule-summary-row { display: flex; align-items: center; justify-content: space-between; gap: 0.6rem; padding: 0.48rem 0.58rem; border-radius: 0.75rem; background: rgba(248, 250, 252, 0.94); }
    .schedule-summary-label { font-size: 0.74rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--admin-text-soft); }
    .schedule-summary-value { text-align: right; font-weight: 600; }
    .schedule-screen-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; }
    .schedule-screen-chip { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.34rem 0.56rem; border-radius: 999px; background: rgba(248, 250, 252, 0.96); border: 1px solid rgba(15, 23, 42, 0.08); }
    .schedule-rules { display: grid; gap: 0.65rem; }
    .schedule-rule-card { border: 1px solid rgba(15, 23, 42, 0.08); border-radius: 0.95rem; background: rgba(255, 255, 255, 0.98); box-shadow: 0 0.2rem 0.7rem rgba(15, 23, 42, 0.04); }
    .schedule-rule-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem; margin-bottom: 0.65rem; }
    .schedule-rule-title { font-weight: 700; }
    .schedule-rule-meta { color: var(--admin-text-soft); font-size: 0.8rem; }
    .schedule-rule-actions { display: flex; align-items: center; gap: 0.4rem; }
    @media (max-width: 991px) {
        .schedule-layout { grid-template-columns: 1fr; }
        .schedule-form-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); }
        .schedule-span-8,
        .schedule-span-6,
        .schedule-span-4 { grid-column: span 6; }
        .schedule-days-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    }
    @media (max-width: 767px) {
        .schedule-page { gap: 0.55rem; }
        .schedule-list-card .card-body,
        .schedule-main-card .card-body,
        .schedule-rule-card { padding: 0.7rem; }
        .schedule-form-grid { gap: 0.55rem; }
        .schedule-summary-row { padding: 0.42rem 0.5rem; }
        .schedule-rule-head { flex-direction: column; align-items: stretch; gap: 0.5rem; }
        .schedule-rule-actions { justify-content: flex-start; }
        .schedule-screen-chip { max-width: 100%; }
    }
</style>

<div class="page-shell schedule-page">
    <div class="section-heading">
        <div>
            <h1 class="h3">Schedules</h1>
            <div class="section-subtitle">Build named schedules once, then assign them to any screen from the screens page.</div>
        </div>
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#createScheduleModal">
            <i class="bi bi-plus-circle"></i>
            <span class="ms-1">Add Schedule</span>
        </button>
    </div>

    <div class="row g-3">
        <div class="col-6 col-xl-3"><div class="card stat-card"><div class="card-body"><div class="stat-label">Schedules</div><div class="stat-number-box"><div class="stat-value"><?= count($schedules) ?></div></div><div class="stat-meta">Named schedule sets</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card stat-card"><div class="card-body"><div class="stat-label">Rules</div><div class="stat-number-box"><div class="stat-value"><?= $ruleCount ?></div></div><div class="stat-meta">Active and inactive entries</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card stat-card"><div class="card-body"><div class="stat-label">Linked Screens</div><div class="stat-number-box"><div class="stat-value"><?= $linkedScreenCount ?></div></div><div class="stat-meta">Screens using schedules</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card stat-card"><div class="card-body"><div class="stat-label">Timezone</div><div class="stat-number-box"><div class="small fw-semibold"><?= e(app_timezone_name()) ?></div></div><div class="stat-meta">Used for all schedule times</div></div></div></div>
    </div>

    <div class="schedule-layout">
        <div class="card list-card schedule-list-card">
            <div class="card-header"><h2 class="h5 mb-0">Named Schedules</h2></div>
            <div class="card-body">
                <div class="schedule-list">
                    <?php if (!$schedules): ?>
                        <div class="text-muted">No schedules created yet.</div>
                    <?php else: ?>
                        <?php foreach ($schedules as $schedule): ?>
                            <a class="schedule-list-link <?= (int) $schedule['id'] === $selectedScheduleId ? 'is-active' : '' ?>" href="<?= e(app_path('/admin/schedules.php?schedule_id=' . (int) $schedule['id'])) ?>">
                                <strong><?= e($schedule['name']) ?></strong>
                                <div class="small"><?= (int) $schedule['rule_count'] ?> rules</div>
                                <div class="small"><?= (int) $schedule['screen_count'] ?> linked screens</div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="schedule-main-grid">
            <?php if (!$selectedSchedule): ?>
                <div class="card schedule-main-card"><div class="card-body text-muted">Create a named schedule, then add time rules to it.</div></div>
            <?php else: ?>
                <div class="card schedule-main-card">
                    <div class="card-header"><h2 class="h5 mb-0">Schedule Details</h2></div>
                    <div class="card-body">
                        <div class="schedule-form-grid">
                            <form class="schedule-form-grid schedule-span-12" method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update_schedule">
                                <input type="hidden" name="schedule_id" value="<?= (int) $selectedSchedule['id'] ?>">
                                <div class="schedule-span-8">
                                    <label class="form-label" for="schedule_name">Schedule Name</label>
                                    <input class="form-control" id="schedule_name" name="name" type="text" value="<?= e($selectedSchedule['name']) ?>" required>
                                </div>
                                <div class="schedule-span-4">
                                    <label class="form-label d-block">Status</label>
                                    <div class="form-check pt-2">
                                        <input class="form-check-input" id="schedule_active" name="active" type="checkbox" <?= (int) $selectedSchedule['active'] === 1 ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="schedule_active">Schedule active</label>
                                    </div>
                                </div>
                                <div class="schedule-span-12 d-flex flex-wrap gap-2">
                                    <button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i><span class="ms-1">Save Schedule</span></button>
                                </div>
                            </form>

                            <div class="schedule-span-12 schedule-summary">
                                <div class="schedule-summary-row"><div class="schedule-summary-label">Linked Screens</div><div class="schedule-summary-value"><?= count($selectedScreens) ?></div></div>
                                <div class="schedule-summary-row"><div class="schedule-summary-label">Rules</div><div class="schedule-summary-value"><?= count($selectedRules) ?></div></div>
                                <div class="schedule-summary-row"><div class="schedule-summary-label">Fallback</div><div class="schedule-summary-value">Screens use their direct playlist when no rule matches.</div></div>
                            </div>

                            <div class="schedule-span-12">
                                <div class="form-label">Screens Using This Schedule</div>
                                <?php if (!$selectedScreens): ?>
                                    <div class="text-muted">No screens are linked yet. Pick this schedule from the screens page.</div>
                                <?php else: ?>
                                    <div class="schedule-screen-chips">
                                        <?php foreach ($selectedScreens as $screen): ?>
                                            <span class="schedule-screen-chip">
                                                <strong><?= e($screen['name']) ?></strong>
                                                <span class="small"><?= e($screen['location'] ?: 'No location') ?></span>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <form class="schedule-span-12" method="post" onsubmit="return confirm('Delete this schedule? Linked screens will fall back to their direct playlist.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_schedule">
                                <input type="hidden" name="schedule_id" value="<?= (int) $selectedSchedule['id'] ?>">
                                <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash"></i><span class="ms-1">Delete Schedule</span></button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="card schedule-main-card">
                    <div class="card-header"><h2 class="h5 mb-0">Add Rule</h2></div>
                    <div class="card-body">
                        <form class="schedule-form-grid" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="create_schedule_rule">
                            <input type="hidden" name="schedule_id" value="<?= (int) $selectedSchedule['id'] ?>">
                            <div class="schedule-span-8">
                                <label class="form-label" for="schedule_playlist_id">Playlist</label>
                                <select class="form-select" id="schedule_playlist_id" name="playlist_id" required>
                                    <option value="">Select playlist</option>
                                    <?php foreach ($playlists as $playlist): ?>
                                        <option value="<?= (int) $playlist['id'] ?>"><?= e($playlist['name']) ?><?= (int) $playlist['active'] === 1 ? '' : ' (inactive)' ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="schedule-span-4">
                                <label class="form-label" for="schedule_rule_label">Label</label>
                                <input class="form-control" id="schedule_rule_label" name="label" type="text" placeholder="Breakfast, Evening, etc">
                            </div>
                            <div class="schedule-span-12">
                                <label class="form-label">Days</label>
                                <div class="schedule-days-grid">
                                    <?php foreach (schedule_day_names() as $dayIndex => $dayLabel): ?>
                                        <label class="schedule-day-chip">
                                            <input type="checkbox" name="days[]" value="<?= $dayIndex ?>" checked>
                                            <span><?= e($dayLabel) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="schedule-span-4">
                                <label class="form-label" for="schedule_start_time">Start</label>
                                <input class="form-control" id="schedule_start_time" name="start_time" type="time" value="09:00" required>
                            </div>
                            <div class="schedule-span-4">
                                <label class="form-label" for="schedule_end_time">End</label>
                                <input class="form-control" id="schedule_end_time" name="end_time" type="time" value="17:00" required>
                            </div>
                            <div class="schedule-span-4">
                                <label class="form-label" for="schedule_priority">Priority</label>
                                <input class="form-control" id="schedule_priority" name="priority" type="number" min="1" value="1" required>
                            </div>
                            <div class="schedule-span-12">
                                <div class="form-check">
                                    <input class="form-check-input" id="schedule_rule_active" name="active" type="checkbox" checked>
                                    <label class="form-check-label" for="schedule_rule_active">Rule active</label>
                                </div>
                            </div>
                            <div class="schedule-span-12">
                                <div class="form-text">Times use <?= e(app_timezone_name()) ?>. If end is earlier than start, the rule runs overnight.</div>
                            </div>
                            <div class="schedule-span-12">
                                <button class="btn btn-primary" type="submit"><i class="bi bi-plus-circle"></i><span class="ms-1">Add Rule</span></button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card schedule-main-card">
                    <div class="card-header"><h2 class="h5 mb-0">Rules</h2></div>
                    <div class="card-body">
                        <?php if (!$selectedRules): ?>
                            <div class="text-muted">No rules yet. Screens using this schedule will fall back to their direct playlist.</div>
                        <?php else: ?>
                            <div class="schedule-rules">
                                <?php foreach ($selectedRules as $rule): ?>
                                    <?php $ruleFormId = 'schedule-rule-form-' . (int) $rule['id']; ?>
                                    <div class="schedule-rule-card">
                                        <div class="schedule-rule-head">
                                            <div>
                                                <div class="schedule-rule-title"><?= e($rule['label'] ?: $rule['playlist_name']) ?></div>
                                                <div class="schedule-rule-meta"><?= e(schedule_day_mask_summary((int) $rule['day_mask'])) ?> | <?= e(schedule_time_label((string) $rule['start_time'])) ?> - <?= e(schedule_time_label((string) $rule['end_time'])) ?></div>
                                            </div>
                                            <div class="schedule-rule-actions">
                                                <button class="btn btn-sm btn-outline-primary" type="submit" form="<?= e($ruleFormId) ?>"><i class="bi bi-check2"></i><span class="ms-1">Save</span></button>
                                                <form method="post" class="m-0" onsubmit="return confirm('Delete this rule?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete_schedule_rule">
                                                    <input type="hidden" name="schedule_id" value="<?= (int) $selectedSchedule['id'] ?>">
                                                    <input type="hidden" name="rule_id" value="<?= (int) $rule['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i><span class="ms-1">Delete</span></button>
                                                </form>
                                            </div>
                                        </div>
                                        <form method="post" id="<?= e($ruleFormId) ?>" class="schedule-form-grid">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="update_schedule_rule">
                                            <input type="hidden" name="schedule_id" value="<?= (int) $selectedSchedule['id'] ?>">
                                            <input type="hidden" name="rule_id" value="<?= (int) $rule['id'] ?>">
                                            <div class="schedule-span-8">
                                                <label class="form-label">Playlist</label>
                                                <select class="form-select" name="playlist_id">
                                                    <?php foreach ($playlists as $playlist): ?>
                                                        <option value="<?= (int) $playlist['id'] ?>" <?= (int) $playlist['id'] === (int) $rule['playlist_id'] ? 'selected' : '' ?>><?= e($playlist['name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="schedule-span-4">
                                                <label class="form-label">Label</label>
                                                <input class="form-control" type="text" name="label" value="<?= e($rule['label']) ?>" placeholder="Optional label">
                                            </div>
                                            <div class="schedule-span-12">
                                                <label class="form-label">Days</label>
                                                <div class="schedule-days-grid">
                                                    <?php foreach (schedule_day_names() as $dayIndex => $dayLabel): ?>
                                                        <?php $isChecked = in_array($dayIndex, schedule_day_mask_days((int) $rule['day_mask']), true); ?>
                                                        <label class="schedule-day-chip">
                                                            <input type="checkbox" name="days[]" value="<?= $dayIndex ?>" <?= $isChecked ? 'checked' : '' ?>>
                                                            <span><?= e($dayLabel) ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <div class="schedule-span-4">
                                                <label class="form-label">Start</label>
                                                <input class="form-control" type="time" name="start_time" value="<?= e(substr((string) $rule['start_time'], 0, 5)) ?>" required>
                                            </div>
                                            <div class="schedule-span-4">
                                                <label class="form-label">End</label>
                                                <input class="form-control" type="time" name="end_time" value="<?= e(substr((string) $rule['end_time'], 0, 5)) ?>" required>
                                            </div>
                                            <div class="schedule-span-4">
                                                <label class="form-label">Priority</label>
                                                <input class="form-control" type="number" min="1" name="priority" value="<?= (int) $rule['priority'] ?>">
                                            </div>
                                            <div class="schedule-span-12">
                                                <div class="form-check">
                                                    <input class="form-check-input" id="schedule_rule_active_<?= (int) $rule['id'] ?>" type="checkbox" name="active" value="1" <?= (int) $rule['active'] === 1 ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="schedule_rule_active_<?= (int) $rule['id'] ?>">Rule active</label>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="createScheduleModal" tabindex="-1" aria-labelledby="createScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 mb-0" id="createScheduleModalLabel">Add Schedule</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form class="dense-form" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_schedule">
                    <div class="mb-3">
                        <label class="form-label" for="create_schedule_name">Schedule Name</label>
                        <input class="form-control" id="create_schedule_name" name="name" type="text" required>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" id="create_schedule_active" name="active" type="checkbox" checked>
                        <label class="form-check-label" for="create_schedule_active">Schedule active</label>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-plus-circle"></i><span class="ms-1">Create Schedule</span></button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

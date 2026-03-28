<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$db = get_db();
sync_screen_statuses($db);
$adminId = current_admin_id();

$counts = [
    'media' => 0,
    'quizzes' => 0,
    'playlists' => 0,
    'screens' => 0,
    'online_screens' => 0,
];

foreach ([
    'media' => "SELECT COUNT(*) AS total FROM media WHERE owner_admin_id = ?",
    'quizzes' => "SELECT COUNT(*) AS total FROM quiz_questions WHERE owner_admin_id = ?",
    'playlists' => "SELECT COUNT(*) AS total FROM playlists WHERE owner_admin_id = ?",
    'screens' => "SELECT COUNT(*) AS total FROM screens WHERE owner_admin_id = ?",
    'online_screens' => "SELECT COUNT(*) AS total FROM screens WHERE owner_admin_id = ? AND status = 'online'",
] as $key => $sql) {
    $statement = $db->prepare($sql);
    $statement->bind_param('i', $adminId);
    $statement->execute();
    $counts[$key] = (int) $statement->get_result()->fetch_assoc()['total'];
    $statement->close();
}

$recentActivity = [];
$latestActivePlaylist = null;
$unassignedScreens = 0;
$attentionScreens = [];

$statement = $db->prepare("SELECT id, name, updated_at
    FROM playlists
    WHERE owner_admin_id = ? AND active = 1
    ORDER BY updated_at DESC, id DESC
    LIMIT 1");
$statement->bind_param('i', $adminId);
$statement->execute();
$latestActivePlaylist = $statement->get_result()->fetch_assoc() ?: null;
$statement->close();

$statement = $db->prepare("SELECT COUNT(*) AS total
    FROM screens
    WHERE owner_admin_id = ? AND playlist_id IS NULL");
$statement->bind_param('i', $adminId);
$statement->execute();
$unassignedScreens = (int) $statement->get_result()->fetch_assoc()['total'];
$statement->close();

$sql = "SELECT s.name, s.location, s.last_seen, s.status, s.last_ip, s.resolution, p.name AS playlist_name
        , s.playlist_id
        FROM screens s
        LEFT JOIN playlists p ON p.id = s.playlist_id AND p.owner_admin_id = s.owner_admin_id
        WHERE s.owner_admin_id = ?
        ORDER BY s.last_seen IS NULL, s.last_seen DESC, s.created_at DESC
        LIMIT 10";
$statement = $db->prepare($sql);
$statement->bind_param('i', $adminId);
$statement->execute();
$result = $statement->get_result();
while ($row = $result->fetch_assoc()) {
    $recentActivity[] = $row;
}
$statement->close();

$statement = $db->prepare("SELECT
        s.name,
        s.location,
        s.last_seen,
        s.status,
        s.playlist_id,
        p.name AS playlist_name
    FROM screens s
    LEFT JOIN playlists p ON p.id = s.playlist_id AND p.owner_admin_id = s.owner_admin_id
    WHERE s.owner_admin_id = ?
    ORDER BY s.status = 'online' ASC, s.last_seen IS NULL DESC, s.last_seen ASC, s.name ASC
    LIMIT 6");
$statement->bind_param('i', $adminId);
$statement->execute();
$result = $statement->get_result();
while ($row = $result->fetch_assoc()) {
    $attentionScreens[] = $row;
}
$statement->close();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
    .dashboard-admin-page { display: grid; gap: 0.75rem; }
    .dashboard-admin-page .section-heading { margin-bottom: 0; }
    .dashboard-admin-page .stat-card .card-body,
    .dashboard-admin-page .card .card-body { padding: 0.82rem; }
    .dashboard-admin-page .card .card-header { padding: 0.78rem 0.82rem; }
    .dashboard-box-head h3 { margin: 0; font-size: 1rem; }
    .dashboard-box-head p { margin: 0.2rem 0 0; color: var(--admin-text-soft); }
    .attention-list { display: grid; gap: 0.6rem; }
    .attention-item { padding: 0.85rem 0.9rem; border: 1px solid rgba(15, 23, 42, 0.08); border-radius: 0.95rem; background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.96)); }
    .attention-item strong { display: block; margin-bottom: 0.18rem; color: var(--admin-text-strong); }
    .attention-item span { display: block; color: var(--admin-text-soft); line-height: 1.3; }
    .dashboard-screen-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 0.75rem; }
    .dashboard-screen-card { border: 1px solid rgba(15, 23, 42, 0.08); border-radius: 1rem; background: linear-gradient(180deg, rgba(255, 255, 255, 0.99), rgba(248, 250, 252, 0.96)); box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05); overflow: hidden; }
    .dashboard-screen-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 0.75rem; padding: 0.9rem 0.95rem 0.7rem; }
    .dashboard-screen-head strong { display: block; color: var(--admin-text-strong); line-height: 1.25; }
    .dashboard-screen-head span:not(.badge) { display: block; margin-top: 0.2rem; color: var(--admin-text-soft); font-size: 0.84rem; }
    .dashboard-screen-body { padding: 0 0.95rem 0.95rem; }
    .summary-list { display: grid; gap: 0.55rem; }
    .summary-row { display: grid; gap: 0.14rem; }
    .summary-label { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--admin-text-soft); }
    .summary-value { color: var(--admin-text-strong); line-height: 1.3; }
    @media (max-width: 767px) {
        .dashboard-admin-page { gap: 0.6rem; }
        .dashboard-admin-page .row.g-3 { --bs-gutter-x: 0.55rem; --bs-gutter-y: 0.55rem; }
        .dashboard-admin-page .stat-card .card-body,
        .dashboard-admin-page .card .card-body { padding: 0.78rem; }
        .dashboard-admin-page .card .card-header { padding: 0.78rem 0.82rem; }
        .dashboard-admin-page .section-heading { gap: 0.6rem; }
        .dashboard-admin-page .section-heading.mb-0 { align-items: stretch; }
        .dashboard-admin-page .section-heading .btn { width: 100%; justify-content: center; }
        .attention-item { padding: 0.8rem 0.82rem; border-radius: 0.9rem; }
        .dashboard-screen-grid { grid-template-columns: 1fr; gap: 0.6rem; }
        .dashboard-screen-card { border-radius: 0.95rem; }
        .dashboard-screen-head { padding: 0.82rem 0.85rem 0.68rem; }
        .dashboard-screen-body { padding: 0 0.85rem 0.85rem; }
    }
</style>
<div class="page-shell dashboard-admin-page">
<div class="section-heading">
    <div>
        <h1 class="h3">Dashboard</h1>
        <div class="section-subtitle">Only the key counts, screens that need action, and recent screen status.</div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Screens</div>
                <div class="stat-number-box"><div class="stat-value"><?= $counts['screens'] ?></div></div>
                <div class="stat-meta"><?= $counts['online_screens'] ?> online</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Unassigned</div>
                <div class="stat-number-box"><div class="stat-value"><?= $unassignedScreens ?></div></div>
                <div class="stat-meta">Need playlist</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Playlists</div>
                <div class="stat-number-box"><div class="stat-value"><?= $counts['playlists'] ?></div></div>
                <div class="stat-meta"><?= $latestActivePlaylist ? 'Latest: ' . e($latestActivePlaylist['name']) : 'No active playlist' ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Content</div>
                <div class="stat-number-box"><div class="stat-value"><?= $counts['media'] + $counts['quizzes'] ?></div></div>
                <div class="stat-meta"><?= $counts['media'] ?> media / <?= $counts['quizzes'] ?> quizzes</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card h-100">
            <div class="card-header"><h2 class="h5 mb-0">Needs Attention</h2></div>
            <div class="card-body">
                <section class="dashboard-box h-100">
                    <div class="dashboard-box-head">
                        <div>
                            <h3>Priority Screens</h3>
                            <p>Offline, unassigned, or behind the current playlist.</p>
                        </div>
                    </div>
                    <div class="dashboard-box-body">
                        <div class="attention-list">
                            <?php $hasAttentionItems = false; ?>
                            <?php if (!$attentionScreens): ?>
                                <div class="attention-item">
                                    <strong>No screens yet</strong>
                                    <span>Create a screen to start monitoring player status.</span>
                                </div>
                            <?php else: ?>
                                <?php foreach ($attentionScreens as $screen): ?>
                                    <?php $screenOnline = screen_is_online($screen['last_seen']); ?>
                                    <?php $assignedPlaylistId = (int) ($screen['playlist_id'] ?? 0); ?>
                                    <?php $latestPlaylistId = (int) ($latestActivePlaylist['id'] ?? 0); ?>
                                    <?php $needsPlaylist = $assignedPlaylistId < 1; ?>
                                    <?php $isOutdated = $latestPlaylistId > 0 && $assignedPlaylistId > 0 && $assignedPlaylistId !== $latestPlaylistId; ?>
                                    <?php if ($screenOnline && !$needsPlaylist && !$isOutdated) { continue; } ?>
                                    <?php $hasAttentionItems = true; ?>
                                    <div class="attention-item">
                                        <strong><?= e($screen['name']) ?></strong>
                                        <span>
                                            <?= e($screen['location'] ?: 'No location') ?> ·
                                            <?= $screenOnline ? 'Online' : 'Offline' ?> ·
                                            <?= $needsPlaylist ? 'Unassigned' : ($isOutdated ? 'Not using latest playlist' : 'Using latest playlist') ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (!$hasAttentionItems): ?>
                                    <div class="attention-item">
                                        <strong>All screens look healthy</strong>
                                        <span>No offline, unassigned, or outdated screens were found.</span>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        <div class="card h-100">
            <div class="card-header">
                <div class="section-heading mb-0">
                    <div>
                        <h2 class="h5 mb-0">Recent Screens</h2>
                        <div class="section-subtitle">Latest activity and assignment status in one place.</div>
                    </div>
                    <a class="btn btn-outline-dark btn-sm" href="<?= e(app_path('/admin/screens.php')) ?>">
                        <i class="bi bi-display"></i>
                        <span class="ms-1">Open Screens</span>
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (!$recentActivity): ?>
                    <p class="text-muted mb-0">No screens created yet.</p>
                <?php else: ?>
                    <div class="dashboard-screen-grid">
                        <?php foreach ($recentActivity as $screen): ?>
                            <?php $online = screen_is_online($screen['last_seen']); ?>
                            <?php $assignedPlaylistId = (int) ($screen['playlist_id'] ?? 0); ?>
                            <?php $latestPlaylistId = (int) ($latestActivePlaylist['id'] ?? 0); ?>
                            <?php $isLatestAssigned = $latestPlaylistId > 0 && $assignedPlaylistId === $latestPlaylistId; ?>
                            <section class="dashboard-screen-card">
                                <div class="dashboard-screen-head">
                                    <div>
                                        <strong><?= e($screen['name']) ?></strong>
                                        <span><?= e($screen['location'] ?: 'No location') ?></span>
                                    </div>
                                    <span class="badge <?= $online ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= $online ? 'Online' : 'Offline' ?>
                                    </span>
                                </div>
                                <div class="dashboard-screen-body">
                                    <div class="summary-list">
                                        <div class="summary-row">
                                            <div class="summary-label">Assigned Playlist</div>
                                            <div class="summary-value"><?= e($screen['playlist_name'] ?: 'Unassigned') ?></div>
                                        </div>
                                        <div class="summary-row">
                                            <div class="summary-label">Latest Playlist Status</div>
                                            <div class="summary-value">
                                                <?php if ($latestActivePlaylist): ?>
                                                    <?= $isLatestAssigned ? 'Using latest' : 'Out of date' ?>
                                                <?php else: ?>
                                                    No active playlist
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="summary-row">
                                            <div class="summary-label">Last Seen</div>
                                            <div class="summary-value"><?= e(format_datetime($screen['last_seen'])) ?></div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

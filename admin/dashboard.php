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
<div class="section-heading">
    <div>
        <h1 class="h3">Dashboard</h1>
        <div class="section-subtitle">Overview first, problems second, recent activity last.</div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Screens</div>
                <div class="stat-value"><?= $counts['screens'] ?></div>
                <div class="stat-meta"><?= $counts['online_screens'] ?> online</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Unassigned</div>
                <div class="stat-value"><?= $unassignedScreens ?></div>
                <div class="stat-meta">Need playlist</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Playlists</div>
                <div class="stat-value"><?= $counts['playlists'] ?></div>
                <div class="stat-meta"><?= $latestActivePlaylist ? 'Latest: ' . e($latestActivePlaylist['name']) : 'No active playlist' ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Content</div>
                <div class="stat-value"><?= $counts['media'] + $counts['quizzes'] ?></div>
                <div class="stat-meta"><?= $counts['media'] ?> media / <?= $counts['quizzes'] ?> quizzes</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-4 col-lg-5">
        <div class="card h-100">
            <div class="card-header"><h2 class="h5 mb-0">At A Glance</h2></div>
            <div class="card-body">
                <div class="panel-grid">
                    <section class="panel-section">
                        <div class="panel-section-head">
                            <div>
                                <h3 class="panel-section-title">System</h3>
                                <p class="panel-section-copy">What is currently live and connected.</p>
                            </div>
                        </div>
                        <div class="panel-section-body">
                            <div class="summary-list">
                                <div class="summary-row">
                                    <div class="summary-label">Latest Active Playlist</div>
                                    <div class="summary-value"><?= e($latestActivePlaylist['name'] ?? 'None') ?></div>
                                </div>
                                <div class="summary-row">
                                    <div class="summary-label">Online Screens</div>
                                    <div class="summary-value"><?= $counts['online_screens'] ?> / <?= $counts['screens'] ?></div>
                                </div>
                                <div class="summary-row">
                                    <div class="summary-label">Offline Screens</div>
                                    <div class="summary-value"><?= max(0, $counts['screens'] - $counts['online_screens']) ?></div>
                                </div>
                                <div class="summary-row">
                                    <div class="summary-label">Unassigned Screens</div>
                                    <div class="summary-value"><?= $unassignedScreens ?></div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <section class="panel-section">
                        <div class="panel-section-head">
                            <div>
                                <h3 class="panel-section-title">Recommended Next Step</h3>
                                <p class="panel-section-copy">The quickest route to a tidier system.</p>
                            </div>
                        </div>
                        <div class="panel-section-body">
                            <div class="stack-list">
                                <div class="stack-item">
                                    <strong>Latest playlist</strong>
                                    <span><?= $latestActivePlaylist ? e($latestActivePlaylist['name']) . ' updated ' . e(format_datetime($latestActivePlaylist['updated_at'])) : 'No active playlist is ready for screens.' ?></span>
                                </div>
                                <div class="stack-item">
                                    <strong>Next action</strong>
                                    <span><?= $unassignedScreens > 0 ? 'Assign playlists to unassigned screens.' : 'Check any offline screens in the attention list.' ?></span>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-8 col-lg-7">
        <div class="card h-100">
            <div class="card-header"><h2 class="h5 mb-0">Needs Attention</h2></div>
            <div class="card-body">
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
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header">
                <div class="section-heading mb-0">
                    <h2 class="h5">Recent Screens</h2>
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
                    <div class="panel-grid">
                        <?php foreach ($recentActivity as $screen): ?>
                            <?php $online = screen_is_online($screen['last_seen']); ?>
                            <?php $assignedPlaylistId = (int) ($screen['playlist_id'] ?? 0); ?>
                            <?php $latestPlaylistId = (int) ($latestActivePlaylist['id'] ?? 0); ?>
                            <?php $isLatestAssigned = $latestPlaylistId > 0 && $assignedPlaylistId === $latestPlaylistId; ?>
                            <section class="panel-section">
                                <div class="panel-section-head">
                                    <div>
                                        <h3 class="panel-section-title"><?= e($screen['name']) ?></h3>
                                        <p class="panel-section-copy"><?= e($screen['location'] ?: 'No location') ?></p>
                                    </div>
                                    <span class="badge <?= $online ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= $online ? 'Online' : 'Offline' ?>
                                    </span>
                                </div>
                                <div class="panel-section-body">
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
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header"><h2 class="h5 mb-0">Quick Links</h2></div>
            <div class="card-body">
                <div class="panel-grid">
                    <a class="panel-section text-decoration-none text-reset" href="<?= e(app_path('/admin/screens.php')) ?>">
                        <div class="panel-section-head">
                            <div>
                                <h3 class="panel-section-title">Screens</h3>
                                <p class="panel-section-copy">Assignments and previews.</p>
                            </div>
                        </div>
                        <div class="panel-section-body">
                            <div class="compact-note">Assignments, browser tests, tokens, and update pushes.</div>
                        </div>
                    </a>
                    <a class="panel-section text-decoration-none text-reset" href="<?= e(app_path('/admin/playlists.php')) ?>">
                        <div class="panel-section-head">
                            <div>
                                <h3 class="panel-section-title">Playlists</h3>
                                <p class="panel-section-copy">What screens are running.</p>
                            </div>
                        </div>
                        <div class="panel-section-body">
                            <div class="compact-note">Update what is live and keep screens on the correct content.</div>
                        </div>
                    </a>
                    <a class="panel-section text-decoration-none text-reset" href="<?= e(app_path('/admin/media.php')) ?>">
                        <div class="panel-section-head">
                            <div>
                                <h3 class="panel-section-title">Media And Quizzes</h3>
                                <p class="panel-section-copy">Content library.</p>
                            </div>
                        </div>
                        <div class="panel-section-body">
                            <div class="compact-note">Manage the content library that feeds your playlists.</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

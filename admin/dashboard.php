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

$statement = $db->prepare("SELECT id, name, updated_at
    FROM playlists
    WHERE owner_admin_id = ? AND active = 1
    ORDER BY updated_at DESC, id DESC
    LIMIT 1");
$statement->bind_param('i', $adminId);
$statement->execute();
$latestActivePlaylist = $statement->get_result()->fetch_assoc() ?: null;
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

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="section-heading">
    <div>
        <h1 class="h3">Dashboard</h1>
        <div class="section-subtitle">Fast view of screens, playlists, and sync status.</div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-2">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Media</div>
                <div class="stat-value"><?= $counts['media'] ?></div>
                <div class="stat-meta">Library items</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Quizzes</div>
                <div class="stat-value"><?= $counts['quizzes'] ?></div>
                <div class="stat-meta">Question sets</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Playlists</div>
                <div class="stat-value"><?= $counts['playlists'] ?></div>
                <div class="stat-meta">Managed feeds</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Screens</div>
                <div class="stat-value"><?= $counts['screens'] ?></div>
                <div class="stat-meta">Registered endpoints</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Online</div>
                <div class="stat-value"><?= $counts['online_screens'] ?></div>
                <div class="stat-meta"><?= max(0, $counts['screens'] - $counts['online_screens']) ?> offline</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="section-heading mb-0">
            <h2 class="h5">Recent Screen Activity</h2>
            <a class="btn btn-outline-dark btn-sm" href="<?= e(app_path('/admin/screens.php')) ?>">
                <i class="bi bi-display"></i>
                <span class="ms-1">Open Screens</span>
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm page-table mb-0">
                <thead>
                    <tr>
                        <th>Screen</th>
                        <th>Location</th>
                        <th>Assigned</th>
                        <th>Latest Active</th>
                        <th>Sync</th>
                        <th>Status</th>
                        <th>Last Seen</th>
                        <th>Last IP</th>
                        <th>Resolution</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$recentActivity): ?>
                    <tr><td colspan="9" class="text-center py-4 text-muted">No screens created yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentActivity as $screen): ?>
                        <?php $online = screen_is_online($screen['last_seen']); ?>
                        <?php $assignedPlaylistId = (int) ($screen['playlist_id'] ?? 0); ?>
                        <?php $latestPlaylistId = (int) ($latestActivePlaylist['id'] ?? 0); ?>
                        <?php $isLatestAssigned = $latestPlaylistId > 0 && $assignedPlaylistId === $latestPlaylistId; ?>
                        <tr>
                            <td>
                                <div class="muted-stack">
                                    <strong><?= e($screen['name']) ?></strong>
                                </div>
                            </td>
                            <td><?= e($screen['location'] ?: 'No location') ?></td>
                            <td><?= e($screen['playlist_name'] ?: 'Unassigned') ?></td>
                            <td>
                                <?php if ($latestActivePlaylist): ?>
                                    <div class="muted-stack">
                                        <strong><?= e($latestActivePlaylist['name']) ?></strong>
                                        <span class="small"><?= e(format_datetime($latestActivePlaylist['updated_at'])) ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">No active playlist</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$latestActivePlaylist): ?>
                                    <span class="badge text-bg-secondary">No Active Playlist</span>
                                <?php elseif ($isLatestAssigned): ?>
                                    <span class="badge text-bg-success">Using Latest</span>
                                <?php else: ?>
                                    <span class="badge text-bg-warning">Out Of Date</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-dot <?= $online ? 'status-online' : 'status-offline' ?>"></span>
                                <?= $online ? 'Online' : 'Offline' ?>
                            </td>
                            <td><?= e(format_datetime($screen['last_seen'])) ?></td>
                            <td><?= e($screen['last_ip'] ?: '-') ?></td>
                            <td><?= e($screen['resolution'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

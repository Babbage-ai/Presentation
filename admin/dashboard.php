<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$db = get_db();
sync_screen_statuses($db);

$counts = [
    'media' => 0,
    'playlists' => 0,
    'screens' => 0,
    'online_screens' => 0,
];

foreach ([
    'media' => "SELECT COUNT(*) AS total FROM media",
    'playlists' => "SELECT COUNT(*) AS total FROM playlists",
    'screens' => "SELECT COUNT(*) AS total FROM screens",
    'online_screens' => "SELECT COUNT(*) AS total FROM screens WHERE status = 'online'",
] as $key => $sql) {
    $result = $db->query($sql);
    $counts[$key] = (int) $result->fetch_assoc()['total'];
}

$recentActivity = [];
$sql = "SELECT s.name, s.location, s.last_seen, s.status, s.last_ip, s.resolution, p.name AS playlist_name
        FROM screens s
        LEFT JOIN playlists p ON p.id = s.playlist_id
        ORDER BY s.last_seen IS NULL, s.last_seen DESC, s.created_at DESC
        LIMIT 10";
$result = $db->query($sql);
while ($row = $result->fetch_assoc()) {
    $recentActivity[] = $row;
}

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small">Total Media</div>
                <div class="display-6"><?= $counts['media'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small">Total Playlists</div>
                <div class="display-6"><?= $counts['playlists'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small">Total Screens</div>
                <div class="display-6"><?= $counts['screens'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small">Online Screens</div>
                <div class="display-6"><?= $counts['online_screens'] ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="h5 mb-0">Recent Screen Activity</h2>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Screen</th>
                        <th>Location</th>
                        <th>Playlist</th>
                        <th>Status</th>
                        <th>Last Seen</th>
                        <th>Last IP</th>
                        <th>Resolution</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$recentActivity): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No screens created yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentActivity as $screen): ?>
                        <?php $online = screen_is_online($screen['last_seen']); ?>
                        <tr>
                            <td><?= e($screen['name']) ?></td>
                            <td><?= e($screen['location']) ?></td>
                            <td><?= e($screen['playlist_name'] ?: 'Unassigned') ?></td>
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

<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$db = get_db();
sync_screen_statuses($db);
$adminId = current_admin_id();

if (is_post_request()) {
    require_valid_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_screen') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));
        $playlistId = (int) ($_POST['playlist_id'] ?? 0);
        $token = generate_screen_token();

        if ($name === '') {
            set_flash('danger', 'Screen name is required.');
            redirect('/admin/screens.php');
        }

        if ($playlistId > 0) {
            $statement = $db->prepare("SELECT COUNT(*) AS total FROM playlists WHERE id = ? AND owner_admin_id = ?");
            $statement->bind_param('ii', $playlistId, $adminId);
            $statement->execute();
            $playlistExists = (int) $statement->get_result()->fetch_assoc()['total'] === 1;
            $statement->close();

            if (!$playlistExists) {
                set_flash('danger', 'Selected playlist was not found in your presentation system.');
                redirect('/admin/screens.php');
            }
        }

        if ($playlistId > 0) {
            $statement = $db->prepare("INSERT INTO screens (owner_admin_id, name, screen_token, location, playlist_id, resolution, last_seen, last_ip, status, player_version, created_at)
                                       VALUES (?, ?, ?, ?, ?, NULL, NULL, NULL, 'offline', NULL, UTC_TIMESTAMP())");
            $statement->bind_param('isssi', $adminId, $name, $token, $location, $playlistId);
        } else {
            $statement = $db->prepare("INSERT INTO screens (owner_admin_id, name, screen_token, location, playlist_id, resolution, last_seen, last_ip, status, player_version, created_at)
                                       VALUES (?, ?, ?, ?, NULL, NULL, NULL, NULL, 'offline', NULL, UTC_TIMESTAMP())");
            $statement->bind_param('isss', $adminId, $name, $token, $location);
        }
        $statement->execute();
        $statement->close();

        set_flash('success', 'Screen created.');
        redirect('/admin/screens.php');
    }

    if ($action === 'update_screen') {
        $screenId = (int) ($_POST['screen_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));
        $playlistId = (int) ($_POST['playlist_id'] ?? 0);

        if ($screenId < 1 || $name === '') {
            set_flash('danger', 'Screen update failed.');
            redirect('/admin/screens.php');
        }

        if ($playlistId > 0) {
            $statement = $db->prepare("SELECT COUNT(*) AS total FROM playlists WHERE id = ? AND owner_admin_id = ?");
            $statement->bind_param('ii', $playlistId, $adminId);
            $statement->execute();
            $playlistExists = (int) $statement->get_result()->fetch_assoc()['total'] === 1;
            $statement->close();

            if (!$playlistExists) {
                set_flash('danger', 'Selected playlist was not found in your presentation system.');
                redirect('/admin/screens.php');
            }
        }

        if ($playlistId > 0) {
            $statement = $db->prepare("UPDATE screens SET name = ?, location = ?, playlist_id = ? WHERE id = ? AND owner_admin_id = ?");
            $statement->bind_param('ssiii', $name, $location, $playlistId, $screenId, $adminId);
        } else {
            $statement = $db->prepare("UPDATE screens SET name = ?, location = ?, playlist_id = NULL WHERE id = ? AND owner_admin_id = ?");
            $statement->bind_param('ssii', $name, $location, $screenId, $adminId);
        }
        $statement->execute();
        $statement->close();
        bump_screen_sync_revision($db, $screenId, $adminId);

        set_flash('success', 'Screen updated.');
        redirect('/admin/screens.php');
    }

    if ($action === 'force_sync') {
        $screenId = (int) ($_POST['screen_id'] ?? 0);

        if ($screenId < 1) {
            set_flash('danger', 'Invalid screen update request.');
            redirect('/admin/screens.php');
        }

        $statement = $db->prepare("SELECT id, name
            FROM playlists
            WHERE owner_admin_id = ? AND active = 1
            ORDER BY updated_at DESC, id DESC
            LIMIT 1");
        $statement->bind_param('i', $adminId);
        $statement->execute();
        $latestPlaylist = $statement->get_result()->fetch_assoc() ?: null;
        $statement->close();

        if (!$latestPlaylist) {
            set_flash('danger', 'No active playlist is available to send to this screen.');
            redirect('/admin/screens.php');
        }

        $latestPlaylistId = (int) $latestPlaylist['id'];

        $statement = $db->prepare("UPDATE screens
            SET playlist_id = ?
            WHERE id = ? AND owner_admin_id = ?");
        $statement->bind_param('iii', $latestPlaylistId, $screenId, $adminId);
        $statement->execute();
        $updated = $statement->affected_rows > 0;
        $statement->close();

        if (!$updated && !bump_screen_sync_revision($db, $screenId, $adminId)) {
            set_flash('danger', 'Screen not found.');
            redirect('/admin/screens.php');
        }

        bump_screen_sync_revision($db, $screenId, $adminId);
        log_screen_event($db, $screenId, 'force_sync', 'Cloud update requested by admin. Latest active playlist assigned to screen.');
        set_flash('success', 'Update sent to screen. The player will switch to "' . $latestPlaylist['name'] . '" on its next heartbeat.');
        redirect('/admin/screens.php');
    }

    if ($action === 'regenerate_token') {
        $screenId = (int) ($_POST['screen_id'] ?? 0);
        $token = generate_screen_token();

        if ($screenId < 1) {
            set_flash('danger', 'Invalid screen token update request.');
            redirect('/admin/screens.php');
        }

        $statement = $db->prepare("UPDATE screens SET screen_token = ? WHERE id = ? AND owner_admin_id = ?");
        $statement->bind_param('sii', $token, $screenId, $adminId);
        $statement->execute();
        $statement->close();

        set_flash('success', 'Screen token regenerated.');
        redirect('/admin/screens.php');
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

$screens = [];
$sql = "SELECT s.*, p.name AS playlist_name
        FROM screens s
        LEFT JOIN playlists p ON p.id = s.playlist_id AND p.owner_admin_id = s.owner_admin_id
        WHERE s.owner_admin_id = ?
        ORDER BY s.created_at DESC, s.id DESC";
$statement = $db->prepare($sql);
$statement->bind_param('i', $adminId);
$statement->execute();
$result = $statement->get_result();
while ($row = $result->fetch_assoc()) {
    $screens[] = $row;
}
$statement->close();

$pageTitle = 'Screens';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="section-heading">
    <div>
        <h1 class="h3">Screens</h1>
        <div class="section-subtitle">Assign playlists, launch previews, and push sync updates with less clutter.</div>
    </div>
</div>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h1 class="h5 mb-0">Create Screen</h1></div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_screen">
                    <div class="mb-3">
                        <label class="form-label" for="screen_name">Screen Name</label>
                        <input class="form-control" id="screen_name" name="name" type="text" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="screen_location">Location</label>
                        <input class="form-control" id="screen_location" name="location" type="text">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="screen_playlist">Assigned Playlist</label>
                        <select class="form-select" id="screen_playlist" name="playlist_id">
                            <option value="0">Unassigned</option>
                            <?php foreach ($playlists as $playlist): ?>
                                <option value="<?= (int) $playlist['id'] ?>"><?= e($playlist['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-plus-circle"></i>
                        <span class="ms-1">Create Screen</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h2 class="h5 mb-0">Screens</h2></div>
            <div class="card-body">
                <?php if (!$screens): ?>
                    <p class="text-muted mb-0">No screens created yet.</p>
                <?php else: ?>
                    <div class="accordion" id="screenAccordion">
                        <?php foreach ($screens as $index => $screen): ?>
                            <?php $online = screen_is_online($screen['last_seen']); ?>
                            <?php $formId = 'screen-form-' . (int) $screen['id']; ?>
                            <?php $playerUrl = player_launch_url($screen['screen_token']); ?>
                            <?php $browserTestUrl = player_browser_test_url($screen['screen_token']); ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading<?= (int) $screen['id'] ?>">
                                    <button class="accordion-button <?= $index > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= (int) $screen['id'] ?>" aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>" aria-controls="collapse<?= (int) $screen['id'] ?>">
                                        <span class="status-dot <?= $online ? 'status-online' : 'status-offline' ?>"></span>
                                        <?= e($screen['name']) ?> <span class="ms-2 text-muted">/ <?= e($screen['location'] ?: 'No location') ?></span>
                                    </button>
                                </h2>
                                <div id="collapse<?= (int) $screen['id'] ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" aria-labelledby="heading<?= (int) $screen['id'] ?>" data-bs-parent="#screenAccordion">
                                    <div class="accordion-body">
                                        <div class="info-grid mb-3">
                                            <div class="info-cell">
                                                <span class="info-label">Assigned Playlist</span>
                                                <div class="info-value"><?= e($screen['playlist_name'] ?: 'Unassigned') ?></div>
                                            </div>
                                            <div class="info-cell">
                                                <span class="info-label">Status</span>
                                                <div class="info-value">
                                                    <span class="status-dot <?= $online ? 'status-online' : 'status-offline' ?>"></span>
                                                    <?= $online ? 'Online' : 'Offline' ?>
                                                </div>
                                            </div>
                                            <div class="info-cell">
                                                <span class="info-label">Last Seen</span>
                                                <div class="info-value"><?= e(format_datetime($screen['last_seen'])) ?></div>
                                            </div>
                                            <div class="info-cell">
                                                <span class="info-label">Last IP</span>
                                                <div class="info-value"><?= e($screen['last_ip'] ?: '-') ?></div>
                                            </div>
                                            <div class="info-cell">
                                                <span class="info-label">Resolution</span>
                                                <div class="info-value"><?= e($screen['resolution'] ?: '-') ?></div>
                                            </div>
                                            <div class="info-cell">
                                                <span class="info-label">Player Version</span>
                                                <div class="info-value"><?= e($screen['player_version'] ?: '-') ?></div>
                                            </div>
                                            <div class="info-cell">
                                                <span class="info-label">Sync Revision</span>
                                                <div class="info-value"><?= (int) $screen['sync_revision'] ?></div>
                                            </div>
                                            <div class="info-cell info-cell-wide">
                                                <span class="info-label">Token</span>
                                                <pre class="token-box bg-light p-2 rounded mt-2"><?= e($screen['screen_token']) ?></pre>
                                            </div>
                                            <div class="info-cell info-cell-wide">
                                                <span class="info-label">Player URL</span>
                                                <pre class="token-box bg-light p-2 rounded mt-2"><?= e($playerUrl) ?></pre>
                                                <div class="mt-2 icon-actions">
                                                    <a class="btn btn-sm btn-outline-success icon-btn icon-btn-sm" href="<?= e($playerUrl) ?>" target="_blank" rel="noopener noreferrer" title="Open player" aria-label="Open player">
                                                        <i class="bi bi-play-fill"></i>
                                                    </a>
                                                    <a class="btn btn-sm btn-outline-primary icon-btn icon-btn-sm" href="<?= e($browserTestUrl) ?>" target="_blank" rel="noopener noreferrer" title="Open browser test" aria-label="Open browser test">
                                                        <i class="bi bi-laptop"></i>
                                                    </a>
                                                </div>
                                                <div class="small text-muted mt-2">Use Browser Test on any computer to preview this screen in a normal web browser.</div>
                                            </div>
                                        </div>

                                        <div class="row g-3 mb-3">
                                            <div class="col-md-4">
                                                <label class="form-label">Screen Name</label>
                                                <input class="form-control" name="name" type="text" value="<?= e($screen['name']) ?>" required form="<?= e($formId) ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Location</label>
                                                <input class="form-control" name="location" type="text" value="<?= e($screen['location']) ?>" form="<?= e($formId) ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Playlist</label>
                                                <select class="form-select" name="playlist_id" form="<?= e($formId) ?>">
                                                    <option value="0">Unassigned</option>
                                                    <?php foreach ($playlists as $playlist): ?>
                                                        <option value="<?= (int) $playlist['id'] ?>" <?= (int) $screen['playlist_id'] === (int) $playlist['id'] ? 'selected' : '' ?>><?= e($playlist['name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-12 compact-form-actions">
                                                <form method="post" id="<?= e($formId) ?>" class="m-0">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="update_screen">
                                                    <input type="hidden" name="screen_id" value="<?= (int) $screen['id'] ?>">
                                                    <button class="btn btn-primary icon-btn" type="submit" title="Save screen" aria-label="Save screen">
                                                        <i class="bi bi-check2"></i>
                                                    </button>
                                                </form>
                                                <form method="post" class="m-0" onsubmit="return confirm('Regenerate the screen token? The player config will need to be updated.');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="regenerate_token">
                                                    <input type="hidden" name="screen_id" value="<?= (int) $screen['id'] ?>">
                                                    <button class="btn btn-outline-warning icon-btn" type="submit" title="Regenerate token" aria-label="Regenerate token">
                                                        <i class="bi bi-key"></i>
                                                    </button>
                                                </form>
                                                <form method="post" class="m-0" onsubmit="return confirm('Assign the latest active playlist to this screen and force a reload on the next heartbeat?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="force_sync">
                                                    <input type="hidden" name="screen_id" value="<?= (int) $screen['id'] ?>">
                                                    <button class="btn btn-outline-success icon-btn" type="submit" title="Send update to screen" aria-label="Send update to screen">
                                                        <i class="bi bi-arrow-repeat"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

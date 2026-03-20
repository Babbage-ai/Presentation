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

        set_flash('success', 'Screen updated.');
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
                    <button class="btn btn-primary" type="submit">Create Screen</button>
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
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading<?= (int) $screen['id'] ?>">
                                    <button class="accordion-button <?= $index > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= (int) $screen['id'] ?>" aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>" aria-controls="collapse<?= (int) $screen['id'] ?>">
                                        <span class="status-dot <?= $online ? 'status-online' : 'status-offline' ?>"></span>
                                        <?= e($screen['name']) ?> <span class="ms-2 text-muted">/ <?= e($screen['location'] ?: 'No location') ?></span>
                                    </button>
                                </h2>
                                <div id="collapse<?= (int) $screen['id'] ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" aria-labelledby="heading<?= (int) $screen['id'] ?>" data-bs-parent="#screenAccordion">
                                    <div class="accordion-body">
                                        <div class="row g-3 mb-3">
                                            <div class="col-md-6"><strong>Assigned playlist:</strong> <?= e($screen['playlist_name'] ?: 'Unassigned') ?></div>
                                            <div class="col-md-6"><strong>Last seen:</strong> <?= e(format_datetime($screen['last_seen'])) ?></div>
                                            <div class="col-md-6"><strong>Last IP:</strong> <?= e($screen['last_ip'] ?: '-') ?></div>
                                            <div class="col-md-6"><strong>Resolution:</strong> <?= e($screen['resolution'] ?: '-') ?></div>
                                            <div class="col-md-6"><strong>Player version:</strong> <?= e($screen['player_version'] ?: '-') ?></div>
                                            <div class="col-md-6"><strong>Status:</strong> <?= $online ? 'Online' : 'Offline' ?></div>
                                            <div class="col-12">
                                                <strong>Token:</strong>
                                                <pre class="token-box bg-light p-2 rounded mt-2"><?= e($screen['screen_token']) ?></pre>
                                            </div>
                                            <div class="col-12">
                                                <strong>Player URL:</strong>
                                                <pre class="token-box bg-light p-2 rounded mt-2"><?= e($playerUrl) ?></pre>
                                                <div class="mt-2 d-flex gap-2 flex-wrap">
                                                    <a class="btn btn-sm btn-outline-success" href="<?= e($playerUrl) ?>" target="_blank" rel="noopener noreferrer">Open Player</a>
                                                </div>
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
                                            <div class="col-12 d-flex gap-2">
                                                <form method="post" id="<?= e($formId) ?>" class="m-0">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="update_screen">
                                                    <input type="hidden" name="screen_id" value="<?= (int) $screen['id'] ?>">
                                                    <button class="btn btn-primary" type="submit">Save Screen</button>
                                                </form>
                                                <form method="post" class="m-0" onsubmit="return confirm('Regenerate the screen token? The player config will need to be updated.');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="regenerate_token">
                                                    <input type="hidden" name="screen_id" value="<?= (int) $screen['id'] ?>">
                                                    <button class="btn btn-outline-warning" type="submit">Regenerate Token</button>
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

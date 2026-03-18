<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$db = get_db();

if (is_post_request()) {
    require_valid_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_playlist') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $active = isset($_POST['active']) ? 1 : 0;

        if ($name === '') {
            set_flash('danger', 'Playlist name is required.');
            redirect('/admin/playlists.php');
        }

        $statement = $db->prepare("INSERT INTO playlists (name, active, created_at, updated_at) VALUES (?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
        $statement->bind_param('si', $name, $active);
        $statement->execute();
        $playlistId = $statement->insert_id;
        $statement->close();

        set_flash('success', 'Playlist created.');
        redirect('/admin/playlists.php?playlist_id=' . $playlistId);
    }

    if ($action === 'update_playlist') {
        $playlistId = (int) ($_POST['playlist_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $active = isset($_POST['active']) ? 1 : 0;

        if ($playlistId < 1 || $name === '') {
            set_flash('danger', 'Playlist update failed.');
            redirect('/admin/playlists.php');
        }

        $statement = $db->prepare("UPDATE playlists SET name = ?, active = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?");
        $statement->bind_param('sii', $name, $active, $playlistId);
        $statement->execute();
        $statement->close();

        set_flash('success', 'Playlist updated.');
        redirect('/admin/playlists.php?playlist_id=' . $playlistId);
    }

    if ($action === 'add_playlist_item') {
        $playlistId = (int) ($_POST['playlist_id'] ?? 0);
        $mediaId = (int) ($_POST['media_id'] ?? 0);
        $sortOrder = normalize_int($_POST['sort_order'] ?? null, 1);
        $imageDuration = max(1, normalize_int($_POST['image_duration'] ?? null, 10));

        if ($playlistId < 1 || $mediaId < 1) {
            set_flash('danger', 'A valid playlist and media item are required.');
            redirect('/admin/playlists.php' . ($playlistId > 0 ? '?playlist_id=' . $playlistId : ''));
        }

        $statement = $db->prepare("INSERT INTO playlist_items (playlist_id, media_id, sort_order, image_duration, active, created_at)
                                   VALUES (?, ?, ?, ?, 1, UTC_TIMESTAMP())");
        $statement->bind_param('iiii', $playlistId, $mediaId, $sortOrder, $imageDuration);
        $statement->execute();
        $statement->close();

        set_flash('success', 'Playlist item added.');
        redirect('/admin/playlists.php?playlist_id=' . $playlistId);
    }

    if ($action === 'update_playlist_item') {
        $playlistId = (int) ($_POST['playlist_id'] ?? 0);
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $sortOrder = normalize_int($_POST['sort_order'] ?? null, 1);
        $imageDuration = max(1, normalize_int($_POST['image_duration'] ?? null, 10));
        $active = isset($_POST['active']) ? 1 : 0;

        if ($playlistId < 1 || $itemId < 1) {
            set_flash('danger', 'Invalid playlist item update.');
            redirect('/admin/playlists.php');
        }

        $statement = $db->prepare("UPDATE playlist_items
                                   SET sort_order = ?, image_duration = ?, active = ?
                                   WHERE id = ? AND playlist_id = ?");
        $statement->bind_param('iiiii', $sortOrder, $imageDuration, $active, $itemId, $playlistId);
        $statement->execute();
        $statement->close();

        set_flash('success', 'Playlist item updated.');
        redirect('/admin/playlists.php?playlist_id=' . $playlistId);
    }

    if ($action === 'delete_playlist_item') {
        $playlistId = (int) ($_POST['playlist_id'] ?? 0);
        $itemId = (int) ($_POST['item_id'] ?? 0);

        if ($playlistId < 1 || $itemId < 1) {
            set_flash('danger', 'Invalid playlist item removal request.');
            redirect('/admin/playlists.php');
        }

        $statement = $db->prepare("DELETE FROM playlist_items WHERE id = ? AND playlist_id = ?");
        $statement->bind_param('ii', $itemId, $playlistId);
        $statement->execute();
        $statement->close();

        set_flash('success', 'Playlist item removed.');
        redirect('/admin/playlists.php?playlist_id=' . $playlistId);
    }
}

$playlists = [];
$result = $db->query("SELECT p.*, (SELECT COUNT(*) FROM playlist_items pi WHERE pi.playlist_id = p.id) AS item_count
                      FROM playlists p
                      ORDER BY p.updated_at DESC, p.id DESC");
while ($row = $result->fetch_assoc()) {
    $playlists[] = $row;
}

$selectedPlaylistId = (int) ($_GET['playlist_id'] ?? ($playlists[0]['id'] ?? 0));
$selectedPlaylist = null;
$playlistItems = [];

if ($selectedPlaylistId > 0) {
    $statement = $db->prepare("SELECT * FROM playlists WHERE id = ? LIMIT 1");
    $statement->bind_param('i', $selectedPlaylistId);
    $statement->execute();
    $selectedPlaylist = $statement->get_result()->fetch_assoc() ?: null;
    $statement->close();

    if ($selectedPlaylist) {
        $statement = $db->prepare("SELECT pi.*, m.title, m.media_type, m.filename
                                   FROM playlist_items pi
                                   INNER JOIN media m ON m.id = pi.media_id
                                   WHERE pi.playlist_id = ?
                                   ORDER BY pi.sort_order ASC, pi.id ASC");
        $statement->bind_param('i', $selectedPlaylistId);
        $statement->execute();
        $result = $statement->get_result();
        while ($row = $result->fetch_assoc()) {
            $playlistItems[] = $row;
        }
        $statement->close();
    }
}

$mediaOptions = [];
$result = $db->query("SELECT id, title, media_type, filename FROM media WHERE active = 1 ORDER BY created_at DESC");
while ($row = $result->fetch_assoc()) {
    $mediaOptions[] = $row;
}

$pageTitle = 'Playlists';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header"><h1 class="h5 mb-0">Create Playlist</h1></div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_playlist">
                    <div class="mb-3">
                        <label class="form-label" for="playlist_name">Name</label>
                        <input class="form-control" id="playlist_name" name="name" type="text" required>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" id="playlist_active" name="active" type="checkbox" checked>
                        <label class="form-check-label" for="playlist_active">Playlist active</label>
                    </div>
                    <button class="btn btn-primary" type="submit">Create</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="h5 mb-0">Existing Playlists</h2></div>
            <div class="list-group list-group-flush">
                <?php if (!$playlists): ?>
                    <div class="list-group-item text-muted">No playlists created yet.</div>
                <?php else: ?>
                    <?php foreach ($playlists as $playlist): ?>
                        <a class="list-group-item list-group-item-action <?= (int) $playlist['id'] === $selectedPlaylistId ? 'active' : '' ?>" href="<?= e(app_path('/admin/playlists.php?playlist_id=' . (int) $playlist['id'])) ?>">
                            <div class="d-flex justify-content-between">
                                <span><?= e($playlist['name']) ?></span>
                                <span class="badge <?= (int) $playlist['active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int) $playlist['item_count'] ?> items</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <?php if (!$selectedPlaylist): ?>
            <div class="card">
                <div class="card-body text-muted">Select a playlist to edit it.</div>
            </div>
        <?php else: ?>
            <div class="card mb-4">
                <div class="card-header"><h2 class="h5 mb-0">Edit Playlist</h2></div>
                <div class="card-body">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_playlist">
                        <input type="hidden" name="playlist_id" value="<?= (int) $selectedPlaylist['id'] ?>">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label" for="selected_playlist_name">Name</label>
                                <input class="form-control" id="selected_playlist_name" name="name" type="text" value="<?= e($selectedPlaylist['name']) ?>" required>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" id="selected_playlist_active" name="active" type="checkbox" <?= (int) $selectedPlaylist['active'] === 1 ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="selected_playlist_active">Playlist active</label>
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-primary mt-3" type="submit">Save Playlist</button>
                    </form>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h2 class="h5 mb-0">Add Media To Playlist</h2></div>
                <div class="card-body">
                    <form method="post" class="row g-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_playlist_item">
                        <input type="hidden" name="playlist_id" value="<?= (int) $selectedPlaylist['id'] ?>">
                        <div class="col-md-6">
                            <label class="form-label" for="media_id">Media</label>
                            <select class="form-select" id="media_id" name="media_id" required>
                                <option value="">Select media</option>
                                <?php foreach ($mediaOptions as $media): ?>
                                    <option value="<?= (int) $media['id'] ?>"><?= e($media['title']) ?> (<?= e($media['media_type']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="sort_order">Sort Order</label>
                            <input class="form-control" id="sort_order" name="sort_order" type="number" min="1" value="1" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="image_duration">Image Duration</label>
                            <input class="form-control" id="image_duration" name="image_duration" type="number" min="1" value="10" required>
                        </div>
                        <div class="col-12">
                            <div class="form-text">Videos ignore image duration and play until completion.</div>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" type="submit">Add Item</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="h5 mb-0">Playlist Items</h2></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Media</th>
                                    <th>Type</th>
                                    <th>File</th>
                                    <th>Order</th>
                                    <th>Image Duration</th>
                                    <th>Active</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!$playlistItems): ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">No items in this playlist.</td></tr>
                            <?php else: ?>
                                <?php foreach ($playlistItems as $item): ?>
                                    <?php $formId = 'playlist-item-form-' . (int) $item['id']; ?>
                                    <tr>
                                        <td><?= e($item['title']) ?></td>
                                        <td><?= e($item['media_type']) ?></td>
                                        <td><?= e($item['filename']) ?></td>
                                        <td>
                                            <input class="form-control form-control-sm" name="sort_order" type="number" min="1" value="<?= (int) $item['sort_order'] ?>" required form="<?= e($formId) ?>">
                                        </td>
                                        <td>
                                            <input class="form-control form-control-sm" name="image_duration" type="number" min="1" value="<?= (int) $item['image_duration'] ?>" required form="<?= e($formId) ?>">
                                        </td>
                                        <td>
                                            <div class="form-check">
                                                <input class="form-check-input" id="active_item_<?= (int) $item['id'] ?>" name="active" type="checkbox" <?= (int) $item['active'] === 1 ? 'checked' : '' ?> form="<?= e($formId) ?>">
                                                <label class="form-check-label small" for="active_item_<?= (int) $item['id'] ?>">Active</label>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <form method="post" id="<?= e($formId) ?>" class="m-0">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="update_playlist_item">
                                                    <input type="hidden" name="playlist_id" value="<?= (int) $selectedPlaylist['id'] ?>">
                                                    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                                                </form>
                                                <form method="post" class="m-0" onsubmit="return confirm('Remove this item from the playlist?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete_playlist_item">
                                                    <input type="hidden" name="playlist_id" value="<?= (int) $selectedPlaylist['id'] ?>">
                                                    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                                                </form>
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
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

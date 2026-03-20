<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$db = get_db();
$adminId = current_admin_id();

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

        $statement = $db->prepare("INSERT INTO playlists (owner_admin_id, name, active, created_at, updated_at) VALUES (?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
        $statement->bind_param('isi', $adminId, $name, $active);
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

        $statement = $db->prepare("UPDATE playlists SET name = ?, active = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND owner_admin_id = ?");
        $statement->bind_param('siii', $name, $active, $playlistId, $adminId);
        $statement->execute();
        $statement->close();
        bump_playlist_screen_sync_revision($db, $playlistId, $adminId);

        set_flash('success', 'Playlist updated.');
        redirect('/admin/playlists.php?playlist_id=' . $playlistId);
    }

    if ($action === 'add_playlist_media_item') {
        $playlistId = (int) ($_POST['playlist_id'] ?? 0);
        $mediaId = (int) ($_POST['media_id'] ?? 0);
        $sortOrder = max(1, normalize_int($_POST['sort_order'] ?? null, 1));
        $imageDuration = max(1, normalize_int($_POST['image_duration'] ?? null, 10));

        if ($playlistId < 1 || $mediaId < 1) {
            set_flash('danger', 'A valid playlist and media item are required.');
            redirect('/admin/playlists.php' . ($playlistId > 0 ? '?playlist_id=' . $playlistId : ''));
        }

        $statement = $db->prepare("SELECT COUNT(*) AS total
            FROM playlists p
            INNER JOIN media m ON m.id = ?
            WHERE p.id = ? AND p.owner_admin_id = ? AND m.owner_admin_id = ?");
        $statement->bind_param('iiii', $mediaId, $playlistId, $adminId, $adminId);
        $statement->execute();
        $validRelation = (int) $statement->get_result()->fetch_assoc()['total'] === 1;
        $statement->close();

        if (!$validRelation) {
            set_flash('danger', 'The selected media or playlist does not belong to your presentation system.');
            redirect('/admin/playlists.php?playlist_id=' . $playlistId);
        }

        $statement = $db->prepare("INSERT INTO playlist_items (playlist_id, item_type, media_id, quiz_question_id, sort_order, image_duration, active, created_at)
            VALUES (?, 'media', ?, NULL, ?, ?, 1, UTC_TIMESTAMP())");
        $statement->bind_param('iiii', $playlistId, $mediaId, $sortOrder, $imageDuration);
        $statement->execute();
        $statement->close();
        bump_playlist_screen_sync_revision($db, $playlistId, $adminId);

        set_flash('success', 'Media item added to playlist.');
        redirect('/admin/playlists.php?playlist_id=' . $playlistId);
    }

    if ($action === 'add_playlist_quiz_item') {
        $playlistId = (int) ($_POST['playlist_id'] ?? 0);
        $quizSelectionMode = (string) ($_POST['quiz_selection_mode'] ?? 'fixed');
        $quizId = (int) ($_POST['quiz_question_id'] ?? 0);
        $sortOrder = max(1, normalize_int($_POST['sort_order'] ?? null, 1));

        if (!in_array($quizSelectionMode, ['fixed', 'random'], true)) {
            $quizSelectionMode = 'fixed';
        }

        if ($playlistId < 1) {
            set_flash('danger', 'A valid playlist is required.');
            redirect('/admin/playlists.php' . ($playlistId > 0 ? '?playlist_id=' . $playlistId : ''));
        }

        $statement = $db->prepare("SELECT COUNT(*) AS total
            FROM playlists p
            WHERE p.id = ? AND p.owner_admin_id = ?");
        $statement->bind_param('ii', $playlistId, $adminId);
        $statement->execute();
        $playlistExists = (int) $statement->get_result()->fetch_assoc()['total'] === 1;
        $statement->close();

        if (!$playlistExists) {
            set_flash('danger', 'The selected playlist was not found in your presentation system.');
            redirect('/admin/playlists.php?playlist_id=' . $playlistId);
        }

        if ($quizSelectionMode === 'fixed') {
            if ($quizId < 1) {
                set_flash('danger', 'Select a quiz question or choose the random marker option.');
                redirect('/admin/playlists.php?playlist_id=' . $playlistId);
            }

            $statement = $db->prepare("SELECT COUNT(*) AS total
                FROM quiz_questions
                WHERE id = ? AND owner_admin_id = ?");
            $statement->bind_param('ii', $quizId, $adminId);
            $statement->execute();
            $quizExists = (int) $statement->get_result()->fetch_assoc()['total'] === 1;
            $statement->close();

            if (!$quizExists) {
                set_flash('danger', 'The selected quiz question was not found in your presentation system.');
                redirect('/admin/playlists.php?playlist_id=' . $playlistId);
            }
        } else {
            $statement = $db->prepare("SELECT COUNT(*) AS total
                FROM quiz_questions
                WHERE owner_admin_id = ? AND active = 1");
            $statement->bind_param('i', $adminId);
            $statement->execute();
            $availableQuizCount = (int) $statement->get_result()->fetch_assoc()['total'];
            $statement->close();

            if ($availableQuizCount < 1) {
                set_flash('danger', 'Create at least one active quiz question before adding a random quiz marker.');
                redirect('/admin/playlists.php?playlist_id=' . $playlistId);
            }

            $quizId = 0;
        }

        if ($quizSelectionMode === 'random') {
            $statement = $db->prepare("INSERT INTO playlist_items (playlist_id, item_type, quiz_selection_mode, media_id, quiz_question_id, sort_order, image_duration, active, created_at)
                VALUES (?, 'quiz', 'random', NULL, NULL, ?, 10, 1, UTC_TIMESTAMP())");
            $statement->bind_param('ii', $playlistId, $sortOrder);
        } else {
            $statement = $db->prepare("INSERT INTO playlist_items (playlist_id, item_type, quiz_selection_mode, media_id, quiz_question_id, sort_order, image_duration, active, created_at)
                VALUES (?, 'quiz', 'fixed', NULL, ?, ?, 10, 1, UTC_TIMESTAMP())");
            $statement->bind_param('iii', $playlistId, $quizId, $sortOrder);
        }
        $statement->execute();
        $statement->close();
        bump_playlist_screen_sync_revision($db, $playlistId, $adminId);

        set_flash('success', $quizSelectionMode === 'random' ? 'Random quiz marker added to playlist.' : 'Quiz question added to playlist.');
        redirect('/admin/playlists.php?playlist_id=' . $playlistId);
    }

    if ($action === 'update_playlist_item') {
        $playlistId = (int) ($_POST['playlist_id'] ?? 0);
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $sortOrder = max(1, normalize_int($_POST['sort_order'] ?? null, 1));
        $imageDuration = max(1, normalize_int($_POST['image_duration'] ?? null, 10));
        $active = isset($_POST['active']) ? 1 : 0;

        if ($playlistId < 1 || $itemId < 1) {
            set_flash('danger', 'Invalid playlist item update.');
            redirect('/admin/playlists.php');
        }

        $statement = $db->prepare("UPDATE playlist_items pi
            INNER JOIN playlists p ON p.id = pi.playlist_id
            SET pi.sort_order = ?, pi.image_duration = ?, pi.active = ?
            WHERE pi.id = ? AND pi.playlist_id = ? AND p.owner_admin_id = ?");
        $statement->bind_param('iiiiii', $sortOrder, $imageDuration, $active, $itemId, $playlistId, $adminId);
        $statement->execute();
        $statement->close();
        bump_playlist_screen_sync_revision($db, $playlistId, $adminId);

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

        $statement = $db->prepare("DELETE pi
            FROM playlist_items pi
            INNER JOIN playlists p ON p.id = pi.playlist_id
            WHERE pi.id = ? AND pi.playlist_id = ? AND p.owner_admin_id = ?");
        $statement->bind_param('iii', $itemId, $playlistId, $adminId);
        $statement->execute();
        $statement->close();
        bump_playlist_screen_sync_revision($db, $playlistId, $adminId);

        set_flash('success', 'Playlist item removed.');
        redirect('/admin/playlists.php?playlist_id=' . $playlistId);
    }

    if ($action === 'duplicate_playlist_item') {
        $playlistId = (int) ($_POST['playlist_id'] ?? 0);
        $itemId = (int) ($_POST['item_id'] ?? 0);

        if ($playlistId < 1 || $itemId < 1) {
            set_flash('danger', 'Invalid playlist item duplication request.');
            redirect('/admin/playlists.php');
        }

        $statement = $db->prepare("SELECT pi.*
            FROM playlist_items pi
            INNER JOIN playlists p ON p.id = pi.playlist_id
            WHERE pi.id = ? AND pi.playlist_id = ? AND p.owner_admin_id = ?
            LIMIT 1");
        $statement->bind_param('iii', $itemId, $playlistId, $adminId);
        $statement->execute();
        $sourceItem = $statement->get_result()->fetch_assoc() ?: null;
        $statement->close();

        if (!$sourceItem) {
            set_flash('danger', 'Playlist item not found.');
            redirect('/admin/playlists.php?playlist_id=' . $playlistId);
        }

        $statement = $db->prepare("SELECT COALESCE(MAX(pi.sort_order), 0) AS max_sort_order
            FROM playlist_items pi
            INNER JOIN playlists p ON p.id = pi.playlist_id
            WHERE pi.playlist_id = ? AND p.owner_admin_id = ?");
        $statement->bind_param('ii', $playlistId, $adminId);
        $statement->execute();
        $newSortOrder = ((int) $statement->get_result()->fetch_assoc()['max_sort_order']) + 1;
        $statement->close();

        $statement = $db->prepare("INSERT INTO playlist_items
            (playlist_id, item_type, quiz_selection_mode, media_id, quiz_question_id, sort_order, image_duration, active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())");
        $statement->bind_param(
            'issiiiii',
            $playlistId,
            $sourceItem['item_type'],
            $sourceItem['quiz_selection_mode'],
            $sourceItem['media_id'],
            $sourceItem['quiz_question_id'],
            $newSortOrder,
            $sourceItem['image_duration'],
            $sourceItem['active']
        );
        $statement->execute();
        $statement->close();
        bump_playlist_screen_sync_revision($db, $playlistId, $adminId);

        set_flash('success', 'Playlist item duplicated.');
        redirect('/admin/playlists.php?playlist_id=' . $playlistId);
    }
}

$playlists = [];
$statement = $db->prepare("SELECT p.*,
        (SELECT COUNT(*) FROM playlist_items pi WHERE pi.playlist_id = p.id) AS item_count
    FROM playlists p
    WHERE p.owner_admin_id = ?
    ORDER BY p.updated_at DESC, p.id DESC");
$statement->bind_param('i', $adminId);
$statement->execute();
$result = $statement->get_result();
while ($row = $result->fetch_assoc()) {
    $playlists[] = $row;
}
$statement->close();

$selectedPlaylistId = (int) ($_GET['playlist_id'] ?? ($playlists[0]['id'] ?? 0));
$selectedPlaylist = null;
$playlistItems = [];

if ($selectedPlaylistId > 0) {
    $statement = $db->prepare("SELECT * FROM playlists WHERE id = ? AND owner_admin_id = ? LIMIT 1");
    $statement->bind_param('ii', $selectedPlaylistId, $adminId);
    $statement->execute();
    $selectedPlaylist = $statement->get_result()->fetch_assoc() ?: null;
    $statement->close();

    if ($selectedPlaylist) {
        $statement = $db->prepare("SELECT
                pi.*,
                m.title AS media_title,
                m.media_type,
                m.filename,
                q.question_text,
                q.correct_option,
                q.countdown_seconds,
                q.reveal_duration
            FROM playlist_items pi
            INNER JOIN playlists p ON p.id = pi.playlist_id
            LEFT JOIN media m ON m.id = pi.media_id AND m.owner_admin_id = p.owner_admin_id
            LEFT JOIN quiz_questions q ON q.id = pi.quiz_question_id AND q.owner_admin_id = p.owner_admin_id
            WHERE pi.playlist_id = ? AND p.owner_admin_id = ?
            ORDER BY pi.sort_order ASC, pi.id ASC");
        $statement->bind_param('ii', $selectedPlaylistId, $adminId);
        $statement->execute();
        $result = $statement->get_result();
        while ($row = $result->fetch_assoc()) {
            $playlistItems[] = $row;
        }
        $statement->close();
    }
}

$mediaOptions = [];
$statement = $db->prepare("SELECT id, title, media_type, filename
    FROM media
    WHERE owner_admin_id = ? AND active = 1
    ORDER BY created_at DESC");
$statement->bind_param('i', $adminId);
$statement->execute();
$result = $statement->get_result();
while ($row = $result->fetch_assoc()) {
    $mediaOptions[] = $row;
}
$statement->close();

$quizOptions = [];
$statement = $db->prepare("SELECT id, question_text, correct_option, countdown_seconds, reveal_duration
    FROM quiz_questions
    WHERE owner_admin_id = ? AND active = 1
    ORDER BY updated_at DESC, id DESC");
$statement->bind_param('i', $adminId);
$statement->execute();
$result = $statement->get_result();
while ($row = $result->fetch_assoc()) {
    $quizOptions[] = $row;
}
$statement->close();

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

            <div class="row g-4">
                <div class="col-xl-6">
                    <div class="card mb-4 h-100">
                        <div class="card-header"><h2 class="h5 mb-0">Add Media To Playlist</h2></div>
                        <div class="card-body">
                            <form method="post" class="row g-3">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="add_playlist_media_item">
                                <input type="hidden" name="playlist_id" value="<?= (int) $selectedPlaylist['id'] ?>">
                                <div class="col-12">
                                    <label class="form-label" for="media_id">Media</label>
                                    <select class="form-select" id="media_id" name="media_id" required>
                                        <option value="">Select media</option>
                                        <?php foreach ($mediaOptions as $media): ?>
                                            <option value="<?= (int) $media['id'] ?>"><?= e($media['title']) ?> (<?= e($media['media_type']) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="media_sort_order">Sort Order</label>
                                    <input class="form-control" id="media_sort_order" name="sort_order" type="number" min="1" value="1" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="image_duration">Image Duration</label>
                                    <input class="form-control" id="image_duration" name="image_duration" type="number" min="1" value="10" required>
                                </div>
                                <div class="col-12">
                                    <div class="form-text">Videos ignore image duration and play until completion.</div>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary" type="submit">Add Media Item</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="card mb-4 h-100">
                        <div class="card-header"><h2 class="h5 mb-0">Add Quiz To Playlist</h2></div>
                        <div class="card-body">
                            <form method="post" class="row g-3">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="add_playlist_quiz_item">
                                <input type="hidden" name="playlist_id" value="<?= (int) $selectedPlaylist['id'] ?>">
                                <div class="col-md-6">
                                    <label class="form-label" for="quiz_selection_mode">Quiz Mode</label>
                                    <select class="form-select" id="quiz_selection_mode" name="quiz_selection_mode">
                                        <option value="fixed">Specific question</option>
                                        <option value="random">Random marker</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="quiz_question_id">Quiz Question</label>
                                    <select class="form-select" id="quiz_question_id" name="quiz_question_id">
                                        <option value="">Select quiz question</option>
                                        <?php foreach ($quizOptions as $quiz): ?>
                                            <option value="<?= (int) $quiz['id'] ?>"><?= e(substr($quiz['question_text'], 0, 80)) ?><?= strlen($quiz['question_text']) > 80 ? '...' : '' ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="quiz_sort_order">Sort Order</label>
                                    <input class="form-control" id="quiz_sort_order" name="sort_order" type="number" min="1" value="1" required>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-text pt-4">Random markers pull from your active quiz bank when the player syncs.</div>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary" type="submit">Add Quiz Item</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="h5 mb-0">Playlist Items</h2></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Type</th>
                                    <th>Details</th>
                                    <th>Order</th>
                                    <th>Duration</th>
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
                                        <td>
                                            <?php if ($item['item_type'] === 'quiz'): ?>
                                                <?= $item['quiz_selection_mode'] === 'random' ? 'Random quiz marker' : e($item['question_text']) ?>
                                            <?php else: ?>
                                                <?= e($item['media_title']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e($item['item_type'] === 'quiz' ? 'quiz' : $item['media_type']) ?></td>
                                        <td class="small text-muted">
                                            <?php if ($item['item_type'] === 'quiz'): ?>
                                                <?php if ($item['quiz_selection_mode'] === 'random'): ?>
                                                    Pulls one active quiz question at random on player sync.
                                                <?php else: ?>
                                                    Answer <?= e($item['correct_option']) ?>, countdown <?= (int) $item['countdown_seconds'] ?>s, reveal <?= (int) $item['reveal_duration'] ?>s
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?= e($item['filename']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <input class="form-control form-control-sm" name="sort_order" type="number" min="1" value="<?= (int) $item['sort_order'] ?>" required form="<?= e($formId) ?>">
                                        </td>
                                        <td>
                                            <?php if ($item['item_type'] === 'quiz'): ?>
                                                <?php if ($item['quiz_selection_mode'] === 'random'): ?>
                                                    <span class="small text-muted">Uses the selected quiz timing</span>
                                                <?php else: ?>
                                                    <span class="small text-muted"><?= (int) $item['countdown_seconds'] + (int) $item['reveal_duration'] ?>s total</span>
                                                <?php endif; ?>
                                                <input type="hidden" name="image_duration" value="<?= (int) $item['image_duration'] ?>" form="<?= e($formId) ?>">
                                            <?php else: ?>
                                                <input class="form-control form-control-sm" name="image_duration" type="number" min="1" value="<?= (int) $item['image_duration'] ?>" required form="<?= e($formId) ?>">
                                            <?php endif; ?>
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
                                                <form method="post" class="m-0">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="duplicate_playlist_item">
                                                    <input type="hidden" name="playlist_id" value="<?= (int) $selectedPlaylist['id'] ?>">
                                                    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-secondary" type="submit">Duplicate</button>
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

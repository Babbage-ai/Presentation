<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$db = get_db();
$adminId = current_admin_id();

$nextPlaylistSortOrder = static function (mysqli $db, int $playlistId, int $adminId): int {
    $statement = $db->prepare("SELECT COALESCE(MAX(pi.sort_order), 0) AS max_sort_order
        FROM playlist_items pi
        INNER JOIN playlists p ON p.id = pi.playlist_id
        WHERE pi.playlist_id = ? AND p.owner_admin_id = ?");
    $statement->bind_param('ii', $playlistId, $adminId);
    $statement->execute();
    $nextSortOrder = ((int) $statement->get_result()->fetch_assoc()['max_sort_order']) + 1;
    $statement->close();

    return max(1, $nextSortOrder);
};

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
        $ajaxRequest = is_ajax_request();

        if ($playlistId < 1 || $name === '') {
            if ($ajaxRequest) {
                json_response(false, 'Playlist update failed.', [], 400);
            }
            set_flash('danger', 'Playlist update failed.');
            redirect('/admin/playlists.php');
        }

        $statement = $db->prepare("UPDATE playlists SET name = ?, active = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND owner_admin_id = ?");
        $statement->bind_param('siii', $name, $active, $playlistId, $adminId);
        $statement->execute();
        $statement->close();
        bump_playlist_screen_sync_revision($db, $playlistId, $adminId);

        if ($ajaxRequest) {
            $statement = $db->prepare("SELECT id, name, active, updated_at
                FROM playlists
                WHERE id = ? AND owner_admin_id = ?
                LIMIT 1");
            $statement->bind_param('ii', $playlistId, $adminId);
            $statement->execute();
            $playlist = $statement->get_result()->fetch_assoc() ?: null;
            $statement->close();

            if (!$playlist) {
                json_response(false, 'Playlist not found.', [], 404);
            }

            json_response(true, 'Playlist updated.', [
                'playlist_id' => (int) $playlist['id'],
                'name' => (string) $playlist['name'],
                'active' => (int) $playlist['active'] === 1,
                'updated_at' => format_datetime($playlist['updated_at']),
            ]);
        }

        set_flash('success', 'Playlist updated.');
        redirect('/admin/playlists.php?playlist_id=' . $playlistId);
    }

    if ($action === 'delete_playlist') {
        $playlistId = (int) ($_POST['playlist_id'] ?? 0);

        if ($playlistId < 1) {
            set_flash('danger', 'Invalid playlist deletion request.');
            redirect('/admin/playlists.php');
        }

        $statement = $db->prepare("SELECT id, name
            FROM playlists
            WHERE id = ? AND owner_admin_id = ?
            LIMIT 1");
        $statement->bind_param('ii', $playlistId, $adminId);
        $statement->execute();
        $playlistToDelete = $statement->get_result()->fetch_assoc() ?: null;
        $statement->close();

        if (!$playlistToDelete) {
            set_flash('danger', 'Playlist not found.');
            redirect('/admin/playlists.php');
        }

        $db->begin_transaction();

        try {
            $statement = $db->prepare("UPDATE screens
                SET playlist_id = NULL,
                    sync_revision = sync_revision + 1
                WHERE playlist_id = ? AND owner_admin_id = ?");
            $statement->bind_param('ii', $playlistId, $adminId);
            $statement->execute();
            $statement->close();

            $statement = $db->prepare("DELETE FROM playlists
                WHERE id = ? AND owner_admin_id = ?");
            $statement->bind_param('ii', $playlistId, $adminId);
            $statement->execute();
            $deleted = $statement->affected_rows > 0;
            $statement->close();

            if (!$deleted) {
                throw new RuntimeException('Playlist could not be deleted.');
            }

            $db->commit();
        } catch (Throwable $exception) {
            $db->rollback();
            throw $exception;
        }

        set_flash('success', 'Playlist "' . $playlistToDelete['name'] . '" deleted.');
        redirect('/admin/playlists.php');
    }

    if ($action === 'add_playlist_item') {
        $playlistId = (int) ($_POST['playlist_id'] ?? 0);
        $selection = trim((string) ($_POST['item_selection'] ?? ''));
        $imageDuration = max(1, normalize_int($_POST['image_duration'] ?? null, 10));

        if ($playlistId < 1 || $selection === '') {
            set_flash('danger', 'A valid playlist and item selection are required.');
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

        $sortOrder = $nextPlaylistSortOrder($db, $playlistId, $adminId);

        if (str_starts_with($selection, 'media:')) {
            $mediaId = (int) substr($selection, 6);

            if ($mediaId < 1) {
                set_flash('danger', 'Select a valid media item.');
                redirect('/admin/playlists.php?playlist_id=' . $playlistId);
            }

            $statement = $db->prepare("SELECT COUNT(*) AS total
                FROM media
                WHERE id = ? AND owner_admin_id = ?");
            $statement->bind_param('ii', $mediaId, $adminId);
            $statement->execute();
            $mediaExists = (int) $statement->get_result()->fetch_assoc()['total'] === 1;
            $statement->close();

            if (!$mediaExists) {
                set_flash('danger', 'The selected media item was not found in your presentation system.');
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

        if ($selection === 'random_quiz') {
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

            $statement = $db->prepare("INSERT INTO playlist_items (playlist_id, item_type, quiz_selection_mode, media_id, quiz_question_id, sort_order, image_duration, active, created_at)
                VALUES (?, 'quiz', 'random', NULL, NULL, ?, 10, 1, UTC_TIMESTAMP())");
            $statement->bind_param('ii', $playlistId, $sortOrder);
            $statement->execute();
            $statement->close();
            bump_playlist_screen_sync_revision($db, $playlistId, $adminId);

            set_flash('success', 'Random quiz marker added to playlist.');
            redirect('/admin/playlists.php?playlist_id=' . $playlistId);
        }

        set_flash('danger', 'Select a valid media item or random quiz option.');
        redirect('/admin/playlists.php?playlist_id=' . $playlistId);
    }

    if ($action === 'update_playlist_item') {
        $playlistId = (int) ($_POST['playlist_id'] ?? 0);
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $sortOrder = max(1, normalize_int($_POST['sort_order'] ?? null, 1));
        $imageDuration = max(1, normalize_int($_POST['image_duration'] ?? null, 10));
        $active = isset($_POST['active']) ? 1 : 0;
        $ajaxRequest = is_ajax_request();

        if ($playlistId < 1 || $itemId < 1) {
            if ($ajaxRequest) {
                json_response(false, 'Invalid playlist item update.', [], 400);
            }
            set_flash('danger', 'Invalid playlist item update.');
            redirect('/admin/playlists.php');
        }

        $statement = $db->prepare("SELECT pi.id, pi.sort_order
            FROM playlist_items pi
            INNER JOIN playlists p ON p.id = pi.playlist_id
            WHERE pi.id = ? AND pi.playlist_id = ? AND p.owner_admin_id = ?
            LIMIT 1");
        $statement->bind_param('iii', $itemId, $playlistId, $adminId);
        $statement->execute();
        $currentItem = $statement->get_result()->fetch_assoc() ?: null;
        $statement->close();

        if (!$currentItem) {
            if ($ajaxRequest) {
                json_response(false, 'Playlist item not found.', [], 404);
            }
            set_flash('danger', 'Playlist item not found.');
            redirect('/admin/playlists.php?playlist_id=' . $playlistId);
        }

        $currentSortOrder = (int) $currentItem['sort_order'];

        $db->begin_transaction();

        $swappedItemId = null;

        try {
            if ($sortOrder !== $currentSortOrder) {
                $statement = $db->prepare("SELECT pi.id
                    FROM playlist_items pi
                    INNER JOIN playlists p ON p.id = pi.playlist_id
                    WHERE pi.playlist_id = ? AND pi.sort_order = ? AND pi.id <> ? AND p.owner_admin_id = ?
                    ORDER BY pi.id ASC
                    LIMIT 1");
                $statement->bind_param('iiii', $playlistId, $sortOrder, $itemId, $adminId);
                $statement->execute();
                $swapItem = $statement->get_result()->fetch_assoc() ?: null;
                $statement->close();

                if ($swapItem) {
                    $swapItemId = (int) $swapItem['id'];
                    $swappedItemId = $swapItemId;
                    $statement = $db->prepare("UPDATE playlist_items pi
                        INNER JOIN playlists p ON p.id = pi.playlist_id
                        SET pi.sort_order = ?
                        WHERE pi.id = ? AND pi.playlist_id = ? AND p.owner_admin_id = ?");
                    $statement->bind_param('iiii', $currentSortOrder, $swapItemId, $playlistId, $adminId);
                    $statement->execute();
                    $statement->close();
                }
            }

            $statement = $db->prepare("UPDATE playlist_items pi
                INNER JOIN playlists p ON p.id = pi.playlist_id
                SET pi.sort_order = ?, pi.image_duration = ?, pi.active = ?
                WHERE pi.id = ? AND pi.playlist_id = ? AND p.owner_admin_id = ?");
            $statement->bind_param('iiiiii', $sortOrder, $imageDuration, $active, $itemId, $playlistId, $adminId);
            $statement->execute();
            $statement->close();

            $db->commit();
        } catch (Throwable $exception) {
            $db->rollback();
            throw $exception;
        }

        bump_playlist_screen_sync_revision($db, $playlistId, $adminId);

        if ($ajaxRequest) {
            json_response(true, 'Playlist item updated.', [
                'playlist_id' => $playlistId,
                'item_id' => $itemId,
                'sort_order' => $sortOrder,
                'image_duration' => $imageDuration,
                'active' => $active === 1,
                'previous_sort_order' => $currentSortOrder,
                'swapped_item_id' => $swappedItemId,
            ]);
        }

        set_flash('success', 'Playlist item updated.');
        redirect('/admin/playlists.php?playlist_id=' . $playlistId . '#playlist-item-row-' . $itemId);
    }

    if ($action === 'replace_playlist_media_item') {
        $playlistId = (int) ($_POST['playlist_id'] ?? 0);
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $mediaId = (int) ($_POST['media_id'] ?? 0);

        if ($playlistId < 1 || $itemId < 1 || $mediaId < 1) {
            set_flash('danger', 'Select a valid playlist item and replacement media.');
            redirect('/admin/playlists.php' . ($playlistId > 0 ? '?playlist_id=' . $playlistId : ''));
        }

        $statement = $db->prepare("SELECT pi.id, pi.media_id
            FROM playlist_items pi
            INNER JOIN playlists p ON p.id = pi.playlist_id
            WHERE pi.id = ? AND pi.playlist_id = ? AND pi.item_type = 'media' AND p.owner_admin_id = ?
            LIMIT 1");
        $statement->bind_param('iii', $itemId, $playlistId, $adminId);
        $statement->execute();
        $playlistItem = $statement->get_result()->fetch_assoc() ?: null;
        $statement->close();

        if (!$playlistItem) {
            set_flash('danger', 'Media playlist item not found.');
            redirect('/admin/playlists.php?playlist_id=' . $playlistId);
        }

        $statement = $db->prepare("SELECT id
            FROM media
            WHERE id = ? AND owner_admin_id = ? AND active = 1
            LIMIT 1");
        $statement->bind_param('ii', $mediaId, $adminId);
        $statement->execute();
        $replacementMedia = $statement->get_result()->fetch_assoc() ?: null;
        $statement->close();

        if (!$replacementMedia) {
            set_flash('danger', 'Replacement media was not found in your active library.');
            redirect('/admin/playlists.php?playlist_id=' . $playlistId . '#playlist-item-row-' . $itemId);
        }

        if ((int) $playlistItem['media_id'] === $mediaId) {
            set_flash('info', 'This playlist item already uses the selected media.');
            redirect('/admin/playlists.php?playlist_id=' . $playlistId . '#playlist-item-row-' . $itemId);
        }

        $statement = $db->prepare("UPDATE playlist_items pi
            INNER JOIN playlists p ON p.id = pi.playlist_id
            SET pi.media_id = ?
            WHERE pi.id = ? AND pi.playlist_id = ? AND p.owner_admin_id = ?");
        $statement->bind_param('iiii', $mediaId, $itemId, $playlistId, $adminId);
        $statement->execute();
        $statement->close();
        bump_playlist_screen_sync_revision($db, $playlistId, $adminId);

        set_flash('success', 'Playlist media item replaced.');
        redirect('/admin/playlists.php?playlist_id=' . $playlistId . '#playlist-item-row-' . $itemId);
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

        $newSortOrder = $nextPlaylistSortOrder($db, $playlistId, $adminId);

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

$activePlaylistCount = 0;
$playlistItemTotal = 0;
foreach ($playlists as $playlist) {
    if ((int) $playlist['active'] === 1) {
        $activePlaylistCount++;
    }
    $playlistItemTotal += (int) $playlist['item_count'];
}

$pageTitle = 'Playlists';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
    .playlist-admin-page { display: grid; gap: 0.75rem; }
    .playlist-admin-page .section-heading { margin-bottom: 0; }
    .playlist-admin-page .stat-card .card-body { padding: 0.72rem 0.82rem 0.76rem; }
    .playlist-admin-page .table-card .card-body,
    .playlist-admin-page .section-card .card-body,
    .playlist-admin-page .list-card .card-body,
    .playlist-admin-page .hero-card .card-body { padding: 0.82rem; }
    .playlist-admin-page .table-card .card-body.p-0,
    .playlist-admin-page .list-card .card-body.p-0 { padding: 0 !important; }
    .playlist-item-table { table-layout: fixed; }
    .playlist-item-table td,
    .playlist-item-table th { vertical-align: top; }
    .playlist-item-table th:nth-child(1),
    .playlist-item-table td:nth-child(1) { width: 39%; }
    .playlist-item-table th:nth-child(2),
    .playlist-item-table td:nth-child(2) { width: 10%; }
    .playlist-item-table th:nth-child(3),
    .playlist-item-table td:nth-child(3) { width: 12%; }
    .playlist-item-table th:nth-child(4),
    .playlist-item-table td:nth-child(4) { width: 11%; }
    .playlist-item-table th:nth-child(5),
    .playlist-item-table td:nth-child(5) { width: 28%; }
    .playlist-item-table .item-selector-form,
    .playlist-item-table .item-selector-form .form-select { width: 100%; }
    .playlist-item-table .icon-actions { flex-wrap: wrap; align-items: center; gap: 0.35rem; }
    .playlist-item-cell { display: grid; gap: 0.45rem; }
    .playlist-item-head { display: flex; align-items: flex-start; gap: 0.55rem; }
    .playlist-item-body { min-width: 0; flex: 1 1 auto; }
    .playlist-order-controls { display: inline-flex; align-items: center; gap: 0.28rem; flex-shrink: 0; }
    .playlist-order-input { display: none; }
    .playlist-item-type-badge { display: inline-flex; align-items: center; gap: 0.38rem; font-size: 0.82rem; font-weight: 600; color: #0f172a; }
    .playlist-item-type-badge i { color: #64748b; }
    .playlist-edit-inline { display: grid; grid-template-columns: minmax(12rem, 2fr) auto auto auto auto; gap: 0.55rem; align-items: end; }
    .playlist-add-form .form-text { color: var(--admin-text-soft); }
    .playlist-add-help { margin: 0; }
    .playlist-list-item {
        margin: 0.2rem 0.25rem;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 0.8rem;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.96));
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
    }
    .playlist-list-item.is-selected {
        border-color: rgba(13, 110, 253, 0.22);
        background: linear-gradient(180deg, rgba(239, 246, 255, 0.98), rgba(248, 250, 252, 0.98));
        box-shadow: 0 6px 16px rgba(13, 110, 253, 0.07);
    }
    .playlist-list-row { display: flex; justify-content: space-between; align-items: center; gap: 0.65rem; padding: 0.55rem 0.7rem; }
    .playlist-list-link {
        display: block;
        min-width: 0;
        flex: 1 1 auto;
        text-decoration: none;
        color: inherit;
    }
    .playlist-list-main { min-width: 0; }
    .playlist-list-name { font-size: 0.92rem; font-weight: 700; color: var(--admin-text-strong); line-height: 1.2; }
    .playlist-list-status { display: flex; align-items: center; gap: 0.32rem; font-size: 0.75rem; color: var(--admin-text-soft); }
    .playlist-list-side { display: flex; align-items: center; gap: 0.65rem; flex-shrink: 0; }
    .playlist-list-controls { display: flex; align-items: center; gap: 0.45rem; }
    .playlist-list-badge { white-space: nowrap; align-self: center; font-size: 0.72rem; padding: 0.28rem 0.48rem; }
    .playlist-status-indicator { display: inline-flex; width: 0.55rem; height: 0.55rem; border-radius: 999px; background: #94a3b8; }
    .playlist-status-indicator.is-active { background: #16a34a; }
    .playlist-list-toggle { display: inline-flex; align-items: center; gap: 0.35rem; margin: 0; font-size: 0.75rem; color: var(--admin-text-strong); }
    .playlist-list-toggle .form-check-input { margin: 0; cursor: pointer; }
    @media (max-width: 991px) {
        .playlist-admin-page { gap: 0.6rem; }
        .playlist-admin-page .section-heading { align-items: stretch; gap: 0.65rem; }
        .playlist-admin-page .section-heading .btn { width: 100%; justify-content: center; }
        .playlist-admin-page .row.g-2 { --bs-gutter-x: 0.6rem; --bs-gutter-y: 0.6rem; }
        .playlist-admin-page .stat-card .card-body,
        .playlist-admin-page .section-card .card-body,
        .playlist-admin-page .table-card .card-body,
        .playlist-admin-page .list-card .card-body { padding: 0.78rem; }
        .playlist-admin-page .card-header { padding: 0.78rem 0.82rem; }
        .playlist-admin-page .table-responsive { overflow: visible; }
        .playlist-admin-page .list-group-item { padding: 0; }
        .playlist-list-row { align-items: stretch; flex-direction: column; }
        .playlist-list-link,
        .playlist-list-main { width: 100%; }
        .playlist-list-name { font-size: 0.88rem; }
        .playlist-list-status { margin-top: 0.2rem; }
        .playlist-list-side { width: 100%; justify-content: space-between; }
        .playlist-list-controls { justify-content: flex-end; }
        .playlist-list-badge { min-width: 4rem; text-align: center; padding: 0.24rem 0.45rem; border-radius: 999px; }
        .playlist-item-table thead { display: none; }
        .playlist-item-table th,
        .playlist-item-table td,
        .playlist-item-table th:nth-child(1),
        .playlist-item-table td:nth-child(1),
        .playlist-item-table th:nth-child(2),
        .playlist-item-table td:nth-child(2),
        .playlist-item-table th:nth-child(3),
        .playlist-item-table td:nth-child(3),
        .playlist-item-table th:nth-child(4),
        .playlist-item-table td:nth-child(4),
        .playlist-item-table th:nth-child(5),
        .playlist-item-table td:nth-child(5) { width: auto !important; }
        .playlist-item-table,
        .playlist-item-table tbody,
        .playlist-item-table td { display: block; width: 100%; }
        .playlist-item-table tbody { padding: 0.45rem; }
        .playlist-item-table tr {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto auto auto;
            grid-template-areas:
                "main main main actions"
                "type duration active actions";
            gap: 0.45rem;
            margin-bottom: 0.6rem;
            padding: 0.72rem;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 1rem;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.96));
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
        }
        .playlist-item-table tr:last-child { margin-bottom: 0; }
        .playlist-item-table td {
            border: 0;
            padding: 0.48rem 0.58rem;
            margin-top: 0;
            border-radius: 0.82rem;
            background: rgba(255, 255, 255, 0.82);
            border: 1px solid rgba(15, 23, 42, 0.06);
        }
        .playlist-item-table td::before { content: attr(data-label); display: block; margin-bottom: 0.2rem; font-size: 0.62rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--admin-text-soft); }
        .playlist-item-table td.playlist-item-main { grid-area: main; padding: 0.56rem 0.62rem; background: rgba(248, 250, 252, 0.92); }
        .playlist-item-table td.playlist-item-main::before { display: none; }
        .playlist-item-table td.playlist-item-type { grid-area: type; min-width: 4.2rem; }
        .playlist-item-table td.playlist-item-metric-duration { grid-area: duration; min-width: 5.2rem; }
        .playlist-item-table td.playlist-item-active { grid-area: active; min-width: 4.2rem; }
        .playlist-item-table td.playlist-item-actions { grid-area: actions; align-self: center; padding: 0.38rem 0.42rem; }
        .playlist-item-main .muted-stack { gap: 0.18rem; }
        .playlist-item-main .muted-stack strong { font-size: 0.92rem; line-height: 1.25; }
        .playlist-item-table td.playlist-item-main .small { color: var(--admin-text-soft); font-size: 0.74rem !important; line-height: 1.3; }
        .playlist-item-table .playlist-item-cell { gap: 0.3rem; }
        .playlist-item-head { gap: 0.4rem; }
        .playlist-item-table .item-selector-form .form-select,
        .playlist-item-table input.form-control { min-height: 2.15rem; padding-top: 0.26rem; padding-bottom: 0.26rem; font-size: 0.84rem; }
        .playlist-item-table td.playlist-item-type::before,
        .playlist-item-table td.playlist-item-metric::before,
        .playlist-item-table td.playlist-item-active::before { margin-bottom: 0.16rem; }
        .playlist-item-table td.playlist-item-actions::before { display: none; }
        .playlist-item-table td.playlist-item-type .playlist-item-type-badge { font-size: 0.75rem; gap: 0.22rem; }
        .playlist-item-table td.playlist-item-type .playlist-item-type-badge span { display: none; }
        .playlist-item-table td.playlist-item-type .playlist-item-type-badge i { font-size: 0.95rem; color: #0f172a; }
        .playlist-item-table td.playlist-item-active .form-check {
            min-height: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .playlist-item-table td.playlist-item-active .form-check-input { margin: 0; width: 2rem; height: 1.1rem; }
        .playlist-item-table td.playlist-item-active .form-check-label { display: none; }
        .playlist-item-table .icon-actions { display: flex; align-items: center; justify-content: flex-end; gap: 0.28rem; flex-wrap: wrap; }
        .playlist-item-table .icon-actions > form,
        .playlist-item-table .icon-actions > button { width: auto; }
        .playlist-item-table .icon-actions .btn {
            width: 2rem;
            min-height: 2rem;
            justify-content: center;
            margin: 0;
        }
        .playlist-item-table .playlist-item-metric .small { display: block; text-align: center; font-size: 0.72rem !important; }
        .playlist-order-controls { justify-content: center; gap: 0.22rem; }
        .playlist-order-controls .btn { width: 1.85rem; min-height: 1.85rem; }
        .playlist-add-form .form-control,
        .playlist-add-form .form-select,
        .playlist-add-form .btn { min-height: 2.9rem; }
        .playlist-add-help { padding: 0.72rem 0.78rem; border-radius: 0.85rem; background: rgba(15, 23, 42, 0.05); }
        .playlist-add-form .form-text.pt-4 { padding-top: 0 !important; }
    }
</style>
<div class="page-shell playlist-admin-page">
<div class="section-heading">
    <div>
        <h1 class="h3">Playlists</h1>
        <div class="section-subtitle">Manage playlists, add content, and reorder items from one cleaner workspace.</div>
    </div>
    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#createPlaylistModal">
        <i class="bi bi-plus-circle"></i>
        <span class="ms-1">Add Playlist</span>
    </button>
</div>
<div class="row g-2">
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Playlists</div>
                <div class="stat-number-box"><div class="stat-value"><?= count($playlists) ?></div></div>
                <div class="stat-meta">Total saved playlists</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Active</div>
                <div class="stat-number-box"><div class="stat-value"><?= $activePlaylistCount ?></div></div>
                <div class="stat-meta">Available for screens</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Items</div>
                <div class="stat-number-box"><div class="stat-value"><?= $playlistItemTotal ?></div></div>
                <div class="stat-meta">Across all playlists</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Selected</div>
                <div class="stat-number-box"><div class="small fw-semibold"><?= $selectedPlaylist ? 'Loaded' : 'None' ?></div></div>
                <div class="stat-meta"><?= $selectedPlaylist ? e($selectedPlaylist['name']) : 'Choose a playlist below' ?></div>
            </div>
        </div>
    </div>
</div>
<div class="card list-card">
    <div class="card-header"><h2 class="h5 mb-0">Existing Playlists</h2></div>
    <div class="card-body p-0">
        <div class="list-group list-group-flush">
            <?php if (!$playlists): ?>
                <div class="list-group-item text-muted">No playlists created yet.</div>
            <?php else: ?>
                <?php foreach ($playlists as $playlist): ?>
                    <?php $playlistId = (int) $playlist['id']; ?>
                    <?php $isSelectedPlaylist = $playlistId === $selectedPlaylistId; ?>
                    <form method="post" id="playlist-delete-form-<?= $playlistId ?>" class="dense-form" onsubmit="return confirm('Delete this playlist? Assigned screens will become unassigned and playlist items will be removed.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_playlist">
                        <input type="hidden" name="playlist_id" value="<?= $playlistId ?>">
                    </form>
                    <form method="post" id="playlist-toggle-form-<?= $playlistId ?>" class="dense-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_playlist">
                        <input type="hidden" name="playlist_id" value="<?= $playlistId ?>">
                        <input type="hidden" name="name" value="<?= e($playlist['name']) ?>">
                        <div class="list-group-item playlist-list-item<?= $isSelectedPlaylist ? ' is-selected' : '' ?>">
                            <div class="playlist-list-row">
                                <a class="playlist-list-link playlist-list-main" href="<?= e(app_path('/admin/playlists.php?playlist_id=' . $playlistId)) ?>">
                                    <div class="playlist-list-name"><?= e($playlist['name']) ?></div>
                                    <span class="playlist-list-status">
                                        <span class="playlist-status-indicator <?= (int) $playlist['active'] === 1 ? 'is-active' : '' ?>" aria-hidden="true"></span>
                                        <span><?= (int) $playlist['active'] === 1 ? 'Active' : 'Inactive' ?></span>
                                    </span>
                                </a>
                                <div class="playlist-list-side">
                                    <span class="badge playlist-list-badge <?= (int) $playlist['active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int) $playlist['item_count'] ?> items</span>
                                    <div class="playlist-list-controls">
                                        <button class="btn btn-outline-danger icon-btn icon-btn-sm" type="submit" form="playlist-delete-form-<?= $playlistId ?>" title="Delete playlist" aria-label="Delete playlist">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <div class="form-check form-switch playlist-list-toggle">
                                            <input class="form-check-input" id="playlist_active_<?= $playlistId ?>" name="active" type="checkbox" value="1" onchange="this.form.submit()" <?= (int) $playlist['active'] === 1 ? 'checked' : '' ?>>
                                            <label class="form-check-label small" for="playlist_active_<?= $playlistId ?>">Active</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!$selectedPlaylist): ?>
    <div class="card">
        <div class="card-body text-muted">Select a playlist to edit settings and manage its items.</div>
    </div>
<?php else: ?>
            <div class="card table-card">
                <div class="card-header">
                    <div class="section-heading mb-0">
                        <div>
                            <h2 class="h5 mb-0">Playlist Items</h2>
                            <div class="section-subtitle">Reorder, enable, duplicate, or remove items inline.</div>
                        </div>
                        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#addPlaylistItemModal">
                            <i class="bi bi-plus-circle"></i>
                            <span class="ms-1">Add Item</span>
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm page-table mb-0 playlist-item-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Type</th>
                                    <th>Duration</th>
                                    <th>Active</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!$playlistItems): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No items in this playlist.</td></tr>
                            <?php else: ?>
                                <?php foreach ($playlistItems as $item): ?>
                                    <?php $formId = 'playlist-item-form-' . (int) $item['id']; ?>
                                    <?php $replaceFormId = 'playlist-item-replace-form-' . (int) $item['id']; ?>
                                    <?php
                                    $isQuizItem = $item['item_type'] === 'quiz';
                                    $isRandomQuiz = $isQuizItem && $item['quiz_selection_mode'] === 'random';
                                    $itemTypeLabel = $isQuizItem ? 'Quiz' : ($item['media_type'] === 'video' ? 'Video' : 'Image');
                                    $itemTypeIcon = $isQuizItem ? 'bi-patch-question' : ($item['media_type'] === 'video' ? 'bi-film' : 'bi-image');
                                    ?>
                                    <tr id="playlist-item-row-<?= (int) $item['id'] ?>">
                                        <td class="playlist-item-main" data-label="Item">
                                            <?php if ($isQuizItem): ?>
                                                <div class="playlist-item-head">
                                                    <div class="playlist-item-body">
                                                        <div class="muted-stack">
                                                            <strong><?= $isRandomQuiz ? 'Random quiz marker' : e($item['question_text']) ?></strong>
                                                            <?php if (!$isRandomQuiz): ?>
                                                                <span class="small">Correct answer <?= e($item['correct_option']) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="playlist-order-controls">
                                                        <button class="btn btn-sm btn-outline-secondary icon-btn icon-btn-sm js-move-playlist-item" type="button" data-direction="up" title="Move up" aria-label="Move up" <?= (int) $item['sort_order'] <= 1 ? 'disabled' : '' ?>>
                                                            <i class="bi bi-chevron-up"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-secondary icon-btn icon-btn-sm js-move-playlist-item" type="button" data-direction="down" title="Move down" aria-label="Move down">
                                                            <i class="bi bi-chevron-down"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="playlist-item-cell">
                                                    <div class="playlist-item-head">
                                                        <div class="playlist-item-body">
                                                            <form method="post" id="<?= e($replaceFormId) ?>" class="item-selector-form m-0">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="action" value="replace_playlist_media_item">
                                                                <input type="hidden" name="playlist_id" value="<?= (int) $selectedPlaylist['id'] ?>">
                                                                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                                                <select class="form-select form-select-sm" name="media_id" aria-label="Replace media item" onchange="this.form.submit()">
                                                                    <?php foreach ($mediaOptions as $media): ?>
                                                                        <option value="<?= (int) $media['id'] ?>" <?= (int) $media['id'] === (int) $item['media_id'] ? 'selected' : '' ?>>
                                                                            <?= e($media['title']) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </form>
                                                        </div>
                                                        <div class="playlist-order-controls">
                                                            <button class="btn btn-sm btn-outline-secondary icon-btn icon-btn-sm js-move-playlist-item" type="button" data-direction="up" title="Move up" aria-label="Move up" <?= (int) $item['sort_order'] <= 1 ? 'disabled' : '' ?>>
                                                                <i class="bi bi-chevron-up"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-secondary icon-btn icon-btn-sm js-move-playlist-item" type="button" data-direction="down" title="Move down" aria-label="Move down">
                                                                <i class="bi bi-chevron-down"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <input class="form-control form-control-sm playlist-order-input" name="sort_order" type="number" min="1" value="<?= (int) $item['sort_order'] ?>" required form="<?= e($formId) ?>">
                                        </td>
                                        <td class="playlist-item-type" data-label="Type">
                                            <span class="playlist-item-type-badge" title="<?= e($itemTypeLabel) ?>" aria-label="<?= e($itemTypeLabel) ?>">
                                                <i class="bi <?= e($itemTypeIcon) ?>"></i>
                                                <span><?= e($itemTypeLabel) ?></span>
                                            </span>
                                        </td>
                                        <td class="playlist-item-metric playlist-item-metric-duration" data-label="Duration">
                                            <?php if ($isQuizItem): ?>
                                                <?php if ($isRandomQuiz): ?>
                                                    <span class="small text-muted">&nbsp;</span>
                                                <?php else: ?>
                                                    <span class="small text-muted"><?= (int) $item['countdown_seconds'] + (int) $item['reveal_duration'] ?>s total</span>
                                                <?php endif; ?>
                                                <input type="hidden" name="image_duration" value="<?= (int) $item['image_duration'] ?>" form="<?= e($formId) ?>">
                                            <?php else: ?>
                                                <input class="form-control form-control-sm" name="image_duration" type="number" min="1" value="<?= (int) $item['image_duration'] ?>" required form="<?= e($formId) ?>">
                                            <?php endif; ?>
                                        </td>
                                        <td class="playlist-item-active" data-label="Active">
                                            <div class="form-check">
                                                <input class="form-check-input" id="active_item_<?= (int) $item['id'] ?>" name="active" type="checkbox" <?= (int) $item['active'] === 1 ? 'checked' : '' ?> form="<?= e($formId) ?>">
                                                <label class="form-check-label small" for="active_item_<?= (int) $item['id'] ?>">Active</label>
                                            </div>
                                        </td>
                                        <td class="playlist-item-actions" data-label="Actions">
                                            <div class="icon-actions">
                                                <form method="post" id="<?= e($formId) ?>" class="m-0">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="update_playlist_item">
                                                    <input type="hidden" name="playlist_id" value="<?= (int) $selectedPlaylist['id'] ?>">
                                                    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                                </form>
                                                <button class="btn btn-sm btn-outline-primary icon-btn icon-btn-sm" type="submit" title="Save item" aria-label="Save item" form="<?= e($formId) ?>">
                                                    <i class="bi bi-check2"></i>
                                                </button>
                                                <form method="post" class="m-0">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="duplicate_playlist_item">
                                                    <input type="hidden" name="playlist_id" value="<?= (int) $selectedPlaylist['id'] ?>">
                                                    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-secondary icon-btn icon-btn-sm" type="submit" title="Duplicate item" aria-label="Duplicate item">
                                                        <i class="bi bi-copy"></i>
                                                    </button>
                                                </form>
                                                <form method="post" class="m-0" onsubmit="return confirm('Remove this item from the playlist?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete_playlist_item">
                                                    <input type="hidden" name="playlist_id" value="<?= (int) $selectedPlaylist['id'] ?>">
                                                    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-danger icon-btn icon-btn-sm" type="submit" title="Remove item" aria-label="Remove item">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
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
<div class="modal fade" id="createPlaylistModal" tabindex="-1" aria-labelledby="createPlaylistModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 mb-0" id="createPlaylistModalLabel">Add Playlist</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form class="dense-form" method="post">
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
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-plus-circle"></i>
                        <span class="ms-1">Create Playlist</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php if ($selectedPlaylist): ?>
<div class="modal fade" id="addPlaylistItemModal" tabindex="-1" aria-labelledby="addPlaylistItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 mb-0" id="addPlaylistItemModalLabel">Add Item To Playlist</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" class="dense-form playlist-add-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_playlist_item">
                    <input type="hidden" name="playlist_id" value="<?= (int) $selectedPlaylist['id'] ?>">
                    <div class="mb-3">
                        <label class="form-label" for="modal_item_selection">Item</label>
                        <select class="form-select" id="modal_item_selection" name="item_selection" required>
                            <option value="">Select media item or random quiz question</option>
                            <option value="random_quiz">Random quiz question</option>
                            <?php foreach ($mediaOptions as $media): ?>
                                <option value="media:<?= (int) $media['id'] ?>"><?= e($media['title']) ?> (<?= e($media['media_type']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="modal_image_duration">Image Duration</label>
                        <input class="form-control" id="modal_image_duration" name="image_duration" type="number" min="1" value="10" required>
                    </div>
                    <div class="form-text playlist-add-help mb-3">New items are added to the end automatically. Image duration applies to media only. Random quizzes use their saved quiz timing.</div>
                    <button class="btn btn-primary w-100" type="submit">
                        <i class="bi bi-plus-circle"></i>
                        <span class="ms-1">Add Item</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const itemForms = document.querySelectorAll('form[id^="playlist-item-form-"]');
    const tableBody = document.querySelector('.table tbody');
    const playlistInlineForm = document.getElementById('playlist-inline-form');
    const moveButtons = document.querySelectorAll('.js-move-playlist-item');

    function syncOrderControls() {
        if (!tableBody) {
            return;
        }

        const rows = Array.from(tableBody.querySelectorAll('tr[id^="playlist-item-row-"]'));
        const totalRows = rows.length;

        rows.forEach(function (row, index) {
            const sortOrderInput = row.querySelector('input[name="sort_order"]');
            const upButton = row.querySelector('.js-move-playlist-item[data-direction="up"]');
            const downButton = row.querySelector('.js-move-playlist-item[data-direction="down"]');
            const orderNumber = index + 1;

            if (sortOrderInput) {
                sortOrderInput.value = String(orderNumber);
            }

            if (upButton) {
                upButton.disabled = index === 0;
            }

            if (downButton) {
                downButton.disabled = index === totalRows - 1;
            }
        });
    }

    function reorderRows() {
        if (!tableBody) {
            return;
        }

        const rows = Array.from(tableBody.querySelectorAll('tr[id^="playlist-item-row-"]'));
        rows.sort(function (rowA, rowB) {
            const inputA = rowA.querySelector('input[name="sort_order"]');
            const inputB = rowB.querySelector('input[name="sort_order"]');
            const orderA = inputA ? Number.parseInt(inputA.value, 10) || 0 : 0;
            const orderB = inputB ? Number.parseInt(inputB.value, 10) || 0 : 0;
            if (orderA !== orderB) {
                return orderA - orderB;
            }

            const idA = Number.parseInt(rowA.id.replace('playlist-item-row-', ''), 10) || 0;
            const idB = Number.parseInt(rowB.id.replace('playlist-item-row-', ''), 10) || 0;
            return idA - idB;
        });

        rows.forEach(function (row) {
            tableBody.appendChild(row);
        });

        syncOrderControls();
    }

    itemForms.forEach(function (form) {
        const controls = document.querySelectorAll('[form="' + form.id + '"]');
        let isSaving = false;

        function setControlsDisabled(disabled) {
            controls.forEach(function (control) {
                control.disabled = disabled;
            });
        }

        async function submitForm() {
            if (isSaving) {
                return;
            }

            try {
                const payload = new URLSearchParams();
                const formControls = Array.from(form.elements);
                const associatedControls = Array.from(controls);
                const allControls = formControls.concat(associatedControls);
                const seenNames = new Set();

                allControls.forEach(function (control) {
                    if (!control.name || control.disabled || seenNames.has(control.name)) {
                        return;
                    }

                    seenNames.add(control.name);

                    if ((control.type === 'checkbox' || control.type === 'radio') && !control.checked) {
                        return;
                    }

                    payload.append(control.name, control.value);
                });

                isSaving = true;
                setControlsDisabled(true);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: payload.toString(),
                    credentials: 'same-origin'
                });

                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error((result && result.message) || 'Playlist item save failed.');
                }

                const data = result.data || {};
                const sortOrderInput = document.querySelector('input[name="sort_order"][form="' + form.id + '"]');
                if (sortOrderInput && typeof data.sort_order !== 'undefined') {
                    sortOrderInput.value = data.sort_order;
                }

                if (data.swapped_item_id) {
                    const swappedFormId = 'playlist-item-form-' + data.swapped_item_id;
                    const swappedSortOrderInput = document.querySelector('input[name="sort_order"][form="' + swappedFormId + '"]');
                    if (swappedSortOrderInput && typeof data.previous_sort_order !== 'undefined') {
                        swappedSortOrderInput.value = data.previous_sort_order;
                    }
                }

                reorderRows();
            } catch (error) {
                console.error(error);
                window.alert(error.message || 'Playlist item save failed.');
            } finally {
                setControlsDisabled(false);
                isSaving = false;
            }
        }

        controls.forEach(function (control) {
            const eventName = control.type === 'checkbox' || control.tagName === 'SELECT' ? 'change' : 'change';
            control.addEventListener(eventName, function () {
                void submitForm();
            });
        });
    });

    moveButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const row = button.closest('tr[id^="playlist-item-row-"]');
            if (!row) {
                return;
            }

            const form = row.querySelector('form[id^="playlist-item-form-"]');
            const sortOrderInput = form ? form.querySelector('input[name="sort_order"]') : null;
            if (!form || !sortOrderInput) {
                return;
            }

            const currentOrder = Number.parseInt(sortOrderInput.value, 10) || 1;
            const direction = button.dataset.direction === 'up' ? -1 : 1;
            const nextOrder = Math.max(1, currentOrder + direction);

            if (nextOrder === currentOrder) {
                return;
            }

            sortOrderInput.value = String(nextOrder);
            sortOrderInput.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    syncOrderControls();

    if (playlistInlineForm) {
        const playlistControls = Array.from(playlistInlineForm.elements).filter(function (control) {
            return control.name && control.type !== 'hidden';
        });
        let isSavingPlaylist = false;

        function setPlaylistControlsDisabled(disabled) {
            playlistControls.forEach(function (control) {
                control.disabled = disabled;
            });
        }

        async function submitPlaylistInlineForm() {
            if (isSavingPlaylist) {
                return;
            }

            try {
                const payload = new URLSearchParams();
                Array.from(playlistInlineForm.elements).forEach(function (control) {
                    if (!control.name || control.disabled) {
                        return;
                    }

                    if ((control.type === 'checkbox' || control.type === 'radio') && !control.checked) {
                        return;
                    }

                    payload.append(control.name, control.value);
                });

                isSavingPlaylist = true;
                setPlaylistControlsDisabled(true);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: payload.toString(),
                    credentials: 'same-origin'
                });

                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error((result && result.message) || 'Playlist save failed.');
                }

                const data = result.data || {};
                const updatedAtElement = document.getElementById('selected-playlist-updated');
                if (updatedAtElement && data.updated_at) {
                    updatedAtElement.textContent = data.updated_at;
                }
            } catch (error) {
                console.error(error);
                window.alert(error.message || 'Playlist save failed.');
            } finally {
                setPlaylistControlsDisabled(false);
                isSavingPlaylist = false;
            }
        }

        playlistControls.forEach(function (control) {
            const eventName = control.type === 'checkbox' || control.tagName === 'SELECT' ? 'change' : 'change';
            control.addEventListener(eventName, function () {
                void submitPlaylistInlineForm();
            });
        });
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

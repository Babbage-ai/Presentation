<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$db = get_db();
ensure_media_upload_dir();
$adminId = current_admin_id();

$ajaxRespond = static function (bool $success, string $message, int $statusCode = 200): void {
    if (is_ajax_request()) {
        json_response($success, $message, [], $statusCode);
    }
};

if (is_post_request()) {
    if (exceeds_post_max_size()) {
        $message = 'Upload failed because the request exceeded the server limit of ' . upload_limit_summary() . '. Increase upload_max_filesize and post_max_size for larger videos.';

        $ajaxRespond(false, $message, 413);
        set_flash('danger', $message);
        redirect('/admin/media.php');
    }

    if (!verify_csrf()) {
        $message = 'Your session expired or the form token is no longer valid. Refresh the page and try again.';

        $ajaxRespond(false, $message, 400);
        set_flash('danger', $message);
        redirect('/admin/media.php');
    }

    require_valid_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'upload_media') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $files = normalize_uploaded_files_array($_FILES['media_file'] ?? null);

        if (!$files) {
            $message = 'Select at least one media file to upload.';
            $ajaxRespond(false, $message, 422);
            set_flash('danger', $message);
            redirect('/admin/media.php');
        }

        $uploadedCount = 0;

        foreach ($files as $index => $file) {
            if (!is_array($file)) {
                $message = 'Uploaded file data was not received correctly.';
                $ajaxRespond(false, $message, 422);
                set_flash('danger', $message);
                redirect('/admin/media.php');
            }

            [$valid, $errorMessage, $mimeType, $mediaType] = validate_uploaded_media($file);

            if (!$valid) {
                $prefix = count($files) > 1 ? 'File ' . ($index + 1) . ': ' : '';
                $message = $prefix . $errorMessage;
                $ajaxRespond(false, $message, 422);
                set_flash('danger', $message);
                redirect('/admin/media.php');
            }

            if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
                $message = 'Invalid upload source.';
                $ajaxRespond(false, $message, 400);
                set_flash('danger', $message);
                redirect('/admin/media.php');
            }

            $safeFilename = sanitize_upload_filename((string) ($file['name'] ?? 'media'));
            $destination = media_upload_dir() . '/' . $safeFilename;

            if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
                $message = 'Could not move uploaded file into place.';
                $ajaxRespond(false, $message, 500);
                set_flash('danger', $message);
                redirect('/admin/media.php');
            }

            $fileSize = (int) filesize($destination);
            $itemTitle = count($files) === 1 && $title !== ''
                ? $title
                : media_title_from_filename((string) ($file['name'] ?? ''));

            $statement = $db->prepare("INSERT INTO media (owner_admin_id, title, filename, mime_type, file_size, duration_seconds, media_type, active, created_at)
                                       VALUES (?, ?, ?, ?, ?, NULL, ?, 1, UTC_TIMESTAMP())");
            $statement->bind_param('isssis', $adminId, $itemTitle, $safeFilename, $mimeType, $fileSize, $mediaType);
            $statement->execute();
            $statement->close();

            $uploadedCount++;
        }

        $message = $uploadedCount === 1
            ? 'Media uploaded successfully.'
            : $uploadedCount . ' media items uploaded successfully.';
        $ajaxRespond(true, $message);
        set_flash('success', $message);
        redirect('/admin/media.php');
    }

    if ($action === 'update_media') {
        $mediaId = (int) ($_POST['media_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $files = normalize_uploaded_files_array($_FILES['media_file'] ?? null);

        if ($mediaId < 1) {
            $message = 'Select a valid media item to update.';
            $ajaxRespond(false, $message, 422);
            set_flash('danger', $message);
            redirect('/admin/media.php');
        }

        if (count($files) !== 1 || !is_array($files[0])) {
            $message = 'Select exactly one replacement file.';
            $ajaxRespond(false, $message, 422);
            set_flash('danger', $message);
            redirect('/admin/media.php');
        }

        $statement = $db->prepare("SELECT id, filename FROM media WHERE id = ? AND owner_admin_id = ? LIMIT 1");
        $statement->bind_param('ii', $mediaId, $adminId);
        $statement->execute();
        $existingMedia = $statement->get_result()->fetch_assoc();
        $statement->close();

        if (!$existingMedia) {
            $message = 'Media item was not found.';
            $ajaxRespond(false, $message, 404);
            set_flash('warning', $message);
            redirect('/admin/media.php');
        }

        $file = $files[0];
        [$valid, $errorMessage, $mimeType, $mediaType] = validate_uploaded_media($file);

        if (!$valid) {
            $ajaxRespond(false, $errorMessage, 422);
            set_flash('danger', $errorMessage);
            redirect('/admin/media.php');
        }

        if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            $message = 'Invalid upload source.';
            $ajaxRespond(false, $message, 400);
            set_flash('danger', $message);
            redirect('/admin/media.php');
        }

        $safeFilename = sanitize_upload_filename((string) ($file['name'] ?? 'media'));
        $destination = media_upload_dir() . '/' . $safeFilename;

        if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
            $message = 'Could not move uploaded file into place.';
            $ajaxRespond(false, $message, 500);
            set_flash('danger', $message);
            redirect('/admin/media.php');
        }

        $fileSize = (int) filesize($destination);
        $itemTitle = $title !== ''
            ? $title
            : media_title_from_filename((string) ($file['name'] ?? ''));

        $statement = $db->prepare("UPDATE media
                                   SET title = ?, filename = ?, mime_type = ?, file_size = ?, duration_seconds = NULL, media_type = ?
                                   WHERE id = ? AND owner_admin_id = ?");
        $statement->bind_param('sssisii', $itemTitle, $safeFilename, $mimeType, $fileSize, $mediaType, $mediaId, $adminId);
        $success = $statement->execute();
        $statement->close();

        if (!$success) {
            if (is_file($destination)) {
                unlink($destination);
            }

            $message = 'Media could not be updated.';
            $ajaxRespond(false, $message, 500);
            set_flash('danger', $message);
            redirect('/admin/media.php');
        }

        $oldFilePath = media_upload_dir() . '/' . $existingMedia['filename'];
        if ($existingMedia['filename'] !== $safeFilename && is_file($oldFilePath)) {
            unlink($oldFilePath);
        }

        $message = 'Media updated.';
        $ajaxRespond(true, $message);
        set_flash('success', $message);
        redirect('/admin/media.php');
    }

    if ($action === 'toggle_active') {
        $mediaId = (int) ($_POST['media_id'] ?? 0);
        $active = (int) ($_POST['active'] ?? 0) === 1 ? 1 : 0;

        $statement = $db->prepare("UPDATE media SET active = ? WHERE id = ? AND owner_admin_id = ?");
        $statement->bind_param('iii', $active, $mediaId, $adminId);
        $statement->execute();
        $statement->close();

        set_flash('success', 'Media status updated.');
        redirect('/admin/media.php');
    }

    if ($action === 'delete_media') {
        $mediaId = (int) ($_POST['media_id'] ?? 0);

        $statement = $db->prepare("SELECT COUNT(*) AS usage_count
                                   FROM playlist_items pi
                                   INNER JOIN playlists p ON p.id = pi.playlist_id
                                   WHERE pi.media_id = ? AND p.owner_admin_id = ?");
        $statement->bind_param('ii', $mediaId, $adminId);
        $statement->execute();
        $usageCount = (int) $statement->get_result()->fetch_assoc()['usage_count'];
        $statement->close();

        if ($usageCount > 0) {
            set_flash('warning', 'Media cannot be deleted while it is referenced by a playlist.');
            redirect('/admin/media.php');
        }

        $statement = $db->prepare("SELECT filename FROM media WHERE id = ? AND owner_admin_id = ? LIMIT 1");
        $statement->bind_param('ii', $mediaId, $adminId);
        $statement->execute();
        $media = $statement->get_result()->fetch_assoc();
        $statement->close();

        if ($media) {
            $statement = $db->prepare("DELETE FROM media WHERE id = ? AND owner_admin_id = ?");
            $statement->bind_param('ii', $mediaId, $adminId);
            $statement->execute();
            $statement->close();

            $filePath = media_upload_dir() . '/' . $media['filename'];
            if (is_file($filePath)) {
                unlink($filePath);
            }

            set_flash('success', 'Media deleted.');
        } else {
            set_flash('warning', 'Media item was not found.');
        }

        redirect('/admin/media.php');
    }
}

$mediaItems = [];
$missingMediaCount = 0;
$sql = "SELECT m.*,
               (SELECT COUNT(*)
                FROM playlist_items pi
                INNER JOIN playlists p ON p.id = pi.playlist_id
                WHERE pi.media_id = m.id AND p.owner_admin_id = m.owner_admin_id) AS usage_count
        FROM media m
        WHERE m.owner_admin_id = ?
        ORDER BY m.created_at DESC, m.id DESC";
$statement = $db->prepare($sql);
$statement->bind_param('i', $adminId);
$statement->execute();
$result = $statement->get_result();
while ($row = $result->fetch_assoc()) {
    $row['file_exists'] = media_file_exists($row['filename']);
    if (!$row['file_exists']) {
        $missingMediaCount++;
    }
    $mediaItems[] = $row;
}
$statement->close();

$activeMediaCount = 0;
foreach ($mediaItems as $item) {
    if ((int) $item['active'] === 1) {
        $activeMediaCount++;
    }
}

$pageTitle = 'Media';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
    .media-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; }
    .media-toolbar-copy { color: var(--admin-text-soft); margin: 0.2rem 0 0; }
    .media-meta-col { width: 10rem; }
    .media-type-col { width: 5rem; text-align: center; }
    .media-status-col { width: 8rem; }
    .media-actions-col { width: 12rem; }
    .media-title-cell { min-width: 14rem; }
    .media-title-wrap { display: flex; align-items: flex-start; gap: 0.65rem; min-width: 0; }
    .media-type-icon { width: 2rem; height: 2rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; background: rgba(15, 23, 42, 0.08); color: #0f172a; font-size: 1rem; }
    .media-title-stack { min-width: 0; }
    .media-title-text { font-weight: 600; line-height: 1.35; }
    .media-inline-meta { margin-top: 0.18rem; color: var(--admin-text-soft); font-size: 0.78rem; }
    .media-inline-meta span + span::before { content: "\2022"; margin: 0 0.35rem; }
    .media-type-badge { display: inline-flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: 999px; background: rgba(15, 23, 42, 0.08); color: #0f172a; }
    .media-status-stack { display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; }
    .media-active-toggle { display: inline-flex; align-items: center; gap: 0.5rem; margin: 0; }
    .media-active-toggle .form-check-input { margin: 0; cursor: pointer; }
    .media-actions { display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; }
    .media-modal-note { color: var(--admin-text-soft); font-size: 0.88rem; }
    .media-modal-current { margin-top: 0.45rem; color: var(--admin-text-soft); font-size: 0.84rem; }
    @media (max-width: 767px) {
        .media-toolbar { align-items: stretch; flex-direction: column; }
        .media-title-cell { min-width: 11rem; }
        .media-actions { justify-content: flex-end; }
    }
</style>
<div class="page-shell">
<div class="section-heading">
    <div class="media-toolbar w-100">
        <div>
            <h1 class="h3 mb-0">Media</h1>
            <div class="media-toolbar-copy">Upload assets, preview them quickly, and keep the library clean.</div>
        </div>
        <button
            class="btn btn-primary js-open-media-modal"
            type="button"
            data-bs-toggle="modal"
            data-bs-target="#mediaEditorModal"
            data-mode="create"
        >
            <i class="bi bi-plus-lg"></i>
            <span class="ms-1">Add New Media</span>
        </button>
    </div>
</div>
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Library</div>
                <div class="stat-number-box"><div class="stat-value"><?= count($mediaItems) ?></div></div>
                <div class="stat-meta">Total media items</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Active</div>
                <div class="stat-number-box"><div class="stat-value"><?= $activeMediaCount ?></div></div>
                <div class="stat-meta">Available for playlists</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Missing Files</div>
                <div class="stat-number-box"><div class="stat-value"><?= $missingMediaCount ?></div></div>
                <div class="stat-meta">Need re-upload</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Upload Limit</div>
                <div class="stat-number-box"><div class="small fw-semibold"><?= e(upload_limit_summary()) ?></div></div>
                <div class="stat-meta">Current server setting</div>
            </div>
        </div>
    </div>
</div>
<div class="row g-3">
    <div class="col-12">
        <div class="card table-card">
            <div class="card-header"><h2 class="h5 mb-0">Media Library</h2></div>
            <div class="card-body p-0">
                <?php if ($missingMediaCount > 0): ?>
                    <div class="alert alert-warning rounded-0 border-0 border-bottom mb-0">
                        <?= $missingMediaCount ?> media item(s) have a database record but the file is missing from `uploads/media`. Use Update Media to replace those files before using them in playlists.
                    </div>
                <?php endif; ?>
                <div class="table-responsive">
                    <table class="table table-sm page-table mb-0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th class="media-type-col">Type</th>
                                <th class="media-meta-col d-none d-md-table-cell">Size</th>
                                <th class="media-meta-col d-none d-md-table-cell">Created</th>
                                <th class="media-status-col">Status</th>
                                <th class="media-actions-col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$mediaItems): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No media uploaded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($mediaItems as $item): ?>
                                <?php
                                $isActive = (int) $item['active'] === 1;
                                $isVideo = $item['media_type'] === 'video';
                                $typeLabel = $isVideo ? 'Video' : 'Image';
                                $typeIcon = $isVideo ? 'bi-film' : 'bi-image';
                                ?>
                                <tr>
                                    <td class="media-title-cell">
                                        <div class="media-title-wrap">
                                            <span class="media-type-icon d-md-none" title="<?= e($typeLabel) ?>" aria-hidden="true">
                                                <i class="bi <?= $typeIcon ?>"></i>
                                            </span>
                                            <div class="media-title-stack">
                                                <div class="media-title-text"><?= e($item['title']) ?></div>
                                                <div class="media-inline-meta">
                                                    <span><?= e($typeLabel) ?></span>
                                                    <?php if ((int) $item['usage_count'] > 0): ?>
                                                        <span>Used in playlist<?= (int) $item['usage_count'] === 1 ? '' : 's' ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!$item['file_exists']): ?>
                                                        <span class="text-danger">File missing</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="media-type-col d-none d-md-table-cell">
                                        <span class="media-type-badge" title="<?= e($typeLabel) ?>" aria-label="<?= e($typeLabel) ?>">
                                            <i class="bi <?= $typeIcon ?>"></i>
                                        </span>
                                    </td>
                                    <td class="d-none d-md-table-cell"><?= e(format_bytes((int) $item['file_size'])) ?></td>
                                    <td class="d-none d-md-table-cell"><?= e(format_datetime($item['created_at'])) ?></td>
                                    <td>
                                        <div class="media-status-stack">
                                            <form method="post" class="m-0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle_active">
                                                <input type="hidden" name="media_id" value="<?= (int) $item['id'] ?>">
                                                <div class="form-check form-switch media-active-toggle">
                                                    <input
                                                        class="form-check-input"
                                                        id="media-active-<?= (int) $item['id'] ?>"
                                                        type="checkbox"
                                                        name="active"
                                                        value="1"
                                                        <?= $isActive ? 'checked' : '' ?>
                                                        onchange="this.form.submit()"
                                                    >
                                                    <label class="form-check-label small" for="media-active-<?= (int) $item['id'] ?>">Active</label>
                                                </div>
                                            </form>
                                            <?php if (!$item['file_exists']): ?>
                                                <span class="badge text-bg-danger">Missing</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="media-actions">
                                            <?php if ($item['file_exists']): ?>
                                                <button
                                                    class="btn btn-sm btn-outline-secondary js-preview-media icon-btn icon-btn-sm"
                                                    type="button"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#mediaPreviewModal"
                                                    data-media-type="<?= e($item['media_type']) ?>"
                                                    data-media-title="<?= e($item['title']) ?>"
                                                    data-media-url="<?= e(media_file_url($item['filename'])) ?>"
                                                    title="Preview media"
                                                    aria-label="Preview media"
                                                >
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button
                                                class="btn btn-sm btn-outline-primary js-open-media-modal"
                                                type="button"
                                                data-bs-toggle="modal"
                                                data-bs-target="#mediaEditorModal"
                                                data-mode="update"
                                                data-media-id="<?= (int) $item['id'] ?>"
                                                data-media-title="<?= e($item['title']) ?>"
                                                data-media-type="<?= e($item['media_type']) ?>"
                                            >
                                                Update Media
                                            </button>
                                            <form method="post" class="m-0" onsubmit="return confirm('Delete this media item?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_media">
                                                <input type="hidden" name="media_id" value="<?= (int) $item['id'] ?>">
                                                <button class="btn btn-sm btn-outline-danger icon-btn icon-btn-sm" type="submit" <?= (int) $item['usage_count'] > 0 ? 'disabled' : '' ?> title="Delete media" aria-label="Delete media">
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
    </div>
</div>
</div>
<div class="modal fade" id="mediaEditorModal" tabindex="-1" aria-labelledby="mediaEditorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 mb-0" id="mediaEditorModalLabel">Add New Media</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="mediaModalForm" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" id="mediaModalAction" value="upload_media">
                    <input type="hidden" name="media_id" id="mediaModalMediaId" value="0">
                    <div class="mb-3">
                        <label class="form-label" for="mediaModalTitle">Name</label>
                        <input class="form-control" id="mediaModalTitle" name="title" type="text" placeholder="Optional for single file uploads">
                        <div id="mediaModalTitleHelp" class="form-text">Single uploads can use a custom name. Batch uploads use each filename as the media title.</div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="mediaModalFile">File</label>
                        <input class="form-control" id="mediaModalFile" name="media_file[]" type="file" accept=".jpg,.jpeg,.png,.webp,.mp4" multiple required>
                        <div id="mediaModalFileHelp" class="form-text">Upload one video or multiple images at once. Supported: JPG, JPEG, PNG, WEBP, MP4. Total request limit: <?= e(upload_limit_summary()) ?>.</div>
                    </div>
                    <div id="mediaModalCurrent" class="media-modal-current d-none"></div>
                    <div id="mediaUploadStatus" class="alert d-none mt-3 mb-0" role="status" aria-live="polite"></div>
                    <div id="mediaUploadProgressWrap" class="progress mt-3 d-none" aria-hidden="true">
                        <div id="mediaUploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">0%</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div id="mediaModalNote" class="media-modal-note me-auto">New media is active right away and ready for playlists.</div>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button id="mediaModalSubmitButton" class="btn btn-primary" type="submit">Upload Media</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="mediaPreviewModal" tabindex="-1" aria-labelledby="mediaPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 mb-0" id="mediaPreviewModalLabel">Media Preview</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="mediaPreviewEmpty" class="text-muted">Select a media item to preview it here.</div>
                <img id="mediaPreviewImage" class="img-fluid rounded d-none" alt="">
                <video id="mediaPreviewVideo" class="w-100 rounded d-none" controls preload="metadata"></video>
            </div>
        </div>
    </div>
</div>
<script>
const mediaEditorButtons = document.querySelectorAll('.js-open-media-modal');
const mediaEditorModal = document.getElementById('mediaEditorModal');
const mediaModalForm = document.getElementById('mediaModalForm');
const mediaModalLabel = document.getElementById('mediaEditorModalLabel');
const mediaModalAction = document.getElementById('mediaModalAction');
const mediaModalMediaId = document.getElementById('mediaModalMediaId');
const mediaModalTitle = document.getElementById('mediaModalTitle');
const mediaModalTitleHelp = document.getElementById('mediaModalTitleHelp');
const mediaModalFile = document.getElementById('mediaModalFile');
const mediaModalFileHelp = document.getElementById('mediaModalFileHelp');
const mediaModalCurrent = document.getElementById('mediaModalCurrent');
const mediaModalNote = document.getElementById('mediaModalNote');
const mediaModalSubmitButton = document.getElementById('mediaModalSubmitButton');
const mediaUploadStatus = document.getElementById('mediaUploadStatus');
const mediaUploadProgressWrap = document.getElementById('mediaUploadProgressWrap');
const mediaUploadProgressBar = document.getElementById('mediaUploadProgressBar');

const configureMediaModal = (button) => {
    const mode = button && button.getAttribute('data-mode') === 'update' ? 'update' : 'create';
    const mediaTitle = button ? (button.getAttribute('data-media-title') || '') : '';
    const mediaType = button ? (button.getAttribute('data-media-type') || '') : '';
    const mediaId = button ? (button.getAttribute('data-media-id') || '0') : '0';

    mediaModalForm.reset();
    mediaUploadStatus.className = 'alert d-none mt-3 mb-0';
    mediaUploadStatus.textContent = '';
    mediaUploadProgressWrap.classList.add('d-none');
    mediaUploadProgressWrap.setAttribute('aria-hidden', 'true');
    mediaUploadProgressBar.style.width = '0%';
    mediaUploadProgressBar.textContent = '0%';
    mediaUploadProgressBar.setAttribute('aria-valuenow', '0');
    mediaModalSubmitButton.disabled = false;

    if (mode === 'update') {
        mediaModalLabel.textContent = 'Update Media';
        mediaModalAction.value = 'update_media';
        mediaModalMediaId.value = mediaId;
        mediaModalTitle.value = mediaTitle;
        mediaModalFile.multiple = false;
        mediaModalFile.required = true;
        mediaModalTitle.placeholder = 'Enter media name';
        mediaModalTitleHelp.textContent = 'Replace the file and optionally rename this library item. Playlist references stay linked to this same media entry.';
        mediaModalFileHelp.textContent = 'Choose one replacement image or video. Supported: JPG, JPEG, PNG, WEBP, MP4. Total request limit: <?= e(upload_limit_summary()) ?>.';
        mediaModalCurrent.textContent = 'Current type: ' + (mediaType || 'media') + '. Uploading a new file will replace the old one.';
        mediaModalCurrent.classList.remove('d-none');
        mediaModalNote.textContent = 'Updating keeps the existing media record, so playlists continue to use it.';
        mediaModalSubmitButton.textContent = 'Update Media';
        return;
    }

    mediaModalLabel.textContent = 'Add New Media';
    mediaModalAction.value = 'upload_media';
    mediaModalMediaId.value = '0';
    mediaModalFile.multiple = true;
    mediaModalFile.required = true;
    mediaModalTitle.placeholder = 'Optional for single file uploads';
    mediaModalTitleHelp.textContent = 'Single uploads can use a custom name. Batch uploads use each filename as the media title.';
    mediaModalFileHelp.textContent = 'Upload one video or multiple images at once. Supported: JPG, JPEG, PNG, WEBP, MP4. Total request limit: <?= e(upload_limit_summary()) ?>.';
    mediaModalCurrent.textContent = '';
    mediaModalCurrent.classList.add('d-none');
    mediaModalNote.textContent = 'New media is active right away and ready for playlists.';
    mediaModalSubmitButton.textContent = 'Upload Media';
};

for (const button of mediaEditorButtons) {
    button.addEventListener('click', () => configureMediaModal(button));
}

if (mediaEditorModal) {
    mediaEditorModal.addEventListener('hidden.bs.modal', () => configureMediaModal(null));
}

if (mediaModalForm && window.XMLHttpRequest && window.FormData) {
    const setStatus = (message, type) => {
        mediaUploadStatus.textContent = message;
        mediaUploadStatus.className = 'alert alert-' + type + ' mt-3 mb-0';
    };

    const setProgress = (percent) => {
        const safePercent = Math.max(0, Math.min(100, percent));
        mediaUploadProgressBar.style.width = safePercent + '%';
        mediaUploadProgressBar.textContent = safePercent + '%';
        mediaUploadProgressBar.setAttribute('aria-valuenow', String(safePercent));
    };

    mediaModalForm.addEventListener('submit', (event) => {
        event.preventDefault();

        const isUpdate = mediaModalAction.value === 'update_media';
        const actionLabel = isUpdate ? 'Updating media...' : 'Uploading media files...';
        const formData = new FormData(mediaModalForm);
        const request = new XMLHttpRequest();

        mediaModalSubmitButton.disabled = true;
        mediaUploadProgressWrap.classList.remove('d-none');
        mediaUploadProgressWrap.setAttribute('aria-hidden', 'false');
        mediaUploadStatus.classList.remove('d-none');
        setStatus(actionLabel, 'info');
        setProgress(0);

        request.open('POST', mediaModalForm.getAttribute('action') || window.location.href, true);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.setRequestHeader('Accept', 'application/json');

        request.upload.addEventListener('progress', (progressEvent) => {
            if (!progressEvent.lengthComputable) {
                return;
            }

            const percent = Math.round((progressEvent.loaded / progressEvent.total) * 100);
            setStatus(actionLabel + ' ' + percent + '%', 'info');
            setProgress(percent);
        });

        request.addEventListener('load', () => {
            let payload = null;

            try {
                payload = JSON.parse(request.responseText);
            } catch (error) {
                payload = null;
            }

            if (request.status >= 200 && request.status < 300 && payload && payload.success) {
                setProgress(100);
                setStatus(payload.message || (isUpdate ? 'Media updated.' : 'Media uploaded successfully.'), 'success');
                window.location.reload();
                return;
            }

            const message = payload && payload.message
                ? payload.message
                : (isUpdate ? 'Media update failed. Please try again.' : 'Upload failed. Please try again.');

            setStatus(message, 'danger');
            mediaModalSubmitButton.disabled = false;
        });

        request.addEventListener('error', () => {
            setStatus('Upload failed because the server could not be reached.', 'danger');
            mediaModalSubmitButton.disabled = false;
        });

        request.addEventListener('abort', () => {
            setStatus('Upload was cancelled.', 'warning');
            mediaModalSubmitButton.disabled = false;
        });

        request.send(formData);
    });
}

configureMediaModal(null);

const previewButtons = document.querySelectorAll('.js-preview-media');
const previewModalLabel = document.getElementById('mediaPreviewModalLabel');
const previewEmpty = document.getElementById('mediaPreviewEmpty');
const previewImage = document.getElementById('mediaPreviewImage');
const previewVideo = document.getElementById('mediaPreviewVideo');
const previewModal = document.getElementById('mediaPreviewModal');

const resetPreview = () => {
    previewEmpty.classList.remove('d-none');
    previewImage.classList.add('d-none');
    previewImage.removeAttribute('src');
    previewImage.alt = '';
    previewVideo.classList.add('d-none');
    previewVideo.pause();
    previewVideo.removeAttribute('src');
    previewVideo.load();
};

for (const button of previewButtons) {
    button.addEventListener('click', () => {
        const mediaType = button.getAttribute('data-media-type') || '';
        const mediaTitle = button.getAttribute('data-media-title') || 'Media Preview';
        const mediaUrl = button.getAttribute('data-media-url') || '';

        resetPreview();
        previewModalLabel.textContent = mediaTitle;

        if (mediaType === 'video') {
            previewVideo.src = mediaUrl;
            previewVideo.classList.remove('d-none');
            previewEmpty.classList.add('d-none');
            return;
        }

        previewImage.src = mediaUrl;
        previewImage.alt = mediaTitle;
        previewImage.classList.remove('d-none');
        previewEmpty.classList.add('d-none');
    });
}

if (previewModal) {
    previewModal.addEventListener('hidden.bs.modal', resetPreview);
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

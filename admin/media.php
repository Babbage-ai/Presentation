<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$db = get_db();
ensure_media_upload_dir();
$adminId = current_admin_id();

if (is_post_request()) {
    if (exceeds_post_max_size()) {
        $message = 'Upload failed because the request exceeded the server limit of ' . upload_limit_summary() . '. Increase upload_max_filesize and post_max_size for larger videos.';

        if (is_ajax_request()) {
            json_response(false, $message, [], 413);
        }

        set_flash('danger', $message);
        redirect('/admin/media.php');
    }

    if (!verify_csrf()) {
        $message = 'Your session expired or the form token is no longer valid. Refresh the page and try the upload again.';

        if (is_ajax_request()) {
            json_response(false, $message, [], 400);
        }

        set_flash('danger', $message);
        redirect('/admin/media.php');
    }

    require_valid_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'upload_media') {
        $respond = static function (bool $success, string $message, int $statusCode = 200): void {
            if (is_ajax_request()) {
                json_response($success, $message, [], $statusCode);
            }
        };

        $title = trim((string) ($_POST['title'] ?? ''));
        $file = $_FILES['media_file'] ?? null;

        if ($title === '' || !is_array($file)) {
            $message = 'Title and media file are required.';
            $respond(false, $message, 422);
            set_flash('danger', $message);
            redirect('/admin/media.php');
        }

        [$valid, $errorMessage, $mimeType, $mediaType] = validate_uploaded_media($file);

        if (!$valid) {
            $respond(false, $errorMessage, 422);
            set_flash('danger', $errorMessage);
            redirect('/admin/media.php');
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            $message = 'Invalid upload source.';
            $respond(false, $message, 400);
            set_flash('danger', $message);
            redirect('/admin/media.php');
        }

        $safeFilename = sanitize_upload_filename($file['name']);
        $destination = media_upload_dir() . '/' . $safeFilename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $message = 'Could not move uploaded file into place.';
            $respond(false, $message, 500);
            set_flash('danger', $message);
            redirect('/admin/media.php');
        }

        $fileSize = (int) filesize($destination);
        $durationSeconds = null;

        $statement = $db->prepare("INSERT INTO media (owner_admin_id, title, filename, mime_type, file_size, duration_seconds, media_type, active, created_at)
                                   VALUES (?, ?, ?, ?, ?, NULL, ?, 1, UTC_TIMESTAMP())");
        $statement->bind_param('isssis', $adminId, $title, $safeFilename, $mimeType, $fileSize, $mediaType);
        $statement->execute();
        $statement->close();

        $message = 'Media uploaded successfully.';
        $respond(true, $message);
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

$pageTitle = 'Media';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="section-heading">
    <div>
        <h1 class="h3">Media</h1>
        <div class="section-subtitle">Upload assets, preview them quickly, and keep the library clean.</div>
    </div>
</div>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h1 class="h5 mb-0">Upload Media</h1></div>
            <div class="card-body">
                <form id="uploadMediaForm" method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="upload_media">
                    <div class="mb-3">
                        <label class="form-label" for="title">Title</label>
                        <input class="form-control" id="title" name="title" type="text" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="media_file">File</label>
                        <input class="form-control" id="media_file" name="media_file" type="file" accept=".jpg,.jpeg,.png,.webp,.mp4" required>
                        <div class="form-text">Supported formats: JPG, JPEG, PNG, WEBP, MP4. Current server upload limit: <?= e(upload_limit_summary()) ?>.</div>
                    </div>
                    <div id="uploadStatus" class="alert d-none" role="status" aria-live="polite"></div>
                    <div id="uploadProgressWrap" class="progress mb-3 d-none" aria-hidden="true">
                        <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">0%</div>
                    </div>
                    <button id="uploadSubmitButton" class="btn btn-primary" type="submit">
                        <i class="bi bi-upload"></i>
                        <span class="ms-1">Upload</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h2 class="h5 mb-0">Media Library</h2></div>
            <div class="card-body p-0">
                <?php if ($missingMediaCount > 0): ?>
                    <div class="alert alert-warning rounded-0 border-0 border-bottom mb-0">
                        <?= $missingMediaCount ?> media item(s) have a database record but the file is missing from `uploads/media`. Re-upload those files before using them in playlists.
                    </div>
                <?php endif; ?>
                <div class="table-responsive">
                    <table class="table table-sm page-table mb-0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Filename</th>
                                <th>Size</th>
                                <th>Created</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$mediaItems): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No media uploaded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($mediaItems as $item): ?>
                                <tr>
                                    <td><?= e($item['title']) ?></td>
                                    <td><span class="badge text-bg-secondary"><?= e($item['media_type']) ?></span></td>
                                    <td><?= e($item['filename']) ?></td>
                                    <td><?= e(format_bytes((int) $item['file_size'])) ?></td>
                                    <td><?= e(format_datetime($item['created_at'])) ?></td>
                                    <td>
                                        <span class="badge <?= (int) $item['active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                            <?= (int) $item['active'] === 1 ? 'Active' : 'Inactive' ?>
                                        </span>
                                        <?php if (!$item['file_exists']): ?>
                                            <span class="badge text-bg-danger ms-1">File Missing</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="icon-actions">
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
                                            <form method="post" class="m-0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle_active">
                                                <input type="hidden" name="media_id" value="<?= (int) $item['id'] ?>">
                                                <input type="hidden" name="active" value="<?= (int) $item['active'] === 1 ? 0 : 1 ?>">
                                                <button class="btn btn-sm btn-outline-primary icon-btn icon-btn-sm" type="submit" title="<?= (int) $item['active'] === 1 ? 'Deactivate media' : 'Activate media' ?>" aria-label="<?= (int) $item['active'] === 1 ? 'Deactivate media' : 'Activate media' ?>">
                                                    <i class="bi <?= (int) $item['active'] === 1 ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="post" class="m-0" onsubmit="return confirm('Delete this media item?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_media">
                                                <input type="hidden" name="media_id" value="<?= (int) $item['id'] ?>">
                                                <button class="btn btn-sm btn-outline-danger icon-btn icon-btn-sm" type="submit" <?= (int) $item['usage_count'] > 0 ? 'disabled' : '' ?> title="Delete media" aria-label="Delete media">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                            <?php if ((int) $item['usage_count'] > 0): ?>
                                                <span class="small text-muted">Used in playlist(s)</span>
                                            <?php endif; ?>
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
const uploadForm = document.getElementById('uploadMediaForm');

if (uploadForm && window.XMLHttpRequest && window.FormData) {
    const statusBox = document.getElementById('uploadStatus');
    const progressWrap = document.getElementById('uploadProgressWrap');
    const progressBar = document.getElementById('uploadProgressBar');
    const submitButton = document.getElementById('uploadSubmitButton');

    const setStatus = (message, type) => {
        statusBox.textContent = message;
        statusBox.className = 'alert alert-' + type;
    };

    const setProgress = (percent) => {
        const safePercent = Math.max(0, Math.min(100, percent));
        progressBar.style.width = safePercent + '%';
        progressBar.textContent = safePercent + '%';
        progressBar.setAttribute('aria-valuenow', String(safePercent));
    };

    uploadForm.addEventListener('submit', (event) => {
        event.preventDefault();

        const formData = new FormData(uploadForm);
        const request = new XMLHttpRequest();

        submitButton.disabled = true;
        progressWrap.classList.remove('d-none');
        progressWrap.setAttribute('aria-hidden', 'false');
        statusBox.classList.remove('d-none');
        setStatus('Uploading media...', 'info');
        setProgress(0);

        request.open('POST', uploadForm.getAttribute('action') || window.location.href, true);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.setRequestHeader('Accept', 'application/json');

        request.upload.addEventListener('progress', (progressEvent) => {
            if (!progressEvent.lengthComputable) {
                return;
            }

            const percent = Math.round((progressEvent.loaded / progressEvent.total) * 100);
            setStatus('Uploading media... ' + percent + '%', 'info');
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
                setStatus(payload.message || 'Media uploaded successfully.', 'success');
                window.location.reload();
                return;
            }

            const message = payload && payload.message
                ? payload.message
                : 'Upload failed. Please try again.';

            setStatus(message, 'danger');
            submitButton.disabled = false;
        });

        request.addEventListener('error', () => {
            setStatus('Upload failed because the server could not be reached.', 'danger');
            submitButton.disabled = false;
        });

        request.addEventListener('abort', () => {
            setStatus('Upload was cancelled.', 'warning');
            submitButton.disabled = false;
        });

        request.send(formData);
    });
}

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

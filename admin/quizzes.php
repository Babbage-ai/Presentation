<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$db = get_db();
$adminId = current_admin_id();

function find_quiz_question(mysqli $db, int $adminId, int $quizId): ?array
{
    $statement = $db->prepare("SELECT q.*,
            (SELECT COUNT(*)
             FROM playlist_items pi
             INNER JOIN playlists p ON p.id = pi.playlist_id
             WHERE pi.quiz_question_id = q.id AND p.owner_admin_id = q.owner_admin_id) AS usage_count,
            COALESCE((
                SELECT GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ')
                FROM playlist_items pi
                INNER JOIN playlists p ON p.id = pi.playlist_id
                WHERE pi.quiz_question_id = q.id AND p.owner_admin_id = q.owner_admin_id
            ), '') AS playlist_names
        FROM quiz_questions q
        WHERE q.id = ? AND q.owner_admin_id = ?
        LIMIT 1");
    $statement->bind_param('ii', $quizId, $adminId);
    $statement->execute();
    $quiz = $statement->get_result()->fetch_assoc() ?: null;
    $statement->close();

    return $quiz;
}

function quiz_question_payload(array $quiz): array
{
    return [
        'id' => (int) $quiz['id'],
        'question_text' => (string) $quiz['question_text'],
        'option_a' => (string) $quiz['option_a'],
        'option_b' => (string) $quiz['option_b'],
        'option_c' => (string) $quiz['option_c'],
        'option_d' => (string) $quiz['option_d'],
        'correct_option' => (string) $quiz['correct_option'],
        'countdown_seconds' => (int) $quiz['countdown_seconds'],
        'reveal_duration' => (int) $quiz['reveal_duration'],
        'active' => (int) $quiz['active'],
        'usage_count' => (int) ($quiz['usage_count'] ?? 0),
        'playlist_names' => (string) ($quiz['playlist_names'] ?? ''),
        'updated_at' => format_datetime($quiz['updated_at'] ?? null),
    ];
}

function quiz_usage_count(mysqli $db, int $adminId, int $quizId): int
{
    $statement = $db->prepare("SELECT COUNT(*) AS usage_count
        FROM playlist_items pi
        INNER JOIN playlists p ON p.id = pi.playlist_id
        WHERE pi.quiz_question_id = ? AND p.owner_admin_id = ?");
    $statement->bind_param('ii', $quizId, $adminId);
    $statement->execute();
    $usageCount = (int) $statement->get_result()->fetch_assoc()['usage_count'];
    $statement->close();

    return $usageCount;
}

if (is_post_request()) {
    require_valid_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_quiz' || $action === 'update_quiz') {
        $quizId = (int) ($_POST['quiz_id'] ?? 0);
        $questionText = trim((string) ($_POST['question_text'] ?? ''));
        $optionA = trim((string) ($_POST['option_a'] ?? ''));
        $optionB = trim((string) ($_POST['option_b'] ?? ''));
        $optionC = trim((string) ($_POST['option_c'] ?? ''));
        $optionD = trim((string) ($_POST['option_d'] ?? ''));
        $correctOption = strtoupper(trim((string) ($_POST['correct_option'] ?? 'A')));
        $countdownSeconds = max(1, normalize_int($_POST['countdown_seconds'] ?? null, 10));
        $revealDuration = max(1, normalize_int($_POST['reveal_duration'] ?? null, 5));
        $active = isset($_POST['active']) ? 1 : 0;

        if ($questionText === '' || $optionA === '' || $optionB === '' || $optionC === '' || $optionD === '') {
            set_flash('danger', 'Question text and all four answers are required.');
            redirect('/admin/quizzes.php');
        }

        if (!in_array($correctOption, ['A', 'B', 'C', 'D'], true)) {
            set_flash('danger', 'Choose a valid correct answer.');
            redirect('/admin/quizzes.php');
        }

        if ($action === 'update_quiz' && $active === 0 && quiz_usage_count($db, $adminId, $quizId) > 0) {
            set_flash('warning', 'Quiz question cannot be deactivated while it is used in a playlist.');
            redirect('/admin/quizzes.php');
        }

        if ($action === 'create_quiz') {
            $statement = $db->prepare("INSERT INTO quiz_questions
                (owner_admin_id, question_text, option_a, option_b, option_c, option_d, correct_option, countdown_seconds, reveal_duration, active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
            $statement->bind_param('issssssiii', $adminId, $questionText, $optionA, $optionB, $optionC, $optionD, $correctOption, $countdownSeconds, $revealDuration, $active);
            $statement->execute();
            $statement->close();

            set_flash('success', 'Quiz question created.');
            redirect('/admin/quizzes.php');
        }

        $statement = $db->prepare("UPDATE quiz_questions
            SET question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ?, countdown_seconds = ?, reveal_duration = ?, active = ?, updated_at = UTC_TIMESTAMP()
            WHERE id = ? AND owner_admin_id = ?");
        $statement->bind_param('ssssssiiiii', $questionText, $optionA, $optionB, $optionC, $optionD, $correctOption, $countdownSeconds, $revealDuration, $active, $quizId, $adminId);
        $statement->execute();
        $statement->close();

        set_flash('success', 'Quiz question updated.');
        redirect('/admin/quizzes.php');
    }

    if ($action === 'toggle_quiz_active') {
        $quizId = (int) ($_POST['quiz_id'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;

        if ($active === 0 && quiz_usage_count($db, $adminId, $quizId) > 0) {
            if (is_ajax_request()) {
                header('Content-Type: application/json');
                http_response_code(409);
                echo json_encode([
                    'ok' => false,
                    'message' => 'Quiz question cannot be deactivated while it is used in a playlist.',
                ]);
                exit;
            }

            set_flash('warning', 'Quiz question cannot be deactivated while it is used in a playlist.');
            redirect('/admin/quizzes.php');
        }

        $statement = $db->prepare("UPDATE quiz_questions
            SET active = ?, updated_at = UTC_TIMESTAMP()
            WHERE id = ? AND owner_admin_id = ?");
        $statement->bind_param('iii', $active, $quizId, $adminId);
        $statement->execute();
        $statement->close();

        $quiz = find_quiz_question($db, $adminId, $quizId);

        if (is_ajax_request()) {
            header('Content-Type: application/json');

            if (!$quiz) {
                http_response_code(404);
                echo json_encode([
                    'ok' => false,
                    'message' => 'Quiz question not found.',
                ]);
                exit;
            }

            echo json_encode([
                'ok' => true,
                'message' => $active === 1 ? 'Quiz question activated.' : 'Quiz question deactivated.',
                'quiz' => quiz_question_payload($quiz),
            ]);
            exit;
        }

        set_flash($quiz ? 'success' : 'danger', $quiz ? ($active === 1 ? 'Quiz question activated.' : 'Quiz question deactivated.') : 'Quiz question not found.');
        redirect('/admin/quizzes.php');
    }

    if ($action === 'delete_quiz') {
        $quizId = (int) ($_POST['quiz_id'] ?? 0);

        $statement = $db->prepare("SELECT COUNT(*) AS usage_count
            FROM playlist_items pi
            INNER JOIN playlists p ON p.id = pi.playlist_id
            WHERE pi.quiz_question_id = ? AND p.owner_admin_id = ?");
        $statement->bind_param('ii', $quizId, $adminId);
        $statement->execute();
        $usageCount = (int) $statement->get_result()->fetch_assoc()['usage_count'];
        $statement->close();

        if ($usageCount > 0) {
            if (is_ajax_request()) {
                header('Content-Type: application/json');
                http_response_code(409);
                echo json_encode([
                    'ok' => false,
                    'message' => 'Quiz question cannot be deleted while it is used in a playlist.',
                ]);
                exit;
            }

            set_flash('warning', 'Quiz question cannot be deleted while it is used in a playlist.');
            redirect('/admin/quizzes.php');
        }

        $statement = $db->prepare("DELETE FROM quiz_questions WHERE id = ? AND owner_admin_id = ?");
        $statement->bind_param('ii', $quizId, $adminId);
        $statement->execute();
        $deleted = $statement->affected_rows > 0;
        $statement->close();

        if (is_ajax_request()) {
            header('Content-Type: application/json');

            if (!$deleted) {
                http_response_code(404);
                echo json_encode([
                    'ok' => false,
                    'message' => 'Quiz question not found.',
                ]);
                exit;
            }

            echo json_encode([
                'ok' => true,
                'message' => 'Quiz question deleted.',
                'quiz_id' => $quizId,
            ]);
            exit;
        }

        set_flash('success', 'Quiz question deleted.');
        redirect('/admin/quizzes.php');
    }
}

$quizQuestions = [];
$statement = $db->prepare("SELECT q.*,
        (SELECT COUNT(*)
         FROM playlist_items pi
         INNER JOIN playlists p ON p.id = pi.playlist_id
         WHERE pi.quiz_question_id = q.id AND p.owner_admin_id = q.owner_admin_id) AS usage_count,
        COALESCE((
            SELECT GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ')
            FROM playlist_items pi
            INNER JOIN playlists p ON p.id = pi.playlist_id
            WHERE pi.quiz_question_id = q.id AND p.owner_admin_id = q.owner_admin_id
        ), '') AS playlist_names
    FROM quiz_questions q
    WHERE q.owner_admin_id = ?
    ORDER BY q.updated_at DESC, q.id DESC");
$statement->bind_param('i', $adminId);
$statement->execute();
$result = $statement->get_result();
while ($row = $result->fetch_assoc()) {
    $quizQuestions[] = $row;
}
$statement->close();

$activeQuizCount = 0;
$quizInUseCount = 0;
foreach ($quizQuestions as $quiz) {
    if ((int) $quiz['active'] === 1) {
        $activeQuizCount++;
    }
    if ((int) $quiz['usage_count'] > 0) {
        $quizInUseCount++;
    }
}

$pageTitle = 'Quizzes';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="section-heading">
    <div>
        <h1 class="h3">Quizzes</h1>
        <div class="section-subtitle">One-line questions, inline playlist usage, and a single modal for add and edit.</div>
    </div>
    <button class="btn btn-primary" type="button" id="openCreateQuizModal">
        <i class="bi bi-plus-circle"></i>
        <span class="ms-1">Add Quiz</span>
    </button>
</div>
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Questions</div>
                <div class="stat-number-box"><div class="stat-value"><?= count($quizQuestions) ?></div></div>
                <div class="stat-meta">Total quiz items</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Active</div>
                <div class="stat-number-box"><div class="stat-value"><?= $activeQuizCount ?></div></div>
                <div class="stat-meta">Available to play</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">In Playlists</div>
                <div class="stat-number-box"><div class="stat-value"><?= $quizInUseCount ?></div></div>
                <div class="stat-meta">Currently referenced</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-label">Available</div>
                <div class="stat-number-box"><div class="stat-value"><?= max(0, count($quizQuestions) - $quizInUseCount) ?></div></div>
                <div class="stat-meta">Not currently in playlists</div>
            </div>
        </div>
    </div>
</div>
<div class="card list-card">
    <div class="card-header"><h2 class="h5 mb-0">Quiz Questions</h2></div>
    <div class="card-body p-0">
        <div class="quiz-list">
            <?php if (!$quizQuestions): ?>
                <div class="p-3 text-muted">No quiz questions created yet.</div>
            <?php else: ?>
                <?php foreach ($quizQuestions as $quiz): ?>
                    <div
                        class="quiz-row"
                        data-quiz="<?= e(json_encode(quiz_question_payload($quiz), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>"
                        tabindex="0"
                        role="button"
                    >
                        <div class="quiz-row-main" title="<?= e($quiz['question_text']) ?>">
                            <div class="quiz-row-line">
                                <span class="quiz-row-title"><?= e($quiz['question_text']) ?></span>
                                <span class="quiz-row-sep">|</span>
                                <span class="quiz-row-inline-meta" data-role="playlists">
                                    <?= (int) $quiz['usage_count'] > 0 ? 'Playlists: ' . e($quiz['playlist_names']) : 'Not in playlists' ?>
                                </span>
                                <span class="quiz-row-sep">|</span>
                                <span class="quiz-row-inline-meta" data-role="active"><?= (int) $quiz['active'] === 1 ? 'Active' : 'Inactive' ?></span>
                            </div>
                        </div>
                        <div class="quiz-row-actions">
                            <label class="quiz-row-toggle" title="Active">
                                <input
                                    class="form-check-input js-quiz-active-toggle"
                                    type="checkbox"
                                    <?= (int) $quiz['active'] === 1 ? 'checked' : '' ?>
                                    data-quiz-id="<?= (int) $quiz['id'] ?>"
                                    data-usage-count="<?= (int) $quiz['usage_count'] ?>"
                                >
                            </label>
                            <button
                                class="btn btn-outline-danger btn-sm icon-btn icon-btn-sm js-quiz-delete"
                                type="button"
                                title="Delete question"
                                data-quiz-id="<?= (int) $quiz['id'] ?>"
                                data-usage-count="<?= (int) $quiz['usage_count'] ?>"
                            >
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="quizEditModal" tabindex="-1" aria-labelledby="quizEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 mb-0" id="quizEditModalLabel">Edit Quiz Question</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form class="dense-form" method="post" id="quizEditForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_quiz" id="edit_quiz_action">
                    <input type="hidden" name="quiz_id" id="edit_quiz_id" value="">
                    <div class="mb-3">
                        <label class="form-label" for="edit_question_text">Question</label>
                        <textarea class="form-control" id="edit_question_text" name="question_text" rows="3" required></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="edit_option_a">Answer A</label>
                            <input class="form-control" id="edit_option_a" name="option_a" type="text" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="edit_option_b">Answer B</label>
                            <input class="form-control" id="edit_option_b" name="option_b" type="text" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="edit_option_c">Answer C</label>
                            <input class="form-control" id="edit_option_c" name="option_c" type="text" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="edit_option_d">Answer D</label>
                            <input class="form-control" id="edit_option_d" name="option_d" type="text" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="edit_correct_option">Correct Answer</label>
                            <select class="form-select" id="edit_correct_option" name="correct_option">
                                <?php foreach (['A', 'B', 'C', 'D'] as $answerKey): ?>
                                    <option value="<?= e($answerKey) ?>"><?= e($answerKey) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="edit_countdown_seconds">Countdown</label>
                            <input class="form-control" id="edit_countdown_seconds" name="countdown_seconds" type="number" min="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="edit_reveal_duration">Reveal Duration</label>
                            <input class="form-control" id="edit_reveal_duration" name="reveal_duration" type="number" min="1" required>
                        </div>
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" id="edit_quiz_active" name="active" type="checkbox">
                        <label class="form-check-label" for="edit_quiz_active">Quiz active</label>
                    </div>
                    <div class="compact-form-actions mt-3">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-check2"></i>
                            <span class="ms-1">Save Quiz</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
window.addEventListener('load', () => {
const quizEditModalEl = document.getElementById('quizEditModal');
const quizEditModal = quizEditModalEl ? new bootstrap.Modal(quizEditModalEl) : null;
const quizList = document.querySelector('.quiz-list');
const createQuizButton = document.getElementById('openCreateQuizModal');
const csrfToken = <?= json_encode(csrf_token()) ?>;
const quizEditFields = {
    action: document.getElementById('edit_quiz_action'),
    id: document.getElementById('edit_quiz_id'),
    questionText: document.getElementById('edit_question_text'),
    optionA: document.getElementById('edit_option_a'),
    optionB: document.getElementById('edit_option_b'),
    optionC: document.getElementById('edit_option_c'),
    optionD: document.getElementById('edit_option_d'),
    correctOption: document.getElementById('edit_correct_option'),
    countdownSeconds: document.getElementById('edit_countdown_seconds'),
    revealDuration: document.getElementById('edit_reveal_duration'),
    active: document.getElementById('edit_quiz_active'),
};

function updateQuizRowState(row, quiz) {
    if (!row || !quiz) {
        return;
    }

    row.dataset.quiz = JSON.stringify(quiz);

    const title = row.querySelector('.quiz-row-title');
    const playlistMeta = row.querySelector('.quiz-row-inline-meta[data-role="playlists"]');
    const activeMeta = row.querySelector('.quiz-row-inline-meta[data-role="active"]');
    const toggle = row.querySelector('.js-quiz-active-toggle');
    const deleteButton = row.querySelector('.js-quiz-delete');

    if (title) {
        title.textContent = quiz.question_text;
    }

    if (playlistMeta) {
        playlistMeta.textContent = Number(quiz.usage_count) > 0 ? `Playlists: ${quiz.playlist_names}` : 'Not in playlists';
    }

    if (activeMeta) {
        activeMeta.textContent = Number(quiz.active) === 1 ? 'Active' : 'Inactive';
    }

    if (toggle) {
        toggle.checked = Number(quiz.active) === 1;
        toggle.dataset.usageCount = String(quiz.usage_count);
    }

    if (deleteButton) {
        deleteButton.dataset.usageCount = String(quiz.usage_count);
    }
}

async function postQuizAction(body) {
    const response = await fetch(<?= json_encode(app_path('/admin/quizzes.php')) ?>, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        body: new URLSearchParams(body),
    });

    const data = await response.json().catch(() => ({
        ok: false,
        message: 'Request failed.',
    }));

    if (!response.ok || !data.ok) {
        throw new Error(data.message || 'Request failed.');
    }

    return data;
}

function resetQuizEditorForCreate() {
    if (!quizEditModal) {
        return;
    }

    document.getElementById('quizEditModalLabel').textContent = 'Add Quiz Question';
    quizEditFields.action.value = 'create_quiz';
    quizEditFields.id.value = '';
    quizEditFields.questionText.value = '';
    quizEditFields.optionA.value = '';
    quizEditFields.optionB.value = '';
    quizEditFields.optionC.value = '';
    quizEditFields.optionD.value = '';
    quizEditFields.correctOption.value = 'A';
    quizEditFields.countdownSeconds.value = 10;
    quizEditFields.revealDuration.value = 5;
    quizEditFields.active.checked = true;
    quizEditFields.active.disabled = false;
    quizEditModal.show();
}

function openQuizEditor(row) {
    if (!row || !quizEditModal) {
        return;
    }

    const quiz = JSON.parse(row.dataset.quiz || '{}');
    document.getElementById('quizEditModalLabel').textContent = 'Edit Quiz Question';
    quizEditFields.action.value = 'update_quiz';
    quizEditFields.id.value = quiz.id || '';
    quizEditFields.questionText.value = quiz.question_text || '';
    quizEditFields.optionA.value = quiz.option_a || '';
    quizEditFields.optionB.value = quiz.option_b || '';
    quizEditFields.optionC.value = quiz.option_c || '';
    quizEditFields.optionD.value = quiz.option_d || '';
    quizEditFields.correctOption.value = quiz.correct_option || 'A';
    quizEditFields.countdownSeconds.value = quiz.countdown_seconds || 10;
    quizEditFields.revealDuration.value = quiz.reveal_duration || 5;
    quizEditFields.active.checked = Number(quiz.active) === 1;
    quizEditFields.active.disabled = Number(quiz.usage_count) > 0 && Number(quiz.active) === 1;
    quizEditModal.show();
}

if (createQuizButton) {
    createQuizButton.addEventListener('click', resetQuizEditorForCreate);
}

if (quizList) {
    quizList.addEventListener('click', async (event) => {
        const toggleLabel = event.target.closest('.quiz-row-toggle');
        if (toggleLabel) {
            event.stopPropagation();
        }

        const toggle = event.target.closest('.js-quiz-active-toggle');
        if (toggle) {
            const usageCount = Number(toggle.dataset.usageCount || '0');
            if (usageCount > 0) {
                event.preventDefault();
                event.stopPropagation();
                window.alert('Quiz question cannot be deactivated while it is used in a playlist.');
                return;
            }

            event.stopPropagation();
            return;
        }

        const deleteButton = event.target.closest('.js-quiz-delete');
        if (deleteButton) {
            event.preventDefault();
            event.stopPropagation();

            const usageCount = Number(deleteButton.dataset.usageCount || '0');
            if (usageCount > 0) {
                window.alert('Quiz question cannot be deleted while it is used in a playlist.');
                return;
            }

            if (!window.confirm('Delete this quiz question?')) {
                return;
            }

            deleteButton.disabled = true;

            try {
                const data = await postQuizAction({
                    csrf_token: csrfToken,
                    action: 'delete_quiz',
                    quiz_id: deleteButton.dataset.quizId || '',
                });
                deleteButton.closest('.quiz-row')?.remove();
                window.alert(data.message);
            } catch (error) {
                window.alert(error.message);
                deleteButton.disabled = false;
            }

            return;
        }

        openQuizEditor(event.target.closest('.quiz-row'));
    });

    quizList.addEventListener('change', async (event) => {
        const toggle = event.target.closest('.js-quiz-active-toggle');
        if (!toggle) {
            return;
        }

        const row = toggle.closest('.quiz-row');
        toggle.disabled = true;

        try {
            const data = await postQuizAction({
                csrf_token: csrfToken,
                action: 'toggle_quiz_active',
                quiz_id: toggle.dataset.quizId || '',
                ...(toggle.checked ? { active: '1' } : {}),
            });
            updateQuizRowState(row, data.quiz);
        } catch (error) {
            toggle.checked = !toggle.checked;
            window.alert(error.message);
        } finally {
            toggle.disabled = false;
        }
    });

    quizList.addEventListener('keydown', (event) => {
        const row = event.target.closest('.quiz-row');
        if (!row) {
            return;
        }

        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            openQuizEditor(row);
        }
    });
}
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

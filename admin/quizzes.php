<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$db = get_db();
$adminId = current_admin_id();

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
            redirect('/admin/quizzes.php' . ($quizId > 0 ? '?quiz_id=' . $quizId : ''));
        }

        if (!in_array($correctOption, ['A', 'B', 'C', 'D'], true)) {
            set_flash('danger', 'Choose a valid correct answer.');
            redirect('/admin/quizzes.php' . ($quizId > 0 ? '?quiz_id=' . $quizId : ''));
        }

        if ($action === 'create_quiz') {
            $statement = $db->prepare("INSERT INTO quiz_questions
                (owner_admin_id, question_text, option_a, option_b, option_c, option_d, correct_option, countdown_seconds, reveal_duration, active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
            $statement->bind_param('issssssiii', $adminId, $questionText, $optionA, $optionB, $optionC, $optionD, $correctOption, $countdownSeconds, $revealDuration, $active);
            $statement->execute();
            $quizId = $statement->insert_id;
            $statement->close();

            set_flash('success', 'Quiz question created.');
            redirect('/admin/quizzes.php?quiz_id=' . $quizId);
        }

        $statement = $db->prepare("UPDATE quiz_questions
            SET question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ?, countdown_seconds = ?, reveal_duration = ?, active = ?, updated_at = UTC_TIMESTAMP()
            WHERE id = ? AND owner_admin_id = ?");
        $statement->bind_param('ssssssiiiii', $questionText, $optionA, $optionB, $optionC, $optionD, $correctOption, $countdownSeconds, $revealDuration, $active, $quizId, $adminId);
        $statement->execute();
        $statement->close();

        set_flash('success', 'Quiz question updated.');
        redirect('/admin/quizzes.php?quiz_id=' . $quizId);
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
            set_flash('warning', 'Quiz question cannot be deleted while it is used in a playlist.');
            redirect('/admin/quizzes.php?quiz_id=' . $quizId);
        }

        $statement = $db->prepare("DELETE FROM quiz_questions WHERE id = ? AND owner_admin_id = ?");
        $statement->bind_param('ii', $quizId, $adminId);
        $statement->execute();
        $statement->close();

        set_flash('success', 'Quiz question deleted.');
        redirect('/admin/quizzes.php');
    }
}

$quizQuestions = [];
$statement = $db->prepare("SELECT q.*,
        (SELECT COUNT(*)
         FROM playlist_items pi
         INNER JOIN playlists p ON p.id = pi.playlist_id
         WHERE pi.quiz_question_id = q.id AND p.owner_admin_id = q.owner_admin_id) AS usage_count
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

$selectedQuizId = (int) ($_GET['quiz_id'] ?? ($quizQuestions[0]['id'] ?? 0));
$selectedQuiz = null;

if ($selectedQuizId > 0) {
    $statement = $db->prepare("SELECT * FROM quiz_questions WHERE id = ? AND owner_admin_id = ? LIMIT 1");
    $statement->bind_param('ii', $selectedQuizId, $adminId);
    $statement->execute();
    $selectedQuiz = $statement->get_result()->fetch_assoc() ?: null;
    $statement->close();
}

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
        <div class="section-subtitle">Build question banks and manage the quiz timing in one place.</div>
    </div>
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
                <div class="stat-label">Selected</div>
                <div class="stat-number-box"><div class="small fw-semibold"><?= $selectedQuiz ? 'Edit' : 'None' ?></div></div>
                <div class="stat-meta"><?= $selectedQuiz ? 'Question loaded below' : 'Choose one from the list' ?></div>
            </div>
        </div>
    </div>
</div>
<div class="card top-create-card mb-3">
    <div class="card-header"><h2 class="h5 mb-0">Add New Quiz</h2></div>
    <div class="card-body">
        <form class="dense-form" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_quiz">
            <div class="row g-3">
                <div class="col-lg-6">
                    <label class="form-label" for="question_text">Question</label>
                    <textarea class="form-control" id="question_text" name="question_text" rows="3" required></textarea>
                </div>
                <div class="col-lg-6">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label" for="option_a">Answer A</label>
                            <input class="form-control" id="option_a" name="option_a" type="text" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="option_b">Answer B</label>
                            <input class="form-control" id="option_b" name="option_b" type="text" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="option_c">Answer C</label>
                            <input class="form-control" id="option_c" name="option_c" type="text" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="option_d">Answer D</label>
                            <input class="form-control" id="option_d" name="option_d" type="text" required>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="correct_option">Correct Answer</label>
                    <select class="form-select" id="correct_option" name="correct_option">
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="countdown_seconds">Countdown</label>
                    <input class="form-control" id="countdown_seconds" name="countdown_seconds" type="number" min="1" value="10" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="reveal_duration">Reveal Duration</label>
                    <input class="form-control" id="reveal_duration" name="reveal_duration" type="number" min="1" value="5" required>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" id="quiz_active" name="active" type="checkbox" checked>
                        <label class="form-check-label" for="quiz_active">Quiz active</label>
                    </div>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-plus-circle"></i>
                        <span class="ms-1">Create Quiz</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<div class="row g-3">
    <div class="col-xl-3 col-lg-4">
        <div class="admin-side-panel panel-stack">
        <div class="card list-card">
            <div class="card-header"><h2 class="h5 mb-0">Quiz Questions</h2></div>
            <div class="list-group list-group-flush">
                <?php if (!$quizQuestions): ?>
                    <div class="list-group-item text-muted">No quiz questions created yet.</div>
                <?php else: ?>
                    <?php foreach ($quizQuestions as $quiz): ?>
                        <a class="list-group-item list-group-item-action <?= (int) $quiz['id'] === $selectedQuizId ? 'active' : '' ?>" href="<?= e(app_path('/admin/quizzes.php?quiz_id=' . (int) $quiz['id'])) ?>">
                            <?php $questionPreview = substr($quiz['question_text'], 0, 80); ?>
                            <div class="small fw-semibold"><?= e($questionPreview) ?><?= strlen($quiz['question_text']) > 80 ? '...' : '' ?></div>
                            <div class="small opacity-75"><?= (int) $quiz['usage_count'] ?> playlist use(s)</div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        </div>
    </div>

    <div class="col-xl-9 col-lg-8">
        <?php if (!$selectedQuiz): ?>
            <div class="card section-card">
                <div class="card-body text-muted">Select a quiz question to edit it.</div>
            </div>
        <?php else: ?>
            <div class="card hero-card">
                <div class="card-header"><h2 class="h5 mb-0">Edit Quiz Question</h2></div>
                <div class="card-body">
                    <form class="dense-form" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_quiz">
                        <input type="hidden" name="quiz_id" value="<?= (int) $selectedQuiz['id'] ?>">
                        <div class="mb-3">
                            <label class="form-label" for="selected_question_text">Question</label>
                            <textarea class="form-control" id="selected_question_text" name="question_text" rows="3" required><?= e($selectedQuiz['question_text']) ?></textarea>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="selected_option_a">Answer A</label>
                                <input class="form-control" id="selected_option_a" name="option_a" type="text" value="<?= e($selectedQuiz['option_a']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="selected_option_b">Answer B</label>
                                <input class="form-control" id="selected_option_b" name="option_b" type="text" value="<?= e($selectedQuiz['option_b']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="selected_option_c">Answer C</label>
                                <input class="form-control" id="selected_option_c" name="option_c" type="text" value="<?= e($selectedQuiz['option_c']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="selected_option_d">Answer D</label>
                                <input class="form-control" id="selected_option_d" name="option_d" type="text" value="<?= e($selectedQuiz['option_d']) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="selected_correct_option">Correct Answer</label>
                                <select class="form-select" id="selected_correct_option" name="correct_option">
                                    <?php foreach (['A', 'B', 'C', 'D'] as $answerKey): ?>
                                        <option value="<?= e($answerKey) ?>" <?= $selectedQuiz['correct_option'] === $answerKey ? 'selected' : '' ?>><?= e($answerKey) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="selected_countdown_seconds">Countdown</label>
                                <input class="form-control" id="selected_countdown_seconds" name="countdown_seconds" type="number" min="1" value="<?= (int) $selectedQuiz['countdown_seconds'] ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="selected_reveal_duration">Reveal Duration</label>
                                <input class="form-control" id="selected_reveal_duration" name="reveal_duration" type="number" min="1" value="<?= (int) $selectedQuiz['reveal_duration'] ?>" required>
                            </div>
                        </div>
                        <div class="form-check mt-3">
                            <input class="form-check-input" id="selected_quiz_active" name="active" type="checkbox" <?= (int) $selectedQuiz['active'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="selected_quiz_active">Quiz active</label>
                        </div>
                        <button class="btn btn-primary mt-3" type="submit">
                            <i class="bi bi-check2"></i>
                            <span class="ms-1">Save Quiz</span>
                        </button>
                    </form>
                    <form method="post" class="mt-2 dense-form" onsubmit="return confirm('Delete this quiz question?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_quiz">
                        <input type="hidden" name="quiz_id" value="<?= (int) $selectedQuiz['id'] ?>">
                        <button class="btn btn-outline-danger" type="submit">
                            <i class="bi bi-trash"></i>
                            <span class="ms-1">Delete Quiz</span>
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    redirect('/admin/dashboard.php');
}

if (is_post_request()) {
    require_valid_csrf();

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        set_flash('danger', 'Username and password are required.');
    } elseif (login_admin($username, $password)) {
        set_flash('success', 'Login successful.');
        redirect('/admin/dashboard.php');
    } else {
        set_flash('danger', 'Invalid login credentials.');
    }
}

$pageTitle = 'Login';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center mt-5">
    <div class="col-sm-10 col-md-6 col-lg-4">
        <div class="card">
            <div class="card-body p-4">
                <h1 class="h3 mb-3">Admin Login</h1>
                <p class="text-muted">Sign in to manage media, playlists, and screens.</p>
                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="username">Username</label>
                        <input class="form-control" id="username" name="username" type="text" autocomplete="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Log In</button>
                </form>
                <p class="small text-muted mt-3 mb-0">TODO: add rate limiting or login lockouts if the deployment becomes internet-exposed.</p>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

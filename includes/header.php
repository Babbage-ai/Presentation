<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$pageTitle = $pageTitle ?? 'Cloud Signage';
$admin = current_admin();
$currentPath = basename($_SERVER['PHP_SELF'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | Cloud Signage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        body { background: #f4f6f9; }
        .navbar-brand { font-weight: 600; letter-spacing: 0.02em; }
        .table td, .table th { vertical-align: middle; }
        .card { box-shadow: 0 0.35rem 1rem rgba(15, 23, 42, 0.06); border: 0; }
        .status-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 6px; }
        .status-online { background: #198754; }
        .status-offline { background: #6c757d; }
        pre.token-box { white-space: pre-wrap; word-break: break-all; margin: 0; font-size: 0.85rem; }
    </style>
</head>
<body>
<?php if ($admin): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= e(app_path('/admin/dashboard.php')) ?>">Cloud Signage</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link <?= $currentPath === 'dashboard.php' ? 'active' : '' ?>" href="<?= e(app_path('/admin/dashboard.php')) ?>">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link <?= $currentPath === 'media.php' ? 'active' : '' ?>" href="<?= e(app_path('/admin/media.php')) ?>">Media</a></li>
                <li class="nav-item"><a class="nav-link <?= $currentPath === 'quizzes.php' ? 'active' : '' ?>" href="<?= e(app_path('/admin/quizzes.php')) ?>">Quizzes</a></li>
                <li class="nav-item"><a class="nav-link <?= $currentPath === 'playlists.php' ? 'active' : '' ?>" href="<?= e(app_path('/admin/playlists.php')) ?>">Playlists</a></li>
                <li class="nav-item"><a class="nav-link <?= $currentPath === 'screens.php' ? 'active' : '' ?>" href="<?= e(app_path('/admin/screens.php')) ?>">Screens</a></li>
            </ul>
            <div class="d-flex align-items-center gap-3 text-white">
                <span class="small">Signed in as <?= e($admin['username']) ?></span>
                <a class="btn btn-outline-light btn-sm" href="<?= e(app_path('/admin/logout.php')) ?>">Logout</a>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>
<main class="container-fluid pb-4">
    <?php foreach (get_flash_messages() as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endforeach; ?>

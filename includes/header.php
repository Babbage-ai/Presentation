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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --admin-bg: #eef2f7;
            --admin-surface: #ffffff;
            --admin-border: rgba(15, 23, 42, 0.08);
            --admin-text-soft: #64748b;
            --admin-shadow: 0 0.35rem 1rem rgba(15, 23, 42, 0.06);
            --admin-radius: 0.85rem;
        }
        body { background: linear-gradient(180deg, #f7f9fc 0%, var(--admin-bg) 100%); color: #0f172a; font-size: 0.94rem; line-height: 1.4; }
        .navbar { --bs-navbar-padding-y: 0.55rem; box-shadow: 0 0.25rem 1rem rgba(15, 23, 42, 0.12); }
        .navbar-brand { font-weight: 600; letter-spacing: 0.02em; font-size: 1rem; }
        .nav-link { padding: 0.35rem 0.7rem !important; border-radius: 0.6rem; font-size: 0.9rem; }
        .nav-link.active { background: rgba(255, 255, 255, 0.12); }
        main.container-fluid { width: min(100%, 1480px); padding-left: 0.9rem; padding-right: 0.9rem; }
        .alert { padding: 0.7rem 0.9rem; border-radius: 0.8rem; font-size: 0.9rem; }
        .row { --bs-gutter-x: 1rem; --bs-gutter-y: 1rem; }
        .table td, .table th { vertical-align: middle; }
        .table-sm td, .table-sm th { padding-top: 0.48rem; padding-bottom: 0.48rem; }
        .card { box-shadow: var(--admin-shadow); border: 1px solid var(--admin-border); border-radius: var(--admin-radius); overflow: hidden; }
        .card-header { background: rgba(255, 255, 255, 0.82); border-bottom: 1px solid var(--admin-border); padding: 0.72rem 0.85rem; }
        .card-body { background: var(--admin-surface); padding: 0.9rem; }
        .h3 { font-size: 1.4rem; }
        .h5 { font-size: 1rem; }
        .form-label { margin-bottom: 0.3rem; font-size: 0.78rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--admin-text-soft); }
        .form-control, .form-select { min-height: 2.3rem; padding: 0.42rem 0.68rem; border-radius: 0.7rem; font-size: 0.92rem; border-color: rgba(15, 23, 42, 0.12); }
        .form-control-sm { min-height: 1.95rem; padding-top: 0.28rem; padding-bottom: 0.28rem; }
        .form-check-input { margin-top: 0.18rem; }
        .form-text, .small { font-size: 0.82rem !important; }
        .btn { --bs-btn-padding-y: 0.42rem; --bs-btn-padding-x: 0.72rem; --bs-btn-font-size: 0.88rem; --bs-btn-border-radius: 0.72rem; }
        .btn-sm { --bs-btn-padding-y: 0.28rem; --bs-btn-padding-x: 0.55rem; --bs-btn-font-size: 0.8rem; --bs-btn-border-radius: 0.62rem; }
        .badge { font-weight: 600; }
        .status-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 6px; }
        .status-online { background: #198754; }
        .status-offline { background: #6c757d; }
        pre.token-box { white-space: pre-wrap; word-break: break-all; margin: 0; font-size: 0.78rem; padding: 0.7rem !important; }
        .section-heading { display: flex; align-items: center; justify-content: space-between; gap: 0.8rem; margin-bottom: 0.8rem; }
        .section-heading h1, .section-heading h2 { margin: 0; }
        .section-subtitle { color: var(--admin-text-soft); font-size: 0.84rem; }
        .stat-card { height: 100%; }
        .stat-card .card-body { padding: 0.82rem 0.9rem 0.8rem; }
        .stat-label { font-size: 0.76rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--admin-text-soft); }
        .stat-value { font-size: clamp(1.3rem, 2.2vw, 2rem); font-weight: 700; line-height: 1; margin-top: 0.28rem; }
        .stat-meta { margin-top: 0.3rem; color: var(--admin-text-soft); font-size: 0.78rem; }
        .icon-btn {
            width: 2rem;
            height: 2rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.62rem;
            padding: 0;
        }
        .icon-btn-sm {
            width: 1.85rem;
            height: 1.85rem;
            border-radius: 0.55rem;
        }
        .icon-actions { display: flex; gap: 0.35rem; flex-wrap: wrap; }
        .info-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.65rem 0.75rem; }
        .info-cell { padding: 0.62rem 0.72rem; border-radius: 0.72rem; background: rgba(248, 250, 252, 0.9); border: 1px solid rgba(15, 23, 42, 0.06); }
        .info-cell-wide { grid-column: 1 / -1; }
        .info-label { display: block; margin-bottom: 0.18rem; font-size: 0.68rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--admin-text-soft); }
        .info-value { font-size: 0.9rem; line-height: 1.28; }
        .list-group-item { border-color: var(--admin-border); padding: 0.65rem 0.8rem; }
        .list-group-item.active { background: #0f172a; border-color: #0f172a; }
        .compact-form-actions { display: flex; gap: 0.42rem; flex-wrap: wrap; align-items: center; }
        .page-table thead th { font-size: 0.69rem; letter-spacing: 0.06em; text-transform: uppercase; color: var(--admin-text-soft); background: #f8fafc; white-space: nowrap; }
        .page-table tbody td { font-size: 0.88rem; }
        .muted-stack { display: flex; flex-direction: column; gap: 0.15rem; }
        .muted-stack .small { color: var(--admin-text-soft); }
        .accordion-item { border: 1px solid var(--admin-border); border-radius: var(--admin-radius) !important; overflow: hidden; margin-bottom: 0.6rem; }
        .accordion-button { gap: 0.5rem; font-weight: 600; padding: 0.75rem 0.9rem; }
        .accordion-button:not(.collapsed) { background: #f8fafc; color: inherit; box-shadow: none; }
        @media (max-width: 1199px) {
            main.container-fluid { width: 100%; padding-left: 0.8rem; padding-right: 0.8rem; }
        }
        @media (max-width: 991px) {
            .info-grid { grid-template-columns: 1fr; }
            .section-heading { align-items: flex-start; flex-direction: column; }
            .navbar .d-flex.align-items-center.gap-3 { gap: 0.6rem !important; }
        }
    </style>
</head>
<body>
<?php if ($admin): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-3">
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
<main class="container-fluid pb-3">
    <?php foreach (get_flash_messages() as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endforeach; ?>

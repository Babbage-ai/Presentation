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
        .page-shell { display: grid; gap: 1rem; }
        .page-shell > .section-heading {
            margin-bottom: 0;
            padding: 0.95rem 1.05rem;
            border: 1px solid var(--admin-border);
            border-radius: 1.15rem;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(241, 245, 249, 0.98));
            box-shadow: var(--admin-shadow);
        }
        .page-shell > .section-heading .section-subtitle {
            max-width: 42rem;
            margin-top: 0.22rem;
        }
        .hero-card,
        .table-card,
        .list-card,
        .section-card,
        .stat-card { border-radius: 1.15rem; }
        .hero-card { background: linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(241, 245, 249, 0.98)); }
        .hero-card .card-header { padding: 0.95rem 1.05rem; background: linear-gradient(135deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.96)); }
        .hero-card .card-body { padding: 1.05rem; }
        .hero-card-title { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.9rem; }
        .hero-card-copy { max-width: 36rem; color: var(--admin-text-soft); font-size: 0.83rem; margin-top: 0.2rem; }
        .hero-grid { display: grid; grid-template-columns: minmax(0, 2fr) minmax(260px, 1fr); gap: 1rem; align-items: start; }
        .hero-side-note { padding: 0.95rem 1rem; border-radius: 1rem; background: rgba(248, 250, 252, 0.9); border: 1px solid rgba(15, 23, 42, 0.06); }
        .hero-side-note strong { display: block; font-size: 0.86rem; margin-bottom: 0.2rem; }
        .hero-side-note span { display: block; color: var(--admin-text-soft); font-size: 0.8rem; }
        .table-card .card-header,
        .list-card .card-header,
        .section-card .card-header { padding: 0.82rem 0.95rem; }
        .table-card .card-body,
        .list-card .card-body,
        .section-card .card-body { padding: 0.95rem; }
        .list-card .list-group-item { background: transparent; }
        .stat-card { height: 100%; border-radius: 1.15rem; background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%); border: 1px solid rgba(15, 23, 42, 0.06); }
        .stat-card .card-body { padding: 0.9rem 1rem 0.92rem; }
        .stat-label { font-size: 0.76rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--admin-text-soft); }
        .stat-number-box { display: inline-flex; align-items: center; justify-content: center; min-width: 5rem; padding: 0.55rem 1rem; border-radius: 999px; margin-top: 0.5rem; background: #0f172a; color: #ffffff; box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08); }
        .stat-value { font-size: clamp(1.3rem, 2.2vw, 2rem); font-weight: 700; line-height: 1; }
        .stat-meta { margin-top: 0.45rem; color: var(--admin-text-soft); font-size: 0.78rem; }
        .top-create-card { border-radius: 1.1rem; }
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
        .admin-side-panel { position: sticky; top: 0.9rem; }
        .panel-stack { display: flex; flex-direction: column; gap: 0.8rem; }
        .compact-card-title { display: flex; align-items: center; justify-content: space-between; gap: 0.6rem; }
        .dense-form .mb-3 { margin-bottom: 0.75rem !important; }
        .dense-form .mt-3 { margin-top: 0.8rem !important; }
        .dense-form .mt-2 { margin-top: 0.55rem !important; }
        .dense-form .pt-4 { padding-top: 1.6rem !important; }
        .panel-note { color: var(--admin-text-soft); font-size: 0.8rem; }
        .page-table td .badge { font-size: 0.72rem; }
        .summary-list { display: grid; gap: 0.55rem; }
        .summary-row { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.55rem 0.7rem; border-radius: 0.7rem; background: rgba(248, 250, 252, 0.92); border: 1px solid rgba(15, 23, 42, 0.05); }
        .summary-label { font-size: 0.76rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--admin-text-soft); }
        .summary-value { font-weight: 600; text-align: right; }
        .stack-list { display: grid; gap: 0.6rem; }
        .stack-item { padding: 0.7rem 0.78rem; border-radius: 0.78rem; background: rgba(248, 250, 252, 0.94); border: 1px solid rgba(15, 23, 42, 0.05); }
        .stack-item strong { display: block; font-size: 0.92rem; }
        .stack-item span { display: block; color: var(--admin-text-soft); font-size: 0.8rem; margin-top: 0.18rem; }
        .panel-grid { display: grid; gap: 0.75rem; }
        .panel-grid.two-up { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .panel-section { border: 1px solid rgba(15, 23, 42, 0.06); border-radius: 0.8rem; background: rgba(248, 250, 252, 0.92); overflow: hidden; }
        .panel-section-head { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.68rem 0.8rem; border-bottom: 1px solid rgba(15, 23, 42, 0.06); background: rgba(255, 255, 255, 0.8); }
        .panel-section-title { margin: 0; font-size: 0.86rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--admin-text-soft); }
        .panel-section-copy { margin: 0.18rem 0 0; color: var(--admin-text-soft); font-size: 0.78rem; }
        .panel-section-body { padding: 0.8rem; }
        .panel-actions { display: flex; flex-wrap: wrap; gap: 0.35rem; align-items: center; }
        .screen-list { display: grid; gap: 0.7rem; }
        .screen-card { border: 1px solid var(--admin-border); border-radius: var(--admin-radius); background: rgba(255, 255, 255, 0.96); overflow: hidden; }
        .screen-card-head { display: flex; align-items: center; justify-content: space-between; gap: 0.8rem; padding: 0.75rem 0.85rem; border-bottom: 1px solid rgba(15, 23, 42, 0.05); background: #fcfdff; }
        .screen-card-title { display: flex; flex-direction: column; gap: 0.12rem; min-width: 0; }
        .screen-card-title strong { font-size: 0.95rem; }
        .screen-card-title span { color: var(--admin-text-soft); font-size: 0.8rem; }
        .screen-card-body { padding: 0.8rem 0.85rem; }
        .screen-card-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 0.7rem; flex-wrap: wrap; margin-bottom: 0.75rem; }
        .screen-card-meta { display: flex; gap: 0.4rem; flex-wrap: wrap; }
        .screen-card-actions { display: flex; gap: 0.35rem; flex-wrap: wrap; align-items: center; }
        .metric-row { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.7rem; }
        .metric-chip { padding: 0.52rem 0.62rem; border-radius: 0.72rem; background: rgba(248, 250, 252, 0.9); border: 1px solid rgba(15, 23, 42, 0.05); }
        .metric-chip-label { display: block; margin-bottom: 0.16rem; font-size: 0.66rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--admin-text-soft); }
        .metric-chip-value { font-size: 0.88rem; font-weight: 600; }
        .compact-note { color: var(--admin-text-soft); font-size: 0.78rem; }
        .details-toggle { border: 1px solid rgba(15, 23, 42, 0.08); border-radius: 0.78rem; background: rgba(248, 250, 252, 0.9); }
        .details-toggle summary { list-style: none; cursor: pointer; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.65rem 0.8rem; font-size: 0.88rem; font-weight: 600; }
        .details-toggle summary::-webkit-details-marker { display: none; }
        .details-toggle summary::after { content: "\F282"; font-family: bootstrap-icons; font-size: 0.8rem; color: var(--admin-text-soft); transition: transform 0.2s ease; }
        .details-toggle[open] summary::after { transform: rotate(180deg); }
        .details-toggle-body { padding: 0 0.8rem 0.8rem; }
        .attention-list { display: grid; gap: 0.55rem; }
        .attention-item { padding: 0.6rem 0.7rem; border-radius: 0.75rem; background: rgba(248, 250, 252, 0.94); border: 1px solid rgba(15, 23, 42, 0.05); }
        .attention-item strong { display: block; font-size: 0.9rem; }
        .attention-item span { color: var(--admin-text-soft); font-size: 0.8rem; }
        .dashboard-box { border: 1px solid rgba(15, 23, 42, 0.08); border-radius: 0.95rem; background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.96)); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.55); }
        .dashboard-box-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.8rem; padding: 0.8rem 0.9rem; border-bottom: 1px solid rgba(15, 23, 42, 0.06); }
        .dashboard-box-head h3 { margin: 0; font-size: 0.95rem; }
        .dashboard-box-head p { margin: 0.18rem 0 0; color: var(--admin-text-soft); font-size: 0.79rem; }
        .dashboard-box-body { padding: 0.82rem 0.9rem 0.9rem; }
        .dashboard-screen-grid { display: grid; gap: 0.7rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .dashboard-screen-card { border: 1px solid rgba(15, 23, 42, 0.06); border-radius: 0.85rem; background: rgba(255, 255, 255, 0.9); overflow: hidden; }
        .dashboard-screen-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem; padding: 0.75rem 0.8rem; border-bottom: 1px solid rgba(15, 23, 42, 0.05); }
        .dashboard-screen-head strong { display: block; font-size: 0.9rem; }
        .dashboard-screen-head span { display: block; margin-top: 0.15rem; color: var(--admin-text-soft); font-size: 0.79rem; }
        .dashboard-screen-body { padding: 0.75rem 0.8rem; }
        .dashboard-screen-body .summary-list { gap: 0.45rem; }
        .dashboard-screen-body .summary-row { padding: 0.48rem 0.58rem; }
        .quiz-list { display: grid; }
        .quiz-row { display: flex; align-items: center; justify-content: space-between; gap: 0.65rem; padding: 0.22rem 0.75rem; border-top: 1px solid var(--admin-border); cursor: pointer; transition: background-color 0.16s ease; }
        .quiz-row:first-child { border-top: 0; }
        .quiz-row:hover,
        .quiz-row:focus-visible { background: rgba(248, 250, 252, 0.9); outline: none; }
        .quiz-row-main { min-width: 0; flex: 1 1 auto; }
        .quiz-row-line { display: flex; align-items: center; gap: 0.45rem; min-width: 0; white-space: nowrap; overflow: hidden; }
        .quiz-row-title { min-width: 0; flex: 1 1 auto; font-weight: 600; line-height: 1.35; overflow: hidden; text-overflow: ellipsis; }
        .quiz-row-sep,
        .quiz-row-inline-meta { flex-shrink: 0; color: var(--admin-text-soft); font-size: 0.78rem; }
        .quiz-row-actions { display: flex; align-items: center; gap: 0.55rem; flex-shrink: 0; }
        .quiz-row-toggle { display: inline-flex; align-items: center; justify-content: center; min-width: 2rem; min-height: 2rem; margin: 0; cursor: pointer; }
        .quiz-row-toggle .form-check-input { margin-top: 0; }
        #quizEditModal .form-control,
        #quizEditModal .form-select { border: 1px solid rgba(15, 23, 42, 0.45); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.45); }
        #quizEditModal .form-control:focus,
        #quizEditModal .form-select:focus { border-color: rgba(15, 23, 42, 0.75); box-shadow: 0 0 0 0.15rem rgba(15, 23, 42, 0.12); }
        .playlist-admin-page { display: grid; gap: 0.75rem; }
        .playlist-admin-page .card-body { padding: 0.78rem; }
        .playlist-admin-page .hero-card .card-body { padding: 0.85rem; }
        .playlist-admin-page .section-card .card-body,
        .playlist-admin-page .list-card .card-body,
        .playlist-admin-page .table-card .card-body { padding: 0.82rem; }
        .playlist-admin-page .dashboard-box-head { padding: 0.65rem 0.75rem; }
        .playlist-admin-page .dashboard-box-body { padding: 0.7rem 0.75rem 0.75rem; }
        .playlist-admin-page .summary-row { padding: 0.42rem 0.55rem; }
        .playlist-admin-page .list-group-item { padding: 0.52rem 0.7rem; }
        .playlist-admin-page .muted-stack { gap: 0.08rem; }
        .playlist-admin-page .form-text.pt-4 { padding-top: 1rem !important; }
        @media (max-width: 1199px) {
            main.container-fluid { width: 100%; padding-left: 0.8rem; padding-right: 0.8rem; }
            .panel-grid.two-up { grid-template-columns: 1fr; }
            .metric-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .hero-grid { grid-template-columns: 1fr; }
            .dashboard-screen-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 991px) {
            .info-grid { grid-template-columns: 1fr; }
            .section-heading { align-items: flex-start; flex-direction: column; }
            .page-shell > .section-heading { padding: 0.85rem 0.9rem; }
            .navbar .d-flex.align-items-center.gap-3 { gap: 0.6rem !important; }
            .admin-side-panel { position: static; }
            .metric-row { grid-template-columns: 1fr; }
            .quiz-row { align-items: flex-start; flex-direction: column; }
            .quiz-row-line { width: 100%; }
            .quiz-row-actions { width: 100%; justify-content: space-between; }
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
                <li class="nav-item"><a class="nav-link <?= $currentPath === 'schedules.php' ? 'active' : '' ?>" href="<?= e(app_path('/admin/schedules.php')) ?>">Schedules</a></li>
                <li class="nav-item"><a class="nav-link <?= $currentPath === 'tickers.php' ? 'active' : '' ?>" href="<?= e(app_path('/admin/tickers.php')) ?>">Tickers</a></li>
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

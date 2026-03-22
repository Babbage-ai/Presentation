<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$siteName = 'Display Flow';
$adminUrl = app_path('/admin/login.php');
$playerGuideUrl = app_path('/raspberry-pi-setup.md');
$appBaseUrl = application_base_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($siteName) ?> | Cloud Signage for Raspberry Pi Screens</title>
    <meta name="description" content="Display Flow is a practical cloud signage platform for Raspberry Pi screens with playlists, media uploads, screen assignment, and remote updates.">
    <style>
        :root {
            color-scheme: light;
            --bg: #f4efe6;
            --surface: rgba(255, 252, 247, 0.82);
            --surface-strong: #fffaf2;
            --text: #1f1d19;
            --muted: #625b4d;
            --line: rgba(63, 53, 35, 0.14);
            --accent: #ba4a1f;
            --accent-dark: #7d2d10;
            --accent-soft: #f4d7c8;
            --shadow: 0 24px 60px rgba(47, 32, 14, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            background:
                radial-gradient(circle at top left, rgba(186, 74, 31, 0.16), transparent 32%),
                radial-gradient(circle at 85% 15%, rgba(198, 150, 90, 0.22), transparent 26%),
                linear-gradient(180deg, #fcf7ef 0%, var(--bg) 100%);
            color: var(--text);
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .page {
            width: min(1120px, calc(100% - 32px));
            margin: 0 auto;
            padding: 24px 0 72px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 10px 0 28px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-size: 1rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .brand-mark {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--accent) 0%, #dd7a34 100%);
            color: #fffaf4;
            font-weight: 700;
            box-shadow: var(--shadow);
        }

        .topbar-links {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(300px, 0.9fr);
            gap: 28px;
            align-items: stretch;
        }

        .panel {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 28px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(8px);
        }

        .hero-copy {
            padding: 40px;
        }

        .eyebrow {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent-dark);
            font-size: 0.76rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        h1 {
            margin: 18px 0 16px;
            font-size: clamp(2.8rem, 6vw, 5rem);
            line-height: 0.92;
            font-weight: 700;
        }

        .hero-copy p,
        .feature-card p,
        .info-card p {
            margin: 0;
            color: var(--muted);
            font-size: 1.05rem;
            line-height: 1.65;
        }

        .actions {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            margin-top: 28px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 0 20px;
            border-radius: 999px;
            border: 1px solid transparent;
            font-size: 0.98rem;
            font-weight: 700;
        }

        .button-primary {
            background: var(--accent);
            color: #fffaf4;
        }

        .button-secondary {
            border-color: var(--line);
            background: rgba(255, 250, 242, 0.72);
        }

        .hero-side {
            padding: 20px;
            display: grid;
            gap: 16px;
            background:
                linear-gradient(180deg, rgba(255, 250, 242, 0.92) 0%, rgba(249, 238, 224, 0.84) 100%);
        }

        .signal-card {
            border-radius: 22px;
            padding: 22px;
            background: var(--surface-strong);
            border: 1px solid var(--line);
        }

        .signal-card strong {
            display: block;
            font-size: 2rem;
            margin-bottom: 6px;
        }

        .signal-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .section {
            margin-top: 28px;
        }

        .section-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .feature-card,
        .info-card {
            padding: 26px;
        }

        .feature-card h2,
        .info-card h2 {
            margin: 0 0 10px;
            font-size: 1.45rem;
        }

        .feature-list,
        .info-list {
            margin: 16px 0 0;
            padding-left: 18px;
            color: var(--muted);
            line-height: 1.7;
        }

        .footer {
            margin-top: 34px;
            padding-top: 20px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: 0.95rem;
        }

        code {
            padding: 2px 6px;
            border-radius: 6px;
            background: rgba(125, 45, 16, 0.08);
            color: var(--accent-dark);
            font-family: "Courier New", monospace;
            font-size: 0.92em;
        }

        @media (max-width: 900px) {
            .hero,
            .section-grid {
                grid-template-columns: 1fr;
            }

            .hero-copy,
            .hero-side,
            .feature-card,
            .info-card {
                padding: 24px;
            }
        }

        @media (max-width: 640px) {
            .page {
                width: min(100% - 20px, 1120px);
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .signal-grid {
                grid-template-columns: 1fr;
            }

            h1 {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <header class="topbar">
            <div class="brand">
                <span class="brand-mark">DF</span>
                <span><?= e($siteName) ?></span>
            </div>
            <nav class="topbar-links" aria-label="Primary">
                <a class="button button-secondary" href="<?= e($adminUrl) ?>">Admin Login</a>
                <a class="button button-secondary" href="<?= e($playerGuideUrl) ?>">Pi Setup Guide</a>
            </nav>
        </header>

        <main>
            <section class="hero">
                <div class="panel hero-copy">
                    <span class="eyebrow">Cloud Signage for Raspberry Pi</span>
                    <h1>Run screen networks without buying an overbuilt platform.</h1>
                    <p>Display Flow gives you a practical admin panel for uploads, playlists, quizzes, screen assignment, and player updates. It is designed for real deployments on normal PHP hosting, with Raspberry Pi screens running a local HTML player.</p>
                    <div class="actions">
                        <a class="button button-primary" href="<?= e($adminUrl) ?>">Open Admin</a>
                        <a class="button button-secondary" href="<?= e($playerGuideUrl) ?>">Read Setup Guide</a>
                    </div>
                </div>
                <aside class="panel hero-side" aria-label="Platform summary">
                    <div class="signal-card">
                        <strong>Phase 1 MVP</strong>
                        <p>Media uploads, playlists, quizzes, screen assignment, JSON APIs, and a Pi-ready player in one small PHP codebase.</p>
                    </div>
                    <div class="signal-grid">
                        <div class="signal-card">
                            <strong>PHP + MySQL</strong>
                            <p>Deployable on standard VPS or shared hosting.</p>
                        </div>
                        <div class="signal-card">
                            <strong>Pi Player</strong>
                            <p>Browser-based playback with heartbeat reporting and offline cache support.</p>
                        </div>
                    </div>
                    <div class="signal-card">
                        <p><strong>Base URL</strong></p>
                        <p><code><?= e($appBaseUrl) ?></code></p>
                    </div>
                </aside>
            </section>

            <section class="section section-grid">
                <article class="panel feature-card">
                    <h2>Keep content moving</h2>
                    <p>Upload images and video, organize them into playlists, and control which screens pull which content without touching the device again.</p>
                    <ul class="feature-list">
                        <li>Batch image uploads with server-side validation</li>
                        <li>Playlist ordering and mixed content support</li>
                        <li>Per-screen playlist assignment</li>
                    </ul>
                </article>
                <article class="panel feature-card">
                    <h2>Operate with less friction</h2>
                    <p>Each screen has a token, the player checks in on a heartbeat, and the cloud side can flag pending updates for the next sync cycle.</p>
                    <ul class="feature-list">
                        <li>Online and offline visibility</li>
                        <li>Controlled media downloads through the API</li>
                        <li>Simple Raspberry Pi rollout path</li>
                    </ul>
                </article>
                <article class="panel feature-card">
                    <h2>Built for straightforward hosting</h2>
                    <p>This is intentionally a light stack: PHP 8, MySQL or MariaDB, static player assets, and no framework lock-in.</p>
                    <ul class="feature-list">
                        <li>No Node backend required</li>
                        <li>No Composer dependency chain</li>
                        <li>Readable files for practical maintenance</li>
                    </ul>
                </article>
            </section>

            <section class="section section-grid">
                <article class="panel info-card">
                    <h2>For operators</h2>
                    <p>Use the admin panel to manage the library, playlists, screens, and quiz content from one place.</p>
                    <ul class="info-list">
                        <li><a href="<?= e($adminUrl) ?>">Admin login</a></li>
                        <li><a href="<?= e(app_path('/admin/media.php')) ?>">Media library</a></li>
                        <li><a href="<?= e(app_path('/admin/screens.php')) ?>">Screen management</a></li>
                    </ul>
                </article>
                <article class="panel info-card">
                    <h2>For installers</h2>
                    <p>Use the Raspberry Pi guide to provision a player and point it at the cloud instance.</p>
                    <ul class="info-list">
                        <li><a href="<?= e($playerGuideUrl) ?>">Raspberry Pi setup</a></li>
                        <li><a href="<?= e(app_path('/player/player.html')) ?>">Player shell</a></li>
                        <li><a href="<?= e(app_path('/player/config.json')) ?>">Player config template</a></li>
                    </ul>
                </article>
                <article class="panel info-card">
                    <h2>For deployments</h2>
                    <p>The application already supports deployment under a subdirectory by setting <code>APP_BASE_PATH</code> if the main site lives at the domain root.</p>
                    <ul class="info-list">
                        <li><code>APP_URL=https://displayflow.co.uk</code></li>
                        <li><code>APP_BASE_PATH=</code> for root app installs</li>
                        <li><code>APP_BASE_PATH=/app</code> if the platform moves below the homepage later</li>
                    </ul>
                </article>
            </section>
        </main>

        <footer class="footer">
            <p><?= e($siteName) ?> is a deployable signage platform for managed Raspberry Pi displays, playlists, quizzes, and remote content delivery.</p>
        </footer>
    </div>
</body>
</html>

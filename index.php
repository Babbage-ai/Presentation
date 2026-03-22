<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$siteName = 'Display Flow';
$adminUrl = app_path('/admin/login.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($siteName) ?> | Affordable Digital Signage for Any Space</title>
    <meta name="description" content="Display Flow helps businesses show the right information on screens in reception areas, waiting rooms, shops, schools, and public spaces without spending a fortune.">
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
            </nav>
        </header>

        <main>
            <section class="hero">
                <div class="panel hero-copy">
                    <span class="eyebrow">Affordable Digital Signage</span>
                    <h1>Put useful information on screens without spending a fortune.</h1>
                    <p>Display Flow helps you keep displays fresh, clear, and easy to manage. Share announcements, promotions, directions, menus, timetables, and internal updates across one screen or an entire network.</p>
                    <div class="actions">
                        <a class="button button-primary" href="<?= e($adminUrl) ?>">Open Admin</a>
                    </div>
                </div>
                <aside class="panel hero-side" aria-label="Platform summary">
                    <div class="signal-card">
                        <strong>Easy to run</strong>
                        <p>Manage content from one place and keep screens updated without constant manual changes on each device.</p>
                    </div>
                    <div class="signal-grid">
                        <div class="signal-card">
                            <strong>Clear messaging</strong>
                            <p>Show the right information at the right time across your locations.</p>
                        </div>
                        <div class="signal-card">
                            <strong>Low cost</strong>
                            <p>A practical option for organisations that want digital signage without enterprise pricing.</p>
                        </div>
                    </div>
                    <div class="signal-card">
                        <p><strong>Possible Applications</strong></p>
                        <p>Anywhere that needs display information without spending a fortune.</p>
                    </div>
                </aside>
            </section>

            <section class="section section-grid">
                <article class="panel feature-card">
                    <h2>Promotions and offers</h2>
                    <p>Highlight products, special offers, seasonal campaigns, and featured services in shops, cafes, salons, and reception spaces.</p>
                    <ul class="feature-list">
                        <li>Retail promotions</li>
                        <li>Food and drink offers</li>
                        <li>Seasonal campaigns</li>
                    </ul>
                </article>
                <article class="panel feature-card">
                    <h2>Information and guidance</h2>
                    <p>Display directions, waiting room notices, timetables, check-in instructions, and public information where people need it most.</p>
                    <ul class="feature-list">
                        <li>Reception areas</li>
                        <li>Waiting rooms</li>
                        <li>Schools and community spaces</li>
                    </ul>
                </article>
                <article class="panel feature-card">
                    <h2>Internal communications</h2>
                    <p>Keep staff informed with shift messages, safety reminders, performance updates, event notices, and company news.</p>
                    <ul class="feature-list">
                        <li>Office dashboards</li>
                        <li>Warehouse notices</li>
                        <li>Staff room updates</li>
                    </ul>
                </article>
            </section>

            <section class="section section-grid">
                <article class="panel info-card">
                    <h2>Simple to manage</h2>
                    <p>Update screens from one dashboard instead of editing content separately on every display.</p>
                    <ul class="info-list">
                        <li><a href="<?= e($adminUrl) ?>">Admin login</a></li>
                        <li>One place to manage your screens</li>
                        <li>Fast content updates across locations</li>
                    </ul>
                </article>
                <article class="panel info-card">
                    <h2>Flexible for many spaces</h2>
                    <p>Use it for a single display or build a wider network across multiple rooms, branches, or public areas.</p>
                    <ul class="info-list">
                        <li>Shops and showrooms</li>
                        <li>Healthcare and education</li>
                        <li>Offices and hospitality</li>
                    </ul>
                </article>
                <article class="panel info-card">
                    <h2>Built around value</h2>
                    <p>Display Flow is designed for organisations that want modern digital signage without the cost and complexity of bigger platforms.</p>
                    <ul class="info-list">
                        <li>Lower setup cost</li>
                        <li>Straightforward day-to-day use</li>
                        <li>Professional presentation for customers and staff</li>
                    </ul>
                </article>
            </section>
        </main>

        <footer class="footer">
            <p><?= e($siteName) ?> helps organisations share useful information on screens clearly, consistently, and affordably.</p>
        </footer>
    </div>
</body>
</html>

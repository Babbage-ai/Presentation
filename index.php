<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$requestedScreenCode = normalize_screen_code((string) ($_GET['screen'] ?? ''));
if ($requestedScreenCode !== '') {
    $playerUrl = app_path('/player/player.html')
        . '?screen=' . rawurlencode($requestedScreenCode)
        . '&api_base_url=' . rawurlencode(application_base_url());
    header('Location: ' . $playerUrl, true, 302);
    exit;
}

$siteName = 'DisplayFlow';
$primaryTagline = 'Simple digital signage that gets screens live fast.';
$supportLine = 'Use the screens you already have. Add hardware only if you want it.';
$adminUrl = app_path('/admin/login.php');
$canonicalUrl = absolute_url('');
$stylesUrl = app_path('/assets/marketing.css');
$scriptUrl = app_path('/assets/marketing.js');
$demoEmail = 'hello@displayflow.co.uk';
?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($siteName) ?> | Digital Signage Software That Works on Any Screen</title>
    <meta name="description" content="DisplayFlow is browser-based digital signage software for menus, promotions, announcements and screen management. Control displays remotely from anywhere with no special hardware required.">
    <meta name="keywords" content="digital signage software, browser-based digital signage, screen management software, cloud digital displays, digital menu board software, digital signage for gyms, digital signage for restaurants, remote screen management, signage software for smart TVs">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($siteName) ?> | <?= e($primaryTagline) ?>">
    <meta property="og:description" content="Cloud-based digital signage software for menus, promotions, announcements and remote screen management.">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta name="theme-color" content="#ffffff">
    <link rel="stylesheet" href="<?= e($stylesUrl) ?>">
</head>
<body>
    <div class="marketing-shell">
        <div class="bg-orb bg-orb-orange" aria-hidden="true"></div>
        <div class="bg-orb bg-orb-blue" aria-hidden="true"></div>

        <header class="site-header">
            <div class="container nav-wrap">
                <a class="brand" href="#top" aria-label="<?= e($siteName) ?> home">
                    <span class="brand-mark">DF</span>
                    <span class="brand-copy">
                        <strong><?= e($siteName) ?></strong>
                        <span><?= e($supportLine) ?></span>
                    </span>
                </a>

                <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" data-nav-toggle>
                    <span></span>
                    <span></span>
                    <span></span>
                    <span class="sr-only">Toggle navigation</span>
                </button>

                <nav class="site-nav" id="site-nav" aria-label="Primary">
                    <a href="#features">Features</a>
                    <a href="#industries">Industries</a>
                    <a href="#pricing">Pricing</a>
                    <a href="#faq">FAQ</a>
                    <a href="#contact">Book a Demo</a>
                    <a class="nav-login" href="<?= e($adminUrl) ?>">Customer Login</a>
                </nav>
            </div>
        </header>

        <main id="top">
            <section class="hero-section section">
                <div class="container hero-grid">
                    <div class="hero-copy reveal">
                        <div class="eyebrow">
                            <span class="eyebrow-dot"></span>
                            Browser-based digital signage for growing businesses
                        </div>
                        <h1><?= e($primaryTagline) ?></h1>
                        <p class="hero-lead">DisplayFlow helps you launch menus, promotions and announcements across your screens without complicated hardware, messy USB updates or expensive custom installs.</p>
                        <p class="hero-support">Start with the TVs, tablets or media devices you already own, then manage everything from one simple dashboard as your rollout grows.</p>

                        <div class="hero-actions">
                            <a class="button button-primary" href="#pricing">View Pricing</a>
                            <a class="button button-secondary" href="#contact">Book a Demo</a>
                        </div>

                        <div class="hero-proof">
                            <span>No long-term setup headache</span>
                            <span>Fast rollout for single or multi-site</span>
                            <span>Optional plug-and-play hardware</span>
                        </div>

                        <div class="hero-notes">
                            <div class="hero-note">
                                <strong>No hardware required</strong>
                                <span>Use screens and devices you already own.</span>
                            </div>
                            <div class="hero-note">
                                <strong>Pair screens in seconds</strong>
                                <span>Simple setup for one site or many locations.</span>
                            </div>
                            <div class="hero-note">
                                <strong>Update displays instantly</strong>
                                <span>Menus, promos and announcements stay current.</span>
                            </div>
                        </div>
                    </div>

                    <div class="hero-visual reveal reveal-delay-1">
                        <div class="product-stage">
                            <div class="dashboard-card glass-card">
                                <div class="card-header">
                                    <div>
                                        <span class="kicker">DisplayFlow Dashboard</span>
                                        <h2>One dashboard for every location</h2>
                                    </div>
                                    <span class="status-pill status-live">12 screens live</span>
                                </div>

                                <div class="stats-row">
                                    <div class="mini-stat">
                                        <span>Active promos</span>
                                        <strong>08</strong>
                                    </div>
                                    <div class="mini-stat">
                                        <span>Menus updated</span>
                                        <strong>2 min ago</strong>
                                    </div>
                                    <div class="mini-stat">
                                        <span>Locations</span>
                                        <strong>4 sites</strong>
                                    </div>
                                </div>

                                <div class="dashboard-grid">
                                    <div class="screen-list">
                                        <div class="screen-row">
                                            <span class="screen-dot online"></span>
                                            <div>
                                                <strong>Front Counter Menu</strong>
                                                <span>City Centre Cafe</span>
                                            </div>
                                            <em>Live</em>
                                        </div>
                                        <div class="screen-row">
                                            <span class="screen-dot online"></span>
                                            <div>
                                                <strong>Reception Welcome Screen</strong>
                                                <span>Martial Arts Academy</span>
                                            </div>
                                            <em>Updated</em>
                                        </div>
                                        <div class="screen-row">
                                            <span class="screen-dot syncing"></span>
                                            <div>
                                                <strong>Promo Display</strong>
                                                <span>Retail Store Window</span>
                                            </div>
                                            <em>Syncing</em>
                                        </div>
                                    </div>

                                    <div class="timeline-card">
                                        <div class="timeline-head">
                                            <strong>Today's schedule</strong>
                                            <span>Auto-switch content</span>
                                        </div>
                                        <div class="timeline-item">
                                            <span>08:00</span>
                                            <div>Breakfast menu live</div>
                                        </div>
                                        <div class="timeline-item">
                                            <span>12:00</span>
                                            <div>Lunch offers and combo upsells</div>
                                        </div>
                                        <div class="timeline-item">
                                            <span>17:00</span>
                                            <div>Evening promos and announcements</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="floating-screens">
                                <article class="screen-card menu-screen">
                                    <span class="screen-label">Menu board</span>
                                    <strong>Fresh bowls</strong>
                                    <ul>
                                        <li>Chicken Caesar <span>£8.95</span></li>
                                        <li>Halloumi Wrap <span>£7.50</span></li>
                                        <li>Soup + Drink <span>£5.95</span></li>
                                    </ul>
                                </article>

                                <article class="screen-card promo-screen">
                                    <span class="screen-label">Promo screen</span>
                                    <strong>2 for 1 before 5pm</strong>
                                    <p>Push timed offers across every branch in seconds.</p>
                                </article>

                                <article class="screen-card pair-screen">
                                    <span class="screen-label">Screen pairing</span>
                                    <strong>Pair this screen</strong>
                                    <div class="pair-code">8R2K</div>
                                    <p>Open the player, enter the code, and assign content.</p>
                                </article>
                            </div>

                            <div class="compatibility-strip">
                                <span>Smart TVs</span>
                                <span>Tablets</span>
                                <span>PCs</span>
                                <span>Fire Stick browsers</span>
                                <span>Kiosk browsers</span>
                                <span>Raspberry Pi</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="trust-strip section">
                <div class="container trust-wrap reveal">
                    <p>Built for businesses that want a straightforward way to turn screens into revenue, promotions and clearer customer communication.</p>
                    <div class="trust-items" aria-label="Industries and device compatibility">
                        <span>Restaurants & cafes</span>
                        <span>Gyms & martial arts schools</span>
                        <span>Retail stores</span>
                        <span>Events & entertainment</span>
                        <span>Salons, clinics & receptions</span>
                        <span>Works on any modern browser</span>
                    </div>
                </div>
            </section>

            <section class="section" id="how-it-works">
                <div class="container">
                    <div class="section-heading reveal">
                        <span class="section-kicker">How it works</span>
                        <h2>From blank screen to live content in three simple steps</h2>
                        <p>DisplayFlow is designed to be easy from day one. No complicated install process. No heavy setup. No need to replace screens you already own.</p>
                    </div>

                    <div class="steps-grid">
                        <article class="step-card reveal">
                            <span class="step-number">01</span>
                            <h3>Open the player on any screen</h3>
                            <p>Use a smart TV browser, tablet, mini PC, Fire Stick browser, kiosk browser or another compatible device already in your business.</p>
                        </article>
                        <article class="step-card reveal reveal-delay-1">
                            <span class="step-number">02</span>
                            <h3>Pair the screen with your account</h3>
                            <p>Add a screen in DisplayFlow, enter the pairing code, and name it by location so your setup stays organised as you grow.</p>
                        </article>
                        <article class="step-card reveal reveal-delay-2">
                            <span class="step-number">03</span>
                            <h3>Push content live instantly</h3>
                            <p>Update menus, promotions, announcements and media in seconds, then schedule content changes whenever you need them.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="section" id="features">
                <div class="container">
                    <div class="section-heading reveal">
                        <span class="section-kicker">Key features</span>
                        <h2>Everything you need to run modern digital displays</h2>
                        <p>Built to save time, keep content current and make your business look sharper across every screen.</p>
                    </div>

                    <div class="features-grid">
                        <article class="feature-card reveal">
                            <h3>Remote screen control</h3>
                            <p>Manage one screen or hundreds from anywhere through a browser.</p>
                        </article>
                        <article class="feature-card reveal reveal-delay-1">
                            <h3>Browser-based playback</h3>
                            <p>No special hardware required. Run content on the screens and devices you already have.</p>
                        </article>
                        <article class="feature-card reveal reveal-delay-2">
                            <h3>Fast screen pairing</h3>
                            <p>Add new screens quickly with a simple pairing flow that keeps setup straightforward.</p>
                        </article>
                        <article class="feature-card reveal">
                            <h3>Menus, promos and announcements</h3>
                            <p>Publish digital menus, offers, welcome screens, notices, slides, images and video content.</p>
                        </article>
                        <article class="feature-card reveal reveal-delay-1">
                            <h3>Scheduling</h3>
                            <p>Show the right content at the right time for breakfast, lunch, events, classes or peak hours.</p>
                        </article>
                        <article class="feature-card reveal reveal-delay-2">
                            <h3>Multi-screen management</h3>
                            <p>Organise screens by location, purpose or audience and keep them all in sync.</p>
                        </article>
                        <article class="feature-card reveal">
                            <h3>Real-time updates</h3>
                            <p>Change promotions or announcements in seconds instead of reprinting or visiting each site.</p>
                        </article>
                        <article class="feature-card reveal reveal-delay-1">
                            <h3>Optional hardware</h3>
                            <p>Prefer a ready-to-go device? Add plug-and-play hardware when you want a simpler rollout.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="section" id="industries">
                <div class="container">
                    <div class="section-heading reveal">
                        <span class="section-kicker">Use cases</span>
                        <h2>Designed for businesses that need information on screen, fast</h2>
                        <p>DisplayFlow works wherever you need content to look polished, stay current and be easy to update.</p>
                    </div>

                    <div class="industry-grid">
                        <article class="industry-card reveal">
                            <h3>Restaurants & cafes</h3>
                            <p>Keep menu boards accurate, run time-based offers, highlight best sellers and update pricing without reprinting.</p>
                        </article>
                        <article class="industry-card reveal reveal-delay-1">
                            <h3>Martial arts schools & gyms</h3>
                            <p>Promote memberships, class schedules, beginner offers, event reminders and sponsor content on reception displays.</p>
                        </article>
                        <article class="industry-card reveal reveal-delay-2">
                            <h3>Events & entertainment</h3>
                            <p>Show running orders, welcome screens, promotions, venue announcements and sponsor messaging across your site.</p>
                        </article>
                        <article class="industry-card reveal">
                            <h3>Retail stores</h3>
                            <p>Drive footfall and conversions with campaign screens, window promos, new product highlights and seasonal messaging.</p>
                        </article>
                        <article class="industry-card reveal reveal-delay-1">
                            <h3>Reception & waiting areas</h3>
                            <p>Create a more professional arrival experience with welcome messages, service updates, directions and queue information.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="section showcase-section" id="showcase">
                <div class="container">
                    <div class="section-heading reveal">
                        <span class="section-kicker">Product showcase</span>
                        <h2>Visuals that show the product before you even book a demo</h2>
                        <p>One clean control centre. Many screen types. Fast pairing. Clear scheduling. Professional on-screen presentation.</p>
                    </div>

                    <div class="showcase-grid">
                        <article class="showcase-panel showcase-dashboard reveal">
                            <div class="showcase-head">
                                <span class="kicker">Admin dashboard</span>
                                <h3>Control content across all screens</h3>
                            </div>
                            <div class="showcase-dashboard-ui">
                                <aside class="dashboard-sidebar">
                                    <span>Overview</span>
                                    <span class="active">Screens</span>
                                    <span>Content</span>
                                    <span>Schedules</span>
                                    <span>Templates</span>
                                </aside>
                                <div class="dashboard-main">
                                    <div class="dashboard-toolbar">
                                        <span class="status-pill status-live">All systems active</span>
                                        <span class="toolbar-chip">Update pushed</span>
                                    </div>
                                    <div class="dashboard-panels">
                                        <div class="ui-card">
                                            <span>Reception screen</span>
                                            <strong>Welcome & queue info</strong>
                                        </div>
                                        <div class="ui-card">
                                            <span>Menu board</span>
                                            <strong>Lunch specials now live</strong>
                                        </div>
                                        <div class="ui-card">
                                            <span>Promo screen</span>
                                            <strong>Weekend campaign queued</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article class="showcase-panel screen-examples reveal reveal-delay-1">
                            <div class="showcase-head">
                                <span class="kicker">Example screens</span>
                                <h3>Menus, promos and announcements</h3>
                            </div>
                            <div class="screen-preview-grid">
                                <div class="preview-frame preview-menu">
                                    <span>Menu template</span>
                                    <strong>Lunch favourites</strong>
                                    <em>Fast to update. Clear to read.</em>
                                </div>
                                <div class="preview-frame preview-promo">
                                    <span>Promo template</span>
                                    <strong>20% off today</strong>
                                    <em>Push limited-time offers instantly.</em>
                                </div>
                                <div class="preview-frame preview-announce">
                                    <span>Announcement screen</span>
                                    <strong>Classes begin at 6pm</strong>
                                    <em>Keep customers informed without printing anything.</em>
                                </div>
                            </div>
                        </article>

                        <article class="showcase-panel pairing-panel reveal reveal-delay-2">
                            <div class="showcase-head">
                                <span class="kicker">Screen pairing</span>
                                <h3>Pair new displays in seconds</h3>
                            </div>
                            <div class="pairing-flow">
                                <div class="pairing-box">
                                    <span>On-screen code</span>
                                    <strong>4Q7M</strong>
                                </div>
                                <div class="pairing-steps">
                                    <div><span>1</span>Open the player</div>
                                    <div><span>2</span>Enter the code</div>
                                    <div><span>3</span>Assign content</div>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section class="section benefits-section">
                <div class="container benefits-grid">
                    <div class="section-heading reveal">
                        <span class="section-kicker">Why businesses choose it</span>
                        <h2>Professional displays without the usual cost and hassle</h2>
                        <p>DisplayFlow helps businesses move away from printed signs, USB updates and clunky screen setups.</p>
                    </div>

                    <div class="benefit-comparison reveal reveal-delay-1">
                        <div class="comparison-card comparison-before">
                            <span class="comparison-label">Before</span>
                            <ul>
                                <li>Printed menus go out of date</li>
                                <li>Promotions are slow to update</li>
                                <li>Different locations show inconsistent content</li>
                                <li>Screen changes rely on manual visits</li>
                            </ul>
                        </div>
                        <div class="comparison-card comparison-after">
                            <span class="comparison-label">After</span>
                            <ul>
                                <li>Content changes in seconds</li>
                                <li>Promotions stay current</li>
                                <li>Displays look more polished</li>
                                <li>Multiple sites are easier to manage</li>
                            </ul>
                        </div>
                    </div>

                    <div class="benefits-list reveal reveal-delay-2">
                        <div class="benefit-item">
                            <strong>Save time updating screens</strong>
                            <p>Make one change once and push it across every relevant display.</p>
                        </div>
                        <div class="benefit-item">
                            <strong>No printing needed</strong>
                            <p>Reduce waste and remove the friction of constantly replacing posters or menus.</p>
                        </div>
                        <div class="benefit-item">
                            <strong>Use screens you already own</strong>
                            <p>Start with the hardware already in your business and scale from there.</p>
                        </div>
                        <div class="benefit-item">
                            <strong>Look more professional</strong>
                            <p>Create a cleaner, more modern customer experience in every location.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section hardware-section">
                <div class="container hardware-grid">
                    <div class="section-heading reveal">
                        <span class="section-kicker">Optional hardware</span>
                        <h2>Use your existing screen hardware or choose a ready-to-go setup</h2>
                        <p>DisplayFlow is software-first. That means you can launch quickly on the equipment you already have and only add hardware when it makes sense.</p>
                    </div>

                    <div class="hardware-cards">
                        <article class="hardware-card reveal">
                            <h3>Software only</h3>
                            <p>Ideal when you already have compatible screens, tablets, TVs or mini PCs and want the fastest route to launch.</p>
                            <ul>
                                <li>Lower upfront cost</li>
                                <li>Works with existing screens</li>
                                <li>Fastest rollout</li>
                            </ul>
                        </article>
                        <article class="hardware-card hardware-card-accent reveal reveal-delay-1">
                            <h3>Plug & play device</h3>
                            <p>Prefer a simple setup? Choose an optional pre-configured device for easy installation and rollout.</p>
                            <ul>
                                <li>Ready-to-go hardware</li>
                                <li>Simple pairing and setup</li>
                                <li>Great for multi-site deployments</li>
                            </ul>
                        </article>
                    </div>
                </div>
            </section>

            <section class="section pricing-section" id="pricing">
                <div class="container">
                    <div class="section-heading reveal">
                        <span class="section-kicker">Pricing</span>
                        <h2>Clear options based on how quickly you want to launch</h2>
                        <p>Start with software only, choose a faster plug-and-play setup, or talk to us about a tailored rollout for multiple sites.</p>
                    </div>

                    <div class="pricing-grid">
                        <article class="pricing-card reveal">
                            <span class="pricing-tier">Software Only</span>
                            <h3>Best when you already have screens</h3>
                            <p>A straightforward per-screen subscription for businesses that want the leanest path to launch.</p>
                            <ul>
                                <li>Cloud-based screen management</li>
                                <li>Menus, promos and announcements</li>
                                <li>Remote updates and scheduling</li>
                            </ul>
                            <a class="button button-secondary" href="#contact">Get pricing</a>
                        </article>
                        <article class="pricing-card pricing-card-featured reveal reveal-delay-1">
                            <span class="pricing-tier">Plug & Play</span>
                            <h3>Best for the fastest rollout</h3>
                            <p>Software plus optional pre-configured hardware for businesses that want to get live with less setup work.</p>
                            <ul>
                                <li>Pre-configured hardware option</li>
                                <li>Faster deployment</li>
                                <li>Ideal for busy operators</li>
                            </ul>
                            <a class="button button-primary" href="#contact">Start with this option</a>
                        </article>
                        <article class="pricing-card reveal reveal-delay-2">
                            <span class="pricing-tier">Custom / Multi-site</span>
                            <h3>Best for larger estates</h3>
                            <p>Talk to us about templates, rollout support, installation options and account structure for multiple sites.</p>
                            <ul>
                                <li>Multi-location support</li>
                                <li>Custom onboarding options</li>
                                <li>Commercial packages available</li>
                            </ul>
                            <a class="button button-secondary" href="#contact">Speak to sales</a>
                        </article>
                    </div>
                </div>
            </section>

            <section class="section faq-section" id="faq">
                <div class="container">
                    <div class="section-heading reveal">
                        <span class="section-kicker">FAQ</span>
                        <h2>Answers to the questions most buyers ask first</h2>
                        <p>Clear, practical answers so you can quickly see whether DisplayFlow fits your business.</p>
                    </div>

                    <div class="faq-list">
                        <article class="faq-item reveal">
                            <button class="faq-question" type="button" aria-expanded="false">
                                <span>Does DisplayFlow need special hardware?</span>
                                <span class="faq-icon" aria-hidden="true"></span>
                            </button>
                            <div class="faq-answer">
                                <p>No. DisplayFlow is software-first and works in a modern browser, so you can often use screens and devices you already own. Hardware is optional, not required.</p>
                            </div>
                        </article>
                        <article class="faq-item reveal reveal-delay-1">
                            <button class="faq-question" type="button" aria-expanded="false">
                                <span>Will it work on my TV?</span>
                                <span class="faq-icon" aria-hidden="true"></span>
                            </button>
                            <div class="faq-answer">
                                <p>If your setup can run a modern browser, there is a good chance it can run DisplayFlow. It is suitable for smart TVs, tablets, PCs, kiosk browsers, Fire Stick browsers and more.</p>
                            </div>
                        </article>
                        <article class="faq-item reveal reveal-delay-2">
                            <button class="faq-question" type="button" aria-expanded="false">
                                <span>Can I manage multiple screens?</span>
                                <span class="faq-icon" aria-hidden="true"></span>
                            </button>
                            <div class="faq-answer">
                                <p>Yes. DisplayFlow is built for single-screen setups and multi-screen estates, including screens across different locations.</p>
                            </div>
                        </article>
                        <article class="faq-item reveal">
                            <button class="faq-question" type="button" aria-expanded="false">
                                <span>Can I show menus and promotions?</span>
                                <span class="faq-icon" aria-hidden="true"></span>
                            </button>
                            <div class="faq-answer">
                                <p>Yes. DisplayFlow is ideal for digital menus, promotions, announcements, welcome screens, event notices and general media playback.</p>
                            </div>
                        </article>
                        <article class="faq-item reveal reveal-delay-1">
                            <button class="faq-question" type="button" aria-expanded="false">
                                <span>What if a screen goes offline?</span>
                                <span class="faq-icon" aria-hidden="true"></span>
                            </button>
                            <div class="faq-answer">
                                <p>You can monitor screen status from the platform, making it easier to spot issues quickly and keep your display network running smoothly.</p>
                            </div>
                        </article>
                        <article class="faq-item reveal reveal-delay-2">
                            <button class="faq-question" type="button" aria-expanded="false">
                                <span>Can you provide hardware if I need it?</span>
                                <span class="faq-icon" aria-hidden="true"></span>
                            </button>
                            <div class="faq-answer">
                                <p>Yes. Optional plug-and-play hardware can be offered for businesses that want a simpler installation path.</p>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section class="section final-cta-section" id="contact">
                <div class="container final-cta-grid">
                    <div class="final-cta-copy reveal">
                        <span class="section-kicker">Get started</span>
                        <h2>Get the right setup for your screens</h2>
                        <p>Tell us what screens you have, how many locations you run, and what you want to show. We’ll point you to the best-fit option and next step to launch.</p>

                        <div class="cta-points">
                            <div>
                                <strong>Designed to make buying simple</strong>
                                <p>We help you choose the quickest path based on your screens, sites and setup needs.</p>
                            </div>
                            <div>
                                <strong>Built for growth after the first screen</strong>
                                <p>Start small, prove it works, then expand without changing platform.</p>
                            </div>
                        </div>
                    </div>

                    <div class="contact-card reveal reveal-delay-1">
                        <form class="demo-form" data-demo-form data-demo-email="<?= e($demoEmail) ?>">
                            <div class="form-row">
                                <label for="demo_name">Name</label>
                                <input id="demo_name" name="name" type="text" placeholder="Your name" required>
                            </div>
                            <div class="form-row">
                                <label for="demo_business">Business</label>
                                <input id="demo_business" name="business" type="text" placeholder="Business name" required>
                            </div>
                            <div class="form-row">
                                <label for="demo_email">Email</label>
                                <input id="demo_email" name="email" type="email" placeholder="you@example.com" required>
                            </div>
                            <div class="form-row">
                                <label for="demo_use_case">Use case</label>
                                <select id="demo_use_case" name="use_case" required>
                                    <option value="">Select your business type</option>
                                    <option>Restaurant, takeaway, cafe or bar</option>
                                    <option>Gym or martial arts school</option>
                                    <option>Event venue or entertainment business</option>
                                    <option>Retail store</option>
                                    <option>Reception, salon, clinic or waiting area</option>
                                    <option>Other</option>
                                </select>
                            </div>
                            <div class="form-row">
                                <label for="demo_message">What do you want to show on screen?</label>
                                <textarea id="demo_message" name="message" rows="4" placeholder="Tell us about your screens, locations, and what you want to display."></textarea>
                            </div>
                            <button class="button button-primary button-block" type="submit">Get Pricing and Demo</button>
                            <p class="form-note">This opens your email app to send your enquiry to <a href="mailto:<?= e($demoEmail) ?>"><?= e($demoEmail) ?></a>.</p>
                        </form>
                    </div>
                </div>
            </section>
        </main>

        <footer class="site-footer">
            <div class="container footer-grid">
                <div>
                    <a class="brand footer-brand" href="#top">
                        <span class="brand-mark">DF</span>
                        <span class="brand-copy">
                            <strong><?= e($siteName) ?></strong>
                            <span>Smart digital displays made simple.</span>
                        </span>
                    </a>
                    <p class="footer-copy">Cloud-based digital signage software for businesses that want modern screens without the usual complexity.</p>
                </div>

                <div>
                    <h3>Explore</h3>
                    <ul class="footer-links">
                        <li><a href="#features">Features</a></li>
                        <li><a href="#industries">Industries</a></li>
                        <li><a href="#pricing">Pricing</a></li>
                        <li><a href="#faq">FAQ</a></li>
                    </ul>
                </div>

                <div>
                    <h3>Get started</h3>
                    <ul class="footer-links">
                        <li><a href="#contact">Book a Demo</a></li>
                        <li><a href="mailto:<?= e($demoEmail) ?>"><?= e($demoEmail) ?></a></li>
                        <li><a href="<?= e($adminUrl) ?>">Customer Login</a></li>
                    </ul>
                </div>
            </div>
        </footer>
    </div>

    <script src="<?= e($scriptUrl) ?>" defer></script>
</body>
</html>

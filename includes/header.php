<?php
/**
 * @var string $pageTitle set by the including page before requiring this file
 * @var string|null $pageDescription optional per-page meta description (falls back to a site default)
 * @var string|null $pageImage optional absolute/relative URL used for OG/Twitter image
 */
$user = current_user();
$description = $pageDescription ?? 'Browse open positions and find your next role with ' . SITE_NAME . ', South Africa\'s trusted personnel and labour hiring specialists.';
$ogImage = isset($pageImage) ? (str_starts_with($pageImage, 'http') ? $pageImage : base_url($pageImage)) : base_url('assets/img/social-share.png');
$canonical = base_url(ltrim($_SERVER['REQUEST_URI'] ?? '', '/'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php if ((defined('GA4_MEASUREMENT_ID') && GA4_MEASUREMENT_ID !== '') || (defined('GOOGLE_ADS_CONVERSION_ID') && GOOGLE_ADS_CONVERSION_ID !== '')): ?>
    <!-- Google Consent Mode v2 — must be set before any Google tag loads.
         Starts every page load fully denied; assets/js/cookie-consent.js
         updates this the instant it reads the visitor's stored choice (or
         a fresh choice from the banner). analytics_storage maps to the
         "Analytics" toggle; ad_storage/ad_user_data/ad_personalization map
         to the "Advertising" toggle (see includes/footer.php). -->
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('consent', 'default', {
            'ad_storage': 'denied',
            'ad_user_data': 'denied',
            'ad_personalization': 'denied',
            'analytics_storage': 'denied',
            'wait_for_update': 500
        });
    </script>
    <?php if (defined('GA4_MEASUREMENT_ID') && GA4_MEASUREMENT_ID !== ''): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= h(GA4_MEASUREMENT_ID) ?>"></script>
    <script>
        gtag('js', new Date());
        gtag('config', <?= json_encode(GA4_MEASUREMENT_ID) ?>);
    </script>
    <?php endif; ?>
    <?php if (defined('GOOGLE_ADS_CONVERSION_ID') && GOOGLE_ADS_CONVERSION_ID !== ''): ?>
    <script<?php if (!defined('GA4_MEASUREMENT_ID') || GA4_MEASUREMENT_ID === ''): ?> async src="https://www.googletagmanager.com/gtag/js?id=<?= h(GOOGLE_ADS_CONVERSION_ID) ?>"><?php endif; ?></script>
    <script>
        gtag('config', <?= json_encode(GOOGLE_ADS_CONVERSION_ID) ?>);
    </script>
    <!-- Enhanced Conversions (real name/email/phone/address matching) is
         deliberately NOT wired up yet — it needs a specific conversion
         action (e.g. "job application submitted") and real per-page user
         variables, which don't exist until a live Ads campaign defines
         what counts as a conversion. When that's ready: call
         gtag('set', 'user_data', {...}) with unhashed values (Google
         hashes them) only on the relevant conversion page, only after
         confirming the visitor granted "Advertising" consent. -->
    <?php endif; ?>
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle ?? SITE_NAME) ?></title>
    <meta name="description" content="<?= h($description) ?>">
    <link rel="canonical" href="<?= h($canonical) ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="en_ZA">
    <meta property="og:site_name" content="<?= h(SITE_NAME) ?>">
    <meta property="og:title" content="<?= h($pageTitle ?? SITE_NAME) ?>">
    <meta property="og:description" content="<?= h($description) ?>">
    <meta property="og:url" content="<?= h($canonical) ?>">
    <meta property="og:image" content="<?= h($ogImage) ?>">
    <?php if (!isset($pageImage)): ?>
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= h($pageTitle ?? SITE_NAME) ?>">
    <meta name="twitter:description" content="<?= h($description) ?>">
    <meta name="twitter:image" content="<?= h($ogImage) ?>">
    <meta name="theme-color" content="#0a1f44">
    <link rel="manifest" href="<?= h(base_url('manifest.json')) ?>">
    <!-- Root-level favicon.ico covers browsers/OS contexts that request it
         directly regardless of these <link> tags (e.g. a bookmark/shortcut
         icon lookup before the page's own HTML has loaded). -->
    <link rel="shortcut icon" href="<?= h(base_url('favicon.ico')) ?>">
    <link rel="icon" type="image/png" href="<?= h(base_url('assets/img/favicon.png')) ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= h(base_url('assets/img/icon-192.png')) ?>">
    <link rel="apple-touch-icon" href="<?= h(base_url('assets/img/icon-192.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php $styleVersion = @filemtime(__DIR__ . '/../assets/css/style.css') ?: time(); ?>
    <link rel="stylesheet" href="<?= h(base_url('assets/css/style.css')) ?>?v=<?= $styleVersion ?>">
    <?php
    $orgSameAs = [];
    foreach (array_keys(social_platforms()) as $platformKey) {
        $platformUrl = get_site_setting('social_' . $platformKey);
        if ($platformUrl !== '') {
            $orgSameAs[] = $platformUrl;
        }
    }
    ?>
    <script type="application/ld+json"><?= json_encode(array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => SITE_NAME,
        'legalName' => COMPANY_LEGAL_NAME,
        'url' => SITE_URL,
        'logo' => base_url('assets/img/logo.png'),
        'sameAs' => $orgSameAs ?: null,
    ]), JSON_UNESCAPED_SLASHES) ?></script>
    <script type="application/ld+json"><?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => SITE_NAME,
        'url' => SITE_URL,
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => rtrim(SITE_URL, '/') . '/jobs.php?q={search_term_string}',
            'query-input' => 'required name=search_term_string',
        ],
    ], JSON_UNESCAPED_SLASHES) ?></script>
    <?= $extraHead ?? '' ?>
</head>
<body>
<nav class="navbar navbar-dark rvz-navbar rvz-navbar-minimal">
    <div class="container d-flex align-items-center justify-content-between flex-nowrap">
        <a class="navbar-brand rvz-brandmark me-2" href="<?= h(base_url('index.php')) ?>">
            <img src="<?= h(base_url('assets/img/logo-mark-white.png')) ?>" alt="<?= h(SITE_NAME) ?>" height="38">
            <span class="word">Personnel Services<small>Labour Hiring Specialists</small></span>
        </a>
        <div class="d-flex align-items-center gap-2 flex-shrink-0">
            <?php if ($user): ?>
                <?php require __DIR__ . '/notifications_widget.php'; render_notification_bell($user); ?>
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?= h($user['first_name'] ?: 'Account') ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php if ($user['role'] === 'recruiter'): ?>
                            <li><a class="dropdown-item" href="<?= h(base_url('recruiter_profile.php')) ?>">My Profile</a></li>
                            <li><a class="dropdown-item" href="<?= h(base_url('company_branding.php')) ?>">Settings</a></li>
                            <li><a class="dropdown-item" href="<?= h(base_url('account_billing.php')) ?>">Account &amp; Billing</a></li>
                            <?php if (is_privileged_recruiter($user)): ?>
                                <li><a class="dropdown-item" href="<?= h(base_url('support_tickets.php')) ?>">Support Tickets</a></li>
                                <li><a class="dropdown-item" href="<?= h(base_url('admin_integrations.php')) ?>">Admin: Integrations</a></li>
                            <?php endif; ?>
                        <?php else: ?>
                            <li><a class="dropdown-item" href="<?= h(base_url('my_applications.php')) ?>">My Applications</a></li>
                            <li><a class="dropdown-item" href="<?= h(base_url('saved_jobs.php')) ?>">Saved Jobs</a></li>
                            <li><a class="dropdown-item" href="<?= h(base_url('profile.php')) ?>">Profile</a></li>
                            <li><a class="dropdown-item" href="<?= h(base_url('documents.php')) ?>">Documents</a></li>
                            <li><a class="dropdown-item" href="<?= h(base_url('my_cv.php')) ?>">My CV</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= h(base_url('become_recruiter.php')) ?>">I'm hiring &rarr;</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= h(base_url('logout.php')) ?>">Logout</a></li>
                    </ul>
                </div>
            <?php else: ?>
                <a class="nav-link rvz-for-employers d-none d-sm-inline-block" href="<?= h(base_url('become_recruiter.php')) ?>">For Employers</a>
                <a class="btn btn-sm btn-primary text-white" href="<?= h(base_url('login.php')) ?>">Login</a>
            <?php endif; ?>
            <button class="navbar-toggler rvz-menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                    aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span></span><span></span><span></span>
            </button>
        </div>
        <div class="collapse navbar-collapse rvz-menu-panel" id="mainNav">
            <div class="rvz-menu-panel-label">Menu</div>
            <div class="navbar-nav ms-auto align-items-lg-center">
                <?php if (!$user || $user['role'] !== 'recruiter'): ?>
                    <a class="nav-link" href="<?= h(base_url('index.php')) ?>">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/></svg>
                        Home
                    </a>
                    <a class="nav-link" href="<?= h(base_url('jobs.php')) ?>">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        Browse Jobs
                    </a>
                    <a class="nav-link" href="<?= h(base_url('pricing.php')) ?>">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        Pricing
                    </a>
                    <a class="nav-link" href="<?= h(base_url('blog.php')) ?>">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                        Blog
                    </a>
                <?php endif; ?>
                <?php if ($user && $user['role'] === 'recruiter'): ?>
                    <a class="nav-link" href="<?= h(base_url('dashboard.php')) ?>">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
                        Dashboard
                    </a>
                    <a class="nav-link" href="<?= h(base_url('recruiter_search.php')) ?>">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                        Direct Search
                    </a>
                    <a class="nav-link" href="<?= h(base_url('pricing.php')) ?>">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        Pricing
                    </a>
                    <hr class="rvz-menu-divider">
                    <a class="nav-link btn btn-sm btn-primary text-white mx-lg-2 my-1 my-lg-0" href="<?= h(base_url('job_create.php')) ?>">+ Post a Job</a>
                    <?php if (!has_active_recruiter_subscription($user) && recruiter_total_credits_remaining((int) $user['id']) < 1): ?>
                        <a class="nav-link btn btn-sm btn-outline-light mx-lg-2 my-1 my-lg-0" href="<?= h(base_url('pricing.php')) ?>">Buy Credits</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<main class="container my-4">
    <?php
    // The homepage is the app's own landing point — there's nothing
    // meaningful to go "back" to from here, so the button is just noise.
    $isHomePage = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php';
    ?>
    <?php if (!$isHomePage): ?>
    <button type="button" class="btn btn-sm rvz-back-btn mb-3" data-fallback="<?= h(base_url('index.php')) ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Back
    </button>
    <?php endif; ?>
    <?php foreach (get_flashes() as $f): ?>
        <div class="alert alert-<?= h($f['type']) ?>"><?= h($f['message']) ?></div>
    <?php endforeach; ?>

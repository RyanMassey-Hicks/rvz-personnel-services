<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Cookie Policy — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-9">
<h1 class="mb-1">Cookie Policy</h1>
<p class="text-muted mb-4">Last updated: <?= date('F Y') ?></p>

<p>This Cookie Policy explains how <?= h(COMPANY_LEGAL_NAME) ?> uses cookies on this website, in line with
the Protection of Personal Information Act 4 of 2013 (POPIA).</p>

<h5 class="mt-4">1. What are cookies?</h5>
<p>Cookies are small text files stored on your device that help websites function and, where you consent,
help us understand how the site is used.</p>

<h5 class="mt-4">2. Cookies we use</h5>
<div class="table-responsive">
<table class="table">
    <thead><tr><th>Cookie</th><th>Purpose</th><th>Type</th></tr></thead>
    <tbody>
        <tr><td><code>PHPSESSID</code></td><td>Keeps you logged in and remembers form security tokens</td><td>Essential</td></tr>
        <tr><td><code>rvz_cookie_consent</code></td><td>Remembers your cookie/AI/analytics/advertising consent choices</td><td>Essential</td></tr>
        <tr><td><code>_ga</code>, <code>_ga_*</code></td><td>Google Analytics — counts visits and usage, only set if you enable "Analytics" in Cookie Preferences</td><td>Analytics (opt-in)</td></tr>
        <tr><td><code>_gcl_*</code></td><td>Google Ads conversion tracking, only set if you enable "Advertising" in Cookie Preferences</td><td>Advertising (opt-in)</td></tr>
    </tbody>
</table>
</div>
<p>Google Analytics is used strictly for usage statistics (e.g. page views) and only runs if you opt in via the
"Analytics" toggle in Cookie Preferences — it stays off by default, and Google's Consent Mode keeps it off until
you grant it.</p>
<p>The "Advertising" toggle governs Google Ads conversion tracking and, where we choose to enable it for a
specific campaign, Enhanced Conversions — which shares identifying information you've given us (such as your
email, phone number, name, or address) with Google in order to match ad clicks to outcomes like a job
application. This is off by default and only takes effect once you explicitly opt in; we do not currently run
any advertising campaign that uses it, but the choice is captured in advance so it's ready when we do.</p>
<p>The support chatbot's "AI Assistance" option is a separate, non-cookie consent choice stored in the same
<code>rvz_cookie_consent</code> preference: if enabled, your typed chat messages are sent to Google Gemini to
generate replies; if disabled (the default), the chatbot uses a built-in rule-based assistant instead and no
message data leaves our servers.</p>

<h5 class="mt-4">3. Managing cookies</h5>
<p>Essential cookies are required for the site to function (staying logged in, protecting forms) and cannot be
disabled without affecting how the Platform works. You can control Analytics, Advertising, and AI Assistance any
time via "Cookie Preferences" in the footer, or clear cookies at any time through your browser settings.</p>

<h5 class="mt-4">4. More information</h5>
<p>See our <a href="<?= h(base_url('privacy-policy.php')) ?>">Privacy Policy</a> for how we handle personal
information more broadly, or <a href="<?= h(base_url('contact-us.php')) ?>">contact us</a> with questions.</p>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>

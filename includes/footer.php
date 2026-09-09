</main>

<footer class="rvz-footer pt-3 pb-3">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 rvz-footer-top">
            <span class="rvz-brandmark" style="color:#fff;">
                <img src="<?= h(base_url('assets/img/logo-mark-white.png')) ?>" alt="<?= h(SITE_NAME) ?>" height="28">
                <span class="word" style="font-size:.92rem;">Personnel Services<small>Labour Hiring Specialists</small></span>
            </span>

            <form method="post" action="<?= h(base_url('subscribe_newsletter.php')) ?>" class="d-flex rvz-newsletter-form">
                <?= csrf_field() ?>
                <input type="email" name="email" required placeholder="Get job alerts by email" class="form-control form-control-sm" aria-label="Email address" style="min-width:200px;">
                <button type="submit" class="btn btn-sm btn-light">Subscribe</button>
            </form>

            <div class="rvz-social-icons">
                <?php foreach (social_platforms() as $key => $label):
                    $url = get_site_setting('social_' . $key);
                    if ($url === '') continue;
                ?>
                    <a href="<?= h($url) ?>" target="_blank" rel="noopener" aria-label="<?= h($label) ?>" title="<?= h($label) ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><?= social_icon_svg($key) ?></svg>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <hr class="my-2" style="border-color:rgba(255,255,255,.15);">

        <div class="rvz-footer-links small">
            <a href="<?= h(base_url('contact-us.php')) ?>">Contact Us</a>
            <a href="<?= h(base_url('become_recruiter.php')) ?>">For Employers</a>
            <a href="<?= h(base_url('values.php')) ?>">Values</a>
            <a href="<?= h(base_url('response-handling.php')) ?>">Response Handling</a>
            <a href="<?= h(base_url('career-interface.php')) ?>">Career Interface</a>
            <a href="<?= h(base_url('blog.php')) ?>">Recruiter Blog</a>
            <a href="<?= h(base_url('job-market-trends-report.php')) ?>">Job Market Trends Report</a>
            <a href="<?= h(base_url('privacy-policy.php')) ?>">Privacy Policy</a>
            <a href="<?= h(base_url('disclaimer.php')) ?>">Disclaimer</a>
            <a href="<?= h(base_url('terms-and-conditions.php')) ?>">Terms and Conditions</a>
            <a href="<?= h(base_url('paia.php')) ?>">PAIA Manual</a>
            <a href="<?= h(base_url('ai-policy.php')) ?>">AI Policy</a>
            <a href="<?= h(base_url('labour-relations-policy.php')) ?>">Labour Relations Policy</a>
            <a href="<?= h(base_url('employment-equity-policy.php')) ?>">Employment Equity Policy</a>
            <a href="<?= h(base_url('electronic-transactions-policy.php')) ?>">Electronic Transactions Policy</a>
            <a href="<?= h(base_url('rica-policy.php')) ?>">RICA Policy</a>
            <a href="<?= h(base_url('cookie-policy.php')) ?>">Cookie Policy</a>
            <a href="#" id="rvzCookiePrefsLink">Cookie Preferences</a>
            <a href="<?= h(base_url('copyright-notice.php')) ?>">Copyright Notice</a>
            <a href="<?= h(base_url('acceptable-use.php')) ?>">Acceptable Use</a>
        </div>

        <div class="d-flex flex-wrap justify-content-between gap-2 mt-2">
            <p class="rvz-legal-line mb-0"><?= h(COMPANY_LEGAL_NAME) ?> (Reg. <?= h(COMPANY_REG_NUMBER) ?>)</p>
            <p class="rvz-legal-line mb-0">&copy; <?= date('Y') ?> RVZ International Group (Pty) Ltd t/a RVZ Personnel Services &amp; Labour Hiring Specialists. All rights reserved.</p>
        </div>
    </div>
</footer>

<div class="rvz-cookie-banner" id="rvzCookieBanner" role="dialog" aria-live="polite" aria-label="Cookie and AI consent">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <p class="mb-0 small" style="max-width:60ch;">
                We use cookies to run this site and, with your consent, to let our support chatbot use AI and to
                understand site usage via Google Analytics. See our
                <a href="<?= h(base_url('cookie-policy.php')) ?>">Cookie Policy</a> for details — this notice is provided in line with
                South Africa's Protection of Personal Information Act (POPIA).
            </p>
            <div class="d-flex gap-2 flex-shrink-0 flex-wrap">
                <button type="button" class="btn btn-outline-light btn-sm" id="rvzCookieManage">Manage Preferences</button>
                <button type="button" class="btn btn-outline-light btn-sm" id="rvzCookieReject">Reject Non-Essential</button>
                <button type="button" class="btn btn-light btn-sm" id="rvzCookieAccept">Accept All</button>
            </div>
        </div>
        <div class="rvz-cookie-prefs" id="rvzCookiePrefs">
            <div class="rvz-cookie-pref-row">
                <div>
                    <strong>Necessary</strong>
                    <p class="mb-0 small text-muted">Keeps you logged in and protects forms (CSRF). Always on — the site can't function without these.</p>
                </div>
                <input type="checkbox" checked disabled aria-label="Necessary (always on)">
            </div>
            <div class="rvz-cookie-pref-row">
                <div>
                    <strong>AI Assistance</strong>
                    <p class="mb-0 small text-muted">Lets our support chatbot send your typed messages to Google Gemini for smarter, personalised replies. If off, you'll still get help from our built-in assistant — no message data leaves our servers.</p>
                </div>
                <input type="checkbox" id="rvzCookiePrefAi" aria-label="AI Assistance">
            </div>
            <div class="rvz-cookie-pref-row">
                <div>
                    <strong>Analytics</strong>
                    <p class="mb-0 small text-muted">Lets Google Analytics count visits and see how the site is used, so we can improve it. No advertising or remarketing — usage stats only.</p>
                </div>
                <input type="checkbox" id="rvzCookiePrefAnalytics" aria-label="Analytics">
            </div>
            <div class="rvz-cookie-pref-row">
                <div>
                    <strong>Advertising</strong>
                    <p class="mb-0 small text-muted">Shares your email, phone number, name, and address with Google Ads for conversion tracking and remarketing. Off by default — only relevant once we run ad campaigns.</p>
                </div>
                <input type="checkbox" id="rvzCookiePrefAdvertising" aria-label="Advertising">
            </div>
            <div class="text-end mt-2">
                <button type="button" class="btn btn-light btn-sm" id="rvzCookieSavePrefs">Save Preferences</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<?php $jsVersion = @filemtime(__DIR__ . '/../assets/js/cookie-consent.js') ?: time(); ?>
<script src="<?= h(base_url('assets/js/cookie-consent.js')) ?>?v=<?= $jsVersion ?>"></script>
<?php $pwdJsVersion = @filemtime(__DIR__ . '/../assets/js/password-toggle.js') ?: time(); ?>
<script src="<?= h(base_url('assets/js/password-toggle.js')) ?>?v=<?= $pwdJsVersion ?>"></script>
<?php require __DIR__ . '/chatbot_widget.php'; render_chatbot_widget(); ?>
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('<?= h(base_url('service-worker.js')) ?>').catch(() => {});
    });
}
// The nav menu is now a floating dropdown panel, not an inline block — close
// it on an outside click/tap, same as any modern dropdown (Bootstrap's plain
// collapse component doesn't do this on its own).
(function () {
    var toggle = document.querySelector('.rvz-menu-toggle');
    var panel = document.getElementById('mainNav');
    if (!toggle || !panel) return;
    document.addEventListener('click', function (e) {
        if (!panel.classList.contains('show')) return;
        if (panel.contains(e.target) || toggle.contains(e.target)) return;
        bootstrap.Collapse.getOrCreateInstance(panel).hide();
    });
})();

// Back button: tracks the actual same-tab page order in sessionStorage
// instead of trusting document.referrer, which breaks for the common
// POST-redirect-GET pattern this app uses everywhere (a form submits to
// itself, then redirects — the referrer ends up pointing at whichever page
// last linked here, not necessarily where the visitor "came from" in any
// meaningful sense). This stack reflects the real path they clicked through.
(function () {
    var STORAGE_KEY = 'rvzNavStack';
    var here = window.location.pathname + window.location.search;
    var stack = [];
    try { stack = JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]'); } catch (e) { stack = []; }
    if (stack[stack.length - 1] !== here) {
        stack.push(here);
        if (stack.length > 30) stack = stack.slice(-30);
        try { sessionStorage.setItem(STORAGE_KEY, JSON.stringify(stack)); } catch (e) {}
    }

    document.querySelectorAll('.rvz-back-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var current = [];
            try { current = JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]'); } catch (e) {}
            current.pop(); // drop the page we're leaving
            var prev = current[current.length - 1];
            if (prev) {
                try { sessionStorage.setItem(STORAGE_KEY, JSON.stringify(current)); } catch (e) {}
                window.location = prev;
            } else {
                window.location = btn.dataset.fallback;
            }
        });
    });
})();
</script>
<?= $extraJs ?? '' ?>
</body>
</html>

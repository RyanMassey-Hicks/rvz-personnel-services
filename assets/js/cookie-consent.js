/**
 * POPIA-aligned consent banner — covers essential cookies plus three opt-in
 * choices: "AI Assistance" (whether the support chatbot may send typed
 * messages to Google Gemini — see ajax/chatbot.php, which reads this same
 * cookie server-side and never calls Gemini without consent), "Analytics"
 * (Google Analytics usage stats), and "Advertising" (Google Ads conversion
 * tracking/remarketing — currently no live campaign uses this, the toggle
 * exists so consent is already captured for when one exists). All three are
 * wired to Google's Consent Mode v2 — see the gtag('consent', 'default', ...)
 * block in includes/header.php, which denies every signal until this banner
 * grants it. Only essential cookies are ever set before a choice is made.
 */
(function () {
    var COOKIE_NAME = 'rvz_cookie_consent';
    var banner = document.getElementById('rvzCookieBanner');
    if (!banner) return;

    var prefsPanel = document.getElementById('rvzCookiePrefs');
    var aiToggle = document.getElementById('rvzCookiePrefAi');
    var analyticsToggle = document.getElementById('rvzCookiePrefAnalytics');
    var advertisingToggle = document.getElementById('rvzCookiePrefAdvertising');

    function getCookie(name) {
        var match = document.cookie.match('(?:^|; )' + name + '=([^;]*)');
        return match ? decodeURIComponent(match[1]) : null;
    }
    function setCookie(name, value, days) {
        var expires = new Date(Date.now() + days * 864e5).toUTCString();
        document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + expires + '; path=/; SameSite=Lax';
    }
    function getConsent() {
        var raw = getCookie(COOKIE_NAME);
        if (!raw) return null;
        try { return JSON.parse(raw); } catch (e) { return null; }
    }
    function updateGoogleConsent(analyticsGranted, advertisingGranted) {
        if (typeof window.gtag === 'function') {
            window.gtag('consent', 'update', {
                'analytics_storage': analyticsGranted ? 'granted' : 'denied',
                'ad_storage': advertisingGranted ? 'granted' : 'denied',
                'ad_user_data': advertisingGranted ? 'granted' : 'denied',
                'ad_personalization': advertisingGranted ? 'granted' : 'denied',
            });
        }
    }

    function recordChoice(choice, ai, analytics, advertising) {
        var consent = { necessary: true, ai: !!ai, analytics: !!analytics, advertising: !!advertising };
        setCookie(COOKIE_NAME, JSON.stringify(consent), 365);
        updateGoogleConsent(consent.analytics, consent.advertising);
        banner.classList.remove('is-visible');
        if (prefsPanel) prefsPanel.classList.remove('is-visible');
        try {
            var params = new URLSearchParams();
            params.set('choice', choice);
            params.set('ai', ai ? '1' : '0');
            params.set('analytics', analytics ? '1' : '0');
            params.set('advertising', advertising ? '1' : '0');
            navigator.sendBeacon
                ? navigator.sendBeacon('/ajax/consent.php', params)
                : fetch('/ajax/consent.php', { method: 'POST', body: params, keepalive: true });
        } catch (e) { /* non-essential — never block the banner on this */ }
    }

    var existing = getConsent();
    if (!existing) {
        banner.classList.add('is-visible');
    } else {
        if (aiToggle) aiToggle.checked = !!existing.ai;
        if (analyticsToggle) analyticsToggle.checked = !!existing.analytics;
        if (advertisingToggle) advertisingToggle.checked = !!existing.advertising;
        // A returning visitor's prior choice still needs to reach Google's
        // consent state on every fresh page load — the header.php default
        // starts every load fully denied until this runs.
        updateGoogleConsent(!!existing.analytics, !!existing.advertising);
    }

    var acceptBtn = document.getElementById('rvzCookieAccept');
    var rejectBtn = document.getElementById('rvzCookieReject');
    var manageBtn = document.getElementById('rvzCookieManage');
    var saveBtn = document.getElementById('rvzCookieSavePrefs');
    var prefsLink = document.getElementById('rvzCookiePrefsLink');

    if (acceptBtn) acceptBtn.addEventListener('click', function () { recordChoice('accepted', true, true, true); });
    if (rejectBtn) rejectBtn.addEventListener('click', function () { recordChoice('rejected', false, false, false); });
    if (manageBtn && prefsPanel) {
        manageBtn.addEventListener('click', function () {
            prefsPanel.classList.toggle('is-visible');
        });
    }
    if (saveBtn && aiToggle && analyticsToggle && advertisingToggle) {
        saveBtn.addEventListener('click', function () {
            recordChoice('custom', aiToggle.checked, analyticsToggle.checked, advertisingToggle.checked);
        });
    }
    if (prefsLink) {
        prefsLink.addEventListener('click', function (e) {
            e.preventDefault();
            banner.classList.add('is-visible');
            if (prefsPanel) prefsPanel.classList.add('is-visible');
        });
    }
})();

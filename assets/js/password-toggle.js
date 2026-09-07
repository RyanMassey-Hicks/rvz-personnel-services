/**
 * Adds a show/hide eye icon to every password field on the site,
 * automatically — no per-page markup needed, works on any password input
 * added now or later.
 */
(function () {
    var EYE_OPEN = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>';
    var EYE_CLOSED = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.24 4.24M9.36 5.11A11.6 11.6 0 0 1 12 5c7 0 11 7 11 7a13.5 13.5 0 0 1-3.15 3.9M6.5 6.5C3.7 8.2 2 11.5 2 11.5s.98 1.75 2.9 3.4"/></svg>';

    function wrapField(input) {
        if (input.dataset.rvzToggled) return;
        input.dataset.rvzToggled = '1';

        var wrapper = document.createElement('div');
        wrapper.style.position = 'relative';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        input.style.paddingRight = '2.5rem';

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.setAttribute('aria-label', 'Show password');
        btn.tabIndex = -1;
        btn.innerHTML = EYE_OPEN;
        btn.style.cssText = 'position:absolute;right:8px;top:50%;transform:translateY(-50%);border:none;background:none;padding:2px;color:#888;cursor:pointer;line-height:0;';
        wrapper.appendChild(btn);

        btn.addEventListener('click', function () {
            var showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            btn.innerHTML = showing ? EYE_OPEN : EYE_CLOSED;
            btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        });
    }

    function init() {
        document.querySelectorAll('input[type="password"]').forEach(wrapField);
    }

    if (document.readyState !== 'loading') init();
    else document.addEventListener('DOMContentLoaded', init);
})();

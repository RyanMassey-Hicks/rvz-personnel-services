/**
 * RVZ Personnel Services job board embed widget.
 *
 * Drop this on any external website to show live job
 * listings pulled from this ATS, with each job linking back here so people
 * apply on the main site (fully logged in — resumes, cover letters, the
 * applicant pipeline, all of it works normally).
 *
 * Usage:
 *   <script src="https://recruitment.rvzgroup.co.za/embed/widget.js" data-target="rvz-jobs" async></script>
 *   <div id="rvz-jobs"></div>
 *
 * Optional attributes on the <script> tag:
 *   data-target   id of the container div to render into (default "rvz-jobs")
 *   data-limit    max jobs to show (default 10)
 *   data-location   filter by location substring
 *   data-q          filter by keyword
 *   data-company-id restrict to one company's jobs (each recruiter's own snippet includes this automatically)
 */
(function () {
    var thisScript = document.currentScript;
    if (!thisScript) return;

    var apiBase = thisScript.src.replace(/embed\/widget\.js.*$/, 'api/jobs.php');
    var targetId = thisScript.getAttribute('data-target') || 'rvz-jobs';
    var limit = thisScript.getAttribute('data-limit') || '10';
    var locationFilter = thisScript.getAttribute('data-location') || '';
    var qFilter = thisScript.getAttribute('data-q') || '';
    var companyId = thisScript.getAttribute('data-company-id') || '';

    function ready(fn) {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    }

    function esc(str) {
        var div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    function formatZar(n) {
        return 'R' + Number(n).toLocaleString('en-ZA');
    }

    function renderJob(job) {
        var salary = '';
        if (job.salary_min) {
            salary = '<span class="ditto-job-salary">' + esc(formatZar(job.salary_min)) +
                (job.salary_max ? ' - ' + esc(formatZar(job.salary_max)) : '') + '</span>';
        }
        // The whole card is clickable, but an explicit Apply button is kept
        // too — a plain "card is a link" pattern is easy for an embedding
        // site's own CSS to accidentally neutralise (unset text-decoration,
        // a global "a { pointer-events: none }" reset, etc.), and a visible
        // button makes the call to action unambiguous regardless.
        return (
            '<div class="ditto-job-card">' +
            (job.logo_url ? '<img class="ditto-job-logo" src="' + esc(job.logo_url) + '" alt="">' : '') +
            '<div class="ditto-job-info">' +
            '<a class="ditto-job-title-link" href="' + esc(job.apply_url) + '" target="_blank" rel="noopener">' +
            '<div class="ditto-job-title">' + esc(job.title) + '</div></a>' +
            '<div class="ditto-job-meta">' + esc(job.company) + ' &middot; ' + esc(job.location) +
            (job.is_remote ? ' &middot; Remote' : '') + '</div>' +
            '<div class="ditto-job-tags"><span class="ditto-job-type">' + esc(job.employment_type) + '</span>' + salary + '</div>' +
            '</div>' +
            '<a class="ditto-job-apply-btn" href="' + esc(job.apply_url) + '" target="_blank" rel="noopener">Apply</a>' +
            '</div>'
        );
    }

    function injectStyles() {
        if (document.getElementById('ditto-jobs-style')) return;
        var style = document.createElement('style');
        style.id = 'ditto-jobs-style';
        style.textContent =
            '.ditto-jobs-list{display:flex;flex-direction:column;gap:12px;font-family:system-ui,-apple-system,sans-serif;}' +
            '.ditto-job-card{display:flex;gap:12px;align-items:center;padding:14px 16px;border:1px solid #e2e2e2;' +
            'border-radius:8px;background:#fff;transition:box-shadow .15s;}' +
            '.ditto-job-card:hover{box-shadow:0 2px 8px rgba(0,0,0,0.08);}' +
            '.ditto-job-logo{width:40px;height:40px;object-fit:contain;flex-shrink:0;}' +
            '.ditto-job-info{flex:1;min-width:0;}' +
            '.ditto-job-title-link{text-decoration:none;color:inherit;}' +
            '.ditto-job-title-link:hover .ditto-job-title{text-decoration:underline;}' +
            '.ditto-job-title{font-weight:600;font-size:1rem;}' +
            '.ditto-job-meta{color:#666;font-size:.875rem;margin-top:2px;}' +
            '.ditto-job-tags{margin-top:6px;display:flex;gap:8px;flex-wrap:wrap;font-size:.75rem;}' +
            '.ditto-job-type{background:#eee;border-radius:4px;padding:2px 8px;}' +
            '.ditto-job-salary{background:#e6f4ea;color:#1e6b34;border-radius:4px;padding:2px 8px;}' +
            '.ditto-job-apply-btn{flex-shrink:0;display:inline-block;padding:8px 18px;border-radius:6px;' +
            'background:#0a1f44;color:#fff;text-decoration:none;font-weight:600;font-size:.875rem;white-space:nowrap;' +
            'transition:background-color .15s;}' +
            '.ditto-job-apply-btn:hover{background:#14305f;color:#fff;}' +
            '@media (max-width:480px){.ditto-job-card{flex-wrap:wrap;}.ditto-job-apply-btn{width:100%;text-align:center;}}';
        document.head.appendChild(style);
    }

    ready(function () {
        var container = document.getElementById(targetId);
        if (!container) return;

        var params = new URLSearchParams();
        params.set('limit', limit);
        if (locationFilter) params.set('location', locationFilter);
        if (qFilter) params.set('q', qFilter);
        if (companyId) params.set('company_id', companyId);

        container.innerHTML = '<p style="font-family:system-ui,sans-serif;color:#888;">Loading open roles…</p>';

        fetch(apiBase + '?' + params.toString())
            .then(function (res) { return res.json(); })
            .then(function (data) {
                injectStyles();
                var jobs = data.jobs || [];
                if (data.error) {
                    container.innerHTML = '<p style="font-family:system-ui,sans-serif;color:#888;">' + esc(data.error) + '</p>';
                    return;
                }
                if (!jobs.length) {
                    container.innerHTML = '<p style="font-family:system-ui,sans-serif;color:#888;">No open roles right now — check back soon.</p>';
                    return;
                }
                container.innerHTML = '<div class="ditto-jobs-list">' + jobs.map(renderJob).join('') + '</div>';
            })
            .catch(function () {
                container.innerHTML = '<p style="font-family:system-ui,sans-serif;color:#c00;">Could not load jobs right now.</p>';
            });
    });
})();

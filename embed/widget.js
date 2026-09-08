/**
 * Ditto Hire job board embed widget.
 *
 * Drop this on any external website to show live job
 * listings pulled from this ATS, with each job linking back here so people
 * apply on the main site (fully logged in — resumes, cover letters, the
 * applicant pipeline, all of it works normally).
 *
 * Usage:
 *   <script src="https://www.nhestate.co.za/embed/widget.js" data-target="rvz-jobs" async></script>
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

    function renderJob(job) {
        var salary = '';
        if (job.salary_min) {
            salary = '<span class="ditto-job-salary">$' + esc(job.salary_min) +
                (job.salary_max ? ' - $' + esc(job.salary_max) : '') + '</span>';
        }
        return (
            '<a class="ditto-job-card" href="' + esc(job.apply_url) + '" target="_blank" rel="noopener">' +
            (job.logo_url ? '<img class="ditto-job-logo" src="' + esc(job.logo_url) + '" alt="">' : '') +
            '<div class="ditto-job-info">' +
            '<div class="ditto-job-title">' + esc(job.title) + '</div>' +
            '<div class="ditto-job-meta">' + esc(job.company) + ' &middot; ' + esc(job.location) +
            (job.is_remote ? ' &middot; Remote' : '') + '</div>' +
            '<div class="ditto-job-tags"><span class="ditto-job-type">' + esc(job.employment_type) + '</span>' + salary + '</div>' +
            '</div></a>'
        );
    }

    function injectStyles() {
        if (document.getElementById('ditto-jobs-style')) return;
        var style = document.createElement('style');
        style.id = 'ditto-jobs-style';
        style.textContent =
            '.ditto-jobs-list{display:flex;flex-direction:column;gap:12px;font-family:system-ui,-apple-system,sans-serif;}' +
            '.ditto-job-card{display:flex;gap:12px;align-items:flex-start;padding:14px 16px;border:1px solid #e2e2e2;' +
            'border-radius:8px;text-decoration:none;color:inherit;background:#fff;transition:box-shadow .15s;}' +
            '.ditto-job-card:hover{box-shadow:0 2px 8px rgba(0,0,0,0.08);}' +
            '.ditto-job-logo{width:40px;height:40px;object-fit:contain;flex-shrink:0;}' +
            '.ditto-job-title{font-weight:600;font-size:1rem;}' +
            '.ditto-job-meta{color:#666;font-size:.875rem;margin-top:2px;}' +
            '.ditto-job-tags{margin-top:6px;display:flex;gap:8px;flex-wrap:wrap;font-size:.75rem;}' +
            '.ditto-job-type{background:#eee;border-radius:4px;padding:2px 8px;}' +
            '.ditto-job-salary{background:#e6f4ea;color:#1e6b34;border-radius:4px;padding:2px 8px;}' +
            '@media (max-width:480px){.ditto-job-card{flex-direction:column;}}';
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

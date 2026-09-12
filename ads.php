<?php
/**
 * "Create a social ad" — AI-generated 1:1 social media graphic for a job.
 *
 * Generation runs as two separate AJAX requests (ajax/ad_plan.php, then
 * ajax/ad_render.php) rather than one synchronous POST. This host's web
 * server returns a 503 for any request over ~60s, and Gemini planning plus
 * a Pollinations render (routinely 40-45s) in a single request was hitting
 * that — the recruiter saw "Service Unavailable" with nothing generated.
 * Split in two, each request stays well inside the limit, and the page can
 * show real progress while it waits.
 */
require __DIR__ . '/includes/bootstrap.php';
require_active_recruiter();

$user = current_user();
$jobId = (int) ($_GET['job_id'] ?? 0);

$stmt = db()->prepare(
    'SELECT jobs.*, companies.name AS company_name, companies.ai_image_provider, companies.ai_brand_guidelines
     FROM jobs
     JOIN companies ON companies.id = jobs.company_id
     WHERE jobs.id = ? AND jobs.company_id = ?'
);
$stmt->execute([$jobId, current_recruiter_company_id()]);
$job = $stmt->fetch();
if (!$job) {
    http_response_code(404);
    die('Job not found, or you do not have permission to create ads for it.');
}

$stmt = db()->prepare('SELECT * FROM ad_generations WHERE job_id = ? ORDER BY created_at DESC');
$stmt->execute([$jobId]);
$previousAds = $stmt->fetchAll();

$pageTitle = 'Ads — ' . $job['title'];
require __DIR__ . '/includes/header.php';
?>
<h2 class="mb-1">Create a social ad</h2>
<p class="text-muted mb-1">for <strong><?= h($job['title']) ?></strong> — Gemini plans a caption and scene, then renders a ready-to-post 1:1 (1024&times;1024) image.</p>
<p class="text-muted small mb-4">
    Using: <?= h(ai_image_provider_label($job['ai_image_provider'] ?? 'free')) ?><?php if (!empty($job['ai_brand_guidelines'])): ?> · brand guidelines applied<?php endif; ?>
</p>

<div class="alert alert-danger" id="rvzAdError" hidden></div>

<form class="card mb-4" id="rvzAdForm">
    <div class="card-body">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="job_id" value="<?= (int) $jobId ?>">
        <div class="mb-3">
            <label class="form-label">Style</label>
            <select name="style" class="form-select">
                <option value="professional">Professional / corporate</option>
                <option value="bold and energetic">Bold and energetic</option>
                <option value="warm and friendly">Warm and friendly</option>
                <option value="minimalist">Minimalist</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" id="rvzAdSubmitBtn">Generate Ad</button>
        <div class="mt-3 small text-muted" id="rvzAdProgress" hidden>
            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
            <span id="rvzAdProgressText"></span>
        </div>
        <noscript><p class="small text-muted mt-2">JavaScript is required to generate ads.</p></noscript>
    </div>
</form>

<div class="card mb-4" id="rvzAdResult" hidden>
    <div class="card-body text-center">
        <img id="rvzAdImage" src="" alt="Generated ad for <?= h($job['title']) ?>" class="img-fluid rounded mb-3" style="max-width:420px;">
        <div class="text-start mx-auto mb-3" style="max-width:420px;" id="rvzAdCopyWrap" hidden>
            <label class="form-label small text-muted mb-1">Suggested caption (AI-written)</label>
            <textarea class="form-control form-control-sm" id="rvzAdCopyText" rows="3" readonly></textarea>
            <button type="button" class="btn btn-link btn-sm p-0 mt-1" id="rvzCopyAdCopyBtn">Copy caption</button>
        </div>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
            <a id="rvzAdDownload" href="" download class="btn btn-outline-primary btn-sm">Download</a>
            <a href="<?= h(base_url('share.php?id=' . $jobId . '&platform=linkedin')) ?>" target="_blank" class="btn btn-outline-primary btn-sm">Share on LinkedIn</a>
            <a href="<?= h(base_url('share.php?id=' . $jobId . '&platform=facebook')) ?>" target="_blank" class="btn btn-outline-primary btn-sm">Share on Facebook</a>
        </div>
    </div>
</div>

<?php if ($previousAds): ?>
    <h5 class="mb-3">Previously generated</h5>
    <div class="row g-3">
        <?php foreach ($previousAds as $ad): ?>
            <div class="col-md-3 col-6">
                <img src="<?= h(UPLOAD_URL . $ad['image_path']) ?>" class="img-fluid rounded shadow-sm" alt="Ad generated <?= h($ad['created_at']) ?>">
                <a href="<?= h(UPLOAD_URL . $ad['image_path']) ?>" download class="btn btn-sm btn-link p-0 mt-1 d-block">Download</a>
                <?php if (!empty($ad['copy_text'])): ?>
                    <details class="small mt-1">
                        <summary class="text-muted" style="cursor:pointer;">Caption</summary>
                        <p class="mb-0"><?= h($ad['copy_text']) ?></p>
                    </details>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
(function () {
    var form = document.getElementById('rvzAdForm');
    var btn = document.getElementById('rvzAdSubmitBtn');
    var progress = document.getElementById('rvzAdProgress');
    var progressText = document.getElementById('rvzAdProgressText');
    var errorBox = document.getElementById('rvzAdError');
    var result = document.getElementById('rvzAdResult');
    var planUrl = <?= json_encode(base_url('ajax/ad_plan.php')) ?>;
    var renderUrl = <?= json_encode(base_url('ajax/ad_render.php')) ?>;

    function setBusy(busy, text) {
        btn.disabled = busy;
        progress.hidden = !busy;
        progressText.textContent = text || '';
    }

    function showError(msg) {
        errorBox.textContent = msg;
        errorBox.hidden = false;
    }

    async function post(url, data) {
        var body = new URLSearchParams(data);
        var res = await fetch(url, { method: 'POST', body: body, credentials: 'same-origin' });
        var json = null;
        try { json = await res.json(); } catch (e) { /* non-JSON (e.g. a 503 page) handled below */ }
        if (!json) {
            throw new Error(res.status === 503
                ? 'The server took too long to respond. Please try again — the image service is slow right now.'
                : 'Unexpected server response (HTTP ' + res.status + '). Please try again.');
        }
        if (!json.ok) throw new Error(json.error || 'Something went wrong. Please try again.');
        return json;
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        errorBox.hidden = true;
        result.hidden = true;
        var fields = new FormData(form);
        var base = { csrf_token: fields.get('csrf_token'), job_id: fields.get('job_id') };

        try {
            setBusy(true, 'Step 1 of 2 — planning your caption and scene…');
            var plan = await post(planUrl, Object.assign({ style: fields.get('style') }, base));

            setBusy(true, 'Step 2 of 2 — rendering the image (this is the slow part, usually 20–45 seconds)…');
            var rendered = await post(renderUrl, Object.assign({ prompt: plan.prompt, copy: plan.copy || '' }, base));

            document.getElementById('rvzAdImage').src = rendered.image_url;
            document.getElementById('rvzAdDownload').href = rendered.image_url;
            var copyWrap = document.getElementById('rvzAdCopyWrap');
            if (rendered.copy) {
                document.getElementById('rvzAdCopyText').value = rendered.copy;
                copyWrap.hidden = false;
            } else {
                copyWrap.hidden = true;
            }
            result.hidden = false;
            result.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } catch (err) {
            showError(err.message);
        } finally {
            setBusy(false);
        }
    });

    document.getElementById('rvzCopyAdCopyBtn').addEventListener('click', function () {
        var el = document.getElementById('rvzAdCopyText');
        el.select();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(el.value).then(function () {
                var b = document.getElementById('rvzCopyAdCopyBtn');
                var original = b.textContent;
                b.textContent = 'Copied!';
                setTimeout(function () { b.textContent = original; }, 1500);
            });
        }
    });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>

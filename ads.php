<?php
require __DIR__ . '/includes/bootstrap.php';
require_active_recruiter();

$user = current_user();
$jobId = (int) ($_GET['job_id'] ?? $_POST['job_id'] ?? 0);

$stmt = db()->prepare(
    'SELECT jobs.*, companies.name AS company_name, companies.ai_image_provider,
            companies.ai_image_api_key_encrypted, companies.ai_brand_guidelines
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

$error = null;
$generated = null;
$generatedCopy = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $style = trim($_POST['style'] ?? 'professional') ?: 'professional';
    $brandGuidelines = $job['ai_brand_guidelines'] ?? '';

    // Two free AI systems working together: Gemini (text) plans a caption
    // and a vivid scene description; the image provider (Pollinations by
    // default) renders that scene. If Gemini's unavailable for any reason,
    // fall back to the plain templated prompt — image generation still works.
    $adCopy = null;
    try {
        $content = gemini_generate_ad_content($job['title'], $job['company_name'], $job['location'], $style, $brandGuidelines);
        $adCopy = $content['copy'];
        $prompt = build_ad_prompt_from_scene($content['scene'], $job['title'], $job['company_name'], $style);
    } catch (AiImageException $e) {
        error_log('Gemini ad-content fallback: ' . $e->getMessage());
        $prompt = build_ad_prompt($job, $job['company_name'], $style, $brandGuidelines);
    }

    try {
        $result = ai_generate_image_for_company($prompt, $job);
        $ext = $result['mime'] === 'image/jpeg' ? 'jpg' : 'png';
        $filename = 'ad_' . $jobId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $destDir = UPLOAD_DIR . 'ads/';
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }
        file_put_contents($destDir . $filename, $result['bytes']);

        db()->prepare('INSERT INTO ad_generations (job_id, user_id, prompt, image_path, provider, copy_text) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$jobId, $user['id'], $prompt, 'ads/' . $filename, $result['provider'], $adCopy]);

        $generated = 'ads/' . $filename;
        $generatedCopy = $adCopy;
    } catch (AiImageException $e) {
        $error = $e->getMessage();
    }
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

<?php if ($error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<form method="post" class="card mb-4"><div class="card-body">
    <?= csrf_field() ?>
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
    <button type="submit" class="btn btn-primary">Generate Ad</button>
</div></form>

<?php if ($generated): ?>
    <div class="card mb-4"><div class="card-body text-center">
        <img src="<?= h(UPLOAD_URL . $generated) ?>" alt="Generated ad for <?= h($job['title']) ?>" class="img-fluid rounded mb-3" style="max-width:420px;">
        <?php if ($generatedCopy): ?>
            <div class="text-start mx-auto mb-3" style="max-width:420px;">
                <label class="form-label small text-muted mb-1">Suggested caption (AI-written)</label>
                <textarea class="form-control form-control-sm" id="rvzAdCopyText" rows="3" readonly><?= h($generatedCopy) ?></textarea>
                <button type="button" class="btn btn-link btn-sm p-0 mt-1" id="rvzCopyAdCopyBtn">Copy caption</button>
            </div>
        <?php endif; ?>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
            <a href="<?= h(UPLOAD_URL . $generated) ?>" download class="btn btn-outline-primary btn-sm">Download</a>
            <a href="<?= h(base_url('share.php?id=' . $jobId . '&platform=linkedin')) ?>" target="_blank" class="btn btn-outline-primary btn-sm">Share on LinkedIn</a>
            <a href="<?= h(base_url('share.php?id=' . $jobId . '&platform=facebook')) ?>" target="_blank" class="btn btn-outline-primary btn-sm">Share on Facebook</a>
        </div>
    </div></div>
    <?php if ($generatedCopy): ?>
    <script>
        document.getElementById('rvzCopyAdCopyBtn').addEventListener('click', function () {
            var el = document.getElementById('rvzAdCopyText');
            el.select();
            navigator.clipboard && navigator.clipboard.writeText(el.value).then(function () {
                var btn = document.getElementById('rvzCopyAdCopyBtn');
                var original = btn.textContent;
                btn.textContent = 'Copied!';
                setTimeout(function () { btn.textContent = original; }, 1500);
            });
        });
    </script>
    <?php endif; ?>
<?php endif; ?>

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

<?php require __DIR__ . '/includes/footer.php'; ?>

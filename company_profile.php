<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/job_card.php';

$companyId = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM companies WHERE id = ?');
$stmt->execute([$companyId]);
$company = $stmt->fetch();

if (!$company) {
    http_response_code(404);
    die('Company not found.');
}

$stmt = db()->prepare(
    'SELECT jobs.*, companies.name AS company_name, companies.logo_path AS company_logo, industries.name AS industry_name
     FROM jobs
     LEFT JOIN companies ON companies.id = jobs.company_id
     LEFT JOIN industries ON industries.id = jobs.industry_id
     WHERE jobs.company_id = ? AND jobs.is_open = 1
     ORDER BY jobs.created_at DESC'
);
$stmt->execute([$companyId]);
$openJobs = $stmt->fetchAll();

$stmt = db()->prepare('SELECT COUNT(*) AS c FROM jobs WHERE company_id = ? AND is_open = 0');
$stmt->execute([$companyId]);
$closedCount = (int) $stmt->fetch()['c'];

$stmt = db()->prepare('SELECT photo_path FROM company_photos WHERE company_id = ? ORDER BY uploaded_at DESC');
$stmt->execute([$companyId]);
$photos = $stmt->fetchAll();

$user = current_user();
$savedJobIds = [];
if ($user && $user['role'] !== 'recruiter') {
    $stmt = db()->prepare('SELECT job_id FROM saved_jobs WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $savedJobIds = array_column($stmt->fetchAll(), 'job_id');
}

$brandColor = ($company['brand_color'] ?? '') && preg_match('/^#[0-9a-fA-F]{6}$/', $company['brand_color']) ? $company['brand_color'] : '#0a1f44';
$palette = array_values(array_filter([
    $company['brand_color'] ?? '',
    $company['brand_color_2'] ?? '',
    $company['brand_color_3'] ?? '',
    $company['brand_color_4'] ?? '',
], fn ($c) => $c && preg_match('/^#[0-9a-fA-F]{6}$/', $c)));
$companyInfoBits = array_filter([
    !empty($company['city']) ? '📍 ' . $company['city'] : '',
    !empty($company['contact_email']) ? '✉️ ' . $company['contact_email'] : '',
    !empty($company['contact_phone']) ? '📞 ' . $company['contact_phone'] : '',
]);

$pageTitle = $company['name'] . ' — Company Profile — ' . SITE_NAME;
$pageDescription = $company['brand_tagline'] ?: ('Open positions and company information for ' . $company['name'] . ' on ' . SITE_NAME . '.');
if (!empty($company['logo_path'])) {
    $pageImage = UPLOAD_URL . $company['logo_path'];
}
require __DIR__ . '/includes/header.php';
?>
<div class="rvz-company-hero mb-4" style="background:<?= h($brandColor) ?>;">
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <?php if (!empty($company['logo_path'])): ?>
            <img src="<?= h(UPLOAD_URL . $company['logo_path']) ?>" alt="<?= h($company['name']) ?>" style="max-height:72px;background:#fff;border-radius:8px;padding:6px;">
        <?php endif; ?>
        <div>
            <h1 class="mb-1 text-white"><?= h($company['name']) ?></h1>
            <?php if (!empty($company['brand_tagline'])): ?>
                <p class="mb-0 text-white-50 fst-italic"><?= h($company['brand_tagline']) ?></p>
            <?php endif; ?>
            <?php if (!empty($company['website'])): ?>
                <a href="<?= h($company['website']) ?>" target="_blank" rel="noopener" class="text-white small">🔗 <?= h($company['website']) ?></a>
            <?php endif; ?>
        </div>
    </div>
    <?php if (count($palette) > 1): ?>
        <div class="d-flex gap-1 mt-3">
            <?php foreach ($palette as $swatch): ?>
                <span style="display:inline-block;width:22px;height:22px;border-radius:4px;background:<?= h($swatch) ?>;border:1px solid rgba(255,255,255,.4);" title="<?= h($swatch) ?>"></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($company['description'])): ?>
    <div class="mb-4" style="white-space:pre-line;"><?= h($company['description']) ?></div>
<?php endif; ?>

<?php if ($companyInfoBits || !empty($company['registration_number'])): ?>
    <div class="d-flex flex-wrap gap-3 mb-3 text-muted small">
        <?php foreach ($companyInfoBits as $bit): ?><span><?= h($bit) ?></span><?php endforeach; ?>
        <?php if (!empty($company['registration_number'])): ?>
            <span>Reg. No. <?= h($company['registration_number']) ?></span>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="d-flex gap-4 mb-4 text-muted small">
    <span><strong><?= count($openJobs) ?></strong> open position<?= count($openJobs) === 1 ? '' : 's' ?></span>
    <span><strong><?= $closedCount ?></strong> positions filled to date</span>
</div>

<?php if ($photos): ?>
    <h5 class="mb-3">Gallery</h5>
    <div class="row g-2 mb-4">
        <?php foreach ($photos as $photo): ?>
            <div class="col-md-3 col-6">
                <img src="<?= h(UPLOAD_URL . $photo['photo_path']) ?>" class="img-fluid rounded" style="height:140px;width:100%;object-fit:cover;" alt="<?= h($company['name']) ?>">
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h5 class="mb-3">Open positions at <?= h($company['name']) ?></h5>
<?php if (!$openJobs): ?>
    <p class="text-muted">No open positions right now — check back soon.</p>
<?php endif; ?>
<?php foreach ($openJobs as $job): ?>
    <?php render_job_card($job, in_array($job['id'], $savedJobIds), $user === null || ($user && $user['role'] !== 'recruiter'), $user ? csrf_token() : null); ?>
<?php endforeach; ?>
<?php if ($user && $user['role'] !== 'recruiter') render_save_job_script(csrf_token()); ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

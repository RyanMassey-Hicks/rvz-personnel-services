<?php
/**
 * Standalone, chrome-free careers listing meant to be embedded via <iframe>
 * on another website. No header/nav/footer — just the
 * job list, styled to sit inside someone else's page. Each job links back to
 * the main site (target="_top" so it breaks out of the iframe) so applying
 * happens fully logged in on this domain.
 *
 * Usage on the external site:
 *   <iframe src="https://recruitment.rvzgroup.co.za/embed/careers.php"
 *           style="width:100%;border:0;min-height:800px;" loading="lazy"></iframe>
 */
require __DIR__ . '/../includes/bootstrap.php';

// Same reasoning as api/jobs.php — this is a public, no-auth page meant to
// be embedded via iframe on someone else's site, so it's a real scrape
// target too. Google/Bing/social crawlers are never limited.
enforce_rate_limit('embed_careers', 60, 60);

$query = trim($_GET['q'] ?? '');
$location = trim($_GET['location'] ?? '');
$companyId = (int) ($_GET['company_id'] ?? 0);

// Filtering to one company is the Paid-plan "embed your jobs on your own
// website" feature — block it here too, not just on the settings page that
// reveals the snippet, so the company_id filter can't be used to get that
// feature for free by loading this iframe directly. Falling through to
// "all companies' jobs" would be misleading for an embed meant to show only
// one company's listings, so this shows an upgrade notice instead.
$companyEmbedBlocked = $companyId > 0 && !company_has_embed_access($companyId);

$jobs = [];
$company = null;
$brandColor = '#0a1f44';

if (!$companyEmbedBlocked) {
    $sql = 'SELECT jobs.*, companies.name AS company_name, companies.logo_path AS company_logo,
                   companies.brand_color AS company_brand_color, companies.brand_tagline AS company_tagline
            FROM jobs
            JOIN companies ON companies.id = jobs.company_id
            WHERE jobs.is_open = 1';
    $params = [];
    if ($query !== '') {
        $sql .= ' AND (jobs.title LIKE ? OR jobs.description LIKE ?)';
        $params[] = '%' . $query . '%';
        $params[] = '%' . $query . '%';
    }
    if ($location !== '') {
        $sql .= ' AND jobs.location LIKE ?';
        $params[] = '%' . $location . '%';
    }
    if ($companyId > 0) {
        $sql .= ' AND jobs.company_id = ?';
        $params[] = $companyId;
    }
    $sql .= ' ORDER BY jobs.created_at DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll();
    $brandColor = $jobs[0]['company_brand_color'] ?? '';
    $brandColor = $brandColor && preg_match('/^#[0-9a-fA-F]{6}$/', $brandColor) ? $brandColor : '#0a1f44';
    $company = $jobs[0] ?? null;
}

$employmentLabels = [
    'full_time' => 'Full-time', 'part_time' => 'Part-time',
    'contract' => 'Contract', 'internship' => 'Internship',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Open Positions — <?= h(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { margin: 0; padding: 12px; background: transparent; }
        .card img { max-height: 40px; max-width: 40px; object-fit: contain; }
        .rvz-career-hero { background: <?= h($brandColor) ?>; color: #fff; padding: 24px; border-radius: 8px; margin-bottom: 16px; }
        .btn-primary { background-color: <?= h($brandColor) ?>; border-color: <?= h($brandColor) ?>; }
    </style>
</head>
<body>
<?php if ($companyEmbedBlocked): ?>
    <p class="text-muted">Website embedding for this company requires an active Paid plan.</p>
<?php endif; ?>
<?php if ($company): ?>
    <div class="rvz-career-hero d-flex align-items-center gap-3">
        <?php if (!empty($company['company_logo'])): ?>
            <img src="<?= h(UPLOAD_URL . $company['company_logo']) ?>" alt="<?= h($company['company_name']) ?>" style="max-height:56px;background:#fff;border-radius:6px;padding:4px;">
        <?php endif; ?>
        <div>
            <h3 class="mb-0"><?= h($company['company_name']) ?></h3>
            <?php if (!empty($company['company_tagline'])): ?><p class="mb-0 small"><?= h($company['company_tagline']) ?></p><?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<?php if (!$jobs && !$companyEmbedBlocked): ?>
    <p class="text-muted">No open roles right now — check back soon.</p>
<?php endif; ?>
<?php foreach ($jobs as $job): ?>
    <div class="card mb-3 shadow-sm">
        <div class="card-body d-flex gap-3 align-items-center flex-wrap">
            <?php if (!empty($job['company_logo'])): ?>
                <img src="<?= h(UPLOAD_URL . $job['company_logo']) ?>" alt="">
            <?php endif; ?>
            <div class="flex-grow-1" style="min-width:200px;">
                <h5 class="mb-1"><a target="_top" href="<?= h(base_url('job.php?id=' . $job['id'])) ?>"><?= h($job['title']) ?></a></h5>
                <p class="text-muted mb-1 small">
                    <?= h($job['company_name']) ?> &middot; <?= h($job['location']) ?>
                    <?= $job['is_remote'] ? ' &middot; Remote' : '' ?>
                </p>
                <span class="badge bg-secondary"><?= h($employmentLabels[$job['employment_type']] ?? $job['employment_type']) ?></span>
                <?php if ($job['salary_min']): ?>
                    <span class="badge bg-success"><?= h(format_zar((float) $job['salary_min'])) ?> - <?= h(format_zar((float) $job['salary_max'])) ?></span>
                <?php endif; ?>
            </div>
            <a target="_top" href="<?= h(base_url('job.php?id=' . $job['id'])) ?>" class="btn btn-sm btn-primary flex-shrink-0">Apply Now</a>
        </div>
    </div>
<?php endforeach; ?>
</body>
</html>

<?php
require __DIR__ . '/includes/bootstrap.php';

// A scraper harvesting the whole database hits many different job IDs
// rapidly; a real visitor views a handful per session. Generous enough
// that legitimate browsing never notices it.
enforce_rate_limit('job_detail', 90, 60);

$jobId = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare(
    'SELECT jobs.*, companies.name AS company_name, companies.website AS company_website,
            companies.logo_path AS company_logo, companies.brand_tagline AS company_tagline
     FROM jobs JOIN companies ON companies.id = jobs.company_id
     WHERE jobs.id = ?'
);
$stmt->execute([$jobId]);
$job = $stmt->fetch();

if (!$job) {
    http_response_code(404);
    die('Job not found.');
}

$user = current_user();

// Count this as a view unless the recruiter who posted it is looking at
// their own listing — that would just inflate their own dashboard numbers
// every time they check the page.
if (!$user || (int) $user['id'] !== (int) $job['posted_by']) {
    db()->prepare('UPDATE jobs SET views_count = views_count + 1 WHERE id = ?')->execute([$jobId]);
    $job['views_count']++; // keep the in-memory copy in sync for this render
}

require __DIR__ . '/includes/job_card.php';

$alreadyApplied = false;
$isSaved = false;
if ($user) {
    if ($user['role'] !== 'recruiter') {
        $stmt = db()->prepare('SELECT id FROM saved_jobs WHERE user_id = ? AND job_id = ?');
        $stmt->execute([$user['id'], $jobId]);
        $isSaved = (bool) $stmt->fetch();
    }
    $stmt = db()->prepare('SELECT id FROM applications WHERE job_id = ? AND candidate_id = ?');
    $stmt->execute([$jobId, $user['id']]);
    $alreadyApplied = (bool) $stmt->fetch();
}

$employmentLabels = [
    'full_time' => 'Full-time', 'part_time' => 'Part-time',
    'contract' => 'Contract', 'internship' => 'Internship',
];

// --- Google for Jobs structured data ---------------------------------------
// No API key needed: Google's crawler reads this JSON-LD directly off the
// page and decides whether/when to surface it in Search's jobs widget.
$jsonLd = [
    '@context' => 'https://schema.org/',
    '@type' => 'JobPosting',
    'title' => $job['title'],
    'description' => $job['description'],
    'identifier' => ['@type' => 'PropertyValue', 'name' => SITE_NAME, 'value' => (string) $job['id']],
    'datePosted' => date('Y-m-d', strtotime($job['created_at'])),
    // Google treats a listing with no validThrough as never expiring, which
    // it warns against — 60 days is a reasonable "still likely open" window
    // for a fresh listing without us having to track a real expiry date.
    'validThrough' => date('Y-m-d\TH:i:sP', strtotime($job['created_at'] . ' +60 days')),
    'employmentType' => strtoupper($job['employment_type']),
    'url' => base_url('job.php?id=' . $job['id']),
    'directApply' => true,
    'hiringOrganization' => array_filter([
        '@type' => 'Organization',
        'name' => $job['company_name'],
        'sameAs' => $job['company_website'] ?: null,
        'logo' => $job['company_logo'] ? (UPLOAD_URL . $job['company_logo']) : null,
    ]),
    'jobLocation' => [
        '@type' => 'Place',
        'address' => ['@type' => 'PostalAddress', 'addressLocality' => $job['location'], 'addressCountry' => 'ZA'],
    ],
];
if ($job['is_remote']) {
    $jsonLd['jobLocationType'] = 'TELECOMMUTE';
    $jsonLd['applicantLocationRequirements'] = ['@type' => 'Country', 'name' => 'South Africa'];
}
if ($job['salary_min']) {
    $jsonLd['baseSalary'] = [
        '@type' => 'MonetaryAmount',
        'currency' => 'ZAR',
        'value' => [
            '@type' => 'QuantitativeValue',
            'minValue' => (int) $job['salary_min'],
            'maxValue' => (int) ($job['salary_max'] ?: $job['salary_min']),
            'unitText' => 'YEAR',
        ],
    ];
}
$extraHead = '<script type="application/ld+json">' . json_encode($jsonLd, JSON_UNESCAPED_SLASHES) . '</script>';

$pageTitle = $job['title'] . ' at ' . $job['company_name'] . ' — ' . SITE_NAME;

$salaryBit = '';
if ($job['salary_min']) {
    $salaryBit = ' | ' . format_zar((float) $job['salary_min']) . ($job['salary_max'] ? '–' . format_zar((float) $job['salary_max']) : '+');
}
$descFlat = trim(preg_replace('/\s+/', ' ', strip_tags($job['description'])));
$descSnippet = mb_substr($descFlat, 0, 150);
$pageDescription = $job['title'] . ' at ' . $job['company_name'] . ' — ' . ($job['location'] ?: 'South Africa')
    . $salaryBit . '. ' . $descSnippet . (mb_strlen($descFlat) > 150 ? '…' : '');
if (!empty($job['company_logo'])) {
    $pageImage = UPLOAD_URL . $job['company_logo'];
}

require __DIR__ . '/includes/header.php';
?>

    <?php $isTeamOwner = $user && $user['role'] === 'recruiter' && current_recruiter_company_id() === (int) $job['company_id']; ?>
    <?php if (!empty($job['company_logo'])): ?>
        <img src="<?= h(UPLOAD_URL . $job['company_logo']) ?>" alt="<?= h($job['company_name']) ?> logo" class="mb-3" style="max-height:64px;">
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-2">
        <h1 class="mb-0"><?= h($job['title']) ?></h1>
        <div class="d-flex align-items-center gap-2 flex-shrink-0">
            <?php render_share_dropdown((int) $job['id'], $job['title'], '_detail'); ?>
            <?php if ($user && $user['role'] !== 'recruiter'): ?>
                <button type="button" class="rvz-save-btn<?= $isSaved ? ' is-saved' : '' ?>" data-job-id="<?= (int) $job['id'] ?>"
                        aria-label="<?= $isSaved ? 'Unsave job' : 'Save job' ?>" title="<?= $isSaved ? 'Saved' : 'Save job' ?>">
                    <?= $isSaved ? RVZ_HEART_FILLED : RVZ_HEART_OUTLINE ?>
                </button>
            <?php endif; ?>
            <?php if ($user && $user['role'] !== 'recruiter' && !$alreadyApplied): ?>
                <a href="<?= h(base_url('apply.php?id=' . $job['id'])) ?>" class="btn btn-primary">Apply Now</a>
            <?php elseif (!$user): ?>
                <a href="<?= h(base_url('login.php?next=' . urlencode('/apply.php?id=' . $job['id']))) ?>" class="btn btn-primary">Sign in to Apply</a>
            <?php endif; ?>
        </div>
    </div>

    <p class="text-muted">
        <a href="<?= h(base_url('company_profile.php?id=' . $job['company_id'])) ?>"><?= h($job['company_name']) ?></a>
        &middot; <?= h($job['location']) ?>
        <?= $job['is_remote'] ? ' &middot; Remote' : '' ?>
    </p>
    <?php if (!empty($job['company_tagline'])): ?>
        <p class="fst-italic small"><?= h($job['company_tagline']) ?></p>
    <?php endif; ?>
    <span class="badge bg-secondary mb-3"><?= h($employmentLabels[$job['employment_type']] ?? $job['employment_type']) ?></span>
    <?php if ($job['salary_min']): ?>
        <span class="badge bg-success mb-3"><?= h(format_zar((float) $job['salary_min'])) ?> - <?= h(format_zar((float) $job['salary_max'])) ?></span>
    <?php endif; ?>

    <div class="mb-4" style="white-space: pre-line;"><?= h($job['description']) ?></div>

    <?php if ($alreadyApplied): ?>
        <div class="alert alert-info">You've already applied to this job.</div>
    <?php endif; ?>

    <?php if ($isTeamOwner): ?>
        <a href="<?= h(base_url('pipeline.php?id=' . $job['id'])) ?>" class="btn btn-outline-secondary">View Pipeline</a>
        <a href="<?= h(base_url('job_edit.php?id=' . $job['id'])) ?>" class="btn btn-outline-secondary">Edit</a>
    <?php endif; ?>

    <div class="form-text mt-3">This page is also structured for Google for Jobs, so it can surface directly in Google Search.</div>

<?php if ($user && $user['role'] !== 'recruiter') render_save_job_script(csrf_token()); ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

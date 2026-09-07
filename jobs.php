<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/job_card.php';

$user = current_user();
if ($user && $user['role'] === 'recruiter') {
    redirect('/dashboard.php');
}
$isCandidate = $user && $user['role'] !== 'recruiter';

$query = trim($_GET['q'] ?? '');
$location = trim($_GET['location'] ?? '');
$employmentType = trim($_GET['employment_type'] ?? '');
$industryId = (int) ($_GET['industry_id'] ?? 0);
$remoteOnly = isset($_GET['remote_only']);
$salaryMin = isset($_GET['salary_min']) && $_GET['salary_min'] !== '' ? (int) $_GET['salary_min'] : null;

$sql = 'SELECT jobs.*, companies.name AS company_name, companies.logo_path AS company_logo, industries.name AS industry_name
        FROM jobs
        JOIN companies ON companies.id = jobs.company_id
        LEFT JOIN industries ON industries.id = jobs.industry_id
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
if ($employmentType !== '' && in_array($employmentType, ['full_time', 'part_time', 'contract', 'internship'], true)) {
    $sql .= ' AND jobs.employment_type = ?';
    $params[] = $employmentType;
}
if ($industryId > 0) {
    $sql .= ' AND jobs.industry_id = ?';
    $params[] = $industryId;
}
if ($remoteOnly) {
    $sql .= ' AND jobs.is_remote = 1';
}
if ($salaryMin !== null) {
    $sql .= ' AND (jobs.salary_max IS NULL OR jobs.salary_max >= ?)';
    $params[] = $salaryMin;
}
$sql .= ' ORDER BY jobs.created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

$industries = db()->query('SELECT id, name FROM industries ORDER BY name')->fetchAll();

$savedJobIds = [];
$recommendedJobs = [];
$savedJobsCount = 0;
$applicationsCount = 0;

if ($isCandidate) {
    $stmt = db()->prepare('SELECT job_id FROM saved_jobs WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $savedJobIds = array_column($stmt->fetchAll(), 'job_id');
    $savedJobsCount = count($savedJobIds);

    $stmt = db()->prepare('SELECT COUNT(*) AS c FROM applications WHERE candidate_id = ?');
    $stmt->execute([$user['id']]);
    $applicationsCount = (int) $stmt->fetch()['c'];

    $stmt = db()->prepare('SELECT skills, location FROM candidate_profiles WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $cp = $stmt->fetch();
    $skillWords = array_filter(array_map('trim', explode(',', $cp['skills'] ?? '')));

    if ($skillWords || !empty($cp['location'])) {
        $condSql = 'SELECT jobs.*, companies.name AS company_name, companies.logo_path AS company_logo, industries.name AS industry_name
                     FROM jobs JOIN companies ON companies.id = jobs.company_id
                     LEFT JOIN industries ON industries.id = jobs.industry_id
                     WHERE jobs.is_open = 1 AND (';
        $conds = [];
        $recParams = [];
        foreach (array_slice($skillWords, 0, 5) as $w) {
            $conds[] = '(jobs.title LIKE ? OR jobs.description LIKE ?)';
            $recParams[] = '%' . $w . '%';
            $recParams[] = '%' . $w . '%';
        }
        if (!empty($cp['location'])) {
            $conds[] = 'jobs.location LIKE ?';
            $recParams[] = '%' . $cp['location'] . '%';
        }
        $condSql .= implode(' OR ', $conds) . ') ORDER BY jobs.created_at DESC LIMIT 4';
        $stmt = db()->prepare($condSql);
        $stmt->execute($recParams);
        $recommendedJobs = $stmt->fetchAll();
    }
}

$pageTitle = $isCandidate ? 'My Jobs — ' . SITE_NAME : 'Browse Jobs — ' . SITE_NAME;
$pageDescription = 'Search open vacancies across South Africa with ' . SITE_NAME . '. Apply directly, save jobs, and get alerted when new roles match your profile.';
require __DIR__ . '/includes/header.php';
?>

<?php if ($isCandidate): ?>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="rvz-summary-card c1">
                <div class="num"><?= count($recommendedJobs) ?></div>
                <div class="label">Recommended Jobs</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="rvz-summary-card c2">
                <div class="num"><?= $savedJobsCount ?></div>
                <div class="label"><a href="<?= h(base_url('saved_jobs.php')) ?>" class="text-white text-decoration-none">Saved Jobs &rarr;</a></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="rvz-summary-card c3">
                <div class="num"><?= $applicationsCount ?></div>
                <div class="label"><a href="<?= h(base_url('my_applications.php')) ?>" class="text-white text-decoration-none">My Applications &rarr;</a></div>
            </div>
        </div>
    </div>

    <?php if ($recommendedJobs): ?>
        <h5 class="mb-3">Recommended for you</h5>
        <?php foreach ($recommendedJobs as $job): ?>
            <?php render_job_card($job, in_array($job['id'], $savedJobIds), true, csrf_token()); ?>
        <?php endforeach; ?>
        <hr class="my-4">
    <?php endif; ?>
<?php endif; ?>

<div class="rvz-hero">
    <h1>Find your next role</h1>
    <p class="mb-0">Search open positions from employers across South Africa.</p>
</div>

<form method="get" class="rvz-search-card row g-2 mb-4">
    <div class="col-md-3">
        <input type="text" name="q" value="<?= h($query) ?>" class="form-control" placeholder="Job title or keyword">
    </div>
    <div class="col-md-2">
        <input type="text" name="location" value="<?= h($location) ?>" class="form-control" placeholder="Location">
    </div>
    <div class="col-md-2">
        <select name="employment_type" class="form-select">
            <option value="">Any type</option>
            <?php foreach (employment_type_labels() as $key => $label): ?>
                <option value="<?= h($key) ?>" <?= $employmentType === $key ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="industry_id" class="form-select">
            <option value="">Any industry</option>
            <?php foreach ($industries as $ind): ?>
                <option value="<?= (int) $ind['id'] ?>" <?= $industryId === (int) $ind['id'] ? 'selected' : '' ?>><?= h($ind['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <input type="number" name="salary_min" value="<?= h((string) ($salaryMin ?? '')) ?>" class="form-control" placeholder="Min salary (R)">
    </div>
    <div class="col-md-1 d-flex align-items-center">
        <button class="btn btn-primary w-100" type="submit">Search</button>
    </div>
    <div class="col-12">
        <div class="form-check">
            <input type="checkbox" name="remote_only" id="remote_only" class="form-check-input" value="1" <?= $remoteOnly ? 'checked' : '' ?>>
            <label class="form-check-label small" for="remote_only">Remote only</label>
        </div>
    </div>
</form>

<h5 class="mb-3"><?= $isCandidate ? 'All open positions' : 'Open Positions' ?></h5>
<?php if (!$jobs): ?>
    <p>No open roles right now — check back soon.</p>
<?php endif; ?>

<?php foreach ($jobs as $job): ?>
    <?php render_job_card($job, in_array($job['id'], $savedJobIds), $user === null || $isCandidate, $user ? csrf_token() : null); ?>
<?php endforeach; ?>

<?php if ($isCandidate) render_save_job_script(csrf_token()); ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

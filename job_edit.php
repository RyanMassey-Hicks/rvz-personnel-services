<?php
require __DIR__ . '/includes/bootstrap.php';
require_recruiter_with_sla();

$user = current_user();
$jobId = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT * FROM jobs WHERE id = ? AND company_id = ?');
$stmt->execute([$jobId, current_recruiter_company_id()]);
$job = $stmt->fetch();
if (!$job) {
    http_response_code(404);
    die('Job not found, or you do not have permission to edit it.');
}

$errors = [];
$values = [
    'title' => $job['title'], 'description' => $job['description'], 'location' => $job['location'],
    'employment_type' => $job['employment_type'], 'salary_min' => $job['salary_min'], 'salary_max' => $job['salary_max'],
    'is_remote' => (bool) $job['is_remote'], 'is_open' => (bool) $job['is_open'],
    'industry_id' => $job['industry_id'] ?? null, 'use_response_handling' => (bool) ($job['use_response_handling'] ?? false),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $values = [
        'title' => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'location' => trim($_POST['location'] ?? ''),
        'employment_type' => $_POST['employment_type'] ?? 'full_time',
        'salary_min' => $_POST['salary_min'] !== '' ? (int) $_POST['salary_min'] : null,
        'salary_max' => $_POST['salary_max'] !== '' ? (int) $_POST['salary_max'] : null,
        'is_remote' => isset($_POST['is_remote']),
        'is_open' => isset($_POST['is_open']),
        'industry_id' => $_POST['industry_id'] !== '' ? (int) $_POST['industry_id'] : null,
        'use_response_handling' => isset($_POST['use_response_handling']),
    ];

    if ($values['title'] === '') $errors[] = 'Title is required.';
    if ($values['description'] === '') $errors[] = 'Description is required.';
    if ($values['location'] === '') $errors[] = 'Location is required.';

    if (!$errors) {
        $wasOpen = (bool) $job['is_open'];
        $nowOpen = $values['is_open'];

        // Track when the job actually closed, so the dashboard can show how
        // long it took to fill. Only touch closed_at on a real transition —
        // closing it stamps "now"; reopening it clears the stamp, since the
        // clock is running again.
        $closedAt = $job['closed_at'];
        if ($wasOpen && !$nowOpen) {
            $closedAt = date('Y-m-d H:i:s');
        } elseif (!$wasOpen && $nowOpen) {
            $closedAt = null;
        }

        $stmt = db()->prepare(
            'UPDATE jobs SET title=?, description=?, location=?, employment_type=?, salary_min=?, salary_max=?, is_remote=?, is_open=?, closed_at=?, industry_id=?, use_response_handling=? WHERE id=?'
        );
        $stmt->execute([
            $values['title'], $values['description'], $values['location'], $values['employment_type'],
            $values['salary_min'], $values['salary_max'], $values['is_remote'] ? 1 : 0, $nowOpen ? 1 : 0,
            $closedAt, $values['industry_id'], $values['use_response_handling'] ? 1 : 0, $jobId,
        ]);
        flash('success', 'Job updated.');
        redirect('/job.php?id=' . $jobId);
    }
}

$pageTitle = 'Edit Job';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/job_form_fields.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h2 class="mb-0">Edit <?= h($job['title']) ?></h2>
    <a href="<?= h(base_url('ads.php?job_id=' . $job['id'])) ?>" class="btn btn-outline-primary">Ads</a>
</div>
<?php if ($job['listing_expires_at']): ?>
    <div class="alert alert-light border d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <span>
            Listing <?= strtotime($job['listing_expires_at']) > time() ? 'expires' : 'expired' ?>
            <strong><?= h(date('M j, Y', strtotime($job['listing_expires_at']))) ?></strong>
        </span>
        <form method="post" action="<?= h(base_url('paystack/initialize_addon.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="addon_type" value="extend_listing">
            <input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-primary">Extend 30 Days (<?= h(format_zar(2000)) ?>)</button>
        </form>
    </div>
<?php endif; ?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= h($e) ?></div><?php endforeach; ?>
<form method="post">
    <?= csrf_field() ?>
    <?php render_job_form_fields($values); ?>
    <button type="submit" class="btn btn-primary mt-2">Save Changes</button>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>

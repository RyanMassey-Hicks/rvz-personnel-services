<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/job_card.php';
require_login();

$user = current_user();
if ($user['role'] === 'recruiter') {
    redirect('/dashboard.php');
}

$stmt = db()->prepare(
    'SELECT jobs.*, companies.name AS company_name, companies.logo_path AS company_logo, industries.name AS industry_name
     FROM saved_jobs
     JOIN jobs ON jobs.id = saved_jobs.job_id
     JOIN companies ON companies.id = jobs.company_id
     LEFT JOIN industries ON industries.id = jobs.industry_id
     WHERE saved_jobs.user_id = ?
     ORDER BY saved_jobs.saved_at DESC'
);
$stmt->execute([$user['id']]);
$jobs = $stmt->fetchAll();

$pageTitle = 'Saved Jobs';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/candidate_tabs.php';
render_candidate_tabs('saved');
?>
<h2 class="mb-4">Saved Jobs</h2>
<?php if (!$jobs): ?>
    <p class="text-muted">You haven't saved any jobs yet — click the heart on any listing to save it here.</p>
<?php endif; ?>
<?php foreach ($jobs as $job): ?>
    <?php render_job_card($job, true, true, csrf_token()); ?>
<?php endforeach; ?>
<?php render_save_job_script(csrf_token()); ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
$stmt = db()->prepare(
    'SELECT applications.*, jobs.title AS job_title, jobs.id AS job_id, companies.name AS company_name
     FROM applications
     JOIN jobs ON jobs.id = applications.job_id
     JOIN companies ON companies.id = jobs.company_id
     WHERE applications.candidate_id = ?
     ORDER BY applications.applied_on DESC'
);
$stmt->execute([$user['id']]);
$applications = $stmt->fetchAll();

$stageLabels = [
    'applied' => 'Applied', 'screening' => 'Screening', 'interview' => 'Interview',
    'offer' => 'Offer', 'hired' => 'Hired', 'rejected' => 'Rejected',
];

$pageTitle = 'My Applications';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/candidate_tabs.php';
render_candidate_tabs('applications');
?>
<h1 class="mb-4">My Applications</h1>
<table class="table">
    <thead><tr><th>Job</th><th>Company</th><th>Stage</th><th>Applied</th></tr></thead>
    <tbody>
    <?php foreach ($applications as $app): ?>
        <tr>
            <td><a href="<?= h(base_url('job.php?id=' . $app['job_id'])) ?>"><?= h($app['job_title']) ?></a></td>
            <td><?= h($app['company_name']) ?></td>
            <td><span class="badge bg-info text-dark"><?= h($stageLabels[$app['stage']] ?? $app['stage']) ?></span></td>
            <td><?= h(date('M j, Y', strtotime($app['applied_on']))) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$applications): ?>
        <tr><td colspan="4">You haven't applied to anything yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
<?php require __DIR__ . '/includes/footer.php'; ?>

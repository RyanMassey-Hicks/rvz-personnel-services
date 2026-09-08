<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/gauge_widget.php';
require_recruiter_with_sla();

$user = current_user();
$companyId = current_recruiter_company_id();

// Jobs are scoped to the whole COMPANY, not just this individual recruiter —
// team members share full visibility over their company's jobs/pipeline.
$statsSelect = "jobs.*, (SELECT COUNT(*) FROM applications WHERE applications.job_id = jobs.id) AS applicant_count,
                users.first_name AS poster_first_name, users.last_name AS poster_last_name";

$stmt = db()->prepare(
    "SELECT $statsSelect FROM jobs JOIN users ON users.id = jobs.posted_by
     WHERE jobs.company_id = ? AND jobs.is_open = 1 ORDER BY jobs.created_at DESC"
);
$stmt->execute([$companyId]);
$activeJobs = $stmt->fetchAll();

$stmt = db()->prepare(
    "SELECT $statsSelect FROM jobs JOIN users ON users.id = jobs.posted_by
     WHERE jobs.company_id = ? AND jobs.is_open = 0 ORDER BY COALESCE(jobs.closed_at, jobs.created_at) DESC"
);
$stmt->execute([$companyId]);
$pastJobs = $stmt->fetchAll();

$privileged = has_free_recruiter_access($user);
$companySub = ($privileged || !$companyId) ? null : get_company_subscription($companyId);
$sub = (!$privileged && !$companySub) ? get_subscription((int) $user['id']) : null;
$isPaid = has_active_recruiter_subscription($user);
$postsThisMonth = $isPaid ? 0 : jobs_posted_this_month((int) $user['id']);

$totalApplicants = array_sum(array_column($activeJobs, 'applicant_count')) + array_sum(array_column($pastJobs, 'applicant_count'));

// Last-7-days application trend across the whole company (a quick sparkline, not a full analytics suite).
$stmt = db()->prepare(
    "SELECT DATE(applications.applied_on) AS day, COUNT(*) AS c FROM applications
     JOIN jobs ON jobs.id = applications.job_id
     WHERE jobs.company_id = ? AND applications.applied_on >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(applications.applied_on)"
);
$stmt->execute([$companyId]);
$dailyCounts = array_column($stmt->fetchAll(), 'c', 'day');
$sparkline = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i day"));
    $sparkline[] = ['label' => date('D', strtotime($day)), 'count' => (int) ($dailyCounts[$day] ?? 0)];
}
$sparkMax = max(1, max(array_column($sparkline, 'count')));

// Recent activity feed — newest applications and stage changes across the company's jobs.
$stmt = db()->prepare(
    "SELECT applications.stage, applications.applied_on, applications.updated_on,
            jobs.title AS job_title, users.first_name, users.last_name, users.username
     FROM applications
     JOIN jobs ON jobs.id = applications.job_id
     JOIN users ON users.id = applications.candidate_id
     WHERE jobs.company_id = ?
     ORDER BY applications.updated_on DESC LIMIT 6"
);
$stmt->execute([$companyId]);
$recentActivity = $stmt->fetchAll();

$pageTitle = 'Recruiter Dashboard';
require __DIR__ . '/includes/header.php';
?>

<?php if ($privileged): ?>
    <div class="alert alert-success">Free recruiter access is active on this account (<?= h($user['email']) ?>).</div>
<?php elseif ($isPaid && $companySub && $companySub['status'] === 'active'): ?>
    <div class="alert alert-success">
        Paid seat active — team plan renews <?= h(date('M j, Y', strtotime($companySub['current_period_end']))) ?>.
    </div>
<?php elseif ($isPaid && $sub && $sub['status'] === 'active'): ?>
    <div class="alert alert-success">
        Subscription active — renews <?= h(date('M j, Y', strtotime($sub['current_period_end']))) ?>.
    </div>
<?php else: ?>
    <div class="alert alert-info">
        You're on the <strong>Free plan</strong> — <?= $postsThisMonth ?> of <?= FREE_TIER_JOB_LIMIT ?> job posts used this month.
        <a href="<?= h(base_url('pricing.php')) ?>">Upgrade for unlimited posts</a> plus Ads, website embed, and Direct Search.
    </div>
<?php endif; ?>

<?php render_candidate_pool_gauges(candidate_pool_stats()); ?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="rvz-summary-card c1">
            <div class="num"><?= count($activeJobs) ?></div>
            <div class="label">Active Listings</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="rvz-summary-card c2">
            <div class="num"><?= (int) $totalApplicants ?></div>
            <div class="label">Total Applications</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="rvz-summary-card c3">
            <div class="num"><?= count($pastJobs) ?></div>
            <div class="label">Closed / Filled</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card h-100 shadow-sm"><div class="card-body">
            <h6 class="mb-3">Applications — last 7 days</h6>
            <div class="rvz-sparkline">
                <?php foreach ($sparkline as $point): ?>
                    <div class="rvz-spark-bar-wrap" title="<?= h($point['label']) ?>: <?= $point['count'] ?>">
                        <div class="rvz-spark-bar" style="height:<?= max(6, round($point['count'] / $sparkMax * 100)) ?>%"></div>
                        <span class="rvz-spark-label"><?= h($point['label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100 shadow-sm"><div class="card-body">
            <h6 class="mb-3">Recent activity</h6>
            <?php if (!$recentActivity): ?>
                <p class="text-muted small mb-0">Nothing yet — activity shows up here as candidates apply and move through your pipeline.</p>
            <?php else: ?>
                <ul class="list-unstyled mb-0 rvz-activity-feed">
                    <?php foreach ($recentActivity as $a): ?>
                        <li>
                            <strong><?= h(trim($a['first_name'] . ' ' . $a['last_name']) ?: $a['username']) ?></strong>
                            <span class="text-muted">&middot; <?= h(ucfirst($a['stage'])) ?> &middot; <?= h($a['job_title']) ?></span>
                            <div class="small text-muted"><?= h(date('M j, g:ia', strtotime($a['updated_on']))) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div></div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="<?= h(base_url('recruiter_search.php')) ?>" class="btn btn-outline-primary btn-sm">🔍 Direct Search</a>
    <a href="<?= h(base_url('embed/careers.php?company_id=' . $companyId)) ?>" class="btn btn-outline-primary btn-sm" target="_blank">🌐 Career Site</a>
    <a href="<?= h(base_url('teams.php')) ?>" class="btn btn-outline-primary btn-sm">👥 Team</a>
    <a href="<?= h(base_url('recruiter_profile.php')) ?>" class="btn btn-outline-primary btn-sm">👤 My Profile</a>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <h1 class="mb-0">Your Jobs</h1>
    <a href="<?= h(base_url('job_create.php')) ?>" class="btn btn-primary">+ Post a Job</a>
</div>

<h5 class="mb-3">Active listings</h5>
<?php if (!$activeJobs): ?>
    <p class="text-muted">No active jobs right now — click "Post a Job" to add one.</p>
<?php endif; ?>

<?php foreach ($activeJobs as $job): ?>
    <?php $daysActive = days_between($job['created_at']); ?>
    <div class="card mb-3 shadow-sm rvz-job-card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <h5 class="mb-1"><a href="<?= h(base_url('job.php?id=' . $job['id'])) ?>"><?= h($job['title']) ?></a></h5>
                    <div class="text-muted small">
                        Posted <?= h(date('M j, Y', strtotime($job['created_at']))) ?>
                        by <?= h(trim($job['poster_first_name'] . ' ' . $job['poster_last_name']) ?: 'a team member') ?>
                    </div>
                </div>
                <div>
                    <a href="<?= h(base_url('pipeline.php?id=' . $job['id'])) ?>" class="btn btn-sm btn-outline-primary">Pipeline</a>
                    <a href="<?= h(base_url('ads.php?job_id=' . $job['id'])) ?>" class="btn btn-sm btn-outline-primary">Ads</a>
                    <a href="<?= h(base_url('job_edit.php?id=' . $job['id'])) ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                </div>
            </div>
            <div class="job-stats-row">
                <span title="Job page views">
                    <?php render_speedometer('Views', (int) $job['views_count'], gauge_nice_max((int) $job['views_count']), '#0a1f44', 84, true); ?>
                </span>
                <span title="Applications received">
                    <?php render_speedometer('Applications', (int) $job['applicant_count'], gauge_nice_max((int) $job['applicant_count']), '#2a5caa', 84, true); ?>
                </span>
                <span title="Days this job has been active">
                    <?php render_speedometer('Days Active', $daysActive, gauge_nice_max($daysActive), '#6f8fb8', 84, true); ?>
                </span>
                <span title="Times shared to LinkedIn/Facebook">
                    <?php render_speedometer('Shares', (int) $job['shares_count'], gauge_nice_max((int) $job['shares_count']), '#37414f', 84, true); ?>
                </span>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<hr class="my-4">
<h5 class="mb-3">Past &amp; inactive listings</h5>
<?php if (!$pastJobs): ?>
    <p class="text-muted">Nothing closed yet — closed or filled jobs will show up here, along with how long they took to fill.</p>
<?php else: ?>
<div class="table-responsive">
<table class="table">
    <thead>
        <tr>
            <th>Title</th><th>Posted</th><th>Closed</th><th>Days to fill</th>
            <th>Applications</th><th>Views</th><th>Shares</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($pastJobs as $job): ?>
        <?php
            $closedAt = $job['closed_at'];
            $duration = days_between($job['created_at'], $closedAt);
        ?>
        <tr>
            <td><a href="<?= h(base_url('job.php?id=' . $job['id'])) ?>"><?= h($job['title']) ?></a></td>
            <td><?= h(date('M j, Y', strtotime($job['created_at']))) ?></td>
            <td>
                <?php if ($closedAt): ?>
                    <?= h(date('M j, Y', strtotime($closedAt))) ?>
                <?php else: ?>
                    <span class="text-muted small">unknown</span>
                <?php endif; ?>
            </td>
            <td>
                <?= $duration ?> day<?= $duration === 1 ? '' : 's' ?>
                <?php if (!$closedAt): ?><span class="text-muted small">(est.)</span><?php endif; ?>
            </td>
            <td><?= (int) $job['applicant_count'] ?></td>
            <td><?= (int) $job['views_count'] ?></td>
            <td><?= (int) $job['shares_count'] ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<p class="small text-muted mt-2">Want to add your jobs to your own website? Find the embed snippets under
<a href="<?= h(base_url('embed_jobs.php')) ?>">Settings &rarr; Embed Your Jobs</a>.</p>

<?php require __DIR__ . '/includes/footer.php'; ?>

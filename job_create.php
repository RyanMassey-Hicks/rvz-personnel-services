<?php
require __DIR__ . '/includes/bootstrap.php';
require_recruiter_with_sla();

$user = current_user();
$stmt = db()->prepare('SELECT * FROM recruiter_profiles WHERE user_id = ?');
$stmt->execute([$user['id']]);
$recruiterProfile = $stmt->fetch();

if (!$recruiterProfile || !$recruiterProfile['company_id']) {
    flash('danger', 'Set up your company first.');
    redirect('/become_recruiter.php');
}

$isPaid = has_active_recruiter_subscription($user);
if (!can_post_another_job($user)) {
    flash('info', 'You don\'t have any job-listing credits left. Buy a package to post a new job.');
    redirect('/pricing.php');
}

$errors = [];
$values = ['title' => '', 'description' => '', 'location' => '', 'employment_type' => 'full_time',
           'salary_min' => '', 'salary_max' => '', 'is_remote' => false, 'industry_id' => null, 'use_response_handling' => false];

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
        'industry_id' => $_POST['industry_id'] !== '' ? (int) $_POST['industry_id'] : null,
        'use_response_handling' => isset($_POST['use_response_handling']),
    ];

    if ($values['title'] === '') $errors[] = 'Title is required.';
    if ($values['description'] === '') $errors[] = 'Description is required.';
    if ($values['location'] === '') $errors[] = 'Location is required.';
    if (!in_array($values['employment_type'], ['full_time', 'part_time', 'contract', 'internship'], true)) {
        $errors[] = 'Invalid employment type.';
    }
    if (!can_post_another_job($user)) {
        $errors[] = 'You don\'t have any job-listing credits left. Buy a package to post a new job.';
    }

    if (!$errors) {
        $listingExpiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        $stmt = db()->prepare(
            'INSERT INTO jobs (company_id, posted_by, title, description, location, employment_type, salary_min, salary_max, is_remote, is_open, industry_id, use_response_handling, listing_expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)'
        );
        $stmt->execute([
            $recruiterProfile['company_id'], $user['id'], $values['title'], $values['description'],
            $values['location'], $values['employment_type'], $values['salary_min'], $values['salary_max'],
            $values['is_remote'] ? 1 : 0, $values['industry_id'], $values['use_response_handling'] ? 1 : 0,
            $listingExpiresAt,
        ]);
        $jobId = db()->lastInsertId();

        if (!$isPaid) {
            $purchaseId = consume_recruiter_listing_credit((int) $user['id']);
            if ($purchaseId) {
                db()->prepare('UPDATE jobs SET recruiter_purchase_id = ? WHERE id = ?')->execute([$purchaseId, $jobId]);
            }
        }

        if ($values['use_response_handling']) {
            send_email(
                PRIVILEGED_RECRUITER_EMAIL,
                'Response Handling requested: ' . $values['title'],
                email_wrap('<p>' . h($user['email']) . ' requested RVZ Response Handling for a new job:</p><p><strong>' . h($values['title']) . '</strong></p><p><a href="' . h(base_url('job_edit.php?id=' . $jobId)) . '">View job</a></p>')
            );
        }

        flash('success', 'Job posted. Share it to LinkedIn/Facebook from the job page.');
        redirect('/job.php?id=' . $jobId);
    }
}

$pageTitle = 'Post a Job';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/job_form_fields.php';
?>
<h2>Post a New Job</h2>
<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= h($e) ?></div><?php endforeach; ?>
<form method="post">
    <?= csrf_field() ?>
    <?php render_job_form_fields($values); ?>
    <button type="submit" class="btn btn-primary mt-2">Post Job</button>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$jobId = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare(
    'SELECT jobs.*, companies.name AS company_name, users.id AS recruiter_id, users.email AS recruiter_email,
            users.first_name AS recruiter_first_name, users.last_name AS recruiter_last_name
     FROM jobs
     JOIN companies ON companies.id = jobs.company_id
     JOIN users ON users.id = jobs.posted_by
     WHERE jobs.id = ? AND jobs.is_open = 1'
);
$stmt->execute([$jobId]);
$job = $stmt->fetch();
if (!$job) {
    http_response_code(404);
    die('Job not found or no longer accepting applications.');
}

$user = current_user();
if ($user['role'] === 'recruiter') {
    die('Recruiter accounts cannot apply to jobs.');
}

$stmt = db()->prepare('SELECT id FROM applications WHERE job_id = ? AND candidate_id = ?');
$stmt->execute([$jobId, $user['id']]);
if ($stmt->fetch()) {
    flash('info', "You've already applied to this job.");
    redirect('/job.php?id=' . $jobId);
}

$stmt = db()->prepare('SELECT * FROM candidate_profiles WHERE user_id = ?');
$stmt->execute([$user['id']]);
$candidateProfile = $stmt->fetch();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $coverLetter = trim($_POST['cover_letter'] ?? '');
    $resumePath = $candidateProfile['resume_path'] ?? '';

    if (!empty($_FILES['resume']['name'])) {
        $file = $_FILES['resume'];
        $allowedExt = ['pdf', 'doc', 'docx'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'There was a problem uploading your resume.';
        } elseif ($file['size'] > MAX_UPLOAD_BYTES) {
            $errors[] = 'Resume must be smaller than 5MB.';
        } elseif (!in_array($ext, $allowedExt, true)) {
            $errors[] = 'Resume must be a PDF or Word document.';
        } else {
            $filename = safe_upload_filename($file['name'], $user['id']);
            $destination = UPLOAD_DIR . 'resumes/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                $errors[] = 'Could not save your resume — please try again.';
            } else {
                $resumePath = 'resumes/' . $filename;
            }
        }
    }

    if (!$errors && !$resumePath) {
        $errors[] = 'Please attach a resume (or add one to your profile first).';
    }

    if (!$errors) {
        $stmt = db()->prepare(
            'INSERT INTO applications (job_id, candidate_id, resume_path, cover_letter, source)
             VALUES (?, ?, ?, ?, ?)'
        );
        $source = in_array($user['signed_up_via'], ['google', 'linkedin', 'facebook'], true) ? $user['signed_up_via'] : 'website';
        $stmt->execute([$jobId, $user['id'], $resumePath, $coverLetter, $source]);

        send_application_confirmation($user, $job, $job['company_name']);
        send_new_application_notice_to_recruiter(
            ['email' => $job['recruiter_email'], 'first_name' => $job['recruiter_first_name'], 'last_name' => $job['recruiter_last_name']],
            $job,
            $user
        );

        create_notification(
            (int) $user['id'],
            'application_status',
            'Application submitted: ' . $job['title'],
            'Your application to ' . $job['company_name'] . ' was received.',
            base_url('my_applications.php')
        );

        $candidateName = trim($user['first_name'] . ' ' . $user['last_name']) ?: $user['username'];
        $teamStmt = db()->prepare('SELECT user_id FROM recruiter_profiles WHERE company_id = ?');
        $teamStmt->execute([$job['company_id']]);
        foreach ($teamStmt->fetchAll() as $teamMember) {
            create_notification(
                (int) $teamMember['user_id'],
                'new_application',
                'New application: ' . $job['title'],
                $candidateName . ' applied for ' . $job['title'],
                base_url('pipeline.php?id=' . $jobId)
            );
        }

        flash('success', 'Application submitted for ' . $job['title'] . '! A confirmation email is on its way.');
        redirect('/my_applications.php');
    }
}

$pageTitle = 'Apply — ' . $job['title'];
require __DIR__ . '/includes/header.php';
?>

<h2>Apply to <?= h($job['title']) ?></h2>
<p class="text-muted"><?= h($job['company_name']) ?> &middot; <?= h($job['location']) ?></p>

<?php if (!empty($candidateProfile['resume_path'])): ?>
    <div class="alert alert-secondary">
        We'll use the resume already on your profile unless you upload a new one below.
    </div>
<?php endif; ?>

<?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><?= h($e) ?></div>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label">Resume <?= empty($candidateProfile['resume_path']) ? '(required)' : '(optional — replaces the one on file)' ?></label>
        <input type="file" name="resume" class="form-control" accept=".pdf,.doc,.docx">
    </div>
    <div class="mb-3">
        <label class="form-label">Cover letter</label>
        <textarea name="cover_letter" rows="6" class="form-control" placeholder="Why are you a great fit for this role?"><?= h($_POST['cover_letter'] ?? '') ?></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Submit Application</button>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>

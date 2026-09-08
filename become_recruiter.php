<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
if ($user['role'] === 'recruiter') {
    redirect('/dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $companyName = trim($_POST['company_name'] ?? '');
    $companyWebsite = trim($_POST['company_website'] ?? '');
    $jobTitle = trim($_POST['job_title'] ?? '');

    if ($companyName === '') {
        $errors[] = 'Company name is required.';
    }
    if ($companyWebsite !== '' && !is_safe_http_url($companyWebsite)) {
        $errors[] = 'Company website must be a valid http:// or https:// URL.';
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id FROM companies WHERE name = ?');
            $stmt->execute([$companyName]);
            $company = $stmt->fetch();

            if ($company) {
                $companyId = (int) $company['id'];
            } else {
                $stmt = $pdo->prepare('INSERT INTO companies (name, website) VALUES (?, ?)');
                $stmt->execute([$companyName, $companyWebsite]);
                $companyId = (int) $pdo->lastInsertId();
            }

            $pdo->prepare('UPDATE users SET role = "recruiter" WHERE id = ?')->execute([$user['id']]);

            $stmt = $pdo->prepare('SELECT user_id FROM recruiter_profiles WHERE user_id = ?');
            $stmt->execute([$user['id']]);
            if ($stmt->fetch()) {
                $pdo->prepare('UPDATE recruiter_profiles SET company_id = ?, job_title = ? WHERE user_id = ?')
                    ->execute([$companyId, $jobTitle, $user['id']]);
            } else {
                $pdo->prepare('INSERT INTO recruiter_profiles (user_id, company_id, job_title) VALUES (?, ?, ?)')
                    ->execute([$user['id'], $companyId, $jobTitle]);
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Something went wrong setting up your workspace. Please try again.';
        }

        if (!$errors) {
            if (is_privileged_recruiter($user)) {
                flash('success', 'Your recruiter workspace is ready — free access is enabled for this account.');
                redirect('/dashboard.php');
            }
            flash('success', 'Your recruiter workspace is ready! Buy a job-listing package from the Pricing page to start posting.');
            redirect('/dashboard.php');
        }
    }
}

$pageTitle = 'Set Up Recruiter Workspace';
require __DIR__ . '/includes/header.php';
?>
<h2>Set up your recruiter workspace</h2>
<p class="text-muted">This unlocks the ATS dashboard where you can post jobs and manage your applicant pipeline.</p>

<?php if (!is_privileged_recruiter($user)): ?>
    <div class="alert alert-info">
        Job listings are sold as once-off packages — no subscription. See <a href="<?= h(base_url('pricing.php')) ?>">Pricing</a>
        for Basic/Standard/Premium packages and what's included.
    </div>
<?php endif; ?>

<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= h($e) ?></div><?php endforeach; ?>

<form method="post">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label">Company name</label>
        <input type="text" name="company_name" class="form-control" required value="<?= h($_POST['company_name'] ?? '') ?>">
    </div>
    <div class="mb-3">
        <label class="form-label">Company website</label>
        <input type="url" name="company_website" class="form-control" value="<?= h($_POST['company_website'] ?? '') ?>">
    </div>
    <div class="mb-3">
        <label class="form-label">Your title</label>
        <input type="text" name="job_title" class="form-control" placeholder="e.g. Talent Acquisition Lead" value="<?= h($_POST['job_title'] ?? '') ?>">
    </div>
    <button type="submit" class="btn btn-primary">Create Workspace</button>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
require __DIR__ . '/includes/bootstrap.php';
require_recruiter();

$user = current_user();
$stmt = db()->prepare(
    'SELECT recruiter_profiles.*, companies.name AS company_name, companies.website AS company_website, companies.logo_path
     FROM recruiter_profiles LEFT JOIN companies ON companies.id = recruiter_profiles.company_id
     WHERE recruiter_profiles.user_id = ?'
);
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

if (!$profile) {
    flash('info', 'Set up your company first.');
    redirect('/become_recruiter.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $jobTitle = trim($_POST['job_title'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $companyWebsite = trim($_POST['company_website'] ?? '');
    if ($companyWebsite !== '' && !is_safe_http_url($companyWebsite)) {
        $errors[] = 'Company website must be a valid http:// or https:// URL.';
    }

    $photoPath = $profile['profile_photo'] ?? '';
    if (!empty($_FILES['profile_photo']['name'])) {
        $file = $_FILES['profile_photo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'There was a problem uploading your profile photo.';
        } elseif ($file['size'] > MAX_LOGO_UPLOAD_BYTES) {
            $errors[] = 'Profile photo must be smaller than 2MB.';
        } elseif (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            $errors[] = 'Profile photo must be a PNG, JPG, or WEBP image.';
        } else {
            $filename = safe_upload_filename($file['name'], $user['id']);
            if (!is_dir(UPLOAD_DIR . 'profile_photos')) {
                @mkdir(UPLOAD_DIR . 'profile_photos', 0755, true);
            }
            move_uploaded_file($file['tmp_name'], UPLOAD_DIR . 'profile_photos/' . $filename);
            $photoPath = 'profile_photos/' . $filename;
        }
    }

    $logoPath = $profile['logo_path'];
    if (!empty($_FILES['company_logo']['name'])) {
        $file = $_FILES['company_logo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'There was a problem uploading the company logo.';
        } elseif ($file['size'] > MAX_LOGO_UPLOAD_BYTES) {
            $errors[] = 'Company logo must be smaller than 2MB.';
        } elseif (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg'], true)) {
            $errors[] = 'Company logo must be a PNG, JPG, WEBP, or SVG file.';
        } else {
            $filename = safe_upload_filename($file['name'], $user['id']);
            move_uploaded_file($file['tmp_name'], UPLOAD_DIR . 'company_logos/' . $filename);
            $logoPath = 'company_logos/' . $filename;
        }
    }

    if (!$errors) {
        db()->prepare('UPDATE users SET first_name = ?, last_name = ? WHERE id = ?')->execute([$firstName, $lastName, $user['id']]);
        db()->prepare('UPDATE recruiter_profiles SET job_title = ?, phone = ?, profile_photo = ? WHERE user_id = ?')
            ->execute([$jobTitle, $phone, $photoPath, $user['id']]);
        if ($profile['company_id']) {
            db()->prepare('UPDATE companies SET website = ?, logo_path = ? WHERE id = ?')
                ->execute([$companyWebsite, $logoPath, $profile['company_id']]);
        }
        flash('success', 'Profile updated.');
        redirect('/recruiter_profile.php');
    }
}

$stmt = db()->prepare('SELECT * FROM recruiter_slas WHERE user_id = ? ORDER BY signed_at DESC LIMIT 1');
$stmt->execute([$user['id']]);
$sla = $stmt->fetch();

$pageTitle = 'My Profile';
require __DIR__ . '/includes/header.php';
?>
<h2 class="mb-4">My Profile</h2>
<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= h($e) ?></div><?php endforeach; ?>

<form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="card mb-3"><div class="card-body">
        <h5 class="mb-3">Your Details</h5>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">First name</label>
                <input type="text" name="first_name" class="form-control" value="<?= h($user['first_name']) ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Last name</label>
                <input type="text" name="last_name" class="form-control" value="<?= h($user['last_name']) ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Job title</label>
                <input type="text" name="job_title" class="form-control" value="<?= h($profile['job_title']) ?>">
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Phone</label>
                <input type="tel" name="phone" class="form-control" value="<?= h($profile['phone'] ?? '') ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" value="<?= h($user['email']) ?>" disabled>
            </div>
        </div>
        <div class="mb-2">
            <label class="form-label">Profile photo</label>
            <input type="file" name="profile_photo" class="form-control" accept=".png,.jpg,.jpeg,.webp">
            <?php if (!empty($profile['profile_photo'])): ?>
                <img src="<?= h(UPLOAD_URL . $profile['profile_photo']) ?>" class="rounded-circle mt-2" width="64" height="64" alt="Profile photo" style="object-fit:cover;">
            <?php endif; ?>
        </div>
    </div></div>

    <div class="card mb-3"><div class="card-body">
        <h5 class="mb-3">Company</h5>
        <p class="text-muted small">Company: <strong><?= h($profile['company_name'] ?? '—') ?></strong></p>
        <div class="mb-3">
            <label class="form-label">Company website</label>
            <input type="url" name="company_website" class="form-control" value="<?= h($profile['company_website'] ?? '') ?>">
        </div>
        <div class="mb-2">
            <label class="form-label">Company logo <span class="text-muted small">(shown on your job postings)</span></label>
            <input type="file" name="company_logo" class="form-control" accept=".png,.jpg,.jpeg,.webp,.svg">
            <?php if (!empty($profile['logo_path'])): ?>
                <img src="<?= h(UPLOAD_URL . $profile['logo_path']) ?>" class="mt-2" style="max-height:64px;" alt="Company logo">
            <?php endif; ?>
        </div>
    </div></div>

    <button type="submit" class="btn btn-primary">Save Profile</button>
</form>

<div class="card mt-4"><div class="card-body">
    <h5 class="mb-3">Service Level Agreement</h5>
    <?php if ($sla): ?>
        <p class="mb-2">Signed by <strong><?= h($sla['signed_name']) ?></strong> on <?= h(date('M j, Y', strtotime($sla['signed_at']))) ?>.</p>
        <a href="<?= h(UPLOAD_URL . $sla['pdf_path']) ?>" target="_blank" class="btn btn-outline-primary btn-sm">Download SLA PDF</a>
    <?php else: ?>
        <p class="text-muted mb-2">No signed SLA on file yet.</p>
        <a href="<?= h(base_url('sla_sign.php')) ?>" class="btn btn-outline-primary btn-sm">Sign SLA</a>
    <?php endif; ?>
</div></div>

<?php require __DIR__ . '/includes/footer.php'; ?>

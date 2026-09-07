<?php
/**
 * Company logo + branded content — available to any active recruiter, scoped
 * to their own company. (Previously restricted to the privileged account
 * only; every company can now brand its own listings and career page.)
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/settings_tabs.php';
require_recruiter_with_sla();

$user = current_user();
$companyId = current_recruiter_company_id();
if (!$companyId) {
    flash('danger', 'Set up your company first.');
    redirect('/become_recruiter.php');
}

$stmt = db()->prepare('SELECT * FROM companies WHERE id = ?');
$stmt->execute([$companyId]);
$company = $stmt->fetch();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $formAction = $_POST['form_action'] ?? 'branding';

    if ($formAction === 'photo_delete') {
        $photoId = (int) ($_POST['photo_id'] ?? 0);
        $stmt = db()->prepare('SELECT photo_path FROM company_photos WHERE id = ? AND company_id = ?');
        $stmt->execute([$photoId, $companyId]);
        $photo = $stmt->fetch();
        if ($photo) {
            @unlink(UPLOAD_DIR . $photo['photo_path']);
            db()->prepare('DELETE FROM company_photos WHERE id = ? AND company_id = ?')->execute([$photoId, $companyId]);
            flash('success', 'Photo removed.');
        }
        redirect('/company_branding.php');
    }

    if ($formAction === 'photo_upload') {
        if (!empty($_FILES['photo']['name'])) {
            $file = $_FILES['photo'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'There was a problem uploading the photo.';
            } elseif ($file['size'] > MAX_LOGO_UPLOAD_BYTES * 2) {
                $errors[] = 'Photo must be smaller than 4MB.';
            } elseif (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
                $errors[] = 'Photo must be a PNG, JPG, or WEBP image.';
            } else {
                if (!is_dir(UPLOAD_DIR . 'company_photos')) {
                    @mkdir(UPLOAD_DIR . 'company_photos', 0755, true);
                }
                $filename = safe_upload_filename($file['name'], $user['id']);
                move_uploaded_file($file['tmp_name'], UPLOAD_DIR . 'company_photos/' . $filename);
                db()->prepare('INSERT INTO company_photos (company_id, photo_path) VALUES (?, ?)')
                    ->execute([$companyId, 'company_photos/' . $filename]);
                flash('success', 'Photo added.');
            }
        }
        if (!$errors) redirect('/company_branding.php');
    }

    if ($formAction === 'branding') {
        $tagline = trim($_POST['brand_tagline'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $color = trim($_POST['brand_color'] ?? '');
        $color2 = trim($_POST['brand_color_2'] ?? '');
        $color3 = trim($_POST['brand_color_3'] ?? '');
        $color4 = trim($_POST['brand_color_4'] ?? '');
        foreach (['Brand colour 1' => $color, 'Brand colour 2' => $color2, 'Brand colour 3' => $color3, 'Brand colour 4' => $color4] as $label => $c) {
            if ($c !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $c)) {
                $errors[] = $label . ' must be a hex code like #123abc.';
            }
        }
        $registrationNumber = trim($_POST['registration_number'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $contactEmail = trim($_POST['contact_email'] ?? '');
        $contactPhone = trim($_POST['contact_phone'] ?? '');
        if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Contact email address is not valid.';
        }

        $logoPath = $company['logo_path'];
        if (!empty($_FILES['logo']['name'])) {
            $file = $_FILES['logo'];
            $allowedExt = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'There was a problem uploading the logo.';
            } elseif ($file['size'] > MAX_LOGO_UPLOAD_BYTES) {
                $errors[] = 'Logo must be smaller than 2MB.';
            } elseif (!in_array($ext, $allowedExt, true)) {
                $errors[] = 'Logo must be a PNG, JPG, WEBP, or SVG file.';
            } else {
                $filename = safe_upload_filename($file['name'], $user['id']);
                $destination = UPLOAD_DIR . 'company_logos/' . $filename;
                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    $errors[] = 'Could not save the logo — please try again.';
                } else {
                    $logoPath = 'company_logos/' . $filename;
                }
            }
        }

        if (!$errors) {
            $stmt = db()->prepare(
                'UPDATE companies SET logo_path = ?, brand_color = ?, brand_color_2 = ?, brand_color_3 = ?, brand_color_4 = ?,
                 brand_tagline = ?, description = ?, registration_number = ?, city = ?, contact_email = ?, contact_phone = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $logoPath, $color, $color2, $color3, $color4,
                $tagline, $description, $registrationNumber, $city, $contactEmail, $contactPhone,
                $company['id'],
            ]);
            flash('success', 'Branding updated.');
            redirect('/company_branding.php');
        }
    }
}

$stmt = db()->prepare('SELECT * FROM company_photos WHERE company_id = ? ORDER BY uploaded_at DESC');
$stmt->execute([$companyId]);
$photos = $stmt->fetchAll();

$pageTitle = 'Branding — Settings';
require __DIR__ . '/includes/header.php';
render_settings_tabs('branding');
?>
<h2 class="mb-1">Company branding</h2>
<p class="text-muted mb-4">Shown on <?= h($company['name']) ?>'s job listings, career page, and public
<a href="<?= h(base_url('company_profile.php?id=' . $companyId)) ?>" target="_blank">company profile</a>.</p>

<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= h($e) ?></div><?php endforeach; ?>

<?php if (!empty($company['logo_path'])): ?>
    <div class="mb-3">
        <img src="<?= h(UPLOAD_URL . $company['logo_path']) ?>" alt="<?= h($company['name']) ?> logo" style="max-height:80px;">
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="mb-4">
    <?= csrf_field() ?>
    <input type="hidden" name="form_action" value="branding">
    <div class="mb-3">
        <label class="form-label">Company logo</label>
        <input type="file" name="logo" class="form-control" accept=".png,.jpg,.jpeg,.webp,.svg">
    </div>
    <div class="mb-3">
        <label class="form-label">Brand colours <span class="text-muted small">(up to 4 — used across your listings and career page)</span></label>
        <div class="row g-2">
            <?php for ($i = 1; $i <= 4; $i++): $field = $i === 1 ? 'brand_color' : 'brand_color_' . $i; ?>
                <div class="col-6 col-md-3">
                    <div class="input-group">
                        <span class="input-group-text p-1">
                            <input type="color" class="form-control form-control-color border-0 p-0"
                                   style="width:2rem;height:2rem;"
                                   value="<?= h($company[$field] ?: '#0a1f44') ?>"
                                   oninput="document.getElementById('<?= $field ?>_text').value = this.value">
                        </span>
                        <input type="text" name="<?= $field ?>" id="<?= $field ?>_text" class="form-control"
                               placeholder="<?= $i === 1 ? '#123abc (primary)' : '#123abc' ?>"
                               value="<?= h($company[$field] ?? '') ?>">
                    </div>
                    <div class="form-text small">Colour <?= $i ?><?= $i === 1 ? ' (primary)' : '' ?></div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label">Branded tagline</label>
        <input type="text" name="brand_tagline" class="form-control" maxlength="255" value="<?= h($company['brand_tagline'] ?? '') ?>">
    </div>
    <div class="mb-3">
        <label class="form-label">Company description <span class="text-muted small">(shown on your public company profile)</span></label>
        <textarea name="description" rows="5" class="form-control" placeholder="Tell candidates about your company, culture, and what you do..."><?= h($company['description'] ?? '') ?></textarea>
    </div>

    <hr class="my-4">
    <h6 class="mb-3">Company information</h6>
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <label class="form-label">Company registration number</label>
            <input type="text" name="registration_number" class="form-control" placeholder="e.g. 2023/905207/07" value="<?= h($company['registration_number'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">City</label>
            <input type="text" name="city" class="form-control" placeholder="e.g. Johannesburg" value="<?= h($company['city'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Contact email</label>
            <input type="email" name="contact_email" class="form-control" placeholder="info@yourcompany.co.za" value="<?= h($company['contact_email'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Contact phone</label>
            <input type="text" name="contact_phone" class="form-control" placeholder="e.g. 011 123 4567" value="<?= h($company['contact_phone'] ?? '') ?>">
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Save Branding</button>
</form>

<div class="card"><div class="card-body">
    <h6 class="mb-3">Company photos <span class="text-muted small fw-normal">(optional gallery on your public profile)</span></h6>
    <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end mb-3">
        <?= csrf_field() ?>
        <input type="hidden" name="form_action" value="photo_upload">
        <div class="col-md-8">
            <input type="file" name="photo" class="form-control" accept=".png,.jpg,.jpeg,.webp" required>
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-outline-primary w-100">Add Photo</button>
        </div>
    </form>
    <div class="row g-2">
        <?php foreach ($photos as $photo): ?>
            <div class="col-md-3 col-6">
                <div class="position-relative">
                    <img src="<?= h(UPLOAD_URL . $photo['photo_path']) ?>" class="img-fluid rounded" style="height:110px;width:100%;object-fit:cover;" alt="">
                    <form method="post" class="position-absolute top-0 end-0 m-1">
                        <?= csrf_field() ?>
                        <input type="hidden" name="form_action" value="photo_delete">
                        <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger py-0 px-1">&times;</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div></div>

<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/profile_save.php';
require __DIR__ . '/includes/gauge_widget.php';
require_login();

$user = current_user();
if ($user['role'] === 'recruiter') {
    redirect('/recruiter_profile.php');
}

$stmt = db()->prepare('SELECT * FROM candidate_profiles WHERE user_id = ?');
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();
if (!$profile) {
    db()->prepare('INSERT INTO candidate_profiles (user_id) VALUES (?)')->execute([$user['id']]);
    $stmt->execute([$user['id']]);
    $profile = $stmt->fetch();
}

$workExperience = json_decode($profile['work_experience'] ?? '[]', true) ?: [];
$education = json_decode($profile['education'] ?? '[]', true) ?: [];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $result = save_candidate_profile($user, $profile, $_POST, $_FILES);
    $errors = $result['errors'];
    if (!$errors) {
        flash('success', 'Profile updated.');
        redirect('/profile.php');
    }
}

// --- Profile completeness (simple weighted check across key fields) ---
$completenessFields = [
    $profile['headline'], $profile['location'], $profile['phone'] ?? '', $profile['professional_summary'] ?? '',
    $profile['skills'], $profile['resume_path'], $profile['profile_photo'] ?? '',
    $workExperience ? '1' : '', $education ? '1' : '', $profile['languages'] ?? '',
];
$filledCount = count(array_filter($completenessFields, fn($v) => trim((string) $v) !== ''));
$completenessPct = (int) round(($filledCount / count($completenessFields)) * 100);

$pageTitle = 'My Profile';
$pageDescription = 'Manage your RVZ Personnel Services candidate profile — work experience, education, skills and job preferences.';
require __DIR__ . '/includes/header.php';

$provinces = ['Eastern Cape', 'Free State', 'Gauteng', 'KwaZulu-Natal', 'Limpopo', 'Mpumalanga', 'Northern Cape', 'North West', 'Western Cape'];
$noticePeriods = ['Immediately available', '1 week', '2 weeks', '1 month', '2 months', '3+ months'];
require __DIR__ . '/includes/candidate_tabs.php';
render_candidate_tabs('profile');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
    <h2 class="mb-0">My Profile</h2>
    <?php
    $completenessColor = $completenessPct >= 80 ? '#198754' : ($completenessPct >= 40 ? '#fd7e14' : '#dc3545');
    render_speedometer_percent('Profile Complete', $completenessPct, $completenessColor, 90);
    ?>
</div>
<?php if ($user['signed_up_via'] !== 'website'): ?>
    <p class="text-muted">Signed up via <strong><?= h(ucfirst($user['signed_up_via'])) ?></strong><?= $user['avatar_url'] ? ' — profile photo imported automatically' : '' ?>.</p>
<?php endif; ?>

<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= h($e) ?></div><?php endforeach; ?>

<div class="d-flex align-items-center gap-2 mb-3">
    <span class="small text-muted" id="rvzAutosaveStatus">Changes save automatically as you go.</span>
</div>

<form method="post" enctype="multipart/form-data" id="rvzProfileForm">
    <?= csrf_field() ?>

    <div class="card mb-3"><div class="card-body">
        <h5 class="mb-3">Personal Info</h5>
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
                <label class="form-label">Headline</label>
                <input type="text" name="headline" class="form-control" value="<?= h($profile['headline']) ?>" placeholder="e.g. Senior Backend Engineer">
            </div>
        </div>
        <div class="row">
            <div class="col-md-3 mb-3">
                <label class="form-label">Date of birth</label>
                <input type="date" name="date_of_birth" class="form-control" value="<?= h($profile['date_of_birth'] ?? '') ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Gender</label>
                <select name="gender" class="form-select">
                    <option value="">Prefer not to say</option>
                    <?php foreach (['Female', 'Male', 'Non-binary', 'Other'] as $g): ?>
                        <option value="<?= h($g) ?>" <?= ($profile['gender'] ?? '') === $g ? 'selected' : '' ?>><?= h($g) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Nationality</label>
                <input type="text" name="nationality" class="form-control" value="<?= h($profile['nationality'] ?? '') ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">ID / Passport number</label>
                <input type="text" name="id_or_passport" class="form-control" value="<?= h($profile['id_or_passport'] ?? '') ?>">
            </div>
        </div>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Driver's license</label>
                <select name="drivers_license" class="form-select">
                    <?php foreach (['None', 'Code B / EB', 'Code C1 / C', 'Code EC'] as $lic): ?>
                        <option value="<?= h($lic) ?>" <?= ($profile['drivers_license'] ?? '') === $lic ? 'selected' : '' ?>><?= h($lic) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Profile photo</label>
                <input type="file" name="profile_photo" class="form-control" accept=".png,.jpg,.jpeg,.webp">
                <?php if (!empty($profile['profile_photo'])): ?>
                    <img src="<?= h(UPLOAD_URL . $profile['profile_photo']) ?>" class="rounded-circle mt-2" width="64" height="64" alt="Current profile photo" style="object-fit:cover;">
                <?php endif; ?>
            </div>
        </div>
    </div></div>

    <div class="card mb-3"><div class="card-body">
        <h5 class="mb-3">Contact Details</h5>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Phone</label>
                <input type="tel" name="phone" class="form-control" value="<?= h($profile['phone'] ?? '') ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">City / Area</label>
                <input type="text" name="location" class="form-control" value="<?= h($profile['location']) ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Province</label>
                <select name="province" class="form-select">
                    <option value="">Select a province</option>
                    <?php foreach ($provinces as $p): ?>
                        <option value="<?= h($p) ?>" <?= ($profile['province'] ?? '') === $p ? 'selected' : '' ?>><?= h($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Postal code</label>
                <input type="text" name="postal_code" class="form-control" value="<?= h($profile['postal_code'] ?? '') ?>">
            </div>
            <div class="col-md-8 mb-3">
                <label class="form-label">Physical address</label>
                <input type="text" name="physical_address" class="form-control" value="<?= h($profile['physical_address'] ?? '') ?>">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">LinkedIn URL</label>
            <input type="url" name="linkedin_url" class="form-control" value="<?= h($profile['linkedin_url']) ?>">
        </div>
    </div></div>

    <div class="card mb-3"><div class="card-body">
        <h5 class="mb-3">Professional Summary</h5>
        <textarea name="professional_summary" rows="4" class="form-control" placeholder="A short summary of your experience and what you're looking for..."><?= h($profile['professional_summary'] ?? '') ?></textarea>
    </div></div>

    <div class="card mb-3"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Work Experience</h5>
            <button type="button" class="btn btn-sm btn-outline-primary" data-rvz-add="experience">+ Add role</button>
        </div>
        <div id="rvzExperienceRows">
            <?php $expRows = $workExperience ?: [['title' => '', 'employer' => '', 'start' => '', 'end' => '', 'current' => false, 'description' => '']]; ?>
            <?php foreach ($expRows as $row): $isCurrent = !empty($row['current']); ?>
                <div class="row rvz-exp-row border-bottom pb-3 mb-3">
                    <div class="col-md-4 mb-2"><label class="form-label small">Job title</label><input type="text" name="exp_title[]" class="form-control" value="<?= h($row['title'] ?? '') ?>"></div>
                    <div class="col-md-4 mb-2"><label class="form-label small">Employer</label><input type="text" name="exp_employer[]" class="form-control" value="<?= h($row['employer'] ?? '') ?>"></div>
                    <div class="col-md-2 mb-2"><label class="form-label small">Start date</label><input type="date" name="exp_start[]" class="form-control" value="<?= h($row['start'] ?? '') ?>"></div>
                    <div class="col-md-2 mb-2">
                        <label class="form-label small">End date</label>
                        <input type="date" name="exp_end[]" class="form-control rvz-exp-end" value="<?= h($isCurrent ? '' : ($row['end'] ?? '')) ?>" <?= $isCurrent ? 'readonly' : '' ?>>
                        <input type="hidden" name="exp_current[]" class="rvz-exp-current-hidden" value="<?= $isCurrent ? '1' : '0' ?>">
                        <div class="form-check mt-1">
                            <input type="checkbox" class="form-check-input rvz-exp-current-cb" <?= $isCurrent ? 'checked' : '' ?>>
                            <label class="form-check-label small">Current role</label>
                        </div>
                    </div>
                    <div class="col-12 mb-2"><label class="form-label small">Description</label><textarea name="exp_description[]" rows="2" class="form-control"><?= h($row['description'] ?? '') ?></textarea></div>
                    <div class="col-12"><button type="button" class="btn btn-sm btn-link text-danger p-0" data-rvz-remove-row>Remove</button></div>
                </div>
            <?php endforeach; ?>
        </div>
        <template id="rvzExperienceTemplate">
            <div class="row rvz-exp-row border-bottom pb-3 mb-3">
                <div class="col-md-4 mb-2"><label class="form-label small">Job title</label><input type="text" name="exp_title[]" class="form-control"></div>
                <div class="col-md-4 mb-2"><label class="form-label small">Employer</label><input type="text" name="exp_employer[]" class="form-control"></div>
                <div class="col-md-2 mb-2"><label class="form-label small">Start date</label><input type="date" name="exp_start[]" class="form-control"></div>
                <div class="col-md-2 mb-2">
                    <label class="form-label small">End date</label>
                    <input type="date" name="exp_end[]" class="form-control rvz-exp-end">
                    <input type="hidden" name="exp_current[]" class="rvz-exp-current-hidden" value="0">
                    <div class="form-check mt-1">
                        <input type="checkbox" class="form-check-input rvz-exp-current-cb">
                        <label class="form-check-label small">Current role</label>
                    </div>
                </div>
                <div class="col-12 mb-2"><label class="form-label small">Description</label><textarea name="exp_description[]" rows="2" class="form-control"></textarea></div>
                <div class="col-12"><button type="button" class="btn btn-sm btn-link text-danger p-0" data-rvz-remove-row>Remove</button></div>
            </div>
        </template>
    </div></div>

    <div class="card mb-3"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Education</h5>
            <button type="button" class="btn btn-sm btn-outline-primary" data-rvz-add="education">+ Add qualification</button>
        </div>
        <div id="rvzEducationRows">
            <?php $eduRows = $education ?: [['institution' => '', 'qualification' => '', 'field' => '', 'year' => '']]; ?>
            <?php foreach ($eduRows as $row): ?>
                <div class="row rvz-edu-row border-bottom pb-3 mb-3">
                    <div class="col-md-3 mb-2"><label class="form-label small">Institution</label><input type="text" name="edu_institution[]" class="form-control" value="<?= h($row['institution'] ?? '') ?>"></div>
                    <div class="col-md-3 mb-2"><label class="form-label small">Qualification</label><input type="text" name="edu_qualification[]" class="form-control" value="<?= h($row['qualification'] ?? '') ?>"></div>
                    <div class="col-md-3 mb-2"><label class="form-label small">Field of study</label><input type="text" name="edu_field[]" class="form-control" value="<?= h($row['field'] ?? '') ?>"></div>
                    <div class="col-md-3 mb-2"><label class="form-label small">Completion date</label><input type="date" name="edu_year[]" class="form-control" value="<?= h($row['year'] ?? '') ?>"></div>
                    <div class="col-12"><button type="button" class="btn btn-sm btn-link text-danger p-0" data-rvz-remove-row>Remove</button></div>
                </div>
            <?php endforeach; ?>
        </div>
        <template id="rvzEducationTemplate">
            <div class="row rvz-edu-row border-bottom pb-3 mb-3">
                <div class="col-md-3 mb-2"><label class="form-label small">Institution</label><input type="text" name="edu_institution[]" class="form-control"></div>
                <div class="col-md-3 mb-2"><label class="form-label small">Qualification</label><input type="text" name="edu_qualification[]" class="form-control"></div>
                <div class="col-md-3 mb-2"><label class="form-label small">Field of study</label><input type="text" name="edu_field[]" class="form-control"></div>
                <div class="col-md-3 mb-2"><label class="form-label small">Completion date</label><input type="date" name="edu_year[]" class="form-control"></div>
                <div class="col-12"><button type="button" class="btn btn-sm btn-link text-danger p-0" data-rvz-remove-row>Remove</button></div>
            </div>
        </template>
    </div></div>

    <div class="card mb-3"><div class="card-body">
        <h5 class="mb-3">Skills &amp; Languages</h5>
        <div class="mb-3">
            <label class="form-label">Skills</label>
            <input type="text" name="skills" class="form-control" value="<?= h($profile['skills']) ?>" placeholder="Python, Django, SQL, ...">
        </div>
        <div class="mb-3">
            <label class="form-label">Languages</label>
            <input type="text" name="languages" class="form-control" value="<?= h($profile['languages'] ?? '') ?>" placeholder="English, Afrikaans, isiZulu, ...">
        </div>
    </div></div>

    <div class="card mb-3"><div class="card-body">
        <h5 class="mb-3">Job Preferences</h5>
        <div class="row">
            <div class="col-md-3 mb-3">
                <label class="form-label">Expected salary min (ZAR/mo)</label>
                <input type="number" name="salary_expectation_min" class="form-control" value="<?= h((string) ($profile['salary_expectation_min'] ?? '')) ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Expected salary max (ZAR/mo)</label>
                <input type="number" name="salary_expectation_max" class="form-control" value="<?= h((string) ($profile['salary_expectation_max'] ?? '')) ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Notice period</label>
                <select name="notice_period" class="form-select">
                    <option value="">Select</option>
                    <?php foreach ($noticePeriods as $np): ?>
                        <option value="<?= h($np) ?>" <?= ($profile['notice_period'] ?? '') === $np ? 'selected' : '' ?>><?= h($np) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Own transport</label>
                <select name="own_transport" class="form-select">
                    <option value="">Select</option>
                    <?php foreach (['Yes', 'No'] as $ot): ?>
                        <option value="<?= h($ot) ?>" <?= ($profile['own_transport'] ?? '') === $ot ? 'selected' : '' ?>><?= h($ot) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-check mb-2">
            <input type="checkbox" name="willing_to_relocate" id="willing_to_relocate" class="form-check-input" <?= !empty($profile['willing_to_relocate']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="willing_to_relocate">Willing to relocate</label>
        </div>
        <div class="form-check">
            <input type="checkbox" name="willing_to_travel" id="willing_to_travel" class="form-check-input" <?= !empty($profile['willing_to_travel']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="willing_to_travel">Willing to travel for work</label>
        </div>
    </div></div>

    <div class="card mb-3"><div class="card-body">
        <h5 class="mb-1">Employment Equity Information <span class="badge rvz-badge-soft">Optional</span></h5>
        <p class="small text-muted">This is special personal information under POPIA and is only used, if you consent, for Employment Equity reporting purposes when an employer requires it. Leave the consent box unchecked to skip this section entirely.</p>
        <div class="form-check mb-3">
            <input type="checkbox" name="ee_consent" id="ee_consent" class="form-check-input" <?= !empty($profile['ee_consent']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="ee_consent">I consent to RVZ processing this information for Employment Equity reporting</label>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">EE / race status</label>
                <select name="race_ee_status" class="form-select">
                    <option value="">Prefer not to say</option>
                    <?php foreach (['African', 'Coloured', 'Indian', 'White', 'Other'] as $r): ?>
                        <option value="<?= h($r) ?>" <?= ($profile['race_ee_status'] ?? '') === $r ? 'selected' : '' ?>><?= h($r) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Disability status</label>
                <select name="disability_status" class="form-select">
                    <option value="">Prefer not to say</option>
                    <option value="None" <?= ($profile['disability_status'] ?? '') === 'None' ? 'selected' : '' ?>>None</option>
                    <option value="Has a disability" <?= ($profile['disability_status'] ?? '') === 'Has a disability' ? 'selected' : '' ?>>Has a disability</option>
                </select>
            </div>
        </div>
    </div></div>

    <div class="card mb-3"><div class="card-body">
        <h5 class="mb-3">Resume / CV</h5>
        <label class="form-label">Resume <?= $profile['resume_path'] ? '(on file — upload to replace)' : '' ?></label>
        <input type="file" name="resume" class="form-control" accept=".pdf,.doc,.docx">
        <?php if ($profile['resume_path']): ?>
            <div class="form-text"><a href="<?= h(UPLOAD_URL . $profile['resume_path']) ?>" target="_blank">View current resume</a></div>
        <?php endif; ?>
    </div></div>

    <div class="card mb-4"><div class="card-body">
        <h5 class="mb-3">Notifications</h5>
        <div class="form-check mb-2">
            <input type="checkbox" name="opt_in_job_alerts" id="opt_in_job_alerts" class="form-check-input" <?= !empty($profile['opt_in_job_alerts']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="opt_in_job_alerts">Email me new job vacancies that match my profile</label>
        </div>
        <div class="form-check">
            <input type="checkbox" name="opt_in_newsletter" id="opt_in_newsletter" class="form-check-input" <?= !empty($profile['opt_in_newsletter']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="opt_in_newsletter">Subscribe me to the RVZ newsletter</label>
        </div>
    </div></div>

    <button type="submit" class="btn btn-primary btn-lg">Save Profile Now</button>
    <span class="text-muted small ms-2">Your changes are already being saved automatically — this button is just for peace of mind.</span>
</form>

<script>
document.querySelectorAll('[data-rvz-add]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var kind = btn.getAttribute('data-rvz-add');
        var tpl = document.getElementById(kind === 'experience' ? 'rvzExperienceTemplate' : 'rvzEducationTemplate');
        var container = document.getElementById(kind === 'experience' ? 'rvzExperienceRows' : 'rvzEducationRows');
        container.appendChild(tpl.content.cloneNode(true));
    });
});
document.addEventListener('click', function (e) {
    if (e.target.matches('[data-rvz-remove-row]')) {
        e.target.closest('.rvz-exp-row, .rvz-edu-row').remove();
    }
});

// "Current role" checkbox: makes the end-date field readonly and blank
// instead of disabling it, since a disabled field would drop out of the
// submitted array entirely and misalign every row after it.
document.addEventListener('change', function (e) {
    if (e.target.matches('.rvz-exp-current-cb')) {
        var row = e.target.closest('.rvz-exp-row');
        var endInput = row.querySelector('.rvz-exp-end');
        var hidden = row.querySelector('.rvz-exp-current-hidden');
        hidden.value = e.target.checked ? '1' : '0';
        endInput.readOnly = e.target.checked;
        if (e.target.checked) endInput.value = '';
    }
});

// --- Autosave ---------------------------------------------------------
(function () {
    var form = document.getElementById('rvzProfileForm');
    var status = document.getElementById('rvzAutosaveStatus');
    var autosaveUrl = <?= json_encode(base_url('ajax/autosave_profile.php')) ?>;
    var debounceTimer = null;
    var saving = false;
    var pendingAgain = false;

    function setStatus(text) {
        status.textContent = text;
    }

    function doSave() {
        if (saving) {
            pendingAgain = true;
            return;
        }
        saving = true;
        setStatus('Saving…');

        var fd = new FormData(form);
        fd.delete('resume');
        fd.delete('profile_photo');

        fetch(autosaveUrl, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                saving = false;
                if (data.ok) {
                    var now = new Date();
                    var hh = String(now.getHours()).padStart(2, '0');
                    var mm = String(now.getMinutes()).padStart(2, '0');
                    setStatus('All changes saved at ' + hh + ':' + mm + '.');
                } else {
                    setStatus('Could not autosave (' + (data.error || 'unknown error') + ') — click "Save Profile Now" below.');
                }
                if (pendingAgain) { pendingAgain = false; doSave(); }
            })
            .catch(function () {
                saving = false;
                setStatus('Could not autosave — check your connection, or click "Save Profile Now" below.');
            });
    }

    function scheduleSave() {
        setStatus('Unsaved changes…');
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(doSave, 1500);
    }

    form.addEventListener('input', function (e) {
        if (e.target.type === 'file') return;
        scheduleSave();
    });
    form.addEventListener('change', function (e) {
        if (e.target.type === 'file') return;
        scheduleSave();
    });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>

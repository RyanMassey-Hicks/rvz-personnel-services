<?php
/**
 * Shared candidate-profile save logic — used by both the normal profile.php
 * form submit and ajax/autosave_profile.php, so the two can never drift out
 * of sync. $files may be empty (autosave never re-sends files); when empty,
 * the existing resume_path/profile_photo on file are left untouched.
 */
function save_candidate_profile(array $user, array $profile, array $post, array $files): array
{
    $errors = [];

    $firstName = trim($post['first_name'] ?? '');
    $lastName = trim($post['last_name'] ?? '');
    $phone = trim($post['phone'] ?? '');
    $idOrPassport = trim($post['id_or_passport'] ?? '');
    $dob = trim($post['date_of_birth'] ?? '');
    $gender = trim($post['gender'] ?? '');
    $nationality = trim($post['nationality'] ?? '');
    $driversLicense = trim($post['drivers_license'] ?? '');
    $ownTransport = trim($post['own_transport'] ?? '');
    $location = trim($post['location'] ?? '');
    $province = trim($post['province'] ?? '');
    $postalCode = trim($post['postal_code'] ?? '');
    $physicalAddress = trim($post['physical_address'] ?? '');
    $linkedinUrl = trim($post['linkedin_url'] ?? '');
    $headline = trim($post['headline'] ?? '');
    $summary = trim($post['professional_summary'] ?? '');
    $skills = trim($post['skills'] ?? '');
    $languages = trim($post['languages'] ?? '');
    $salaryMin = ($post['salary_expectation_min'] ?? '') !== '' ? (int) $post['salary_expectation_min'] : null;
    $salaryMax = ($post['salary_expectation_max'] ?? '') !== '' ? (int) $post['salary_expectation_max'] : null;
    $noticePeriod = trim($post['notice_period'] ?? '');
    $willingRelocate = isset($post['willing_to_relocate']) ? 1 : 0;
    $willingTravel = isset($post['willing_to_travel']) ? 1 : 0;
    $optInJobAlerts = isset($post['opt_in_job_alerts']) ? 1 : 0;
    $optInNewsletter = isset($post['opt_in_newsletter']) ? 1 : 0;
    $eeConsent = isset($post['ee_consent']) ? 1 : 0;
    $raceEeStatus = $eeConsent ? trim($post['race_ee_status'] ?? '') : '';
    $disabilityStatus = $eeConsent ? trim($post['disability_status'] ?? '') : '';

    if ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
        $errors[] = 'Please enter a valid date of birth.';
    }

    // Work experience rows arrive as parallel arrays from the repeatable form rows.
    $expTitles = $post['exp_title'] ?? [];
    $expEmployers = $post['exp_employer'] ?? [];
    $expStarts = $post['exp_start'] ?? [];
    $expEnds = $post['exp_end'] ?? [];
    $expCurrents = $post['exp_current'] ?? [];
    $expDesc = $post['exp_description'] ?? [];
    $workExperienceNew = [];
    foreach ($expTitles as $i => $title) {
        $title = trim($title);
        if ($title === '' && trim($expEmployers[$i] ?? '') === '') continue;
        $isCurrent = ($expCurrents[$i] ?? '0') === '1';
        $workExperienceNew[] = [
            'title' => $title,
            'employer' => trim($expEmployers[$i] ?? ''),
            'start' => trim($expStarts[$i] ?? ''),
            'end' => $isCurrent ? 'Present' : trim($expEnds[$i] ?? ''),
            'current' => $isCurrent,
            'description' => trim($expDesc[$i] ?? ''),
        ];
    }

    $eduInstitutions = $post['edu_institution'] ?? [];
    $eduQualifications = $post['edu_qualification'] ?? [];
    $eduFields = $post['edu_field'] ?? [];
    $eduYears = $post['edu_year'] ?? [];
    $educationNew = [];
    foreach ($eduInstitutions as $i => $inst) {
        $inst = trim($inst);
        if ($inst === '' && trim($eduQualifications[$i] ?? '') === '') continue;
        $educationNew[] = [
            'institution' => $inst,
            'qualification' => trim($eduQualifications[$i] ?? ''),
            'field' => trim($eduFields[$i] ?? ''),
            'year' => trim($eduYears[$i] ?? ''),
        ];
    }

    $resumePath = $profile['resume_path'] ?? '';
    $resumeText = $profile['resume_text'] ?? '';
    if (!empty($files['resume']['name'])) {
        $file = $files['resume'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'There was a problem uploading your resume.';
        } elseif ($file['size'] > MAX_UPLOAD_BYTES) {
            $errors[] = 'Resume must be smaller than 5MB.';
        } elseif (!in_array($ext, ['pdf', 'doc', 'docx'], true)) {
            $errors[] = 'Resume must be a PDF or Word document.';
        } else {
            $filename = safe_upload_filename($file['name'], $user['id']);
            $destination = UPLOAD_DIR . 'resumes/' . $filename;
            move_uploaded_file($file['tmp_name'], $destination);
            $resumePath = 'resumes/' . $filename;
            // Best-effort — Direct Search can then match a keyword that only
            // appears inside the file, not just the Skills field. Never
            // blocks the upload if extraction finds nothing (e.g. a scanned
            // PDF, or a legacy .doc — see includes/resume_text.php).
            $resumeText = extract_resume_text($destination, $ext);
        }
    }

    $profilePhotoPath = $profile['profile_photo'] ?? '';
    if (!empty($files['profile_photo']['name'])) {
        $file = $files['profile_photo'];
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
            $profilePhotoPath = 'profile_photos/' . $filename;
        }
    }

    if ($errors) {
        return ['errors' => $errors];
    }

    db()->prepare('UPDATE users SET first_name = ?, last_name = ? WHERE id = ?')
        ->execute([$firstName, $lastName, $user['id']]);

    $stmt = db()->prepare(
        'UPDATE candidate_profiles SET
            headline = ?, location = ?, linkedin_url = ?, skills = ?, resume_path = ?, resume_text = ?,
            phone = ?, id_or_passport = ?, date_of_birth = ?, gender = ?, nationality = ?,
            drivers_license = ?, own_transport = ?, province = ?, postal_code = ?, physical_address = ?,
            professional_summary = ?, languages = ?, salary_expectation_min = ?, salary_expectation_max = ?,
            notice_period = ?, willing_to_relocate = ?, willing_to_travel = ?,
            work_experience = ?, education = ?, profile_photo = ?,
            opt_in_job_alerts = ?, opt_in_newsletter = ?, ee_consent = ?, race_ee_status = ?, disability_status = ?
         WHERE user_id = ?'
    );
    $stmt->execute([
        $headline, $location, $linkedinUrl, $skills, $resumePath, $resumeText,
        $phone, $idOrPassport, $dob ?: null, $gender, $nationality,
        $driversLicense, $ownTransport, $province, $postalCode, $physicalAddress,
        $summary, $languages, $salaryMin, $salaryMax,
        $noticePeriod, $willingRelocate, $willingTravel,
        json_encode($workExperienceNew), json_encode($educationNew), $profilePhotoPath,
        $optInJobAlerts, $optInNewsletter, $eeConsent, $raceEeStatus, $disabilityStatus,
        $user['id'],
    ]);

    return ['errors' => [], 'resume_path' => $resumePath, 'profile_photo' => $profilePhotoPath];
}

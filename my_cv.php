<?php
/**
 * Generates a professional, branded PDF CV/résumé from the candidate's
 * profile data — no third-party PDF library, built on includes/pdf.php.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/candidate_tabs.php';
require_login();

$user = current_user();
if ($user['role'] === 'recruiter') {
    redirect('/dashboard.php');
}

$stmt = db()->prepare('SELECT * FROM candidate_profiles WHERE user_id = ?');
$stmt->execute([$user['id']]);
$profile = $stmt->fetch() ?: [];

$workExperience = json_decode($profile['work_experience'] ?? '[]', true) ?: [];
$education = json_decode($profile['education'] ?? '[]', true) ?: [];

/** Formats an ISO YYYY-MM-DD as YYYY/MM/DD for display; passes through anything else unchanged (old free-text entries, "Present"). */
function format_date_display(?string $value): string
{
    $value = trim((string) $value);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return str_replace('-', '/', $value);
    }
    return $value;
}

function build_candidate_cv_pdf(array $user, array $profile, array $workExperience, array $education): SimplePdf
{
    $NAVY = [10, 31, 68];
    $WHITE = [255, 255, 255];
    $SILVER = [210, 216, 226];
    $GRAY = [90, 90, 90];

    $pdf = new SimplePdf();
    $fullName = trim($user['first_name'] . ' ' . $user['last_name']) ?: $user['username'];

    // --- Header band ---
    $pdf->addRect(0, 0, $pdf->pageWidth(), 110, $NAVY);
    $pdf->addTextAt(56, 45, $fullName, 24, true, $WHITE);
    if (!empty($profile['headline'])) {
        $pdf->addTextAt(56, 66, $profile['headline'], 13, false, $SILVER);
    }
    $contactBits = array_filter([
        $user['email'],
        $profile['phone'] ?? '',
        trim(($profile['location'] ?? '') . (!empty($profile['province']) ? ', ' . $profile['province'] : '')),
    ]);
    $pdf->addTextAt(56, 90, implode('   |   ', $contactBits), 10, false, $SILVER);
    $pdf->setCursorY($pdf->pageHeight() - 110 - 26);

    if (!empty($profile['professional_summary'])) {
        $pdf->addSectionLabel('Professional Summary', $NAVY);
        $pdf->addText($profile['professional_summary'], 10.5, false, [30, 30, 30]);
    }

    if ($workExperience) {
        $pdf->addSectionLabel('Work Experience', $NAVY);
        foreach ($workExperience as $job) {
            if (empty($job['title']) && empty($job['employer'])) continue;
            $pdf->addText(trim(($job['title'] ?? '') . ' — ' . ($job['employer'] ?? '')), 11.5, true, [20, 20, 20]);
            $dates = trim(format_date_display($job['start'] ?? '') . ' - ' . format_date_display($job['end'] ?? ''), ' -');
            if ($dates !== '') {
                $pdf->addText($dates, 9.5, false, $GRAY);
            }
            if (!empty($job['description'])) {
                $pdf->addText($job['description'], 10, false, [40, 40, 40]);
            }
            $pdf->addSpacer(8);
        }
    }

    if ($education) {
        $pdf->addSectionLabel('Education', $NAVY);
        foreach ($education as $edu) {
            if (empty($edu['institution']) && empty($edu['qualification'])) continue;
            $pdf->addText(trim(($edu['qualification'] ?? '') . ' — ' . ($edu['institution'] ?? '')), 11, true, [20, 20, 20]);
            $meta = trim(($edu['field'] ?? '') . (!empty($edu['year']) ? ' (' . $edu['year'] . ')' : ''));
            if ($meta !== '') {
                $pdf->addText($meta, 9.5, false, $GRAY);
            }
            $pdf->addSpacer(6);
        }
    }

    if (!empty($profile['skills'])) {
        $pdf->addSectionLabel('Skills', $NAVY);
        $pdf->addText($profile['skills'], 10.5, false, [30, 30, 30]);
    }

    if (!empty($profile['languages'])) {
        $pdf->addSectionLabel('Languages', $NAVY);
        $pdf->addText($profile['languages'], 10.5, false, [30, 30, 30]);
    }

    $prefBits = [];
    if (!empty($profile['own_transport'])) $prefBits[] = 'Own transport: ' . $profile['own_transport'];
    if (!empty($profile['notice_period'])) $prefBits[] = 'Notice period: ' . $profile['notice_period'];
    if (!empty($profile['salary_expectation_min'])) {
        $prefBits[] = 'Expected salary: ' . format_zar((float) $profile['salary_expectation_min'])
            . (!empty($profile['salary_expectation_max']) ? ' - ' . format_zar((float) $profile['salary_expectation_max']) : '') . '/mo';
    }
    if (!empty($profile['willing_to_relocate'])) $prefBits[] = 'Willing to relocate';
    if (!empty($profile['willing_to_travel'])) $prefBits[] = 'Willing to travel';
    if (!empty($profile['drivers_license']) && $profile['drivers_license'] !== 'None') $prefBits[] = 'Driver\'s license: ' . $profile['drivers_license'];
    if ($prefBits) {
        $pdf->addSectionLabel('Job Preferences', $NAVY);
        $pdf->addText(implode('  •  ', $prefBits), 10, false, [30, 30, 30]);
    }

    $pdf->addFooterToAllPages(
        SITE_NAME . '  •  Generated via ' . SITE_URL . '  •  © ' . date('Y') . ' ' . COMPANY_LEGAL_NAME . '  •  Confidential'
    );

    return $pdf;
}

if (($_GET['download'] ?? '') === '1') {
    $pdf = build_candidate_cv_pdf($user, $profile, $workExperience, $education);
    $bytes = $pdf->output();
    $filename = preg_replace('/[^A-Za-z0-9_-]+/', '_', trim($user['first_name'] . '_' . $user['last_name']) ?: $user['username']) . '_CV.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($bytes));
    echo $bytes;
    exit;
}

$missingFields = [];
if (empty($profile['professional_summary'])) $missingFields[] = 'Professional Summary';
if (!$workExperience) $missingFields[] = 'Work Experience';
if (!$education) $missingFields[] = 'Education';
if (empty($profile['skills'])) $missingFields[] = 'Skills';

$pageTitle = 'My CV';
require __DIR__ . '/includes/header.php';
render_candidate_tabs('cv');
?>
<h2 class="mb-1">My CV</h2>
<p class="text-muted mb-4">A professional, branded PDF built automatically from your profile — ready to send to any employer.</p>

<?php if ($missingFields): ?>
    <div class="alert alert-warning">
        Your CV will look stronger with a few more details:
        <strong><?= h(implode(', ', $missingFields)) ?></strong>.
        <a href="<?= h(base_url('profile.php')) ?>">Update your profile &rarr;</a>
    </div>
<?php endif; ?>

<div class="card"><div class="card-body text-center py-5">
    <p class="mb-4">Your CV is generated fresh from your profile every time you download it — keep your profile
    up to date and your CV stays current automatically.</p>
    <a href="<?= h(base_url('my_cv.php?download=1')) ?>" class="btn btn-primary btn-lg">Download My CV (PDF)</a>
</div></div>

<?php require __DIR__ . '/includes/footer.php'; ?>

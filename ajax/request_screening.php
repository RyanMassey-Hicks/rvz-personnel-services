<?php
/** MIE background-screening handoff — no live MIE API access, just a tracked request + email to RVZ's team. */
require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

if (!is_logged_in() || !is_recruiter()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Recruiter access only.']);
    exit;
}

$sent = $_POST['csrf_token'] ?? '';
if (!$sent || !hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid security token, please reload the page.']);
    exit;
}

$applicationId = (int) ($_POST['application_id'] ?? 0);
$user = current_user();

$stmt = db()->prepare(
    'SELECT applications.*, jobs.title AS job_title, users.first_name, users.last_name, users.email, users.username
     FROM applications
     JOIN jobs ON jobs.id = applications.job_id
     JOIN users ON users.id = applications.candidate_id
     WHERE applications.id = ? AND jobs.company_id = ?'
);
$stmt->execute([$applicationId, current_recruiter_company_id()]);
$application = $stmt->fetch();
if (!$application) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Application not found.']);
    exit;
}

$stmt = db()->prepare('SELECT id FROM screening_requests WHERE application_id = ? AND status = "requested"');
$stmt->execute([$applicationId]);
if ($stmt->fetch()) {
    echo json_encode(['ok' => true, 'already' => true]);
    exit;
}

db()->prepare('INSERT INTO screening_requests (application_id, requested_by) VALUES (?, ?)')
    ->execute([$applicationId, $user['id']]);

$candidateName = trim($application['first_name'] . ' ' . $application['last_name']) ?: $application['username'];
send_email(
    PRIVILEGED_RECRUITER_EMAIL,
    'MIE screening request: ' . $candidateName . ' — ' . $application['job_title'],
    email_wrap(
        '<p>' . h($user['email']) . ' has requested a background screening for a candidate.</p>'
        . '<p><strong>Candidate:</strong> ' . h($candidateName) . ' (' . h($application['email']) . ')</p>'
        . '<p><strong>Job:</strong> ' . h($application['job_title']) . '</p>'
        . ($application['resume_path'] ? '<p><a href="' . h(UPLOAD_URL . $application['resume_path']) . '">View resume</a></p>' : '')
        . '<p>Process this via MIE\'s portal: <a href="https://epcv.mie.co.za/Account/TermsLogin">epcv.mie.co.za</a></p>'
        . '<p><a href="' . h(base_url('pipeline.php?id=' . $application['job_id'])) . '">View in pipeline</a></p>'
    )
);

echo json_encode(['ok' => true, 'already' => false]);

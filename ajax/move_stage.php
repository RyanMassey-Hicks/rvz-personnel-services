<?php
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
$newStage = $_POST['stage'] ?? '';

$validStages = ['applied', 'screening', 'interview', 'offer', 'hired', 'rejected'];
if (!in_array($newStage, $validStages, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid stage.']);
    exit;
}

$user = current_user();

// Ownership check: the application must belong to a job THIS recruiter posted.
$stmt = db()->prepare(
    'SELECT applications.*, jobs.title AS job_title, jobs.id AS job_id, jobs.company_id AS company_id, companies.name AS company_name,
            users.email AS candidate_email, users.first_name AS candidate_first_name, users.last_name AS candidate_last_name,
            users.username AS candidate_username, candidate_profiles.opt_in_job_alerts
     FROM applications
     JOIN jobs ON jobs.id = applications.job_id
     JOIN companies ON companies.id = jobs.company_id
     JOIN users ON users.id = applications.candidate_id
     LEFT JOIN candidate_profiles ON candidate_profiles.user_id = applications.candidate_id
     WHERE applications.id = ? AND jobs.company_id = ?'
);
$stmt->execute([$applicationId, current_recruiter_company_id()]);
$application = $stmt->fetch();
if (!$application) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Application not found.']);
    exit;
}

$stageChanged = $newStage !== $application['stage'];
db()->prepare('UPDATE applications SET stage = ? WHERE id = ?')->execute([$newStage, $applicationId]);

$stageLabels = ['applied' => 'Applied', 'screening' => 'Screening', 'interview' => 'Interview', 'offer' => 'Offer', 'hired' => 'Hired', 'rejected' => 'Not selected'];
if ($stageChanged) {
    $stageLabel = $stageLabels[$newStage] ?? ucfirst($newStage);
    if (!empty($application['opt_in_job_alerts'])) {
        send_stage_change_notification(
            ['email' => $application['candidate_email'], 'first_name' => $application['candidate_first_name'], 'last_name' => $application['candidate_last_name'], 'username' => $application['candidate_username']],
            ['id' => $application['job_id'], 'title' => $application['job_title'], 'company_id' => $application['company_id']],
            $application['company_name'],
            $stageLabel
        );
    }
    create_notification(
        (int) $application['candidate_id'],
        'application_status',
        'Application update: ' . $application['job_title'],
        $application['company_name'] . ' moved you to "' . $stageLabel . '"',
        base_url('my_applications.php')
    );
}

echo json_encode(['ok' => true, 'stage' => $newStage]);

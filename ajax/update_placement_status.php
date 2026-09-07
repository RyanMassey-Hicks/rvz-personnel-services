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
$status = $_POST['status'] ?? '';
if (!in_array($status, ['', 'active', 'ended'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid status.']);
    exit;
}

$user = current_user();
$stmt = db()->prepare(
    'SELECT applications.id FROM applications JOIN jobs ON jobs.id = applications.job_id
     WHERE applications.id = ? AND jobs.company_id = ? AND applications.stage = "hired"'
);
$stmt->execute([$applicationId, current_recruiter_company_id()]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Application not found.']);
    exit;
}

db()->prepare('UPDATE applications SET placement_status = ? WHERE id = ?')->execute([$status ?: null, $applicationId]);
echo json_encode(['ok' => true]);

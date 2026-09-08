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
$flag = $_POST['flag'] ?? '';
$value = ($_POST['value'] ?? '') === '1' ? 1 : 0;

$allowedFlags = [
    'viewed_by_recruiter' => 'viewed_at',
    'contacted' => 'contacted_at',
    'shortlisted' => null,
];
if (!array_key_exists($flag, $allowedFlags)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid flag.']);
    exit;
}

$user = current_user();
$stmt = db()->prepare(
    'SELECT applications.id FROM applications
     JOIN jobs ON jobs.id = applications.job_id
     WHERE applications.id = ? AND jobs.company_id = ?'
);
$stmt->execute([$applicationId, current_recruiter_company_id()]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Application not found.']);
    exit;
}

$timestampCol = $allowedFlags[$flag];
if ($timestampCol) {
    $sql = "UPDATE applications SET `$flag` = ?, `$timestampCol` = ? WHERE id = ?";
    db()->prepare($sql)->execute([$value, $value ? date('Y-m-d H:i:s') : null, $applicationId]);
} else {
    db()->prepare("UPDATE applications SET `$flag` = ? WHERE id = ?")->execute([$value, $applicationId]);
}

echo json_encode(['ok' => true]);

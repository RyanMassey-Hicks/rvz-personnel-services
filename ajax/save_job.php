<?php
require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please sign in to save jobs.']);
    exit;
}

$sent = $_POST['csrf_token'] ?? '';
if (!$sent || !hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid security token, please reload the page.']);
    exit;
}

$user = current_user();
$jobId = (int) ($_POST['job_id'] ?? 0);

$stmt = db()->prepare('SELECT id FROM jobs WHERE id = ?');
$stmt->execute([$jobId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Job not found.']);
    exit;
}

$stmt = db()->prepare('SELECT id FROM saved_jobs WHERE user_id = ? AND job_id = ?');
$stmt->execute([$user['id'], $jobId]);
$existing = $stmt->fetch();

if ($existing) {
    db()->prepare('DELETE FROM saved_jobs WHERE id = ?')->execute([$existing['id']]);
    echo json_encode(['ok' => true, 'saved' => false]);
} else {
    db()->prepare('INSERT INTO saved_jobs (user_id, job_id) VALUES (?, ?)')->execute([$user['id'], $jobId]);
    echo json_encode(['ok' => true, 'saved' => true]);
}

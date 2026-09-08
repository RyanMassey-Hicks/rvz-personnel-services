<?php
/** Autosaves the candidate profile form as the user types — same save logic as profile.php's manual submit, minus file uploads. */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/profile_save.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Please sign in.']);
    exit;
}

$user = current_user();
if ($user['role'] === 'recruiter') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not available for recruiter accounts.']);
    exit;
}

$sent = $_POST['csrf_token'] ?? '';
if (!$sent || !hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Security token expired — reload the page.']);
    exit;
}

$stmt = db()->prepare('SELECT * FROM candidate_profiles WHERE user_id = ?');
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();
if (!$profile) {
    db()->prepare('INSERT INTO candidate_profiles (user_id) VALUES (?)')->execute([$user['id']]);
    $stmt->execute([$user['id']]);
    $profile = $stmt->fetch();
}

$result = save_candidate_profile($user, $profile, $_POST, []);

if ($result['errors']) {
    echo json_encode(['ok' => false, 'error' => implode(' ', $result['errors'])]);
    exit;
}

echo json_encode(['ok' => true]);

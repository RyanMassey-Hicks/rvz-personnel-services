<?php
/** Removes one browser's PushSubscription (e.g. the user clicked "unsubscribe" or revoked permission client-side). */
require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Please sign in.']);
    exit;
}

$sent = $_POST['csrf_token'] ?? '';
if (!$sent || !hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid security token, please reload the page.']);
    exit;
}

$user = current_user();
$endpoint = trim($_POST['endpoint'] ?? '');
if ($endpoint === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing endpoint.']);
    exit;
}

db()->prepare('DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?')->execute([$user['id'], $endpoint]);
echo json_encode(['ok' => true]);

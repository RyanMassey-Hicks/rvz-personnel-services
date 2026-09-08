<?php
/** Stores (or refreshes) one browser's PushSubscription for the logged-in user — see includes/webpush.php. */
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
$sub = json_decode($_POST['subscription'] ?? '', true);

$endpoint = trim($sub['endpoint'] ?? '');
$p256dh = trim($sub['keys']['p256dh'] ?? '');
$auth = trim($sub['keys']['auth'] ?? '');

if ($endpoint === '' || $p256dh === '' || $auth === '' || !is_safe_http_url($endpoint)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid subscription.']);
    exit;
}

$userAgent = mb_substr(trim($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

db()->prepare(
    'INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, user_agent) VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth), user_agent = VALUES(user_agent)'
)->execute([$user['id'], $endpoint, $p256dh, $auth, $userAgent]);

echo json_encode(['ok' => true]);

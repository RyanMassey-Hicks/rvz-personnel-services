<?php
/** Logs a cookie-consent choice for POPIA audit purposes. Public endpoint — no login required. */
require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

$choice = $_POST['choice'] ?? '';
if (!in_array($choice, ['accepted', 'rejected', 'custom'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}
$aiConsent = ($_POST['ai'] ?? '0') === '1' ? 1 : 0;
$analyticsConsent = ($_POST['analytics'] ?? '0') === '1' ? 1 : 0;
$advertisingConsent = ($_POST['advertising'] ?? '0') === '1' ? 1 : 0;

$user = current_user();
$stmt = db()->prepare(
    'INSERT INTO consent_logs (user_id, choice, ai_consent, analytics_consent, advertising_consent, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    $user['id'] ?? null,
    $choice,
    $aiConsent,
    $analyticsConsent,
    $advertisingConsent,
    $_SERVER['REMOTE_ADDR'] ?? '',
    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
]);

echo json_encode(['ok' => true]);

<?php
/**
 * Step 2 of 2 for "Create a social ad" (see ads.php): renders the image for
 * a prompt produced by ajax/ad_plan.php, saves it, and records it. This is
 * the only slow external call in the request, so it fits inside the web
 * server's ~60s ceiling on its own (see ad_plan.php for why it's split).
 */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/ad_auth.php';

header('Content-Type: application/json');
$job = ad_authorize_request();
$user = current_user();

// Keeps PHP's own execution limit from firing first inside a long-but-legal
// request; the real ceiling is the web server's, which this can't change —
// the image call's own time budget is what keeps us under that.
@set_time_limit(90);

$prompt = trim($_POST['prompt'] ?? '');
$copy = trim($_POST['copy'] ?? '');
if ($prompt === '' || mb_strlen($prompt) > 4000) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing or invalid ad prompt — please try generating again.']);
    exit;
}

try {
    $result = ai_generate_image_for_company($prompt, $job);
} catch (AiImageException $e) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}

$ext = $result['mime'] === 'image/jpeg' ? 'jpg' : 'png';
$filename = 'ad_' . (int) $job['id'] . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$destDir = UPLOAD_DIR . 'ads/';
if (!is_dir($destDir)) {
    @mkdir($destDir, 0755, true);
}
if (file_put_contents($destDir . $filename, $result['bytes']) === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'The image was generated but could not be saved. Please try again.']);
    exit;
}

db()->prepare('INSERT INTO ad_generations (job_id, user_id, prompt, image_path, provider, copy_text) VALUES (?, ?, ?, ?, ?, ?)')
    ->execute([$job['id'], $user['id'], $prompt, 'ads/' . $filename, $result['provider'], $copy !== '' ? $copy : null]);

echo json_encode([
    'ok' => true,
    'image_url' => UPLOAD_URL . 'ads/' . $filename,
    'copy' => $copy !== '' ? $copy : null,
]);

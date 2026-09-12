<?php
/**
 * Step 1 of 2 for "Create a social ad" (see ads.php): asks Gemini to plan a
 * caption + scene, and returns the finished image prompt. Deliberately a
 * separate request from ajax/ad_render.php — this host's web server kills
 * any request over ~60s with a 503, and Gemini planning (up to 12s) plus a
 * Pollinations render (routinely 40-45s) bundled into one request blew
 * through that. Split in two, each stays well inside the limit.
 */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/ad_auth.php';

header('Content-Type: application/json');
$job = ad_authorize_request(); // exits with a JSON error on any failure

$style = trim($_POST['style'] ?? 'professional') ?: 'professional';
$brandGuidelines = $job['ai_brand_guidelines'] ?? '';

$copy = null;
try {
    $content = gemini_generate_ad_content($job['title'], $job['company_name'], $job['location'], $style, $brandGuidelines);
    $copy = $content['copy'];
    $prompt = build_ad_prompt_from_scene($content['scene'], $job['title'], $job['company_name'], $style);
} catch (AiImageException $e) {
    // Gemini being unavailable never blocks the ad — fall back to the
    // templated prompt; the image still gets rendered, just without a
    // Gemini-written caption.
    error_log('Gemini ad-content fallback: ' . $e->getMessage());
    $prompt = build_ad_prompt($job, $job['company_name'], $style, $brandGuidelines);
}

echo json_encode(['ok' => true, 'prompt' => $prompt, 'copy' => $copy]);

<?php
/**
 * Tracks outbound job shares, then redirects to the real share target.
 * Routing every share button through here (instead of linking straight to
 * the destination) is what lets the recruiter dashboard show a "Shares"
 * count per job — there's no other reliable way to know a share button was
 * clicked once the browser has left the page.
 *
 * Usage: share.php?id=123&platform=linkedin|facebook|whatsapp|sms|x
 */
require __DIR__ . '/includes/bootstrap.php';

$jobId = (int) ($_GET['id'] ?? 0);
$platform = $_GET['platform'] ?? '';

if (!in_array($platform, ['linkedin', 'facebook', 'whatsapp', 'sms', 'x'], true)) {
    http_response_code(400);
    die('Unknown share platform.');
}

$stmt = db()->prepare('SELECT id, title FROM jobs WHERE id = ?');
$stmt->execute([$jobId]);
$job = $stmt->fetch();
if (!$job) {
    http_response_code(404);
    die('Job not found.');
}

$stmt = db()->prepare('UPDATE jobs SET shares_count = shares_count + 1 WHERE id = ?');
$stmt->execute([$jobId]);

$absoluteUrl = base_url('job.php?id=' . $jobId);
$text = $job['title'] . ' — ' . SITE_NAME;
$targets = [
    'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . urlencode($absoluteUrl),
    'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($absoluteUrl),
    'whatsapp' => 'https://wa.me/?text=' . urlencode($text . ' ' . $absoluteUrl),
    'sms' => 'sms:?body=' . urlencode($text . ' ' . $absoluteUrl),
    'x' => 'https://twitter.com/intent/tweet?url=' . urlencode($absoluteUrl) . '&text=' . urlencode($text),
];

redirect($targets[$platform]);

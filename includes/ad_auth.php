<?php
/**
 * Shared guard for the two "Create a social ad" AJAX endpoints
 * (ajax/ad_plan.php, ajax/ad_render.php). Applies exactly the same access
 * rules as the ads.php page itself — same paid-feature gate, CSRF check, and
 * "this job belongs to your company" ownership check — but answers in JSON
 * and exits on failure, since these are fetch() targets, not page loads.
 *
 * Returns the job row (joined with the company's AI provider settings)
 * on success.
 */
function ad_authorize_request(): array
{
    if (!is_logged_in() || !is_recruiter()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Please sign in as a recruiter.']);
        exit;
    }
    // Same gate as require_active_recruiter() on the page, without its redirect.
    if (!has_current_sla() || !has_active_recruiter_subscription()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Ad generation is not enabled on this account — see "Looking for More?" on the Pricing page.']);
        exit;
    }

    $sent = $_POST['csrf_token'] ?? '';
    if (!$sent || !hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid security token, please reload the page.']);
        exit;
    }

    $jobId = (int) ($_POST['job_id'] ?? 0);
    $stmt = db()->prepare(
        'SELECT jobs.*, companies.name AS company_name, companies.ai_image_provider,
                companies.ai_image_api_key_encrypted, companies.ai_brand_guidelines
         FROM jobs
         JOIN companies ON companies.id = jobs.company_id
         WHERE jobs.id = ? AND jobs.company_id = ?'
    );
    $stmt->execute([$jobId, current_recruiter_company_id()]);
    $job = $stmt->fetch();
    if (!$job) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Job not found, or you do not have permission to create ads for it.']);
        exit;
    }
    return $job;
}

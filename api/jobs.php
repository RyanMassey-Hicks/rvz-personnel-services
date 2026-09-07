<?php
/**
 * Public JSON feed of open jobs, meant to be fetched from OTHER websites —
 * see embed/widget.js for the ready-made script that consumes this, or call it directly to build a
 * custom layout. No auth required; this is intentionally public, read-only,
 * and limited to open jobs only.
 *
 * Query params (all optional):
 *   q          - keyword match against title/description
 *   location   - substring match against location
 *   company_id - restrict to one company
 *   limit      - max results, default 50, capped at 100
 */
require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
// Public, read-only, cross-origin by design — this is what lets an external
// website's browser-side JS pull the job list directly.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$query = trim($_GET['q'] ?? '');
$location = trim($_GET['location'] ?? '');
$companyId = (int) ($_GET['company_id'] ?? 0);
$limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));

// Filtering to one company is the Paid-plan "embed your jobs on your own
// website" feature — block it here too, not just on the settings page that
// reveals the snippet, so the company_id filter can't be used to get that
// feature for free by calling this endpoint directly.
if ($companyId > 0 && !company_has_embed_access($companyId)) {
    http_response_code(403);
    echo json_encode(['jobs' => [], 'count' => 0, 'error' => 'Company-scoped embedding requires an active Paid plan.']);
    exit;
}

$sql = 'SELECT jobs.id, jobs.title, jobs.description, jobs.location, jobs.employment_type,
               jobs.salary_min, jobs.salary_max, jobs.is_remote, jobs.created_at,
               companies.name AS company_name, companies.logo_path AS company_logo
        FROM jobs
        JOIN companies ON companies.id = jobs.company_id
        WHERE jobs.is_open = 1';
$params = [];

if ($query !== '') {
    $sql .= ' AND (jobs.title LIKE ? OR jobs.description LIKE ?)';
    $params[] = '%' . $query . '%';
    $params[] = '%' . $query . '%';
}
if ($location !== '') {
    $sql .= ' AND jobs.location LIKE ?';
    $params[] = '%' . $location . '%';
}
if ($companyId > 0) {
    $sql .= ' AND jobs.company_id = ?';
    $params[] = $companyId;
}
$sql .= ' ORDER BY jobs.created_at DESC LIMIT ' . $limit;

$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$employmentLabels = [
    'full_time' => 'Full-time', 'part_time' => 'Part-time',
    'contract' => 'Contract', 'internship' => 'Internship',
];

$jobs = array_map(function ($job) use ($employmentLabels) {
    $excerpt = mb_substr(trim(preg_replace('/\s+/', ' ', $job['description'])), 0, 220);
    return [
        'id' => (int) $job['id'],
        'title' => $job['title'],
        'company' => $job['company_name'],
        'logo_url' => $job['company_logo'] ? UPLOAD_URL . $job['company_logo'] : null,
        'location' => $job['location'],
        'is_remote' => (bool) $job['is_remote'],
        'employment_type' => $employmentLabels[$job['employment_type']] ?? $job['employment_type'],
        'salary_min' => $job['salary_min'] !== null ? (int) $job['salary_min'] : null,
        'salary_max' => $job['salary_max'] !== null ? (int) $job['salary_max'] : null,
        'posted_at' => $job['created_at'],
        'excerpt' => $excerpt . (mb_strlen($job['description']) > 220 ? '…' : ''),
        // Apply happens on the main site so login/resume/CSRF all work normally.
        'apply_url' => base_url('job.php?id=' . $job['id']),
    ];
}, $rows);

echo json_encode(['jobs' => $jobs, 'count' => count($jobs)], JSON_UNESCAPED_SLASHES);

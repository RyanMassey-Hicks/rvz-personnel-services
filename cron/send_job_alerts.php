<?php
/**
 * Run this daily via cPanel -> Cron Jobs, e.g.:
 *   0 7 * * * php /usr/www/users/nhestvnbej/public_html/cron/send_job_alerts.php
 * (adjust the path to wherever this project is deployed). Shared hosting has
 * no persistent worker process, so a scheduled cron hit is how "automated"
 * emails actually get sent here — there is nothing else running in the
 * background.
 *
 * Sends two kinds of digests, both scoped to roughly the last 24 hours so
 * re-running this manually never spams anyone with duplicates from further back:
 *   1. New job vacancies -> opted-in candidates whose skills/location match.
 *   2. New matching CVs -> recruiters with a saved Direct Search.
 */
require __DIR__ . '/../includes/bootstrap.php';

$since = date('Y-m-d H:i:s', strtotime('-1 day'));

// --- 1. New jobs -> candidates ---------------------------------------------
$newJobs = db()->prepare('SELECT jobs.*, companies.name AS company_name FROM jobs JOIN companies ON companies.id = jobs.company_id WHERE jobs.is_open = 1 AND jobs.created_at >= ?');
$newJobs->execute([$since]);
$newJobs = $newJobs->fetchAll();

if ($newJobs) {
    $candidates = db()->query(
        "SELECT users.id, users.email, users.first_name, candidate_profiles.skills, candidate_profiles.location
         FROM candidate_profiles JOIN users ON users.id = candidate_profiles.user_id
         WHERE candidate_profiles.opt_in_job_alerts = 1"
    )->fetchAll();

    foreach ($candidates as $cand) {
        $words = array_filter(array_map('trim', explode(',', $cand['skills'] ?? '')));
        $matches = [];
        foreach ($newJobs as $job) {
            $haystack = strtolower($job['title'] . ' ' . $job['description'] . ' ' . $job['location']);
            $isMatch = false;
            foreach ($words as $w) {
                if ($w !== '' && str_contains($haystack, strtolower($w))) { $isMatch = true; break; }
            }
            if (!$isMatch && !empty($cand['location']) && str_contains(strtolower($job['location']), strtolower($cand['location']))) {
                $isMatch = true;
            }
            if ($isMatch) $matches[] = $job;
        }
        if (!$matches) continue;

        $rows = '';
        foreach (array_slice($matches, 0, 8) as $job) {
            $rows .= '<p style="margin:0 0 10px;"><a href="' . h(base_url('job.php?id=' . $job['id'])) . '"><strong>' . h($job['title']) . '</strong></a><br>'
                . h($job['company_name']) . ' &middot; ' . h($job['location']) . '</p>';
        }
        send_email($cand['email'], count($matches) . ' new job(s) that match your profile', email_wrap(
            '<p>Hi ' . h($cand['first_name'] ?: 'there') . ',</p><p>New vacancies posted in the last day that match your profile:</p>' . $rows
        ));
    }
}

// --- 2. New matching CVs -> recruiters with a saved search -----------------
$newCandidates = db()->prepare(
    "SELECT users.id, users.first_name, users.last_name, candidate_profiles.skills, candidate_profiles.location, candidate_profiles.languages
     FROM candidate_profiles JOIN users ON users.id = candidate_profiles.user_id
     WHERE users.role = 'candidate' AND users.created_at >= ?"
);
$newCandidates->execute([$since]);
$newCandidates = $newCandidates->fetchAll();

if ($newCandidates) {
    $searches = db()->query(
        "SELECT saved_searches.*, users.email, users.first_name FROM saved_searches
         JOIN users ON users.id = saved_searches.recruiter_id WHERE saved_searches.notify = 1"
    )->fetchAll();

    foreach ($searches as $search) {
        $matches = [];
        foreach ($newCandidates as $cand) {
            $ok = true;
            if ($search['skills'] && !str_contains(strtolower($cand['skills'] ?? ''), strtolower($search['skills']))) $ok = false;
            if ($ok && $search['location'] && !str_contains(strtolower($cand['location'] ?? ''), strtolower($search['location']))) $ok = false;
            if ($ok && $search['languages'] && !str_contains(strtolower($cand['languages'] ?? ''), strtolower($search['languages']))) $ok = false;
            if ($ok) $matches[] = $cand;
        }
        if (!$matches) continue;

        $rows = '';
        foreach ($matches as $cand) {
            $rows .= '<p style="margin:0 0 6px;">' . h(trim($cand['first_name'] . ' ' . $cand['last_name']) ?: 'New candidate') . '</p>';
        }
        send_email($search['email'], count($matches) . ' new CV(s) match your saved search', email_wrap(
            '<p>Hi ' . h($search['first_name'] ?: 'there') . ',</p><p>New candidates match your saved Direct Search:</p>' . $rows
            . '<p><a href="' . h(base_url('recruiter_search.php?skills=' . urlencode($search['skills']) . '&location=' . urlencode($search['location']))) . '">View in Direct Search</a></p>'
        ));
    }
}

echo "Job alerts run complete.\n";

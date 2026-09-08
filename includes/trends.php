<?php
/**
 * Live data aggregation for the RVZ Job Market Trends Report — every figure
 * here comes straight from this platform's own jobs/applications/candidate
 * data (see job-market-trends-report.php). Deliberately NOT modelled on any
 * third party's numbers — RVZ is a smaller, newer platform than the big
 * national job boards, so these are honestly smaller figures, not an
 * imitation of a bigger competitor's report.
 */

/** [start, end) datetime bounds for a given "YYYY-MM" month string (or the current month if omitted). */
function trends_month_bounds(?string $month = null): array
{
    $month = $month ?: date('Y-m');
    $start = $month . '-01 00:00:00';
    $end = date('Y-m-01 00:00:00', strtotime($start . ' +1 month'));
    return [$start, $end];
}

function trends_hiring_activity(string $month): array
{
    [$start, $end] = trends_month_bounds($month);
    [$prevStart, $prevEnd] = trends_month_bounds(date('Y-m', strtotime($start . ' -1 month')));

    $stmt = db()->prepare('SELECT COUNT(*) AS c FROM jobs WHERE created_at >= ? AND created_at < ?');
    $stmt->execute([$start, $end]);
    $jobsThis = (int) $stmt->fetch()['c'];

    $stmt->execute([$prevStart, $prevEnd]);
    $jobsPrev = (int) $stmt->fetch()['c'];

    $stmt = db()->prepare('SELECT COUNT(*) AS c FROM applications WHERE applied_on >= ? AND applied_on < ?');
    $stmt->execute([$start, $end]);
    $appsThis = (int) $stmt->fetch()['c'];
    $stmt->execute([$prevStart, $prevEnd]);
    $appsPrev = (int) $stmt->fetch()['c'];

    return [
        'jobs_this' => $jobsThis, 'jobs_prev' => $jobsPrev, 'jobs_change_pct' => trends_pct_change($jobsPrev, $jobsThis),
        'apps_this' => $appsThis, 'apps_prev' => $appsPrev, 'apps_change_pct' => trends_pct_change($appsPrev, $appsThis),
    ];
}

function trends_pct_change(int $prev, int $current): ?float
{
    if ($prev <= 0) {
        return $current > 0 ? null : 0.0; // null = "new activity" (no baseline), not a fake percentage
    }
    return round((($current - $prev) / $prev) * 100, 1);
}

/** Last 6 months of jobs-posted + applications-received counts, oldest first — for the activity mini-chart. */
function trends_activity_history(string $month, int $months = 6): array
{
    $rows = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $m = date('Y-m', strtotime($month . '-01 -' . $i . ' months'));
        [$start, $end] = trends_month_bounds($m);
        $stmt = db()->prepare('SELECT COUNT(*) AS c FROM jobs WHERE created_at >= ? AND created_at < ?');
        $stmt->execute([$start, $end]);
        $jobs = (int) $stmt->fetch()['c'];
        $stmt = db()->prepare('SELECT COUNT(*) AS c FROM applications WHERE applied_on >= ? AND applied_on < ?');
        $stmt->execute([$start, $end]);
        $apps = (int) $stmt->fetch()['c'];
        $rows[] = ['month' => $m, 'label' => date('M \'y', strtotime($m . '-01')), 'jobs' => $jobs, 'applications' => $apps];
    }
    return $rows;
}

/** Top job titles by application volume this month. */
function trends_top_jobs(string $month, int $limit = 5): array
{
    [$start, $end] = trends_month_bounds($month);
    $stmt = db()->prepare(
        'SELECT jobs.title, industries.name AS sector, COUNT(applications.id) AS app_count
         FROM applications
         JOIN jobs ON jobs.id = applications.job_id
         LEFT JOIN industries ON industries.id = jobs.industry_id
         WHERE applications.applied_on >= ? AND applications.applied_on < ?
         GROUP BY jobs.id, jobs.title, industries.name
         ORDER BY app_count DESC, jobs.title ASC
         LIMIT ' . $limit
    );
    $stmt->execute([$start, $end]);
    return $stmt->fetchAll();
}

/** Job postings per sector this month vs last, sorted by this-month volume. */
function trends_top_sectors(string $month, int $limit = 5): array
{
    [$start, $end] = trends_month_bounds($month);
    [$prevStart, $prevEnd] = trends_month_bounds(date('Y-m', strtotime($start . ' -1 month')));

    $stmt = db()->prepare(
        "SELECT COALESCE(industries.name, 'Unspecified') AS sector, COUNT(*) AS c
         FROM jobs LEFT JOIN industries ON industries.id = jobs.industry_id
         WHERE jobs.created_at >= ? AND jobs.created_at < ?
         GROUP BY sector ORDER BY c DESC LIMIT $limit"
    );
    $stmt->execute([$start, $end]);
    $current = $stmt->fetchAll();

    $stmt = db()->prepare(
        "SELECT COALESCE(industries.name, 'Unspecified') AS sector, COUNT(*) AS c
         FROM jobs LEFT JOIN industries ON industries.id = jobs.industry_id
         WHERE jobs.created_at >= ? AND jobs.created_at < ? GROUP BY sector"
    );
    $stmt->execute([$prevStart, $prevEnd]);
    $prevBySector = array_column($stmt->fetchAll(), 'c', 'sector');

    foreach ($current as &$row) {
        $prevCount = (int) ($prevBySector[$row['sector']] ?? 0);
        $row['prev_count'] = $prevCount;
        $row['change_pct'] = trends_pct_change($prevCount, (int) $row['c']);
    }
    unset($row);
    return $current;
}

/** Registered candidates by province — a cumulative snapshot (this platform doesn't yet have enough monthly volume for a rolling regional trend). */
function trends_regional_snapshot(int $limit = 3): array
{
    $stmt = db()->prepare(
        "SELECT candidate_profiles.province AS province, COUNT(*) AS c
         FROM candidate_profiles
         JOIN users ON users.id = candidate_profiles.user_id
         WHERE users.role = 'candidate' AND candidate_profiles.province IS NOT NULL AND candidate_profiles.province != ''
         GROUP BY province ORDER BY c DESC LIMIT $limit"
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

/** Average days-to-fill for jobs closed this month (mirrors dashboard.php's past-jobs "days to fill" logic). */
function trends_avg_days_to_fill(string $month): ?float
{
    [$start, $end] = trends_month_bounds($month);
    $stmt = db()->prepare(
        "SELECT created_at, closed_at FROM jobs
         WHERE is_open = 0 AND closed_at IS NOT NULL AND closed_at >= ? AND closed_at < ?"
    );
    $stmt->execute([$start, $end]);
    $rows = $stmt->fetchAll();
    if (!$rows) {
        return null;
    }
    $total = 0;
    foreach ($rows as $row) {
        $total += days_between($row['created_at'], $row['closed_at']);
    }
    return round($total / count($rows), 1);
}

/** Builds (and caches in the trend_reports table) the full stats payload for one month. */
function trends_build_report(string $month): array
{
    return [
        'month' => $month,
        'month_label' => date('F Y', strtotime($month . '-01')),
        'generated_at' => date('Y-m-d H:i:s'),
        'activity' => trends_hiring_activity($month),
        'history' => trends_activity_history($month),
        'top_jobs' => trends_top_jobs($month),
        'top_sectors' => trends_top_sectors($month),
        'regional' => trends_regional_snapshot(),
        'avg_days_to_fill' => trends_avg_days_to_fill($month),
    ];
}

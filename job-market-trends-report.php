<?php
require __DIR__ . '/includes/bootstrap.php';

$month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
if ($month > date('Y-m')) {
    $month = date('Y-m'); // no peeking at a month that hasn't happened yet
}
$stats = trends_build_report($month);

if (($_GET['format'] ?? '') === 'pdf') {
    $pdf = trend_report_render_pdf($stats);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="rvz-job-market-trends-' . $month . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

/** Renders the same report data as a downloadable PDF, in RVZ's own branding (navy/purple/silver). */
function trend_report_render_pdf(array $stats): string
{
    $navy = [10, 31, 68];
    $purple = [124, 58, 237];
    $grey = [110, 110, 120];
    $light = [235, 237, 243];

    $pdf = new SimplePdf();

    // Cover
    $pdf->addRect(0, 0, $pdf->pageWidth(), $pdf->pageHeight(), $navy);
    $pdf->addRect(0, $pdf->pageHeight() - 260, $pdf->pageWidth(), 260, $purple);
    $pdf->addTextAt(42, 120, 'RVZ Job Market', 26, true, [255, 255, 255]);
    $pdf->addTextAt(42, 152, 'Trends Report', 26, true, [255, 255, 255]);
    $pdf->addTextAt(42, 182, $stats['month_label'], 16, true, [255, 255, 255]);
    $pdf->addTextAt(42, 340, 'A monthly look at hiring activity on the RVZ Personnel Services platform —', 11, false, [230, 230, 240]);
    $pdf->addTextAt(42, 356, 'jobs posted, applications received, and where South African employers are hiring.', 11, false, [230, 230, 240]);
    $pdf->addTextAt(42, 780, SITE_NAME, 10, true, [255, 255, 255]);
    $pdf->addTextAt(42, 796, COMPANY_LEGAL_NAME . ' (Reg. ' . COMPANY_REG_NUMBER . ')', 8, false, [200, 200, 215]);

    // Job market activity
    $pdf->addPage();
    $pdf->addHeading('Job Market Activity', 18);
    $pdf->addText('All figures below are drawn live from RVZ\'s own platform data — job postings and applications submitted through this site — not a national industry-wide sample.', 9, false, $grey);
    $pdf->addSpacer(8);

    $activity = $stats['activity'];
    trend_report_pdf_stat_row($pdf, 'Jobs posted this month', $activity['jobs_this'], $activity['jobs_change_pct'], $purple);
    trend_report_pdf_stat_row($pdf, 'Applications received this month', $activity['apps_this'], $activity['apps_change_pct'], $purple);
    if ($stats['avg_days_to_fill'] !== null) {
        $pdf->addText('Average days to fill a role (closed this month): ' . $stats['avg_days_to_fill'] . ' days', 10, false, [30, 30, 30]);
        $pdf->addSpacer(6);
    }

    $pdf->addSpacer(6);
    $pdf->addSectionLabel('6-Month Activity', $navy);
    trend_report_pdf_bar_chart($pdf, $stats['history']);

    // Trending jobs & sectors
    $pdf->addPage();
    $pdf->addHeading('Trending Jobs', 18);
    if ($stats['top_jobs']) {
        $rank = 1;
        foreach ($stats['top_jobs'] as $job) {
            $pdf->addText('#' . $rank . '  ' . $job['title'] . ($job['sector'] ? ' — ' . $job['sector'] : ''), 11, true, [30, 30, 30]);
            $pdf->addText($job['app_count'] . ' application(s) this month', 9, false, $grey);
            $pdf->addSpacer(6);
            $rank++;
        }
    } else {
        $pdf->addText('Not enough application activity yet this month to rank trending jobs.', 10, false, $grey);
    }

    $pdf->addSectionLabel('Trending Sectors', $navy);
    if ($stats['top_sectors']) {
        foreach ($stats['top_sectors'] as $sector) {
            $change = $sector['change_pct'];
            $changeLabel = $change === null ? 'new this month' : ($change >= 0 ? '+' . $change . '%' : $change . '%');
            $pdf->addText($sector['sector'] . ': ' . $sector['c'] . ' job(s) posted (' . $changeLabel . ' vs last month)', 10, false, [30, 30, 30]);
            $pdf->addSpacer(4);
        }
    } else {
        $pdf->addText('No jobs posted yet this month.', 10, false, $grey);
    }

    // Regional
    $pdf->addPage();
    $pdf->addHeading('Regional Candidate Snapshot', 18);
    $pdf->addText('Where registered candidates on RVZ are based, by province (cumulative, all-time registrations).', 9, false, $grey);
    $pdf->addSpacer(8);
    if ($stats['regional']) {
        foreach ($stats['regional'] as $row) {
            $pdf->addText($row['province'] . ': ' . $row['c'] . ' candidate(s)', 10, false, [30, 30, 30]);
            $pdf->addSpacer(4);
        }
    } else {
        $pdf->addText('Not enough candidates have set a province yet.', 10, false, $grey);
    }

    $pdf->addSectionLabel('The RVZ Perspective', $navy);
    $pdf->addText(trend_report_perspective_text($stats), 10, false, [30, 30, 30]);

    $pdf->addSpacer(20);
    $pdf->addText('This report is compiled from RVZ Personnel Services\' own live platform data and is provided for general informational purposes. For data-related queries, contact ' . GENERAL_INFO_EMAIL . '.', 8, false, $grey);

    $pdf->addWatermarkToAllPages('');
    $pdf->addFooterToAllPages(SITE_NAME . '  |  ' . SITE_URL . '  |  (c) ' . date('Y') . ' ' . COMPANY_LEGAL_NAME);

    return $pdf->output();
}

function trend_report_pdf_stat_row(SimplePdf $pdf, string $label, int $value, ?float $changePct, array $accent): void
{
    $changeLabel = $changePct === null ? ($value > 0 ? 'new activity' : 'no activity yet') : ($changePct >= 0 ? '+' . $changePct . '% MoM' : $changePct . '% MoM');
    $pdf->addText($label . ': ' . $value . '  (' . $changeLabel . ')', 11, true, $accent);
    $pdf->addSpacer(4);
}

/** Simple filled-bar mini chart for the 6-month jobs/applications history. */
function trend_report_pdf_bar_chart(SimplePdf $pdf, array $history): void
{
    $maxVal = 1;
    foreach ($history as $row) {
        $maxVal = max($maxVal, $row['jobs'], $row['applications']);
    }
    $chartW = $pdf->pageWidth() - 2 * $pdf->margin();
    $chartH = 110;
    $slot = $chartW / max(1, count($history));
    $barW = min(18, $slot / 2 - 4);
    $baseY = $pdf->cursorY() + $chartH;

    foreach ($history as $i => $row) {
        $slotX = $pdf->margin() + $i * $slot + ($slot - (2 * $barW + 4)) / 2;
        $jobsH = $maxVal > 0 ? ($row['jobs'] / $maxVal) * ($chartH - 20) : 0;
        $appsH = $maxVal > 0 ? ($row['applications'] / $maxVal) * ($chartH - 20) : 0;
        $pdf->addRect($slotX, $baseY - $jobsH, $barW, $jobsH, [124, 58, 237]);
        $pdf->addRect($slotX + $barW + 4, $baseY - $appsH, $barW, $appsH, [10, 31, 68]);
        $pdf->addTextAt($slotX, $baseY + 12, $row['label'], 7, false, [110, 110, 120]);
    }
    $pdf->setCursorY($pdf->cursorY() - $chartH - 20);
    $pdf->addText('Purple = jobs posted   |   Navy = applications received', 8, false, [110, 110, 120]);
    $pdf->addSpacer(10);
}

function trend_report_perspective_text(array $stats): string
{
    $a = $stats['activity'];
    if ($a['jobs_this'] === 0 && $a['apps_this'] === 0) {
        return 'RVZ is a growing platform, and ' . $stats['month_label'] . ' saw no new job postings recorded yet. '
            . 'As more employers list roles here, this report will track hiring activity, trending sectors and regional demand month over month.';
    }
    $jobsTrend = $a['jobs_change_pct'] === null ? 'a fresh start in postings' : (
        $a['jobs_change_pct'] >= 0 ? 'growth of ' . $a['jobs_change_pct'] . '% month-on-month' : 'a decrease of ' . abs($a['jobs_change_pct']) . '% month-on-month'
    );
    return 'In ' . $stats['month_label'] . ', RVZ recorded ' . $a['jobs_this'] . ' job posting(s) and ' . $a['apps_this']
        . ' application(s) on the platform, reflecting ' . $jobsTrend . ' in job postings. As RVZ\'s recruiter and '
        . 'candidate base continues to grow, this monthly report will offer an increasingly detailed view of hiring '
        . 'trends across the sectors and regions RVZ serves.';
}

$pageTitle = 'Job Market Trends Report — ' . SITE_NAME;
$pageDescription = 'RVZ\'s monthly Job Market Trends Report — live hiring activity, trending jobs and sectors, and regional candidate demand, straight from RVZ\'s own platform data.';
require __DIR__ . '/includes/header.php';
$activity = $stats['activity'];
?>
<div class="rvz-trends-hero p-4 p-md-5 rounded-4 mb-4 text-white">
    <h1 class="mb-1">Job Market Trends Report</h1>
    <p class="mb-3 opacity-75"><?= h($stats['month_label']) ?></p>
    <p class="mb-4" style="max-width:60ch;">A monthly look at hiring activity on the RVZ Personnel Services platform —
    jobs posted, applications received, and where South African employers are hiring. Compiled live from RVZ's own data.</p>
    <a class="btn btn-light" href="<?= h(base_url('job-market-trends-report.php?month=' . $month . '&format=pdf')) ?>">Download PDF Report</a>
</div>

<h3 class="mb-3">Job Market Activity</h3>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <p class="text-muted small mb-1">Jobs posted this month</p>
            <div class="display-6"><?= (int) $activity['jobs_this'] ?></div>
            <p class="small mb-0"><?= trend_report_change_badge($activity['jobs_change_pct']) ?></p>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <p class="text-muted small mb-1">Applications received</p>
            <div class="display-6"><?= (int) $activity['apps_this'] ?></div>
            <p class="small mb-0"><?= trend_report_change_badge($activity['apps_change_pct']) ?></p>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <p class="text-muted small mb-1">Average days to fill a role</p>
            <div class="display-6"><?= $stats['avg_days_to_fill'] !== null ? h((string) $stats['avg_days_to_fill']) : '—' ?></div>
            <p class="small mb-0 text-muted"><?= $stats['avg_days_to_fill'] !== null ? 'days, roles closed this month' : 'No roles closed this month yet' ?></p>
        </div></div>
    </div>
</div>

<h5 class="mb-3">6-Month Activity</h5>
<div class="rvz-trends-barchart mb-5">
    <?php $maxVal = max(1, ...array_merge(array_column($stats['history'], 'jobs'), array_column($stats['history'], 'applications'))); ?>
    <?php foreach ($stats['history'] as $row): ?>
        <div class="rvz-trends-bar-slot">
            <div class="rvz-trends-bars">
                <div class="rvz-trends-bar rvz-trends-bar-jobs" style="height:<?= (int) round(($row['jobs'] / $maxVal) * 100) ?>%" title="<?= (int) $row['jobs'] ?> jobs"></div>
                <div class="rvz-trends-bar rvz-trends-bar-apps" style="height:<?= (int) round(($row['applications'] / $maxVal) * 100) ?>%" title="<?= (int) $row['applications'] ?> applications"></div>
            </div>
            <span class="small text-muted"><?= h($row['label']) ?></span>
        </div>
    <?php endforeach; ?>
</div>
<p class="small text-muted mb-5"><span class="rvz-legend-swatch rvz-legend-purple"></span> Jobs posted &nbsp; <span class="rvz-legend-swatch rvz-legend-navy"></span> Applications received</p>

<div class="row g-4 mb-5">
    <div class="col-lg-7">
        <h3 class="mb-3">Trending Jobs</h3>
        <?php if ($stats['top_jobs']): ?>
            <div class="row g-3">
                <?php foreach ($stats['top_jobs'] as $i => $job): ?>
                    <div class="col-6 col-md-4">
                        <div class="card h-100 text-center"><div class="card-body">
                            <div class="fw-bold text-primary mb-1">#<?= $i + 1 ?></div>
                            <div class="small fw-semibold"><?= h($job['title']) ?></div>
                            <div class="small text-muted"><?= h($job['sector'] ?: 'General') ?></div>
                        </div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">Not enough application activity yet this month to rank trending jobs.</p>
        <?php endif; ?>

        <h3 class="mt-4 mb-3">Trending Sectors</h3>
        <?php if ($stats['top_sectors']): ?>
            <?php foreach ($stats['top_sectors'] as $sector): ?>
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span><?= h($sector['sector']) ?></span>
                    <span class="text-muted small"><?= (int) $sector['c'] ?> posted &middot; <?= trend_report_change_badge($sector['change_pct']) ?></span>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted">No jobs posted yet this month.</p>
        <?php endif; ?>
    </div>
    <div class="col-lg-5">
        <h3 class="mb-3">Regional Candidate Snapshot</h3>
        <p class="text-muted small">Where registered candidates are based, by province (cumulative).</p>
        <?php if ($stats['regional']): ?>
            <?php foreach ($stats['regional'] as $row): ?>
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span><?= h($row['province']) ?></span>
                    <span class="text-muted small"><?= (int) $row['c'] ?> candidate(s)</span>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted">Not enough candidates have set a province yet.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card bg-light border-0 mb-5"><div class="card-body">
    <h5>The RVZ Perspective</h5>
    <p class="mb-0"><?= h(trend_report_perspective_text($stats)) ?></p>
</div></div>

<p class="text-muted small">
    This report is compiled from RVZ Personnel Services' own live platform data and is provided for general
    informational purposes. For data-related queries, contact <a href="mailto:<?= h(GENERAL_INFO_EMAIL) ?>"><?= h(GENERAL_INFO_EMAIL) ?></a>.
</p>

<?php
function trend_report_change_badge(?float $pct): string
{
    if ($pct === null) {
        return '<span class="text-success">new</span>';
    }
    $cls = $pct > 0 ? 'text-success' : ($pct < 0 ? 'text-danger' : 'text-muted');
    $sign = $pct > 0 ? '+' : '';
    return '<span class="' . $cls . '">' . $sign . h((string) $pct) . '% MoM</span>';
}
require __DIR__ . '/includes/footer.php'; ?>

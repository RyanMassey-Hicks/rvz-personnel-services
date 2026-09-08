<?php
/**
 * Speedometer-style circular gauges (SVG, no chart library) — a 270-degree
 * ring with a gap at the bottom. This is the site's one shared style for
 * every circular data indicator (candidate-pool stats, per-job stat
 * circles, profile-completeness rings) — no plain flat-filled circles.
 */

/** Rounds a value up to a visually "nice" ceiling for a gauge with no natural max (e.g. a raw headcount). */
function gauge_nice_max(int $value): int
{
    foreach ([10, 25, 50, 100, 250, 500, 1000, 2500, 5000] as $step) {
        if ($value <= $step) {
            return $step;
        }
    }
    return (int) ceil(($value + 1) / 1000) * 1000;
}

/**
 * Renders one speedometer gauge. $max should be >= 1; pass gauge_nice_max($value)
 * when there's no natural denominator. $size in pixels (viewBox stays fixed, so
 * everything — ring, text — scales together). $compact hides the "of N" subtext
 * and centers a bigger number, for small/dense placements (e.g. per-job stat rows).
 */
function render_speedometer(string $label, int $value, int $max, string $color, int $size = 120, bool $compact = false): void
{
    $max = max(1, $max);
    $pct = max(0, min(1, $value / $max));

    $radius = 50;
    $circumference = 2 * M_PI * $radius;
    $trackLen = $circumference * 0.75; // 270-degree sweep, 90-degree gap at the bottom
    $valueLen = $trackLen * $pct;
    $numberY = $compact ? 67 : 58;
    $numberSize = $compact ? 30 : 24;

    printf(
        '<div class="rvz-gauge">
            <svg viewBox="0 0 120 120" width="%1$d" height="%1$d">
                <circle cx="60" cy="60" r="%2$d" fill="none" stroke="#e9ecef" stroke-width="10"
                        stroke-dasharray="%3$.2F %4$.2F" stroke-linecap="round" transform="rotate(135 60 60)"/>
                <circle cx="60" cy="60" r="%2$d" fill="none" stroke="%5$s" stroke-width="10"
                        stroke-dasharray="%6$.2F %4$.2F" stroke-linecap="round" transform="rotate(135 60 60)"
                        style="transition: stroke-dasharray .6s ease;"/>
                <text x="60" y="%7$d" text-anchor="middle" font-size="%8$d" font-weight="700" fill="#0a1f44">%9$s</text>
                %10$s
            </svg>
            <div class="rvz-gauge-label">%11$s</div>
        </div>',
        $size,
        $radius,
        $trackLen,
        $circumference,
        h($color),
        $valueLen,
        $numberY,
        $numberSize,
        number_format($value),
        $compact ? '' : sprintf('<text x="60" y="76" text-anchor="middle" font-size="9" fill="#6c757d">of %s</text>', number_format($max)),
        h($label)
    );
}

/** Same gauge, but the number in the center is a "NN%" — for completeness-style indicators. */
function render_speedometer_percent(string $label, int $percent, string $color, int $size = 120): void
{
    $percent = max(0, min(100, $percent));
    $radius = 50;
    $circumference = 2 * M_PI * $radius;
    $trackLen = $circumference * 0.75;
    $valueLen = $trackLen * ($percent / 100);

    printf(
        '<div class="rvz-gauge">
            <svg viewBox="0 0 120 120" width="%1$d" height="%1$d">
                <circle cx="60" cy="60" r="%2$d" fill="none" stroke="#e9ecef" stroke-width="10"
                        stroke-dasharray="%3$.2F %4$.2F" stroke-linecap="round" transform="rotate(135 60 60)"/>
                <circle cx="60" cy="60" r="%2$d" fill="none" stroke="%5$s" stroke-width="10"
                        stroke-dasharray="%6$.2F %4$.2F" stroke-linecap="round" transform="rotate(135 60 60)"
                        style="transition: stroke-dasharray .6s ease;"/>
                <text x="60" y="65" text-anchor="middle" font-size="26" font-weight="700" fill="#0a1f44">%7$d%%</text>
            </svg>
            <div class="rvz-gauge-label">%8$s</div>
        </div>',
        $size,
        $radius,
        $trackLen,
        $circumference,
        h($color),
        $valueLen,
        $percent,
        h($label)
    );
}

/** A row of gauges summarising the candidate pool — used on dashboard.php and recruiter_search.php. */
function render_candidate_pool_gauges(array $stats): void
{
    $total = $stats['total'];
    ?>
    <div class="card mb-4"><div class="card-body">
        <h6 class="mb-3">Candidate Pool Overview</h6>
        <div class="rvz-gauge-row">
            <?php
            render_speedometer('Total Candidates', $total, gauge_nice_max($total), '#0a1f44');
            render_speedometer('Complete Profiles', $stats['complete'], $total, '#198754');
            render_speedometer('Incomplete Profiles', $stats['incomplete'], $total, '#fd7e14');
            render_speedometer('CV/Resume Uploaded', $stats['with_resume'], $total, '#0d6efd');
            ?>
        </div>
    </div></div>
    <?php
}

<?php
/**
 * Per-IP rate limiting for public, unauthenticated endpoints that are real
 * bulk-scraping targets (the job JSON feed, the embeddable careers page,
 * the public job board/detail pages). Plain MySQL-backed — no Redis/
 * Memcached on this shared host, and traffic here is modest enough that a
 * table + indexed lookup is plenty fast.
 *
 * Deliberately does NOT block legitimate crawlers (Google, Bing, and the
 * social-preview bots — Facebook/Twitter/LinkedIn/Slack/WhatsApp/Discord).
 * Blocking those would hurt SEO and break link previews, which is a real
 * bug this project hit once already (see includes/header.php's Google for
 * Jobs structured data and the www-redirect fix for the Facebook preview
 * issue) — the goal here is bulk/automated scraping, not search or social
 * crawling, which this platform actively wants.
 */

/** Real client IP. No CDN/proxy in front of this host, so REMOTE_ADDR is the actual visitor. */
function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** True for known-good search/social crawlers, which are never rate-limited. */
function is_known_good_bot(): bool
{
    $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '') {
        return false;
    }
    $allowlist = [
        'googlebot', 'bingbot', 'duckduckbot', 'applebot',
        'facebookexternalhit', 'twitterbot', 'linkedinbot',
        'slackbot', 'whatsapp', 'discordbot', 'telegrambot',
    ];
    foreach ($allowlist as $bot) {
        if (str_contains($ua, $bot)) {
            return true;
        }
    }
    return false;
}

/**
 * Returns true if this request is within the allowed rate, false if the
 * caller has exceeded $maxRequests within the last $windowSeconds. Known
 * good crawlers always pass. Also does cheap, probabilistic cleanup of old
 * log rows so this table never grows unbounded (no cron job required).
 */
function rate_limit_allow(string $bucket, int $maxRequests, int $windowSeconds): bool
{
    if (is_known_good_bot()) {
        return true;
    }

    $ip = client_ip();

    $stmt = db()->prepare(
        'SELECT COUNT(*) AS c FROM rate_limit_log WHERE ip_address = ? AND bucket = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)'
    );
    $stmt->execute([$ip, $bucket, $windowSeconds]);
    $count = (int) $stmt->fetch()['c'];

    db()->prepare('INSERT INTO rate_limit_log (ip_address, bucket) VALUES (?, ?)')->execute([$ip, $bucket]);

    if (random_int(1, 50) === 1) {
        db()->exec("DELETE FROM rate_limit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    }

    return $count < $maxRequests;
}

/**
 * Call at the top of a public endpoint; sends 429 and exits if the caller
 * has exceeded the limit. $json controls the error body format — true for
 * JSON APIs (api/jobs.php), false for HTML pages (jobs.php, job.php,
 * embed/careers.php).
 */
function enforce_rate_limit(string $bucket, int $maxRequests, int $windowSeconds, bool $json = false): void
{
    if (rate_limit_allow($bucket, $maxRequests, $windowSeconds)) {
        return;
    }
    http_response_code(429);
    header('Retry-After: ' . $windowSeconds);
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Too many requests. Please slow down and try again shortly.']);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>Too Many Requests</title>'
            . '<body style="font-family:sans-serif;max-width:32rem;margin:4rem auto;text-align:center;">'
            . '<h1>Too Many Requests</h1><p>You\'ve made too many requests in a short time. Please wait a moment and try again.</p></body>';
    }
    exit;
}

<?php
/**
 * Shared helpers used on every page. Include this after config.php + db.php.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Output escaping -------------------------------------------------------

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// --- CSRF protection ---------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!$sent || !hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
        http_response_code(403);
        die('Security check failed (invalid or expired form). Please go back and try again.');
    }
}

// --- Flash messages -----------------------------------------------------------

function flash(string $type, string $message): void
{
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $flashes;
}

// --- Auth ---------------------------------------------------------------------

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $cached = $user ?: null;
    return $cached;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_recruiter(): bool
{
    $u = current_user();
    return $u && $u['role'] === 'recruiter';
}

function require_login(): void
{
    if (!is_logged_in()) {
        $next = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        redirect('/login.php?next=' . $next);
    }
}

function require_recruiter(): void
{
    require_login();
    if (!is_recruiter()) {
        http_response_code(403);
        die('Recruiter access only.');
    }
}

// --- Recruiter billing (Paystack) ------------------------------------------

/** The one account (configured in config.php) that always gets free recruiter access + branding privileges. */
function is_privileged_recruiter(?array $user = null): bool
{
    $user = $user ?? current_user();
    if (!$user || empty($user['email'])) {
        return false;
    }
    return strcasecmp(trim($user['email']), trim(PRIVILEGED_RECRUITER_EMAIL)) === 0;
}

function get_subscription(int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM subscriptions WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

/** True if this user is on the same company/team as the privileged account (e.g. invited by ryan@rvzgroup.co.za) — those team members ride on the same free access. */
function is_on_privileged_team(?array $user = null): bool
{
    $user = $user ?? current_user();
    if (!$user) {
        return false;
    }
    $stmt = db()->prepare('SELECT company_id FROM recruiter_profiles WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();
    if (!$row || !$row['company_id']) {
        return false;
    }
    $stmt = db()->prepare(
        'SELECT recruiter_profiles.company_id FROM recruiter_profiles
         JOIN users ON users.id = recruiter_profiles.user_id
         WHERE users.email = ?'
    );
    $stmt->execute([PRIVILEGED_RECRUITER_EMAIL]);
    $privRow = $stmt->fetch();
    return $privRow && $privRow['company_id'] && (int) $privRow['company_id'] === (int) $row['company_id'];
}

/** True if this account gets free recruiter access — either it IS the privileged account, or it's a team member the privileged account invited. */
function has_free_recruiter_access(?array $user = null): bool
{
    $user = $user ?? current_user();
    return is_privileged_recruiter($user) || is_on_privileged_team($user);
}

/** Encrypts a secret (e.g. a company's custom SMTP password) for storage — AES-256-CBC using CREDENTIAL_ENCRYPTION_KEY. */
function encrypt_secret(string $plaintext): string
{
    if ($plaintext === '') {
        return '';
    }
    $key = hash('sha256', CREDENTIAL_ENCRYPTION_KEY, true);
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cipher);
}

/** Decrypts a value produced by encrypt_secret(). Returns '' if empty/invalid rather than throwing. */
function decrypt_secret(?string $encoded): string
{
    if (!$encoded) {
        return '';
    }
    $key = hash('sha256', CREDENTIAL_ENCRYPTION_KEY, true);
    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) <= 16) {
        return '';
    }
    $iv = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    $plain = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return $plain === false ? '' : $plain;
}

/**
 * The company's custom outbound SMTP settings, ready to hand to send_email(),
 * or null if the company hasn't enabled custom SMTP (in which case the
 * site's own SMTP_* config.php constants are used instead).
 */
function get_company_smtp_config(int $companyId): ?array
{
    $stmt = db()->prepare(
        'SELECT use_custom_smtp, smtp_host, smtp_port, smtp_secure, smtp_user, smtp_pass_encrypted, smtp_from_email, smtp_from_name
         FROM companies WHERE id = ?'
    );
    $stmt->execute([$companyId]);
    $row = $stmt->fetch();
    if (!$row || !$row['use_custom_smtp'] || !$row['smtp_host'] || !$row['smtp_from_email']) {
        return null;
    }
    return [
        'host' => $row['smtp_host'],
        'port' => (int) ($row['smtp_port'] ?: 587),
        'secure' => $row['smtp_secure'] ?: 'tls',
        'user' => $row['smtp_user'],
        'pass' => decrypt_secret($row['smtp_pass_encrypted']),
        'from_email' => $row['smtp_from_email'],
        'from_name' => $row['smtp_from_name'] ?: $row['smtp_from_email'],
    ];
}

/** The company's combined seat-billing subscription row, or null if it has never bought seats. */
function get_company_subscription(int $companyId): ?array
{
    $stmt = db()->prepare('SELECT * FROM company_subscriptions WHERE company_id = ?');
    $stmt->execute([$companyId]);
    return $stmt->fetch() ?: null;
}

/** True if the company's combined seat subscription is active and not expired. */
function company_has_active_billing(int $companyId): bool
{
    $sub = get_company_subscription($companyId);
    if (!$sub || $sub['status'] !== 'active' || !$sub['current_period_end']) {
        return false;
    }
    return strtotime($sub['current_period_end']) > time();
}

/** How many of the company's purchased seats are occupied — accepted members plus pending invites. */
function company_seats_used(int $companyId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) AS c FROM recruiter_profiles WHERE company_id = ?');
    $stmt->execute([$companyId]);
    $members = (int) $stmt->fetch()['c'];

    $stmt = db()->prepare("SELECT COUNT(*) AS c FROM team_invites WHERE company_id = ? AND status = 'pending'");
    $stmt->execute([$companyId]);
    $pending = (int) $stmt->fetch()['c'];

    return $members + $pending;
}

/**
 * True if this specific member's seat is one of the company's purchased,
 * currently-active PAID seats — not everyone on a team is automatically
 * paid just because the company has bought SOME seats; only the earliest
 * `seat_quantity` members (by join date) are covered. Anyone else on the
 * team is on the Free plan until the company buys more seats.
 */
function is_within_paid_seats(int $userId, int $companyId): bool
{
    $sub = get_company_subscription($companyId);
    if (!$sub || $sub['status'] !== 'active' || !$sub['current_period_end'] || strtotime($sub['current_period_end']) <= time()) {
        return false;
    }
    $seatQuantity = (int) $sub['seat_quantity'];
    if ($seatQuantity < 1) {
        return false;
    }
    $stmt = db()->prepare(
        "SELECT users.id FROM recruiter_profiles
         JOIN users ON users.id = recruiter_profiles.user_id
         WHERE recruiter_profiles.company_id = ?
         ORDER BY users.created_at ASC, users.id ASC
         LIMIT $seatQuantity"
    );
    $stmt->execute([$companyId]);
    return in_array($userId, array_column($stmt->fetchAll(), 'id'), true);
}

/** True if this user currently has paid recruiter access (or free access per has_free_recruiter_access()). */
function has_active_recruiter_subscription(?array $user = null): bool
{
    $user = $user ?? current_user();
    if (!$user) {
        return false;
    }
    if (has_free_recruiter_access($user)) {
        return true;
    }
    $stmt = db()->prepare('SELECT company_id FROM recruiter_profiles WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();
    if ($row && $row['company_id'] && is_within_paid_seats((int) $user['id'], (int) $row['company_id'])) {
        return true;
    }
    $sub = get_subscription((int) $user['id']);
    if (!$sub || $sub['status'] !== 'active' || !$sub['current_period_end']) {
        return false;
    }
    return strtotime($sub['current_period_end']) > time();
}

/**
 * Aggregate stats over the whole candidate pool — total candidates, how many
 * have a complete profile (same fields checked as my_cv.php's missing-fields
 * warning), and how many have uploaded a CV/resume. Used for the dashboard
 * and Direct Search gauge widgets.
 */
function candidate_pool_stats(): array
{
    $total = (int) db()->query("SELECT COUNT(*) AS c FROM users WHERE role = 'candidate'")->fetch()['c'];

    $complete = (int) db()->query(
        "SELECT COUNT(*) AS c FROM candidate_profiles
         JOIN users ON users.id = candidate_profiles.user_id
         WHERE users.role = 'candidate'
           AND professional_summary IS NOT NULL AND professional_summary != ''
           AND skills IS NOT NULL AND skills != ''
           AND work_experience IS NOT NULL AND work_experience NOT IN ('', '[]')
           AND education IS NOT NULL AND education NOT IN ('', '[]')"
    )->fetch()['c'];

    $withResume = (int) db()->query(
        "SELECT COUNT(*) AS c FROM candidate_profiles
         JOIN users ON users.id = candidate_profiles.user_id
         WHERE users.role = 'candidate' AND resume_path IS NOT NULL AND resume_path != ''"
    )->fetch()['c'];

    return [
        'total' => $total,
        'complete' => $complete,
        'incomplete' => max(0, $total - $complete),
        'with_resume' => $withResume,
    ];
}

/**
 * True if this company is entitled to the Paid-only "embed your jobs on your
 * own website" feature — either it has active combined seat billing, or it's
 * the privileged (free-access) account's own company. Used by api/jobs.php
 * and embed/careers.php so the company-scoped embed feed can't just be
 * called directly to get the Paid feature for free — embed_jobs.php (the
 * settings page that reveals the snippets) already requires an active
 * subscription, but that alone doesn't stop someone hitting the underlying
 * public endpoint directly with a guessed/known company_id.
 */
function company_has_embed_access(int $companyId): bool
{
    if (company_has_active_billing($companyId)) {
        return true;
    }
    $stmt = db()->prepare(
        'SELECT recruiter_profiles.company_id FROM recruiter_profiles
         JOIN users ON users.id = recruiter_profiles.user_id
         WHERE users.email = ?'
    );
    $stmt->execute([PRIVILEGED_RECRUITER_EMAIL]);
    $row = $stmt->fetch();
    return $row && $row['company_id'] && (int) $row['company_id'] === $companyId;
}

/** How many jobs this recruiter has posted so far in the current calendar month — used for the Free plan's post limit. */
function jobs_posted_this_month(int $userId): int
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) AS c FROM jobs WHERE posted_by = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
    );
    $stmt->execute([$userId]);
    return (int) $stmt->fetch()['c'];
}

/** True if this recruiter can post another job right now — unlimited on the Paid plan, capped at FREE_TIER_JOB_LIMIT/month on Free. */
function can_post_another_job(?array $user = null): bool
{
    $user = $user ?? current_user();
    if (!$user) {
        return false;
    }
    if (has_active_recruiter_subscription($user)) {
        return true;
    }
    return jobs_posted_this_month((int) $user['id']) < FREE_TIER_JOB_LIMIT;
}

/**
 * Gate for recruiter pages available on both the Free and Paid plan
 * (dashboard, pipeline, edit job, branding, team management) — any
 * recruiter can in, once they've signed the current SLA. Feature-level
 * limits (job post cap, Ads, website embed) are enforced separately where
 * they apply — see can_post_another_job() and require_active_recruiter().
 */
function require_recruiter_with_sla(): void
{
    require_recruiter();
    if (!has_current_sla()) {
        flash('info', 'We\'ve updated our Service Level Agreement — please review and sign it to continue.');
        redirect('/sla_sign.php');
    }
}

/**
 * Gate for Paid-plan-only recruiter features (Ads, website embed, Direct
 * Search). Free-plan recruiters are sent to the Pricing page to upgrade.
 */
function require_active_recruiter(): void
{
    require_recruiter_with_sla();
    if (!has_active_recruiter_subscription()) {
        flash('info', 'This feature is part of the Paid plan — upgrade for unlimited access.');
        redirect('/pricing.php');
    }
}

/** True if this recruiter's most recent signed SLA is the current version (see CURRENT_SLA_VERSION). */
function has_current_sla(?array $user = null): bool
{
    $user = $user ?? current_user();
    if (!$user) {
        return false;
    }
    $stmt = db()->prepare('SELECT sla_version FROM recruiter_slas WHERE user_id = ? ORDER BY signed_at DESC LIMIT 1');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();
    return $row && (int) $row['sla_version'] >= CURRENT_SLA_VERSION;
}

/** Records a notification for the in-app bell. Kept separate from email — always available even if SMTP isn't. */
function create_notification(int $userId, string $type, string $title, string $body = '', string $link = ''): void
{
    db()->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)')
        ->execute([$userId, $type, $title, $body, $link]);
    // Every notification, from every current and future feature, also goes
    // out as a real OS-level browser push — one shared choke point, no
    // per-feature wiring needed. No-op for a user with no push subscriptions.
    webpush_notify_user($userId, $title, $body, $link);
}

function unread_notification_count(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int) $stmt->fetch()['c'];
}

/** Where someone should land right after logging in / signing up, based on role. */
function post_login_redirect_path(array $user): string
{
    return $user['role'] === 'recruiter' ? '/dashboard.php' : '/jobs.php';
}

/** The company_id of the current recruiter's team, or null if they have none yet. */
function current_recruiter_company_id(): ?int
{
    $user = current_user();
    if (!$user) {
        return null;
    }
    $stmt = db()->prepare('SELECT company_id FROM recruiter_profiles WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();
    return $row && $row['company_id'] ? (int) $row['company_id'] : null;
}

function format_zar(float $amount): string
{
    return 'R' . number_format($amount, 2);
}

/**
 * True only for a well-formed http(s) URL. PHP's FILTER_VALIDATE_URL alone
 * is NOT enough here — it happily accepts "javascript:alert(1)" as a "valid
 * URL" since that's still syntactically scheme:content, so anything that
 * later gets rendered as a raw href (company website, social links, etc.)
 * needs this scheme check too or it's a stored-XSS vector via a self-service
 * form field.
 */
function is_safe_http_url(string $url): bool
{
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}

/** Whole days between two datetime strings (or now, if $end is omitted). Never negative. */
function days_between(string $start, ?string $end = null): int
{
    $startTs = strtotime($start);
    $endTs = $end ? strtotime($end) : time();
    if ($startTs === false || $endTs === false) {
        return 0;
    }
    return max(0, (int) floor(($endTs - $startTs) / 86400));
}

function log_in_user(int $userId): void
{
    // Regenerate the session id on every login to prevent session fixation.
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function log_out_user(): void
{
    $_SESSION = [];
    session_destroy();
}

// --- Misc -----------------------------------------------------------------------

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function base_url(string $path = ''): string
{
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}

/** Safe, unique filename for an uploaded file. */
function safe_upload_filename(string $originalName, int $ownerId): string
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $ext = preg_replace('/[^a-z0-9]/', '', $ext);
    return $ownerId . '_' . bin2hex(random_bytes(8)) . ($ext ? '.' . $ext : '');
}

// --- Site-wide settings (privileged-account-managed: social links, etc.) ---

function get_site_setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $stmt = db()->query('SELECT setting_key, setting_value FROM site_settings');
        foreach ($stmt->fetchAll() as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}

function set_site_setting(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

/** [platform key => display label] for the footer social icons. */
function social_platforms(): array
{
    return [
        'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'instagram' => 'Instagram',
        'x' => 'X (Twitter)', 'tiktok' => 'TikTok', 'youtube' => 'YouTube',
    ];
}

/** Real, recognizable single-color (currentColor) SVG glyphs for the footer social icons — no icon font/CDN needed. */
function social_icon_svg(string $platform): string
{
    $icons = [
        'facebook' => '<path d="M13.5 9H15V6.5h-1.75C11.5 6.5 10.5 7.6 10.5 9.4V11H9v2.5h1.5V19h2.5v-5.5h1.75l.5-2.5h-2.25v-1.2c0-.55.25-.8.75-.8Z"/>',
        'linkedin' => '<path d="M6.94 8.5a1.44 1.44 0 1 0 0-2.88 1.44 1.44 0 0 0 0 2.88ZM6 10h1.88v8.5H6V10Zm4.5 0h1.8v1.16h.03c.25-.47.87-1.16 1.8-1.16 1.92 0 2.27 1.27 2.27 2.92v4.58h-1.88v-4.06c0-.97-.02-2.22-1.35-2.22-1.35 0-1.56 1.06-1.56 2.15v4.13H10.5V10Z"/>',
        'instagram' => '<path d="M9 5h6a4 4 0 0 1 4 4v6a4 4 0 0 1-4 4H9a4 4 0 0 1-4-4V9a4 4 0 0 1 4-4Zm0 1.5A2.5 2.5 0 0 0 6.5 9v6A2.5 2.5 0 0 0 9 17.5h6a2.5 2.5 0 0 0 2.5-2.5V9A2.5 2.5 0 0 0 15 6.5H9Zm3 2.75A3.25 3.25 0 1 1 8.75 12 3.25 3.25 0 0 1 12 8.75Zm0 1.5A1.75 1.75 0 1 0 13.75 12 1.75 1.75 0 0 0 12 10.25Zm3.6-2.65a.8.8 0 1 1-.8.8.8.8 0 0 1 .8-.8Z"/>',
        'x' => '<path d="M6 6h3.2l3.05 4.1L15.8 6H18l-4.6 5.65L18.4 18h-3.2l-3.36-4.5L7.9 18H5.7l4.9-6.02Z"/>',
        'tiktok' => '<path d="M14.3 5h1.9c.2 1.4 1.15 2.55 2.6 2.9v1.9c-1 0-1.9-.3-2.6-.85v4.5a4.05 4.05 0 1 1-4.05-4.05c.2 0 .4.02.6.05v1.95a2.1 2.1 0 1 0 1.55 2.05V5Z"/>',
        'youtube' => '<path d="M18.6 8.2a2.2 2.2 0 0 0-1.55-1.56C15.7 6.3 12 6.3 12 6.3s-3.7 0-5.05.34A2.2 2.2 0 0 0 5.4 8.2 22.9 22.9 0 0 0 5.05 12a22.9 22.9 0 0 0 .35 3.8 2.2 2.2 0 0 0 1.55 1.56C8.3 17.7 12 17.7 12 17.7s3.7 0 5.05-.34a2.2 2.2 0 0 0 1.55-1.56A22.9 22.9 0 0 0 18.95 12a22.9 22.9 0 0 0-.35-3.8ZM10.4 14.4V9.6L14.6 12Z"/>',
    ];
    return $icons[$platform] ?? '';
}

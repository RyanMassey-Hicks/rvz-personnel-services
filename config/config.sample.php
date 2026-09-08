<?php
/**
 * Copy this file to config.php (same folder) and fill in your real values.
 * config.php is the file every other script actually loads — keep the real
 * one out of version control / never share it publicly.
 *
 * Where to find your MySQL details:
 *   cPanel (login at https://yourdomain:2083, or your host-h.net
 *   reseller's cPanel URL) → MySQL® Databases → create a database + a
 *   database user, then add the user to the database with ALL
 *   PRIVILEGES. cPanel usually prefixes both the database name and
 *   username with your cPanel account username, e.g. "cpuser_dittohire"
 *   and "cpuser_dittouser" — check the MySQL® Databases page for the
 *   exact names it created.
 *
 * IMPORTANT — DB_HOST is NOT always "localhost" on this host: some
 * host-h.net / Host Africa cPanel reseller accounts put MySQL on a
 * separate database server with its own hostname (for this project,
 * confirmed as sql63.jnb2.host-h.net). Check cPanel → MySQL® Databases
 * (or ask your host) for the exact hostname before assuming localhost.
 */

// --- Database (from cPanel → MySQL® Databases) ---
define('DB_HOST', 'localhost');          // Change if your host uses a separate DB server hostname (see note above)
define('DB_NAME', 'cpuser_dittohire');
define('DB_USER', 'cpuser_dittouser');
define('DB_PASS', 'your-db-password');

// --- Site ---
define('SITE_NAME', 'RVZ Personnel Services & Labour Hiring Specialists');
define('SITE_URL', 'https://www.nhestate.co.za');  // no trailing slash — adjust if this runs in a subfolder, e.g. '.../careers'
define('COMPANY_LEGAL_NAME', 'RVZ International Group (Pty) Ltd t/a RVZ Personnel Services & Labour Hiring Specialists');
define('COMPANY_REG_NUMBER', '2023/905207/07'); // shown in the footer and on the SLA PDF
define('GENERAL_INFO_EMAIL', 'info@yourdomain.co.za'); // homepage "Contact Us" form goes here

// --- Google OAuth (console.cloud.google.com → APIs & Services → Credentials) ---
define('GOOGLE_CLIENT_ID', '');
define('GOOGLE_CLIENT_SECRET', '');

// --- LinkedIn OAuth (linkedin.com/developers/apps → Auth tab) ---
define('LINKEDIN_CLIENT_ID', '');
define('LINKEDIN_CLIENT_SECRET', '');

// --- Facebook OAuth (developers.facebook.com/apps → Facebook Login → Settings) ---
define('FACEBOOK_CLIENT_ID', '');
define('FACEBOOK_CLIENT_SECRET', '');

// --- Uploads ---
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5MB
define('MAX_LOGO_UPLOAD_BYTES', 2 * 1024 * 1024); // 2MB, company logos only

// --- Recruiter access & billing (Paystack) ---
// This one email always gets free recruiter access + branding privileges
// (uploading the company logo and branded content). Every other account
// that clicks "I'm hiring" must pay the monthly recruiter fee below via
// Paystack before they can post jobs or use the pipeline. Matching is
// case-insensitive; see includes/functions.php: is_privileged_recruiter().
// This is a login credential, not a site domain — it doesn't need to match
// SITE_URL. Change it if Ryan's real login email is different.
define('PRIVILEGED_RECRUITER_EMAIL', 'ryan@rvzgroup.co.za');
define('RECRUITER_MONTHLY_PRICE_ZAR', 2500);
define('FREE_TIER_JOB_LIMIT', 2); // job posts per recruiter per calendar month on the Free plan

// From dashboard.paystack.com → Settings → API Keys & Webhooks.
// Use the sk_test_.../pk_test_... pair while testing, then switch to the
// sk_live_.../pk_live_... pair when you go live.
define('PAYSTACK_SECRET_KEY', '');
define('PAYSTACK_PUBLIC_KEY', '');
// Create a Plan in the Paystack dashboard (Payments → Plans → New Plan):
// amount R2500, interval "Monthly", currency ZAR. Paste its plan code
// (looks like PLN_xxxxxxxx) here — this is what makes billing recurring.
define('PAYSTACK_PLAN_CODE', '');
// Paystack dashboard → Settings → API Keys & Webhooks → Webhook URL:
//   https://www.nhestate.co.za/paystack/webhook.php
// This is what keeps a subscription's current_period_end rolling forward
// each month automatically, and catches failed/cancelled renewals.

// Encrypts per-company custom SMTP passwords at rest (Admin → Integrations,
// ryan@rvzgroup.co.za only). Generate a fresh 64-char hex key once per
// install, e.g. `openssl rand -hex 32`, and never change it afterwards —
// doing so makes any already-saved company SMTP passwords undecryptable.
define('CREDENTIAL_ENCRYPTION_KEY', 'change-me-generate-a-random-64-char-hex-string');

// --- Outgoing email (SMTP via an Xneelo cPanel mailbox) ---
// Create the mailbox first in cPanel -> Email Accounts (e.g. noreply@yourdomain.co.za),
// then fill these in. Until SMTP_USER/SMTP_PASS are set, the site logs
// emails via error_log() instead of sending them, so nothing breaks in the
// meantime — everything (application confirmations, stage-change alerts,
// the newsletter, the signed SLA PDF) is wired up and ready the moment you
// add real credentials here.
define('SMTP_HOST', 'mail.yourdomain.co.za');
define('SMTP_PORT', 587); // 587 = STARTTLS, 465 = SSL
define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM_EMAIL', 'noreply@yourdomain.co.za');
define('SMTP_FROM_NAME', SITE_NAME);

// --- AI support chatbot (includes/gemini_chat.php) ---
// aistudio.google.com -> Get API key. Gemini's TEXT models have a genuine
// free tier, so the chatbot uses Gemini as its "head" AI for natural
// replies. Leave blank, or if Gemini errors/hits a quota limit at any
// point, ajax/chatbot.php automatically falls back to the built-in
// rule-based engine — a user never sees a provider billing/quota error.
define('GEMINI_API_KEY', '');
define('GEMINI_CHAT_MODEL', 'gemini-3.6-flash');

// --- AI job-ad generation (includes/ai_image.php) ---
// Every company defaults to a free, keyless image provider (Pollinations) —
// see ai_image_provider on the companies table — so there is no
// platform-wide AI credit balance that can ever run low. A company can
// instead bring its own Gemini or OpenAI API key, set up per-company in
// admin_integrations.php ("the control room"); that key/cost is theirs,
// never stored here. GEMINI_IMAGE_MODEL is just the model version used
// whenever a company's own Gemini key is active.
//
// Note: Gemini's free tier commonly has 0 quota for image-generation
// models specifically (Google requires billing enabled even for the first
// image) — that's exactly why image generation uses a separate free
// provider by default instead of GEMINI_API_KEY above.
define('GEMINI_IMAGE_MODEL', 'gemini-2.5-flash-image');

// --- Google Analytics (GA4), gated by Consent Mode v2 — see includes/header.php ---
// Leave blank to not load GA4 at all (no script, no consent-mode plumbing
// either — nothing to gate). Set to a real Measurement ID (G-XXXXXXX) from
// analytics.google.com to enable it; the cookie/consent banner's "Analytics"
// toggle controls whether tracking is actually granted, per visitor.
define('GA4_MEASUREMENT_ID', '');

// --- Google Ads conversion tracking, gated by Consent Mode v2 — see includes/header.php ---
// Leave blank until there's an actual Google Ads campaign to attach this
// to — the cookie/consent banner's "Advertising" toggle already collects
// consent for this purpose, but no data is sent anywhere until an ID is
// set here. Set to your Conversion ID (AW-XXXXXXXXX) from
// ads.google.com -> Tools -> Conversions to enable it. Enhanced
// Conversions (real name/email/phone/address matching) still needs to be
// wired up per-page once a specific conversion action is defined — see
// the comment next to where this constant is used in includes/header.php.
define('GOOGLE_ADS_CONVERSION_ID', '');

// --- Web Push (browser push notifications, works even when the site isn't
// open — see includes/webpush.php) ---
// Generate ONE P-256 "application server key" pair for this install and
// never rotate it once real subscriptions exist (every stored subscription
// is bound to this exact key pair). Easiest way to generate: temporarily
// drop a one-off script using openssl_pkey_new(['curve_name' => 'prime256v1',
// 'private_key_type' => OPENSSL_KEYTYPE_EC]) + openssl_pkey_get_details(),
// base64url-encode the raw 65-byte public point (0x04 || X || Y) and the
// raw 32-byte private scalar, then delete the script.
define('VAPID_PUBLIC_KEY', '');
define('VAPID_PRIVATE_KEY', '');
define('VAPID_SUBJECT', 'mailto:' . GENERAL_INFO_EMAIL);

// --- Scheduled auto-review agent (maintenance_api.php) ---
// A narrow, purpose-built secret for the daily automated error-log review —
// generate your own with e.g. `php -r "echo bin2hex(random_bytes(32));"`.
// Deliberately NOT the FTP password: this key can only do what
// maintenance_api.php explicitly allows.
define('MAINTENANCE_API_KEY', '');

// Bump this whenever sla_sign.php's content changes materially — anyone
// whose signed sla_version is lower than this must re-sign before using any
// recruiter feature (see require_active_recruiter()).
define('CURRENT_SLA_VERSION', 1);

// --- Misc ---
define('DEBUG_MODE', false); // shows errors on-page when true — only for active local debugging, never on the live site
// Errors are always logged (regardless of DEBUG_MODE) to a file inside
// config/ — already blocked from every web request by config/.htaccess,
// so it's never downloadable but is still reviewable via FTP/File Manager.
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_errors.log');
ini_set('display_errors', DEBUG_MODE ? '1' : '0');

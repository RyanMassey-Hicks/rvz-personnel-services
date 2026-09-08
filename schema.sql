-- RVZ Personnel Services & Labour Hiring Specialists — MySQL schema
-- Import this via cPanel: log in at https://nhestate.co.za:2083 (or your
-- host-h.net reseller's cPanel login) → phpMyAdmin → select your database
-- (rvzrecruit) → Import tab → choose this file → Go.

SET NAMES utf8mb4;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(150) NOT NULL UNIQUE,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NULL,           -- NULL allowed: pure social-login accounts may never set a password
    first_name VARCHAR(100) DEFAULT '',
    last_name VARCHAR(100) DEFAULT '',
    role ENUM('candidate','recruiter') NOT NULL DEFAULT 'candidate',
    avatar_url VARCHAR(500) DEFAULT '',
    signed_up_via VARCHAR(30) DEFAULT 'website', -- 'website', 'google', 'linkedin', 'facebook'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE oauth_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    provider VARCHAR(30) NOT NULL,             -- 'google', 'linkedin', 'facebook'
    provider_user_id VARCHAR(190) NOT NULL,
    UNIQUE KEY provider_user (provider, provider_user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    website VARCHAR(255) DEFAULT '',
    logo_path VARCHAR(255) DEFAULT '',       -- company logo upload, restricted to the privileged recruiter account
    brand_color VARCHAR(20) DEFAULT '',      -- optional hex colour for branded company pages, e.g. #123abc
    brand_tagline VARCHAR(255) DEFAULT '',   -- optional short branded tagline shown on the job board/company page
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE candidate_profiles (
    user_id INT PRIMARY KEY,
    headline VARCHAR(200) DEFAULT '',
    location VARCHAR(150) DEFAULT '',
    linkedin_url VARCHAR(255) DEFAULT '',
    resume_path VARCHAR(255) DEFAULT '',
    resume_text MEDIUMTEXT NULL,
    skills VARCHAR(500) DEFAULT '',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FULLTEXT INDEX idx_resume_text (resume_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE recruiter_profiles (
    user_id INT PRIMARY KEY,
    company_id INT NULL,
    job_title VARCHAR(150) DEFAULT '',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    posted_by INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(150) DEFAULT '',
    employment_type ENUM('full_time','part_time','contract','internship') DEFAULT 'full_time',
    salary_min INT NULL,
    salary_max INT NULL,
    is_remote TINYINT(1) DEFAULT 0,
    is_open TINYINT(1) DEFAULT 1,
    views_count INT UNSIGNED NOT NULL DEFAULT 0,   -- incremented on job.php views by non-owners; dashboard "Views" circle
    shares_count INT UNSIGNED NOT NULL DEFAULT 0,  -- incremented by share.php; dashboard "Shares" circle
    closed_at DATETIME NULL,                       -- set when is_open flips 1→0; powers "days to fill" on past listings
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    candidate_id INT NOT NULL,
    resume_path VARCHAR(255) DEFAULT '',
    cover_letter TEXT,
    stage ENUM('applied','screening','interview','offer','hired','rejected') NOT NULL DEFAULT 'applied',
    source VARCHAR(20) DEFAULT 'website',       -- 'website', 'google', 'linkedin', 'facebook'
    applied_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY job_candidate (job_id, candidate_id),
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (candidate_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Recruiter-side billing (Paystack) ---------------------------------------
-- Everyone gets a candidate account and can browse/apply for free. Only the
-- recruiter side (posting jobs, the pipeline) is paid, at R2500/month, EXCEPT
-- the one privileged account configured as PRIVILEGED_RECRUITER_EMAIL in
-- config.php (see includes/functions.php: is_privileged_recruiter()), which
-- always has free recruiter access.

CREATE TABLE subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    paystack_customer_code VARCHAR(100) DEFAULT '',
    paystack_subscription_code VARCHAR(100) DEFAULT '',
    paystack_authorization_code VARCHAR(100) DEFAULT '',
    plan_code VARCHAR(100) DEFAULT '',
    status ENUM('inactive','active','past_due','cancelled') NOT NULL DEFAULT 'inactive',
    amount_cents INT DEFAULT 250000,          -- R2500.00, in the smallest currency unit (cents) as Paystack expects
    current_period_end DATETIME NULL,         -- recruiter tools stay unlocked until this timestamp
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY one_subscription_per_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reference VARCHAR(100) NOT NULL UNIQUE,   -- Paystack transaction reference
    amount_cents INT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',  -- pending, success, failed
    paystack_event VARCHAR(50) DEFAULT '',    -- which webhook event last touched this row, if any
    raw_response TEXT,                        -- last Paystack API/webhook payload, for reconciliation/support
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =================================================================
-- Migration (safe to re-run) — for databases that already imported an
-- earlier version of this schema. Recent MariaDB versions (10.3+, which
-- covers current host-h.net/cPanel MySQL databases) support
-- "ADD COLUMN IF NOT EXISTS", so this no-ops harmlessly on a
-- fresh import where the jobs table above already has these columns.
-- =================================================================
ALTER TABLE jobs ADD COLUMN IF NOT EXISTS views_count INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE jobs ADD COLUMN IF NOT EXISTS shares_count INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE jobs ADD COLUMN IF NOT EXISTS closed_at DATETIME NULL;

-- =================================================================
-- Migration 2026-08 — platform overhaul (in-depth profiles, saved jobs,
-- newsletter, site settings, cookie consent log, recruiter SLA e-signature,
-- AI ad generation, Direct Search talent pool/saved searches, industries).
-- Same file as database/rvzrecruit_migration_2026-08.sql at the project
-- root — kept in sync here so a brand-new install ends up identical to the
-- live, migrated Xneelo database. See that file's header comment for the
-- full explanation; only the SQL is repeated below.
-- =================================================================
ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS phone VARCHAR(30) DEFAULT '';
ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS id_or_passport VARCHAR(30) DEFAULT '';
ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS date_of_birth DATE NULL;
ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS gender VARCHAR(30) DEFAULT '';
ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS nationality VARCHAR(80) DEFAULT '';
ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS drivers_license VARCHAR(60) DEFAULT '';
ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS province VARCHAR(80) DEFAULT '';
ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS postal_code VARCHAR(12) DEFAULT '';
ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS physical_address VARCHAR(255) DEFAULT '';
ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS professional_summary TEXT NULL;
ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS languages VARCHAR(255) DEFAULT '';
ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS salary_expectation_min INT NULL;
ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS salary_expectation_max INT NULL;
ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS notice_period VARCHAR(60) DEFAULT '';
ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS willing_to_relocate TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS willing_to_travel TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS work_experience TEXT NULL;
ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS education TEXT NULL;
ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(255) DEFAULT '';
ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS opt_in_job_alerts TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS opt_in_newsletter TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS ee_consent TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS race_ee_status VARCHAR(40) DEFAULT '';
ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS disability_status VARCHAR(40) DEFAULT '';

CREATE TABLE IF NOT EXISTS industries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO industries (name)
SELECT v.name FROM (
    SELECT 'Accounting & Finance' AS name UNION ALL SELECT 'Administration & Office Support'
    UNION ALL SELECT 'Agriculture & Farming' UNION ALL SELECT 'Architecture & Engineering'
    UNION ALL SELECT 'Automotive' UNION ALL SELECT 'Banking & Financial Services'
    UNION ALL SELECT 'Call Centre & Customer Service' UNION ALL SELECT 'Construction'
    UNION ALL SELECT 'Consulting' UNION ALL SELECT 'Education & Training'
    UNION ALL SELECT 'Events & Hospitality' UNION ALL SELECT 'FMCG & Retail'
    UNION ALL SELECT 'Government & Public Sector' UNION ALL SELECT 'Healthcare & Medical'
    UNION ALL SELECT 'Human Resources' UNION ALL SELECT 'Information Technology'
    UNION ALL SELECT 'Insurance' UNION ALL SELECT 'Legal'
    UNION ALL SELECT 'Logistics, Warehousing & Supply Chain' UNION ALL SELECT 'Manufacturing'
    UNION ALL SELECT 'Marketing, Media & Design' UNION ALL SELECT 'Mining & Resources'
    UNION ALL SELECT 'NGO & Non-Profit' UNION ALL SELECT 'Sales & Business Development'
    UNION ALL SELECT 'Security' UNION ALL SELECT 'Skilled Trades & Labour'
    UNION ALL SELECT 'Telecommunications' UNION ALL SELECT 'Transport & Freight'
    UNION ALL SELECT 'Travel & Tourism' UNION ALL SELECT 'Other'
) v
WHERE NOT EXISTS (SELECT 1 FROM industries WHERE industries.name = v.name);

ALTER TABLE jobs ADD COLUMN IF NOT EXISTS industry_id INT NULL;
ALTER TABLE jobs ADD COLUMN IF NOT EXISTS use_response_handling TINYINT(1) NOT NULL DEFAULT 0;

SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'jobs_industry_fk'
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE jobs ADD CONSTRAINT jobs_industry_fk FOREIGN KEY (industry_id) REFERENCES industries(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE applications ADD COLUMN IF NOT EXISTS viewed_by_recruiter TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE applications ADD COLUMN IF NOT EXISTS viewed_at DATETIME NULL;
ALTER TABLE applications ADD COLUMN IF NOT EXISTS contacted TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE applications ADD COLUMN IF NOT EXISTS contacted_at DATETIME NULL;
ALTER TABLE applications ADD COLUMN IF NOT EXISTS shortlisted TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE applications ADD COLUMN IF NOT EXISTS placement_status VARCHAR(20) NULL;

ALTER TABLE recruiter_profiles ADD COLUMN IF NOT EXISTS phone VARCHAR(30) DEFAULT '';
ALTER TABLE recruiter_profiles ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(255) DEFAULT '';

CREATE TABLE IF NOT EXISTS saved_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY user_job (user_id, job_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    token VARCHAR(64) DEFAULT '',
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    unsubscribed_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS consent_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    choice VARCHAR(20) NOT NULL,
    ai_consent TINYINT(1) NOT NULL DEFAULT 0,
    analytics_consent TINYINT(1) NOT NULL DEFAULT 0,
    advertising_consent TINYINT(1) NOT NULL DEFAULT 0,
    ip_address VARCHAR(45) DEFAULT '',
    user_agent VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS recruiter_slas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    signed_name VARCHAR(150) NOT NULL,
    signed_at DATETIME NOT NULL,
    ip_address VARCHAR(45) DEFAULT '',
    pdf_path VARCHAR(255) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ad_generations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    user_id INT NOT NULL,
    prompt TEXT NULL,
    image_path VARCHAR(255) NOT NULL,
    provider VARCHAR(20) NOT NULL DEFAULT 'free',
    copy_text TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS talent_pool (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recruiter_id INT NOT NULL,
    candidate_id INT NOT NULL,
    notes TEXT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY recruiter_candidate (recruiter_id, candidate_id),
    FOREIGN KEY (recruiter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (candidate_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS saved_searches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recruiter_id INT NOT NULL,
    skills VARCHAR(255) DEFAULT '',
    location VARCHAR(150) DEFAULT '',
    languages VARCHAR(255) DEFAULT '',
    notify TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recruiter_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    subject VARCHAR(255) DEFAULT '',
    transcript TEXT NULL,
    status ENUM('open','resolved') NOT NULL DEFAULT 'open',
    resolution_note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS team_invites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    invited_by INT NOT NULL,
    email VARCHAR(190) NOT NULL,
    job_title VARCHAR(150) DEFAULT '',
    token VARCHAR(64) NOT NULL UNIQUE,
    status ENUM('pending','accepted','revoked') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    accepted_at DATETIME NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS candidate_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    doc_type ENUM('id_document','passport','drivers_license','qualification','cover_letter','other') NOT NULL,
    label VARCHAR(150) DEFAULT '',
    file_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) DEFAULT '',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE companies ADD COLUMN IF NOT EXISTS description TEXT NULL;

CREATE TABLE IF NOT EXISTS company_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    photo_path VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS screening_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    requested_by INT NOT NULL,
    status ENUM('requested','completed') NOT NULL DEFAULT 'requested',
    result_note TEXT NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE recruiter_slas ADD COLUMN IF NOT EXISTS sla_version INT NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(40) NOT NULL DEFAULT 'general',
    title VARCHAR(255) NOT NULL,
    body VARCHAR(500) DEFAULT '',
    link VARCHAR(255) DEFAULT '',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS own_transport VARCHAR(10) DEFAULT '';

ALTER TABLE companies ADD COLUMN IF NOT EXISTS brand_color_2 VARCHAR(20) DEFAULT '';
ALTER TABLE companies ADD COLUMN IF NOT EXISTS brand_color_3 VARCHAR(20) DEFAULT '';
ALTER TABLE companies ADD COLUMN IF NOT EXISTS brand_color_4 VARCHAR(20) DEFAULT '';
ALTER TABLE companies ADD COLUMN IF NOT EXISTS registration_number VARCHAR(60) DEFAULT '';
ALTER TABLE companies ADD COLUMN IF NOT EXISTS city VARCHAR(120) DEFAULT '';
ALTER TABLE companies ADD COLUMN IF NOT EXISTS contact_email VARCHAR(190) DEFAULT '';
ALTER TABLE companies ADD COLUMN IF NOT EXISTS contact_phone VARCHAR(40) DEFAULT '';

-- ----------------------------------------------------------------------------
-- Combined team-seat billing — the head/company account pre-purchases a
-- number of seats and pays ONE recurring monthly amount (seats x
-- RECRUITER_MONTHLY_PRICE_ZAR) instead of every team member billing
-- individually. Invites are free up to the purchased seat count.
-- ----------------------------------------------------------------------------
ALTER TABLE companies ADD COLUMN IF NOT EXISTS seat_quantity INT NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS company_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    paystack_customer_code VARCHAR(100) DEFAULT '',
    paystack_authorization_code VARCHAR(100) DEFAULT '',
    plan_code VARCHAR(100) DEFAULT '',
    seat_quantity INT NOT NULL DEFAULT 1,
    amount_cents INT NOT NULL DEFAULT 0,
    status ENUM('inactive','active','past_due','cancelled') NOT NULL DEFAULT 'inactive',
    current_period_end DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY one_subscription_per_company (company_id),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One shared Paystack Plan per distinct seat count (e.g. every company that
-- buys 3 seats reuses the same "3 seats" Plan) — created automatically via
-- the Paystack API the first time that seat count is purchased.
CREATE TABLE IF NOT EXISTS paystack_seat_plans (
    seats INT PRIMARY KEY,
    plan_code VARCHAR(100) NOT NULL,
    amount_cents INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Per-company custom outbound email (SMTP) — lets a Paid company send
-- system emails (application confirmations, stage updates, etc.) from its
-- own domain/mailbox instead of RVZ's shared SMTP. Set up by RVZ admin
-- (ryan@rvzgroup.co.za only) as a support/onboarding action — see
-- admin_integrations.php. smtp_pass_encrypted is encrypted at rest with
-- CREDENTIAL_ENCRYPTION_KEY, never stored or displayed in plain text.
-- ----------------------------------------------------------------------------
ALTER TABLE companies ADD COLUMN IF NOT EXISTS use_custom_smtp TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE companies ADD COLUMN IF NOT EXISTS smtp_host VARCHAR(190) DEFAULT '';
ALTER TABLE companies ADD COLUMN IF NOT EXISTS smtp_port INT DEFAULT 587;
ALTER TABLE companies ADD COLUMN IF NOT EXISTS smtp_secure VARCHAR(10) DEFAULT 'tls';
ALTER TABLE companies ADD COLUMN IF NOT EXISTS smtp_user VARCHAR(190) DEFAULT '';
ALTER TABLE companies ADD COLUMN IF NOT EXISTS smtp_pass_encrypted TEXT NULL;
ALTER TABLE companies ADD COLUMN IF NOT EXISTS smtp_from_email VARCHAR(190) DEFAULT '';
ALTER TABLE companies ADD COLUMN IF NOT EXISTS smtp_from_name VARCHAR(150) DEFAULT '';

-- ----------------------------------------------------------------------------
-- Per-company AI ad-image generation (see admin_integrations.php — "the
-- control room"). Every company defaults to 'free' (Pollinations — no API
-- key, no billing risk, ever). A company can instead bring its own Gemini
-- or OpenAI key (their own cost/account) plus free-form brand guidelines
-- that get folded into every ad prompt. ai_image_api_key_encrypted is
-- encrypted at rest with CREDENTIAL_ENCRYPTION_KEY, same as smtp_pass_encrypted.
-- ----------------------------------------------------------------------------
ALTER TABLE companies ADD COLUMN IF NOT EXISTS ai_image_provider VARCHAR(20) NOT NULL DEFAULT 'free';
ALTER TABLE companies ADD COLUMN IF NOT EXISTS ai_image_api_key_encrypted TEXT NULL;
ALTER TABLE companies ADD COLUMN IF NOT EXISTS ai_brand_guidelines TEXT NULL;

-- ----------------------------------------------------------------------------
-- Web Push subscriptions — one row per browser/device a user has granted
-- notification permission on (see includes/webpush.php, ajax/push_subscribe.php).
-- endpoint/p256dh/auth come straight from the browser's PushSubscription
-- object; a dead subscription (push service returns 404/410) is deleted by
-- webpush_send() the next time a send to it fails that way.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    endpoint VARCHAR(500) NOT NULL,
    p256dh VARCHAR(255) NOT NULL,
    auth VARCHAR(255) NOT NULL,
    user_agent VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY user_endpoint (user_id, endpoint(255)),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Rate limiting for public, unauthenticated endpoints (the job JSON feed,
-- embeddable careers page, public job board/detail pages) — see
-- includes/rate_limit.php. One row per request; old rows are pruned
-- probabilistically by the app itself, no cron job needed.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rate_limit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    bucket VARCHAR(40) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_bucket_time (ip_address, bucket, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =================================================================
-- Migration 2026-09 — recruiter blog, once-off listing packages
-- (replacing the Free/monthly-subscription pricing model), and the
-- Job Market Trends Report (built entirely from live queries against
-- existing tables — jobs, applications, candidate_profiles — no new
-- tables needed for that feature).
-- =================================================================

CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(190) NOT NULL UNIQUE,
    title VARCHAR(200) NOT NULL,
    excerpt VARCHAR(400) DEFAULT '',
    body LONGTEXT NOT NULL,
    author_name VARCHAR(100) DEFAULT 'RVZ Personnel Services',
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    published_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Once-off job-listing packages (Basic/Standard/Premium) — replaces the old
-- Free-tier + R2500/month subscription model. A package grants a fixed
-- number of job-listing credits plus a fixed number of days of account
-- access; each listing posted against it stays live 30 days (extendable —
-- see addon_purchases below).
CREATE TABLE IF NOT EXISTS recruiter_packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    price_cents INT NOT NULL,
    listing_credits INT NOT NULL,
    access_days INT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO recruiter_packages (code, name, price_cents, listing_credits, access_days, sort_order)
SELECT * FROM (SELECT 'basic' AS code, 'Basic' AS name, 250000 AS price_cents, 1 AS listing_credits, 35 AS access_days, 1 AS sort_order) v
WHERE NOT EXISTS (SELECT 1 FROM recruiter_packages WHERE code = 'basic');
INSERT INTO recruiter_packages (code, name, price_cents, listing_credits, access_days, sort_order)
SELECT * FROM (SELECT 'standard' AS code, 'Standard' AS name, 550000 AS price_cents, 2 AS listing_credits, 70 AS access_days, 2 AS sort_order) v
WHERE NOT EXISTS (SELECT 1 FROM recruiter_packages WHERE code = 'standard');
INSERT INTO recruiter_packages (code, name, price_cents, listing_credits, access_days, sort_order)
SELECT * FROM (SELECT 'premium' AS code, 'Premium' AS name, 950000 AS price_cents, 3 AS listing_credits, 105 AS access_days, 3 AS sort_order) v
WHERE NOT EXISTS (SELECT 1 FROM recruiter_packages WHERE code = 'premium');

-- One row per package a recruiter has bought. credits_remaining is
-- decremented each time a job is posted against this purchase (see
-- job_create.php); access_expires_at is the account-access deadline
-- (35/70/105 days from purchase, per package).
CREATE TABLE IF NOT EXISTS recruiter_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    package_id INT NOT NULL,
    reference VARCHAR(100) NOT NULL UNIQUE,
    amount_cents INT NOT NULL,
    status ENUM('pending','active','expired') NOT NULL DEFAULT 'pending',
    credits_total INT NOT NULL,
    credits_remaining INT NOT NULL,
    access_expires_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES recruiter_packages(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- "Additional Online Features" a la carte add-ons.
CREATE TABLE IF NOT EXISTS addon_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NULL,
    addon_type ENUM('extend_listing','company_presentation') NOT NULL,
    reference VARCHAR(100) NOT NULL UNIQUE,
    amount_cents INT NOT NULL,
    status ENUM('pending','completed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE jobs ADD COLUMN IF NOT EXISTS recruiter_purchase_id INT NULL;
ALTER TABLE jobs ADD COLUMN IF NOT EXISTS listing_expires_at DATETIME NULL;
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'jobs_recruiter_purchase_fk'
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE jobs ADD CONSTRAINT jobs_recruiter_purchase_fk FOREIGN KEY (recruiter_purchase_id) REFERENCES recruiter_purchases(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE companies ADD COLUMN IF NOT EXISTS presentation_purchased TINYINT(1) NOT NULL DEFAULT 0;

-- =================================================================
-- Optional seed data
-- =================================================================
-- Safe to run more than once (checks for an existing row first, since
-- there's no unique key on companies.name to rely on INSERT IGNORE for).
-- This only pre-creates the RVZ company record so it's there before anyone
-- signs up. It deliberately does NOT create a user account or password —
-- there is nothing to seed for the privileged recruiter login: sign up
-- normally through signup.php with whatever email is set as
-- PRIVILEGED_RECRUITER_EMAIL in config/config.php, then click "I'm hiring"
-- once, and includes/functions.php's is_privileged_recruiter() recognises
-- that email automatically and grants free recruiter access + branding
-- privileges. No row here can bypass that check or substitute for it, so
-- seeding a password here would only be a second place a credential could
-- leak from — there's no reason to.

INSERT INTO companies (name, website)
SELECT 'RVZ Personnel Services & Labour Hiring Specialists', ''
WHERE NOT EXISTS (
    SELECT 1 FROM companies WHERE name = 'RVZ Personnel Services & Labour Hiring Specialists'
);

-- A handful of original launch articles for the Recruiter Blog, so the
-- section isn't empty on day one. Original RVZ-authored content.
INSERT INTO blog_posts (slug, title, excerpt, body, published_at)
SELECT 'writing-job-ads-that-attract-the-right-candidates',
       'Writing Job Ads That Attract the Right Candidates',
       'Five practical changes to a job ad that measurably improve who applies — not just how many people apply.',
       'A job ad that gets 200 applications isn''t automatically a good job ad if 190 of them are unqualified. The goal isn''t volume, it''s fit.\n\nStart with the first two lines. Most candidates decide whether to keep reading in the first two lines of your ad, so lead with what the role actually involves day to day, not a generic paragraph about company culture.\n\nBe specific about requirements. "3+ years'' experience with Excel and basic bookkeeping" filters far better than "detail-oriented team player." Vague requirements attract vague applicants.\n\nState the salary range where you can. Candidates increasingly skip ads that hide pay entirely, and it saves both sides time during screening.\n\nList the practical details. Location, remote/hybrid/on-site, employment type and start date all belong near the top, not buried in a paragraph.\n\nEnd with a clear next step. Tell candidates exactly what happens after they apply — that alone reduces drop-off during your screening process.',
       NOW()
WHERE NOT EXISTS (SELECT 1 FROM blog_posts WHERE slug = 'writing-job-ads-that-attract-the-right-candidates');

INSERT INTO blog_posts (slug, title, excerpt, body, published_at)
SELECT 'reducing-time-to-hire-without-cutting-corners',
       'Reducing Time-to-Hire Without Cutting Corners',
       'A slow hiring process loses good candidates to faster-moving competitors. Here''s how to tighten yours safely.',
       'Every extra week a vacancy stays open costs money and momentum — and your strongest candidates are usually the ones with other offers on the table, so they''re the first to walk away from a slow process.\n\nScreen against a checklist, not a gut feeling. Deciding in advance what "qualified" looks like for a specific role removes a huge amount of second-guessing later in the process.\n\nBatch your interviews. Reviewing five candidates in one sitting is faster and more consistent than reviewing them one at a time across two weeks.\n\nUse a shared pipeline. When everyone involved in a hiring decision can see where each candidate stands, you avoid the back-and-forth emails that quietly add days to a process.\n\nDecide your interview stages before you start, not during. Adding an extra round mid-process because someone wasn''t sure is one of the most common (and avoidable) sources of delay.\n\nNone of this means rushing a decision — it means removing the friction that has nothing to do with decision quality.',
       NOW()
WHERE NOT EXISTS (SELECT 1 FROM blog_posts WHERE slug = 'reducing-time-to-hire-without-cutting-corners');

INSERT INTO blog_posts (slug, title, excerpt, body, published_at)
SELECT 'what-response-handling-actually-solves',
       'What Response Handling Actually Solves',
       'Outsourcing your first-pass screening isn''t about being hands-off — it''s about spending your time where it matters most.',
       'Most hiring managers don''t dislike interviewing — they dislike the hours spent reading through applications that were never going to be a fit in the first place.\n\nResponse Handling exists for that specific gap: a dedicated team advertises your vacancy, reads every application against your stated criteria, and hands you a shortlist instead of an inbox. You still make every hiring decision — you''re just making it from a filtered list instead of a raw one.\n\nIt tends to matter most for two kinds of roles: high-volume postings where the sheer number of applications is the bottleneck, and specialist roles where screening requires knowing exactly what to look for on a CV.\n\nIf you''re currently spending more time reading applications than interviewing candidates, that''s usually the clearest sign it''s worth trying.',
       NOW()
WHERE NOT EXISTS (SELECT 1 FROM blog_posts WHERE slug = 'what-response-handling-actually-solves');

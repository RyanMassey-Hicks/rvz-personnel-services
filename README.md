# RVZ Personnel Services & Labour Hiring Specialists — PHP Edition (for cPanel Shared Hosting)

Plain PHP + MySQL rewrite of the Django ATS. No Composer, no Node,
no background workers, no Python — just files you upload via FTP or
cPanel's File Manager, and a MySQL database created through the same
cPanel account. This matches what this project's actual hosting supports:
a cPanel account on Host Africa (host-h.net) infrastructure, running a LAMP
stack (Linux, Apache, MySQL/MariaDB, PHP). Every step below uses the real
cPanel tool names.

> **Confirmed host details for this site:**
> - cPanel login: `https://nhestate.co.za:2083` (or your host-h.net
>   reseller's cPanel URL — ask your host if the port is different)
> - Account home / interim path: `/usr/www/users/nhestvnbej/` — note this
>   is **not** the web root; your files must go inside
>   `/usr/www/users/nhestvnbej/public_html/`
> - Interim/temporary URL (works before DNS/SSL are fully live):
>   `http://nhestate.co.za.www6.jnb3.host-h.net`
> - MySQL database host: `sql63.jnb2.host-h.net` (**not** `localhost` —
>   see the note in Section 4)

## What you get

Same feature set as the Django version, plus paid recruiter access and
website embedding:
- Public job board with search — free for everyone, no login needed to browse
- Candidate signup/login via **email, Google, LinkedIn, or Facebook**
- Resume upload + application form
- Recruiter dashboard with a **drag-and-drop Kanban pipeline**
  (Applied → Screening → Interview → Offer → Hired/Rejected)
- **Share on LinkedIn/Facebook** buttons on every job
- **Google for Jobs** structured data on every job page
- **Paid recruiter access via Paystack** (R2500/month) — everyone can browse
  and apply for free; posting jobs and using the pipeline requires an active
  subscription, except one privileged account (see below)
- One configured email address (`PRIVILEGED_RECRUITER_EMAIL`) gets free
  recruiter access **and** exclusive company branding privileges (logo +
  brand colour + tagline upload)
- **Embed your job listings on another website** — a JS widget, an iframe,
  and a plain JSON API, all in `embed/` and `api/`
- **Recruiter analytics dashboard** — every active job shows four colour-coded
  stat circles (views, applications, days active, shares), and a separate
  table lists past/closed jobs with how many days each took to fill
- Mobile/tablet/desktop responsive layout (Bootstrap 5, collapsible nav)

See "What's actually integrated" in the original project notes — the one
honest limitation carries over: auto-*posting* directly into LinkedIn's own
job board needs LinkedIn's invite-only Talent Partner API, which isn't
self-serve for an individual developer, so it isn't implemented here.

---

## 1. Requirements

Any cPanel shared/reseller hosting plan with:
- PHP 8.0+ (check/set this under cPanel's **MultiPHP Manager** or
  **Select PHP Version** tool — ask your host/reseller which PHP 8.x
  versions are available on this account)
- MySQL/MariaDB database (included with cPanel hosting)
- The `curl` PHP extension enabled (on by default on most cPanel hosts)

No SSH or Composer access needed — everything here is plain `.php` files.
This is a PHP-only (LAMP stack) deployment — there's no Python/Django
component to worry about mixing in on this hosting account.

---

## 2. Create the MySQL database (cPanel)

1. Log in to cPanel: `https://nhestate.co.za:2083` (or your host-h.net
   reseller's cPanel URL).
2. Open **MySQL® Databases**.
3. Create a new database (e.g. `dittohire`) — cPanel will likely prefix it
   automatically with your account username, e.g. `cpuser_dittohire`. For
   this project the database is already created as **`rvzrecruit`**.
4. Create a database user + strong password, then add that user to the
   database with **ALL PRIVILEGES**. For this project the user is already
   created as **`rvzrecruit`**.
5. Open **phpMyAdmin** (its own icon in cPanel's home screen, or via
   MySQL® Databases) → select the `rvzrecruit` database → **Import** tab →
   choose `schema.sql` from this project → Go.
   This creates all the tables (and is safe to re-run later if this file
   is updated — see the "Migration" section near the bottom of `schema.sql`).

---

## 3. Upload the files

> **This repo now deploys automatically.** Pushing to `main` on GitHub
> triggers `.github/workflows/deploy.yml`, which uploads every changed
> file straight to `public_html` over FTP — no manual upload needed for
> day-to-day changes. It's additive-only (uses `lftp mirror --reverse`
> with no `--delete`), so it never touches `config/config.php` or
> `uploads/` (both are gitignored and therefore never part of the
> checkout it deploys). Required repo secrets: `FTP_SERVER`,
> `FTP_USERNAME`, `FTP_PASSWORD` (Settings → Secrets and variables →
> Actions). The manual steps below are still useful for the *first-ever*
> upload (config.php doesn't exist on a fresh server yet) or if you ever
> need to deploy without GitHub.

1. In cPanel, open **File Manager** (or use an FTP/SFTP client such as
   FileZilla — your FTP login details are in cPanel under **FTP Accounts**).
2. Navigate to your account's web root: **`public_html`** inside your
   account home directory. For this account that's:
   `/usr/www/users/nhestvnbej/public_html/`
   (the bare path `/usr/www/users/nhestvnbej/` shown in Apache errors is
   the account home — it is **not** the web root; files must sit inside
   the `public_html` folder underneath it, or Apache will return an
   AH01276 "no matching DirectoryIndex" error).
3. Upload the **entire contents** of this project folder into that
   `public_html` folder (or a subfolder like `public_html/dittohire` if
   you want it at `nhestate.co.za/dittohire`), so that `index.php` sits
   directly inside `public_html` (or inside the subfolder).
4. Make sure `uploads/resumes/` and `uploads/company_logos/` are writable
   (File Manager → select the folder → Permissions → `755` is usually
   fine; use `775` only if uploads fail with a permissions error).

---

## 4. Configure the app

1. In File Manager (or FTP), duplicate `config/config.sample.php` and rename
   the copy to `config/config.php`.
2. Edit `config/config.php` and fill in:
   - `DB_NAME` = `rvzrecruit`, `DB_USER` = `rvzrecruit`, `DB_PASS` = your
     rotated database password from Step 2.
   - `DB_HOST` — **do not leave this as `localhost`** on this account.
     This host-h.net cPanel reseller puts MySQL on a separate database
     server; the confirmed hostname for this project is
     `sql63.jnb2.host-h.net`.
   - `SITE_URL` — your real domain, e.g. `https://www.nhestate.co.za`
     (or `https://www.nhestate.co.za/dittohire` if you used a subfolder).
     While DNS/SSL are still propagating, you can smoke-test against the
     interim URL instead: `http://nhestate.co.za.www6.jnb3.host-h.net`
     — but switch `SITE_URL` back to the real domain before going live,
     since OAuth and Paystack redirect URLs need to match the real domain.
   - `PRIVILEGED_RECRUITER_EMAIL` — already set to `ryan@rvzgroup.co.za`;
     this account gets free recruiter access and branding privileges,
     everyone else pays.
3. Leave the OAuth keys blank for now — the site works fully without them
   (email/password signup, job posting, the pipeline, and the share buttons
   all work immediately). Add real keys later using Section 5.
4. Leave the Paystack keys blank while testing signup/browsing — anyone who
   isn't the privileged account will just see the subscribe page instead of
   the dashboard until you fill in Section 5b.

Visit your domain — you should see the empty job board.

### Quick end-to-end test (no OAuth or Paystack keys needed)
1. On the site, **Sign up** with email/password using `ryan@rvzgroup.co.za`
   (the privileged account — free recruiter access, no payment needed).
2. Click **"I'm hiring"** → enter a company name → this creates the company
   for you and upgrades you to a recruiter.
3. Click **Post a Job**, fill it in, save.
4. Open a private/incognito window, sign up as a second (candidate) account.
5. Open the job, click **Apply Now**, upload any PDF, submit.
6. Back in your recruiter window: **Dashboard → Pipeline** on that job —
   drag the candidate's card between columns. It saves instantly via AJAX.
7. On the job page, try **Share on LinkedIn / Facebook** — these work with
   zero configuration, no API keys required.
8. Back on **Dashboard**, you should now see the job's stat circles:
   1 view (from step 5's job page load — your own dashboard/edit visits as
   the recruiter don't count), 1 application, days active ticking up from
   0, and 1 share from step 7.
9. In **Edit**, uncheck "Job is open for applications" and save — the job
   moves from "Active listings" down to "Past & inactive listings" on the
   dashboard, showing days-to-fill.
10. To see the paywall, sign up a *third* account with a different email and
    click "I'm hiring" — this one lands on `subscribe.php` instead of the
    dashboard, since it isn't the privileged account and Paystack isn't
    configured yet.

---

## 5. Turning on real Google / LinkedIn / Facebook login

Same OAuth apps as the Django version — just point the redirect URLs at your
PHP callback files instead.

### Google
1. https://console.cloud.google.com/ → create a project → **OAuth consent screen** (External).
2. **Credentials → Create Credentials → OAuth client ID** → Web application.
3. Authorized redirect URI:
   `https://www.nhestate.co.za/oauth/google_callback.php`
   (adjust the path if you installed into a subfolder)
4. Copy the Client ID/Secret into `config/config.php`'s
   `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET`.

### LinkedIn
1. https://www.linkedin.com/developers/apps → Create app.
2. Under **Products**, add **"Sign In with LinkedIn using OpenID Connect"**
   (instant, self-serve).
3. Under **Auth**, add redirect URL:
   `https://www.nhestate.co.za/oauth/linkedin_callback.php`
4. Copy Client ID/Secret into `LINKEDIN_CLIENT_ID` / `LINKEDIN_CLIENT_SECRET`.

### Facebook
1. https://developers.facebook.com/apps → Create App → "Consumer".
2. Add the **Facebook Login** product.
3. Under Facebook Login → Settings, add redirect URI:
   `https://www.nhestate.co.za/oauth/facebook_callback.php`
4. Copy App ID/Secret into `FACEBOOK_CLIENT_ID` / `FACEBOOK_CLIENT_SECRET`.
5. While in "Development" mode, only accounts added as Testers (Roles tab)
   can log in — submit for App Review to open it to the public.

No restart needed — PHP reads `config.php` fresh on every request. Just
reload the login page after saving your changes.

---

## 5b. Turning on Paystack billing (team-seat subscriptions)

Everyone can browse jobs and apply for free. Only the recruiter side (posting
jobs, the pipeline) is paid — **R2500/month per seat** — except the one
account set in `PRIVILEGED_RECRUITER_EMAIL` (`ryan@rvzgroup.co.za` by
default) and anyone that account invites to its team, who always have free
recruiter access and exclusive branding privileges.

Billing is combined per company: on `subscribe.php`, the account holder picks
how many seats they need (themselves + any team members they plan to invite
via **Settings → Teams**) and pays one recurring monthly amount
(seats × R2500) instead of every team member paying individually. Invites are
free up to the purchased seat count; going over it prompts the account holder
to buy more seats. **No manual Plan setup is needed in the Paystack
dashboard** — the app creates (and reuses) a Paystack Plan for each distinct
seat count automatically via the API the first time it's needed.

1. Sign up at https://dashboard.paystack.com if you haven't already, and
   switch to a **South Africa (ZAR)** business if prompted.
2. **Settings → API Keys & Webhooks** → copy the **Secret Key** and
   **Public Key** into `config/config.php`'s `PAYSTACK_SECRET_KEY` /
   `PAYSTACK_PUBLIC_KEY`. Use the `sk_test_...` / `pk_test_...` pair first —
   Paystack's test mode uses fake cards, so nothing is actually charged.
3. Still in **Settings → API Keys & Webhooks**, set the **Webhook URL** to:
   `https://www.nhestate.co.za/paystack/webhook.php`
   This is what keeps subscriptions renewing automatically and catches
   failed/cancelled payments — without it, a recruiter's access will still
   unlock right after they pay (via `paystack/callback.php`), but it won't
   auto-renew next month.
4. Test it: sign up a non-privileged account, click "I'm hiring", set up a
   company, then on `subscribe.php` choose a seat count and click
   **Subscribe with Paystack**. Use one of
   [Paystack's test cards](https://paystack.com/docs/payments/test-payments)
   to complete checkout — you'll land back on the dashboard with recruiter
   access unlocked, and the Plan for that seat count will now show up under
   **Payments → Plans** in the Paystack dashboard (created automatically).
5. `PAYSTACK_PLAN_CODE` in `config/config.php` can be left blank — it's only
   used by the older single-user checkout path, which the seat-based flow
   above has replaced for all new signups.
6. When ready to take real payments, swap in the `sk_live_...` /
   `pk_live_...` keys and create a matching live-mode Plan (test-mode plan
   codes don't work in live mode).

---

## 6. Go live checklist

- [ ] `config/config.php` has real DB credentials and your real `SITE_URL`
- [ ] `DEBUG_MODE` is set to `false` in `config/config.php`
- [ ] HTTPS is working on your domain — cPanel's **AutoSSL** normally issues
      a free Let's Encrypt certificate automatically once your domain's `@`
      and `www` A-records point at this host-h.net server and DNS has
      propagated (this is also why the interim domain-based URL,
      `nhestate.co.za.www6.jnb3.host-h.net`, exists — it lets AutoSSL and
      testing work before DNS fully points here). Visit `https://` your
      domain and confirm you see a padlock, not an ⓘ icon — if it's not
      issued yet, check cPanel's **SSL/TLS Status** page, or ask your
      host-h.net reseller to run AutoSSL manually. OAuth providers and
      Paystack both generally require HTTPS redirect/webhook URLs in production
- [ ] `uploads/.htaccess` and `config/.htaccess` were uploaded (they block
      direct access to uploaded files being run as scripts, and to your
      config file)
- [ ] Live-mode Paystack keys + plan code are in `config/config.php`, and the
      webhook URL in the Paystack dashboard points at your live domain
- [ ] You've tested the full signup → post job → apply → pipeline flow
      on the live domain, not just locally
- [ ] You've tested subscribing as a non-privileged account with a real
      (small) live payment, and confirmed the dashboard unlocks
- [ ] You've confirmed `ryan@rvzgroup.co.za` still gets free access after
      switching to live keys (it doesn't depend on Paystack at all)
- [ ] You've posted a test job, viewed it from a logged-out browser, applied,
      shared it, then closed it — and confirmed the dashboard's stat circles
      and "Past & inactive listings" table reflect all of that correctly

---

## 7. File map

```
ditto-hire-php/
├── schema.sql                  # Import this into phpMyAdmin first
├── config/
│   ├── config.sample.php       # Copy to config.php and fill in
│   ├── config.php              # (you create this — never share it)
│   ├── db.php                  # PDO connection
│   └── .htaccess               # Blocks direct access
├── includes/
│   ├── bootstrap.php           # Every page starts by requiring this
│   ├── functions.php           # Auth, CSRF, flash messages, subscription checks
│   ├── paystack.php            # Paystack API client (init/verify/activate)
│   ├── http.php                # Tiny cURL wrapper for OAuth calls
│   ├── oauth_helper.php        # Find-or-create user from social login
│   ├── job_form_fields.php     # Shared job form markup
│   ├── header.php / footer.php # Shared layout
│   └── .htaccess
├── oauth/
│   ├── google.php / google_callback.php
│   ├── linkedin.php / linkedin_callback.php
│   └── facebook.php / facebook_callback.php
├── paystack/
│   ├── initialize.php          # Starts a subscription checkout
│   ├── callback.php            # Verifies payment when the user returns
│   └── webhook.php             # Handles recurring renewals/cancellations
├── api/
│   └── jobs.php                # Public JSON job feed (CORS-enabled, for embedding)
├── embed/
│   ├── widget.js               # Drop-in JS widget for external websites
│   └── careers.php             # Iframe-embeddable careers page
├── ajax/
│   └── move_stage.php          # Pipeline drag-and-drop endpoint
├── assets/css/style.css
├── uploads/{resumes,company_logos}/  # + .htaccess (no PHP execution)
├── index.php                   # Job board
├── job.php                     # Job detail + Google Jobs schema + tracks views
├── share.php                   # Tracks a share, then redirects to LinkedIn/Facebook
├── apply.php
├── my_applications.php
├── profile.php
├── become_recruiter.php
├── subscribe.php               # Recruiter subscription paywall page
├── company_branding.php        # Logo/branding upload — privileged account only
├── login.php / signup.php / logout.php
├── dashboard.php                # Active-job stat circles + past-listings table
├── job_create.php / job_edit.php / pipeline.php
```

### How the dashboard analytics work

No separate analytics tables or background jobs — just a few counter
columns on `jobs`, updated inline as things happen:
- **Views** (`jobs.views_count`) — incremented once per page load of
  `job.php`, but not when the posting recruiter views their own listing
  (so checking your own job doesn't inflate its own stats).
- **Applications** — not stored separately; counted live from the
  `applications` table each time the dashboard loads.
- **Days Active** — computed from `jobs.created_at` to now, for jobs still
  open.
- **Shares** (`jobs.shares_count`) — incremented by `share.php`, which sits
  between the "Share on LinkedIn/Facebook" buttons and the actual share
  dialog: it logs the click, then redirects. This is the only reliable way
  to count a share, since the browser leaves the page immediately after a
  normal outbound link.
- **Days to fill** (past listings) — `jobs.closed_at` is stamped
  automatically the moment a recruiter unchecks "Job is open for
  applications" in `job_edit.php`, and cleared if they reopen it. The past
  listings table shows `closed_at − created_at` in days.

## 7b. 2026-08 platform overhaul — what's new and how to finish setup

This project was significantly expanded: in-depth candidate profiles, a
PNet-style candidate home page, saved jobs, application-confirmation and
stage-change emails, a newsletter, footer legal pages + a POPIA cookie
banner, recruiter Direct Search / talent pool / saved-search alerts, an
AI-generated social ad tool, a click-to-sign SLA (PDF, emailed and stored),
a recruiter profile page, an industries dropdown, SEO metadata/sitemap, and
a PWA (installable app, no store submission yet). To bring a live site up to
date:

1. **Run the migration**: phpMyAdmin -> `rvzrecruit` -> Import ->
   `database/rvzrecruit_migration_2026-08.sql` (from the project root, not
   `ditto-hire-php/`). Safe to re-run; adds new tables/columns only, never
   touches existing rows. `schema.sql` already includes the same statements
   for brand-new installs.
2. **Copy `config/config.sample.php` -> `config/config.php`** if not already
   done, and fill in the new constants it now defines: `COMPANY_LEGAL_NAME`,
   `COMPANY_REG_NUMBER`, the `SMTP_*` block, and `OPENAI_API_KEY`.
3. **Create an SMTP mailbox** in cPanel -> Email Accounts (e.g.
   `noreply@yourdomain.co.za`), then fill in `SMTP_HOST`/`SMTP_USER`/
   `SMTP_PASS` in `config.php`. Until these are set, outgoing mail
   (application confirmations, stage-change alerts, the newsletter, SLA
   PDFs) is logged via `error_log()` instead of sent — nothing breaks, it
   just won't actually deliver yet.
   For this project, `SMTP_HOST` is already set to this account's real
   server hostname (`www6.jnb3.host-h.net`) rather than `mail.rvzgroup.co.za`
   — same reasoning as `DB_HOST` above: safer than a domain-based hostname
   until DNS/AutoSSL are fully propagated on rvzgroup.co.za. If mail
   delivery fails once live, double-check the exact host/port/security
   combo under cPanel -> Email Accounts -> Connect Devices for this mailbox
   (587/STARTTLS is what's configured; 465/SSL is the usual fallback).
4. **Add an OpenAI API key** (platform.openai.com -> API keys) to
   `OPENAI_API_KEY` to enable the recruiter "Ads" button. Leaving it blank
   only disables that one feature.
5. **Schedule the job-alert cron** in cPanel -> Cron Jobs, once daily, e.g.:
   `php /usr/www/users/nhestvnbej/public_html/cron/send_job_alerts.php`
   (adjust the path). This is what actually sends the "new jobs matching
   your profile" and "new CVs matching your saved search" digest emails —
   there is no persistent background worker on shared hosting, so nothing
   sends without this cron entry.
6. **Create `uploads/ads/`, `uploads/slas/`, `uploads/profile_photos/`**
   (writable, same as the existing `uploads/resumes/` and
   `uploads/company_logos/`) if your hosting doesn't auto-create them on
   first upload.
7. **Supply brand assets**: `assets/img/logo.png`, `favicon.png`,
   `icon-192.png`, `icon-512.png`, and `social-share.png` are referenced by
   the new header/footer/PWA manifest but are not included — drop in RVZ's
   real logo/icon files at those paths once available.
8. **Legal pages are generic South African templates** (POPIA/PAIA/ECT
   Act-aware) — have them reviewed by an attorney before relying on them in
   a dispute, per the standard disclaimer on this kind of generated content.

## 8. Sensible next steps

- Add email notifications (PHP's `mail()` works out of the box on most
  cPanel hosts, or use your host-h.net reseller's SMTP details for a more
  reliable send) when an application changes stage, or when a subscription
  payment fails.
- Add resume parsing to auto-fill skills from an uploaded PDF.
- Move `config.php`'s secrets to a location outside `public_html` if this
  cPanel account's file structure allows it, for extra defense in depth.
- Add a small "Manage billing / cancel subscription" page for recruiters
  (currently, cancelling is done from the Paystack dashboard/customer portal;
  `paystack/webhook.php` picks up the cancellation automatically either way).
- Consider giving `PRIVILEGED_RECRUITER_EMAIL` support for more than one
  address (e.g. an array) if RVZ ever wants a second in-house recruiter with
  free + branding access.

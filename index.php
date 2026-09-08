<?php
/**
 * Public marketing homepage — about RVZ, mission/vision, and a contact form.
 * Job browsing lives at jobs.php; recruiters land on dashboard.php instead
 * of this page after logging in (see post_login_redirect_path()).
 */
require __DIR__ . '/includes/bootstrap.php';

$currentUser = current_user();
if ($currentUser && $currentUser['role'] === 'recruiter') {
    redirect('/dashboard.php');
}

$stmt = db()->query(
    "SELECT DISTINCT companies.id, companies.name, companies.logo_path FROM companies
     JOIN jobs ON jobs.company_id = companies.id
     WHERE jobs.is_open = 1 AND companies.logo_path <> '' LIMIT 8"
);
$hiringCompanies = $stmt->fetchAll();

$openJobsCount = (int) db()->query('SELECT COUNT(*) AS c FROM jobs WHERE is_open = 1')->fetch()['c'];
$industriesCount = (int) db()->query('SELECT COUNT(*) AS c FROM industries')->fetch()['c'];

$pageTitle = SITE_NAME . ' — Personnel & Labour Hiring Specialists';
$pageDescription = 'RVZ Personnel Services & Labour Hiring Specialists connects South African job seekers with employers — free for candidates, powerful tools for recruiters.';
require __DIR__ . '/includes/header.php';
?>

<div class="rvz-marketing-hero text-center">
    <div class="container py-5">
        <h1 class="display-5 fw-bold mb-3">Connecting South African Talent<br>With the Right Opportunities</h1>
        <p class="lead mb-4" style="max-width:60ch;margin:0 auto;">
            RVZ Personnel Services &amp; Labour Hiring Specialists is part of RVZ International Group —
            built to make hiring and job hunting simpler, faster, and fairer for everyone involved.
        </p>
        <form method="get" action="<?= h(base_url('jobs.php')) ?>" class="rvz-search-card rvz-hero-search mx-auto text-start mb-4">
            <div class="row g-2 align-items-center">
                <div class="col-md-5">
                    <input type="text" name="q" class="form-control form-control-lg" placeholder="Job title or keyword">
                </div>
                <div class="col-md-4">
                    <input type="text" name="location" class="form-control form-control-lg" placeholder="Location">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-lg w-100">Search Jobs</button>
                </div>
            </div>
            <div class="form-check mt-2 mb-0">
                <input type="checkbox" name="remote_only" value="1" class="form-check-input" id="heroRemoteOnly">
                <label class="form-check-label small text-muted" for="heroRemoteOnly">Remote only</label>
            </div>
        </form>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a href="<?= h(base_url('jobs.php')) ?>" class="btn btn-light btn-lg">Browse Jobs</a>
            <a href="<?= h(base_url('signup.php')) ?>" class="btn btn-outline-light btn-lg">I'm Hiring &rarr;</a>
        </div>
    </div>
</div>

<div class="container my-5">
    <div class="row g-4 mb-5">
        <div class="col-md-6">
            <div class="card h-100 shadow-sm rvz-mv-card">
                <div class="card-body">
                    <h3 class="rvz-mv-icon">🎯</h3>
                    <h4>Our Mission</h4>
                    <p class="text-muted mb-0">To connect capable, motivated people with the employers who need them —
                    quickly, transparently, and without unnecessary barriers — while giving South African businesses
                    the recruitment tools they need to hire with confidence.</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100 shadow-sm rvz-mv-card">
                <div class="card-body">
                    <h3 class="rvz-mv-icon">🔭</h3>
                    <h4>Our Vision</h4>
                    <p class="text-muted mb-0">To be one of South Africa's most trusted personnel and labour hiring
                    platforms — known for pairing genuine human recruitment expertise with modern technology, under
                    the same standard of excellence as RVZ International Group's other divisions.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row align-items-center g-5 mb-5">
        <div class="col-lg-6">
            <h2 class="mb-3">About RVZ</h2>
            <p>RVZ Personnel Services &amp; Labour Hiring Specialists operates under <strong>RVZ International Group
            (Pty) Ltd</strong>, a South African business group built on innovation, vision, and purpose. Where our
            sister divisions cover events, marketing &amp; design, travel, and more, this platform is dedicated
            entirely to one thing: getting the right person into the right role.</p>
            <p class="mb-0">Whether you're searching for your next career move or you're a business that needs to
            hire reliably and efficiently, RVZ Personnel Services brings a specialist recruiter's judgment together
            with a modern applicant tracking platform — not just a job board.</p>
        </div>
        <div class="col-lg-6">
            <div class="row g-3">
                <div class="col-6">
                    <div class="rvz-stat-box">
                        <div class="num"><?= $openJobsCount ?></div>
                        <div class="label">Open Positions Right Now</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="rvz-stat-box">
                        <div class="num"><?= $industriesCount ?></div>
                        <div class="label">Industries Covered</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="rvz-stat-box">
                        <div class="num">Free</div>
                        <div class="label">Always, for Candidates</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="rvz-stat-box">
                        <div class="num">AI</div>
                        <div class="label">Powered Recruiter Tools</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <h2 class="text-center mb-4">Why Choose RVZ</h2>
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="text-center">
                <div class="rvz-feature-icon">🧑‍💼</div>
                <h5>For Candidates</h5>
                <p class="text-muted small">Build a rich profile once, apply in a click, track every application,
                and get alerted the moment a matching role goes live — completely free.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="text-center">
                <div class="rvz-feature-icon">🏢</div>
                <h5>For Employers</h5>
                <p class="text-muted small">Post jobs, manage a real applicant pipeline, search our candidate
                database directly, and generate AI-assisted social ads — all from one dashboard.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="text-center">
                <div class="rvz-feature-icon">🤝</div>
                <h5>Real Recruitment Expertise</h5>
                <p class="text-muted small">Backed by RVZ's own Response Handling and Direct Search services when
                you need more than a self-service job board.</p>
            </div>
        </div>
    </div>

    <?php if ($hiringCompanies): ?>
        <h5 class="text-center text-muted mb-4">Companies hiring on RVZ right now</h5>
        <div class="d-flex flex-wrap justify-content-center align-items-center gap-4 mb-5">
            <?php foreach ($hiringCompanies as $c): ?>
                <a href="<?= h(base_url('company_profile.php?id=' . $c['id'])) ?>" title="<?= h($c['name']) ?>">
                    <img src="<?= h(UPLOAD_URL . $c['logo_path']) ?>" alt="<?= h($c['name']) ?>" style="max-height:48px;max-width:120px;object-fit:contain;filter:grayscale(1);opacity:.75;">
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="rvz-cta-banner text-center mb-5">
        <h3 class="mb-2">Ready to get started?</h3>
        <p class="mb-4">Whether you're looking for work or looking to hire, RVZ makes the next step easy.</p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a href="<?= h(base_url('jobs.php')) ?>" class="btn btn-primary btn-lg">Find a Job</a>
            <a href="<?= h(base_url('signup.php')) ?>" class="btn btn-outline-primary btn-lg">Post a Job</a>
        </div>
    </div>

    <div class="row justify-content-center" id="contact">
        <div class="col-lg-7">
            <h2 class="text-center mb-4">Contact Us</h2>
            <div class="card shadow-sm"><div class="card-body p-4">
                <form method="post" action="<?= h(base_url('home_contact.php')) ?>">
                    <?= csrf_field() ?>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input type="tel" name="phone" class="form-control"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Message</label><textarea name="message" rows="4" class="form-control" required></textarea></div>
                    <button type="submit" class="btn btn-primary w-100">Send Message</button>
                </form>
            </div></div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

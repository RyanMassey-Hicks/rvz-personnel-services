<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Our Values — ' . SITE_NAME;
$pageDescription = 'The vision and values behind RVZ Personnel Services & Labour Hiring Specialists, part of RVZ International Group.';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-9">

<h1 class="mb-1">Our Values</h1>
<p class="text-muted mb-4">What drives RVZ Personnel Services &amp; Labour Hiring Specialists</p>

<h4 class="mt-4">Our Vision</h4>
<p>We believe the right hire changes more than a business's bottom line — it changes a person's life. Our vision is
a South African labour market where every candidate is matched to work that fits their skills and ambitions, and
every employer can hire with confidence, speed, and fairness. As part of RVZ International Group — <em>Empowering
Excellence Across Diverse Industries</em> — we bring that same standard of excellence to recruitment and labour
hiring specifically.</p>

<h4 class="mt-4">Our Values</h4>
<div class="row g-3 mt-1 mb-4">
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h6 class="mb-1">We hire with integrity</h6>
            <p class="text-muted small mb-0">Every job posting, every candidate profile, every placement — handled honestly, transparently, and in line with South African labour law.</p>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h6 class="mb-1">We move with urgency</h6>
            <p class="text-muted small mb-0">A vacancy costs a business money every day it stays open, and every day a candidate stays unemployed matters to them. We don't sit on either side of that.</p>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h6 class="mb-1">We back excellence across industries</h6>
            <p class="text-muted small mb-0">As part of a group spanning Events, Business, Recruitment, Travel, Marketing &amp; Design, Cosmetics and Fashion, we understand that great talent looks different in every sector — and we recruit accordingly.</p>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h6 class="mb-1">We protect people's information</h6>
            <p class="text-muted small mb-0">CVs, ID documents, and personal details are handled under POPIA, not treated as a commodity. See our <a href="<?= h(base_url('privacy-policy.php')) ?>">Privacy Policy</a>.</p>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h6 class="mb-1">We stay accessible</h6>
            <p class="text-muted small mb-0">A candidate should never need to pay to be found. Browsing and applying for jobs on RVZ is, and always will be, free for job seekers.</p>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h6 class="mb-1">We build long-term relationships</h6>
            <p class="text-muted small mb-0">We'd rather place the right candidate once than the wrong candidate three times. Our Response Handling and Direct Search services exist because repeat clients trust us to get it right.</p>
        </div></div>
    </div>
</div>

<div class="card bg-light border-0 mb-4"><div class="card-body">
    <h6 class="mb-1">Part of RVZ International Group (Pty) Ltd</h6>
    <p class="text-muted small mb-0"><?= h(COMPANY_LEGAL_NAME) ?> (Reg. <?= h(COMPANY_REG_NUMBER) ?>). Learn more about the wider group at
    <a href="https://www.rvzgroup.co.za" target="_blank" rel="noopener">rvzgroup.co.za</a>.</p>
</div></div>

<a href="<?= h(base_url('become_recruiter.php')) ?>" class="btn btn-primary">Start Hiring With RVZ</a>

</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Response Handling — ' . SITE_NAME;
$pageDescription = 'Let RVZ\'s recruitment team advertise, screen and shortlist candidates for your vacancy — response handling for busy hiring teams.';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-9">

<h1 class="mb-1">Response Handling</h1>
<p class="text-muted mb-4">Let RVZ match you to the right candidates for your jobs</p>

<p class="lead">Posting a vacancy is only the first step — reading through every application, screening out
mismatches, and building a shortlist takes real time. Our Response Handling service takes that off your plate:
our own recruitment team manages the advertising and screening for your job, and hands you a shortlist of
candidates who actually meet your criteria.</p>

<div class="row g-4 my-4">
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <h6>1. We advertise</h6>
            <p class="text-muted small mb-0">Your vacancy is posted and actively promoted to candidates matching the role.</p>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <h6>2. We screen</h6>
            <p class="text-muted small mb-0">Our team reviews every application against your requirements before it ever reaches you.</p>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <h6>3. We shortlist</h6>
            <p class="text-muted small mb-0">Candidates who meet your criteria are stored in your pipeline, ready for you to review and interview.</p>
        </div></div>
    </div>
</div>

<h5 class="mt-4">How to use it</h5>
<p>Response Handling is built directly into job posting — when you create or edit a job listing, tick
<strong>"Use RVZ Response Handling for this job"</strong> and our team takes it from there. Shortlisted candidates
land straight in your <a href="<?= h(base_url('pipeline.php')) ?>">applicant pipeline</a> alongside everyone else,
so nothing changes about how you review and manage applicants day to day.</p>

<div class="alert alert-light border">
    Response Handling is one of RVZ's dedicated-support services, arranged directly with our team rather than
    sold as a self-serve add-on — see <a href="<?= h(base_url('pricing.php')) ?>#looking-for-more">Pricing</a> for what else
    falls under this, or <a href="<?= h(base_url('contact-us.php')) ?>">contact us</a> to enable it for your account.
</div>

<a href="<?= h(base_url('become_recruiter.php')) ?>" class="btn btn-primary me-2">Start Hiring</a>
<a href="<?= h(base_url('contact-us.php')) ?>" class="btn btn-outline-secondary">Talk to Us</a>

</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Applications — ' . SITE_NAME;
$pageDescription = 'How applying for a job on RVZ Personnel Services works, from search to hearing back.';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-9">
<h1 class="mb-1">Applications</h1>
<p class="text-muted mb-4">How applying for a job on RVZ works</p>

<div class="row g-4 my-3">
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h6>1. Find a role</h6>
            <p class="text-muted small mb-0">Search and filter open positions on <a href="<?= h(base_url('jobs.php')) ?>">Browse Jobs</a> by keyword, location, employment type, industry, or salary — no account needed to look.</p>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h6>2. Create your profile once</h6>
            <p class="text-muted small mb-0">Sign up free, build your profile — work experience, education, skills, and your CV — and reuse it for every application instead of retyping it each time.</p>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h6>3. Apply in a click</h6>
            <p class="text-muted small mb-0">Click Apply on any job, add an optional cover note, and submit — your profile and CV go straight to the recruiter, fully logged in so nothing gets lost.</p>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h6>4. Track your status</h6>
            <p class="text-muted small mb-0">Every application moves through Applied → Screening → Interview → Offer → Hired/Rejected — check <a href="<?= h(base_url('my_applications.php')) ?>">My Applications</a> any time to see exactly where each one stands.</p>
        </div></div>
    </div>
</div>

<h5 class="mt-4">What happens after you apply</h5>
<p>The recruiter reviews your application directly, or — for jobs using RVZ's
<a href="<?= h(base_url('response-handling.php')) ?>">Response Handling</a> service — our own team screens it
first. You'll see your status update in <a href="<?= h(base_url('my_applications.php')) ?>">My Applications</a> as
it moves through the pipeline, and get a notification when it changes.</p>

<h5 class="mt-4">Saving jobs for later</h5>
<p>Not ready to apply yet? Click the bookmark icon on any job card to add it to
<a href="<?= h(base_url('saved_jobs.php')) ?>">Saved Jobs</a> so you can come back to it.</p>

<h5 class="mt-4">Job alerts</h5>
<p>Subscribe to job alerts from the footer or your profile settings to get emailed when a new role matching your
interests goes live, instead of checking back manually.</p>

<h5 class="mt-4">Cost</h5>
<p>Searching, applying, and tracking applications is completely free for candidates — always. See our
<a href="<?= h(base_url('values.php')) ?>">Values</a> page for why.</p>

<a href="<?= h(base_url('jobs.php')) ?>" class="btn btn-primary">Browse Jobs</a>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Career Interface (ATS) — ' . SITE_NAME;
$pageDescription = 'RVZ\'s Career Interface: a branded careers page for your own website plus a full applicant tracking dashboard, at no extra cost.';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-9">

<h1 class="mb-1">Career Interface</h1>
<p class="text-muted mb-4">A branded careers page and applicant tracking system, built into your RVZ account</p>

<p class="lead">Your Career Interface is a cloud-based recruitment workspace you can reach from any computer,
phone or tablet — no separate ATS subscription required. It combines a custom-branded careers page for your own
website with a live dashboard that tracks every applicant from first response through to hire.</p>

<div class="row g-4 my-4">
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h6>Your own branded careers page</h6>
            <p class="text-muted small mb-2">Showcase your logo, brand colour and company tagline on a careers page that lists only your open roles — ready to embed directly on your own website as an iframe or JS widget.</p>
            <a href="<?= h(base_url('embed_jobs.php')) ?>" class="small">See embed options &rarr;</a>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h6>Drag-and-drop applicant pipeline</h6>
            <p class="text-muted small mb-2">Track every candidate through Applied → Screening → Interview → Offer → Hired, with a Kanban board built for how hiring teams actually work.</p>
            <a href="<?= h(base_url('pipeline.php')) ?>" class="small">Open your pipeline &rarr;</a>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h6>Live hiring dashboard</h6>
            <p class="text-muted small mb-2">Views, applications, days active and shares for every open role, plus a record of how long past roles took to fill — so you can see what's working.</p>
            <a href="<?= h(base_url('dashboard.php')) ?>" class="small">View dashboard &rarr;</a>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h6>Direct candidate search</h6>
            <p class="text-muted small mb-2">Search RVZ's candidate pool directly by skills, location and experience instead of waiting on applications alone.</p>
            <a href="<?= h(base_url('recruiter_search.php')) ?>" class="small">Search candidates &rarr;</a>
        </div></div>
    </div>
</div>

<h5 class="mt-4">Set up your branding</h5>
<p>Upload your company logo, choose a brand colour, and add your tagline from
<a href="<?= h(base_url('company_branding.php')) ?>">Company Branding</a> — it's reflected instantly across your
careers page, job listings and shared job cards.</p>

<a href="<?= h(base_url('become_recruiter.php')) ?>" class="btn btn-primary">Get Your Career Interface</a>

</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>

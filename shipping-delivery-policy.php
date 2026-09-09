<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Shipping & Delivery Policy — ' . SITE_NAME;
$pageDescription = 'RVZ Personnel Services is a digital platform — how job listings, packages and documents are delivered, since nothing physical ships.';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-9">
<h1 class="mb-1">Shipping &amp; Delivery Policy</h1>
<p class="text-muted mb-4">Last updated: <?= date('F Y') ?></p>

<p><?= h(COMPANY_LEGAL_NAME) ?> ("RVZ") sells digital access and services through this Platform — there is no
physical product to ship. This policy explains how what you buy is actually delivered.</p>

<h5 class="mt-4">1. Job-listing packages</h5>
<p>When a Basic, Standard, or Premium package purchase is confirmed by Paystack, your listing credits and account
access are activated immediately and automatically — you can post a job as soon as you return to the site, with
no waiting period. See <a href="<?= h(base_url('pricing.php')) ?>">Pricing</a> for what each package includes.</p>

<h5 class="mt-4">2. Add-ons</h5>
<p>A listing extension or company presentation add-on takes effect immediately once payment is confirmed — an
extended listing's new expiry date updates straight away, and a purchased company presentation becomes visible on
your public profile without any further action needed from you.</p>

<h5 class="mt-4">3. Documents</h5>
<p>Signed Service Level Agreements are generated as a PDF the moment you sign, and emailed to the address on your
account immediately — a copy is also always available from your Recruiter Profile. The monthly
<a href="<?= h(base_url('job-market-trends-report.php')) ?>">Job Market Trends Report</a> PDF is generated
on demand whenever you click download.</p>

<h5 class="mt-4">4. If something doesn't arrive</h5>
<p>If a payment succeeded but your credits, access, or a document didn't appear, this is treated as a fault
rather than a delivery delay — <a href="<?= h(base_url('contact-us.php')) ?>">contact us</a> with your payment
reference and we'll resolve it directly.</p>

<h5 class="mt-4">5. No physical goods</h5>
<p>RVZ does not ship, courier, or post any physical item as part of using this Platform. If you ever receive a
communication claiming otherwise on RVZ's behalf, please treat it as suspicious and
<a href="<?= h(base_url('contact-us.php')) ?>">report it to us</a>.</p>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>

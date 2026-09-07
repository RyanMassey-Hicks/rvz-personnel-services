<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'PAIA Manual — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-9">
<h1 class="mb-1">PAIA Manual</h1>
<p class="text-muted mb-4">Manual in terms of Section 51 of the Promotion of Access to Information Act 2 of 2000 — Last updated: <?= date('F Y') ?></p>

<p>This manual is published by <?= h(COMPANY_LEGAL_NAME) ?> (Reg. <?= h(COMPANY_REG_NUMBER) ?>) ("RVZ") in
terms of Section 51 of the Promotion of Access to Information Act 2 of 2000 ("PAIA"), to facilitate requests
for access to records held by RVZ.</p>

<h5 class="mt-4">1. Contact details</h5>
<p>Information Officer: <?= h(PRIVILEGED_RECRUITER_EMAIL) ?><br>
Postal/registered address: available on request via <a href="<?= h(base_url('contact-us.php')) ?>">Contact Us</a>.</p>

<h5 class="mt-4">2. Guide on how to use PAIA</h5>
<p>The South African Human Rights Commission publishes a guide on how to use PAIA, available from the
Commission's website, to assist requesters in exercising their rights under the Act.</p>

<h5 class="mt-4">3. Records held by RVZ</h5>
<ul>
    <li>Candidate and recruiter account records (names, contact details, profiles, applications)</li>
    <li>Job postings and recruitment pipeline records</li>
    <li>Financial records related to recruiter subscription billing (processed via Paystack)</li>
    <li>Correspondence records (support/contact requests)</li>
    <li>Records required under other legislation (e.g. tax, labour, and company law records)</li>
</ul>

<h5 class="mt-4">4. Records automatically available</h5>
<p>This website, including our <a href="<?= h(base_url('privacy-policy.php')) ?>">Privacy Policy</a>,
<a href="<?= h(base_url('terms-and-conditions.php')) ?>">Terms and Conditions</a>, and other published policies,
is available to the public without a formal PAIA request.</p>

<h5 class="mt-4">5. How to request access to a record</h5>
<p>Requests for access to records must be made in writing (Form 2, as prescribed under PAIA regulations) and
directed to the Information Officer above. RVZ will respond within the statutory timeframes prescribed by PAIA.
A prescribed fee may apply for processing certain requests.</p>

<h5 class="mt-4">6. Grounds for refusal</h5>
<p>Access to a record may be refused on grounds set out in PAIA, including where disclosure would unreasonably
disclose the personal information of a third party (see our <a href="<?= h(base_url('privacy-policy.php')) ?>">Privacy
Policy</a> for how we handle personal information under POPIA), or where the record is legally privileged.</p>

<h5 class="mt-4">7. Internal appeals / regulator</h5>
<p>A requester who is dissatisfied with a decision may lodge a complaint with the Information Regulator of
South Africa (inforeg.co.za) or pursue remedies available under PAIA.</p>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>

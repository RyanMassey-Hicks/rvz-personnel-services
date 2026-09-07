<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Copyright Notice — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-9">
<h1 class="mb-1">Copyright Notice</h1>
<p class="text-muted mb-4">Last updated: <?= date('F Y') ?></p>

<p>&copy; <?= date('Y') ?> RVZ International Group (Pty) Ltd t/a RVZ Personnel Services &amp; Labour Hiring
Specialists. All rights reserved.</p>

<p>All content on this Platform — including but not limited to text, graphics, logos, icons, the RVZ brand
mark, page layouts, and underlying software — is the property of <?= h(COMPANY_LEGAL_NAME) ?> (Reg.
<?= h(COMPANY_REG_NUMBER) ?>) or its licensors, and is protected by South African and international copyright
and trademark law, except where otherwise noted.</p>

<h5 class="mt-4">Use of content</h5>
<p>You may view and print pages from this Platform for your own personal, non-commercial use in connection
with your job search or recruitment activity. You may not reproduce, distribute, modify, or create derivative
works from any part of this Platform without our prior written consent, except as expressly permitted (for
example, the embeddable job-widget provided for recruiters' own websites).</p>

<h5 class="mt-4">User-submitted content</h5>
<p>Resumes, job postings, company logos, and other content you upload remain your property. By uploading
content, you grant RVZ a non-exclusive licence to display and use that content for the purpose of operating
the Platform (e.g. showing your job posting to candidates, or your resume to recruiters you apply to).</p>

<h5 class="mt-4">Third-party trademarks</h5>
<p>Third-party names and logos referenced on this Platform (including Paystack, Google, LinkedIn, and Facebook)
are the trademarks of their respective owners and are used for identification purposes only.</p>

<h5 class="mt-4">Reporting infringement</h5>
<p>If you believe content on this Platform infringes your copyright, please contact us via
<a href="<?= h(base_url('contact-us.php')) ?>">Contact Us</a> with details of the material and your claim.</p>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>

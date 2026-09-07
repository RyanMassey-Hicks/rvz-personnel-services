<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Acceptable Use Policy — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-9">
<h1 class="mb-1">Acceptable Use Policy</h1>
<p class="text-muted mb-4">Last updated: <?= date('F Y') ?></p>

<p>This Acceptable Use Policy applies to all users of the RVZ Personnel Services &amp; Labour Hiring
Specialists platform, operated by <?= h(COMPANY_LEGAL_NAME) ?> ("RVZ").</p>

<h5 class="mt-4">You agree not to:</h5>
<ul>
    <li>Post job listings that are discriminatory, unlawful, misleading, or that violate the Employment Equity
        Act or other South African labour legislation;</li>
    <li>Post job listings for pyramid schemes, unpaid "opportunities" disguised as employment, or roles that
        require upfront payment from candidates;</li>
    <li>Upload false, fraudulent, or plagiarised information in a profile, resume, or job posting;</li>
    <li>Use candidate data obtained through Direct Search or the applicant pipeline for any purpose other than
        legitimate recruitment for roles posted on the Platform;</li>
    <li>Attempt to scrape, harvest, or bulk-download candidate or job data outside the provided embed/API tools;</li>
    <li>Send unsolicited bulk communications ("spam") to candidates or recruiters via the Platform;</li>
    <li>Attempt to bypass security controls, probe for vulnerabilities, or interfere with the Platform's normal
        operation;</li>
    <li>Impersonate another person or entity, or misrepresent your affiliation with a company;</li>
    <li>Upload malicious files (viruses, malware) via resume, logo, or ad-upload features;</li>
    <li>Use the AI ad-generation feature to create misleading, offensive, or unlawful advertising content.</li>
</ul>

<h5 class="mt-4">Enforcement</h5>
<p>Violations of this policy may result in content removal, account suspension, or termination, at RVZ's
discretion, without prejudice to any other legal remedies available to RVZ.</p>

<h5 class="mt-4">Reporting a violation</h5>
<p>If you encounter content or behaviour on the Platform that violates this policy, please report it via
<a href="<?= h(base_url('contact-us.php')) ?>">Contact Us</a>.</p>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Terms and Conditions — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-9">
<h1 class="mb-1">Terms and Conditions</h1>
<p class="text-muted mb-4">Last updated: <?= date('F Y') ?></p>

<p>These Terms and Conditions govern your use of the RVZ Personnel Services &amp; Labour Hiring Specialists
platform, operated by <?= h(COMPANY_LEGAL_NAME) ?> (Reg. <?= h(COMPANY_REG_NUMBER) ?>) ("RVZ", "the Platform").
By creating an account or using the Platform, you agree to these Terms.</p>

<h5 class="mt-4">1. Eligibility</h5>
<p>You must be at least 18 years old, or have the consent of a parent/guardian, to create an account.</p>

<h5 class="mt-4">2. Candidate accounts</h5>
<p>Candidates may browse and apply to job listings free of charge. You are responsible for the accuracy of the
information in your profile and applications. Submitting false information, or applying on behalf of someone
else without authorisation, is prohibited.</p>

<h5 class="mt-4">3. Recruiter accounts</h5>
<p>Recruiter accounts can post jobs and use the applicant pipeline on a Free plan (a limited number of job posts
per month, per user) or upgrade to a Paid plan (currently <?= h(format_zar(RECRUITER_MONTHLY_PRICE_ZAR)) ?>/user/month,
billed via Paystack) for unlimited job posts and additional tools such as Direct Search, AI-generated ads, and
website embedding — see the Pricing page for current plan details. Paid subscriptions renew monthly until
cancelled, except where RVZ grants free access to a specific account. Recruiters agree to the Service Level
Agreement signed at account setup.</p>

<h5 class="mt-4">4. Job postings</h5>
<p>Recruiters are solely responsible for the accuracy and legality of job postings, including compliance with
the Employment Equity Act, the Labour Relations Act, and other applicable South African employment legislation.
RVZ does not vet job postings for compliance and reserves the right to remove listings that violate these Terms
or the <a href="<?= h(base_url('acceptable-use.php')) ?>">Acceptable Use Policy</a>.</p>

<h5 class="mt-4">5. Fees and payment</h5>
<p>Recruiter subscription fees are processed by Paystack. Fees are billed in advance and are non-refundable
except as required by law. RVZ may change pricing with reasonable notice.</p>

<h5 class="mt-4">6. Intellectual property</h5>
<p>The Platform, its design, and underlying software are the property of RVZ — see our
<a href="<?= h(base_url('copyright-notice.php')) ?>">Copyright Notice</a>. Content you upload (resumes, job
postings, company logos) remains your property, but you grant RVZ a licence to display it on the Platform for
its intended purpose.</p>

<h5 class="mt-4">7. Limitation of liability</h5>
<p>See our <a href="<?= h(base_url('disclaimer.php')) ?>">Disclaimer</a>. RVZ facilitates connections between
candidates and recruiters but is not a party to any employment relationship formed as a result.</p>

<h5 class="mt-4">8. Termination</h5>
<p>RVZ may suspend or terminate accounts that violate these Terms. You may close your account at any time via
<a href="<?= h(base_url('contact-us.php')) ?>">Contact Us</a>.</p>

<h5 class="mt-4">9. Governing law</h5>
<p>These Terms are governed by the laws of the Republic of South Africa, and are entered into and performed
electronically in accordance with the Electronic Communications and Transactions Act 25 of 2002.</p>

<h5 class="mt-4">10. Changes to these Terms</h5>
<p>We may update these Terms from time to time; continued use of the Platform after changes constitutes
acceptance of the updated Terms.</p>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>

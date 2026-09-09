<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Confidentiality Policy — ' . SITE_NAME;
$pageDescription = 'How RVZ Personnel Services protects confidential candidate, recruiter and business information beyond what POPIA requires for personal data.';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-9">
<h1 class="mb-1">Confidentiality Policy</h1>
<p class="text-muted mb-4">Last updated: <?= date('F Y') ?></p>

<p>This policy covers confidential information in the broader sense — beyond personal information already
covered by our <a href="<?= h(base_url('privacy-policy.php')) ?>">Privacy Policy</a> — such as business, salary,
and strategic information shared with <?= h(COMPANY_LEGAL_NAME) ?> ("RVZ") in the course of using the Platform.</p>

<h5 class="mt-4">1. What we treat as confidential</h5>
<ul>
    <li>A candidate's current salary, notice period, or reasons for leaving a role, where shared with us or a
    recruiter through the Platform.</li>
    <li>A recruiter's or company's unpublished job requirements, internal hiring criteria, headcount plans, or
    commercial terms.</li>
    <li>Any information either party marks or clearly intends as confidential when communicating through the
    Platform (messages, notes, applications).</li>
</ul>

<h5 class="mt-4">2. How we protect it</h5>
<p>Confidential information is only accessible to the parties it concerns (a candidate's application is visible
only to the recruiter(s) they applied to, or those found via Direct Search) and to RVZ staff/systems where
necessary to operate the Platform. We do not disclose it to any other user, or to a third party, except:</p>
<ul>
    <li>where you've consented (for example, submitting an application makes your profile visible to that
    recruiter);</li>
    <li>to our service providers strictly as needed to run the Platform (see our
    <a href="<?= h(base_url('privacy-policy.php')) ?>">Privacy Policy</a> for the full list);</li>
    <li>where required by law, a court order, or a regulator.</li>
</ul>

<h5 class="mt-4">3. Recruiters' own confidentiality obligations</h5>
<p>Recruiters and companies using the Platform must not disclose a candidate's confidential information (salary
history, application status, personal circumstances) to anyone outside their own hiring process without the
candidate's consent, and must not use candidate data obtained through the Platform for any purpose unrelated to
the specific hiring process it was shared for.</p>

<h5 class="mt-4">4. RVZ staff and contractors</h5>
<p>RVZ staff and any contractor engaged in delivering Response Handling or similar services are bound by
confidentiality obligations covering everything they access while performing that work, and access is limited to
what each role genuinely needs.</p>

<h5 class="mt-4">5. How long confidentiality lasts</h5>
<p>These confidentiality commitments continue after an application, job posting, or account is closed, for as
long as the information remains genuinely confidential and not otherwise public.</p>

<h5 class="mt-4">6. Questions</h5>
<p><a href="<?= h(base_url('contact-us.php')) ?>">Contact us</a> with any questions, or to report a suspected
breach of confidentiality by another user.</p>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>

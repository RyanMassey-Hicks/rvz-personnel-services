<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'RICA Policy — ' . SITE_NAME;
$pageDescription = 'RVZ Personnel Services\' position under the Regulation of Interception of Communications Act (RICA).';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-9">
<h1 class="mb-1">RICA Policy</h1>
<p class="text-muted mb-4">Regulation of Interception of Communications and Provision of Communication-Related
Information Act 70 of 2002 ("RICA") — Last updated: <?= date('F Y') ?></p>

<p>RICA regulates the interception of communications in South Africa and sets rules for who may lawfully monitor
or record them. This page explains how it applies to <?= h(COMPANY_LEGAL_NAME) ?> ("RVZ").</p>

<h5 class="mt-4">1. We do not intercept your communications</h5>
<p>RVZ does not monitor, intercept, or record private communications between candidates and recruiters beyond
what is necessary to operate the Platform itself (for example, storing a message you choose to send through it,
or the support chatbot conversation you opt into — see our <a href="<?= h(base_url('ai-policy.php')) ?>">AI
Policy</a>). We do not covertly monitor any communication.</p>

<h5 class="mt-4">2. Phone calls made by our Response Handling team</h5>
<p>Where RVZ's Response Handling service involves a phone call with a candidate or recruiter, any call recording
is done only with the knowledge of at least one party to the call (typically RVZ's own team member), consistent
with RICA's consent-based exception to the general prohibition on interception. We do not record calls without
disclosing that we may do so.</p>

<h5 class="mt-4">3. What RICA does not require of this Platform</h5>
<p>RICA's SIM-card registration and telecommunications-service-provider record-keeping obligations apply to
telecommunications and cellular network operators, not to a website/recruitment platform like this one. RVZ does
not operate a telecommunications network and does not collect or store SIM registration data.</p>

<h5 class="mt-4">4. Lawful requests from authorities</h5>
<p>If RVZ is presented with a lawful court order or direction under RICA or other applicable law compelling
disclosure of specific data we hold, we will comply with our legal obligations while limiting disclosure to what
is legally required.</p>

<h5 class="mt-4">5. Questions</h5>
<p>See our <a href="<?= h(base_url('privacy-policy.php')) ?>">Privacy Policy</a> for how we handle personal
information generally, or <a href="<?= h(base_url('contact-us.php')) ?>">contact us</a> with questions about this
policy.</p>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>

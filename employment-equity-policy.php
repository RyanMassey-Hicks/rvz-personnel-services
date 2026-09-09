<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Employment Equity Policy — ' . SITE_NAME;
$pageDescription = 'How RVZ Personnel Services handles Employment Equity information, in line with the Employment Equity Act.';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-9">
<h1 class="mb-1">Employment Equity Policy</h1>
<p class="text-muted mb-4">Employment Equity Act 55 of 1998 ("EEA") — Last updated: <?= date('F Y') ?></p>

<p>The EEA promotes equal opportunity and fair treatment in employment, and requires designated employers to
address workplace disadvantage and report on their workforce profile. This policy explains how
<?= h(COMPANY_LEGAL_NAME) ?> ("RVZ") supports this through the Platform, and how we handle Employment Equity (EE)
data.</p>

<h5 class="mt-4">1. Non-discrimination on this Platform</h5>
<p>RVZ does not discriminate against candidates on any ground listed in the EEA — including race, gender,
disability, age, religion, or any other arbitrary ground — in how the Platform itself operates (search visibility,
application processing, account access). Job postings that appear to solicit unlawful discriminatory criteria may
be removed — see our <a href="<?= h(base_url('acceptable-use.php')) ?>">Acceptable Use</a> policy.</p>

<h5 class="mt-4">2. Employment Equity data is optional and separately consented to</h5>
<p>A candidate's profile may optionally include EE-related information (such as race and disability status) so
that employers who are designated employers under the EEA can meet their own reporting obligations. This is
"special personal information" under POPIA — we only collect and process it where you give explicit, separate
consent on your profile, distinct from your general account consent. See section 5 of our
<a href="<?= h(base_url('privacy-policy.php')) ?>">Privacy Policy</a>.</p>

<h5 class="mt-4">3. How EE data is used</h5>
<p>Where provided, EE data is visible to recruiters as aggregate/individual profile information to support their
own EEA compliance and workforce planning — it is never used by RVZ to rank, filter, or otherwise algorithmically
sort candidates, and Employment Equity considerations remain each employer's own legal responsibility, not RVZ's.
RVZ is not the "designated employer" for candidates placed through the Platform merely by virtue of operating it.</p>

<h5 class="mt-4">4. Employers' own EEA obligations</h5>
<p>Employers using this Platform who qualify as "designated employers" under the EEA (broadly, employers above
the applicable employee-count or annual-turnover thresholds) remain solely responsible for their own EEA
compliance, including consultation, analysis, employment equity plans, and reporting to the Department of
Employment and Labour. RVZ provides tools and optional data to assist, not compliance on an employer's behalf.</p>

<h5 class="mt-4">5. RVZ's own commitment</h5>
<p>As an employer in its own right, RVZ is committed to fair, non-discriminatory recruitment and employment
practices consistent with the EEA's principles, regardless of whether it meets the statutory threshold for a
"designated employer" at any given time.</p>

<h5 class="mt-4">6. Questions or complaints</h5>
<p>Contact us via <a href="<?= h(base_url('contact-us.php')) ?>">Contact Us</a>. Complaints about unfair
discrimination may also be referred to the CCMA or the Labour Court, as applicable.</p>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>

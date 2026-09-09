<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Labour Relations Policy — ' . SITE_NAME;
$pageDescription = 'RVZ Personnel Services\' position under the Labour Relations Act, including temporary employment services provisions.';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-9">
<h1 class="mb-1">Labour Relations Policy</h1>
<p class="text-muted mb-4">Labour Relations Act 66 of 1995 ("LRA") — Last updated: <?= date('F Y') ?></p>

<p>This policy sets out how <?= h(COMPANY_LEGAL_NAME) ?> ("RVZ") approaches its obligations under the LRA in
connection with the recruitment, job-matching, and labour hiring services it provides.</p>

<h5 class="mt-4">1. This Platform's role</h5>
<p>Through this website, RVZ operates a recruitment and applicant-tracking platform: employers publish job
vacancies, candidates apply directly, and the employer makes its own hiring decision. For most postings on this
Platform, the hiring employer — not RVZ — is the candidate's employer once hired, and is directly responsible for
that employment relationship under the LRA.</p>

<h5 class="mt-4">2. Where RVZ acts as a Temporary Employment Service</h5>
<p>Where RVZ separately provides labour hiring / temporary employment services (placing a worker with a client
business), we recognise our obligations as a Temporary Employment Service ("TES") under section 198 of the LRA,
including that:</p>
<ul>
    <li>a written contract of employment applies to workers we place, on terms compliant with the LRA and Basic
    Conditions of Employment Act;</li>
    <li>a placed employee performing ongoing work for a client for longer than three months, earning below the
    earnings threshold, is deemed the employee of that client for LRA purposes under section 198A, unless a
    genuine and temporary need justifies the arrangement continuing;</li>
    <li>placed employees are treated on the whole not less favourably than the client's own employees performing
    the same or similar work, as required by section 198B/198C where applicable.</li>
</ul>
<p>These arrangements are agreed separately and directly with the relevant client and worker — not through this
website's self-service job-posting flow — and are governed by their own written agreements.</p>

<h5 class="mt-4">3. Unfair dismissal and unfair labour practice</h5>
<p>RVZ does not make dismissal or disciplinary decisions on behalf of the employers who use this Platform to
advertise roles. Any dispute about dismissal, discipline, or an unfair labour practice arising from employment
obtained through this Platform is between the candidate and their actual employer, who remains responsible for
following a fair process and, where relevant, the CCMA/Labour Court dispute resolution routes provided for in
the LRA.</p>

<h5 class="mt-4">4. Freedom of association and organisational rights</h5>
<p>RVZ does not restrict, and no job posting on this Platform may lawfully restrict, a worker's rights under the
LRA to join a trade union, participate in its activities, or exercise organisational rights.</p>

<h5 class="mt-4">5. Questions</h5>
<p>If you have questions about the employment status of a specific placement, please
<a href="<?= h(base_url('contact-us.php')) ?>">contact us</a> directly rather than relying solely on this general
policy — individual arrangements can differ, and this page is not a substitute for reviewing your own written
contract.</p>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>

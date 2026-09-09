<?php
require __DIR__ . '/includes/bootstrap.php';

$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $contactName = trim($_POST['name'] ?? '');
    $contactEmail = trim($_POST['email'] ?? '');

    if ($category !== '' && $description !== '') {
        $body = '<p><strong>Category:</strong> ' . h($category) . '</p>'
            . '<p><strong>Disclosure:</strong></p><p>' . nl2br(h($description)) . '</p>'
            . '<p><strong>Reporter contact:</strong> ' . ($contactName !== '' || $contactEmail !== ''
                ? h($contactName) . ' ' . h($contactEmail) : '<em>Anonymous — no contact details provided</em>') . '</p>';
        // Deliberately does NOT log the submitter's IP address or session
        // anywhere in the app, unlike other forms on this site — an
        // anonymous whistleblower report should stay that way at the
        // application layer, even though the web server's own standard
        // access logs are outside this app's control (see the note below).
        send_email(
            PRIVILEGED_RECRUITER_EMAIL,
            'Whistleblower disclosure: ' . $category,
            email_wrap($body)
        );
        $sent = true;
    } else {
        flash('danger', 'Please select a category and describe the concern.');
    }
}

$pageTitle = 'Whistleblowing Policy — ' . SITE_NAME;
$pageDescription = 'How to report suspected wrongdoing at RVZ Personnel Services, and your protection under the Protected Disclosures Act.';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-9">
<h1 class="mb-1">Whistleblowing Policy</h1>
<p class="text-muted mb-4">Protected Disclosures Act 26 of 2000 — Last updated: <?= date('F Y') ?></p>

<p>This policy explains how to report suspected fraud, corruption, unlawful conduct, or serious wrongdoing
connected to <?= h(COMPANY_LEGAL_NAME) ?> ("RVZ") or its Platform, and the legal protection available to you for
doing so.</p>

<h5 class="mt-4">1. What this covers</h5>
<p>A "disclosure" under this policy includes information suggesting: a criminal offence, failure to comply with
a legal obligation, a miscarriage of justice, danger to health or safety, damage to the environment, unfair
discrimination, or deliberate concealment of any of the above — whether by RVZ, an RVZ employee, or a company
using this Platform.</p>

<h5 class="mt-4">2. Legal protection</h5>
<p>The Protected Disclosures Act 26 of 2000 protects employees and workers who make a "protected disclosure" in
good faith from being subjected to any occupational detriment (dismissal, disciplinary action, harassment,
demotion, or other prejudice) as a result. This protection is why the reporting channel below allows you to
remain anonymous if you choose.</p>

<h5 class="mt-4">3. Confidentiality</h5>
<p>Reports are directed to RVZ's designated contact and are not shared beyond those who need to investigate the
matter. You may choose to provide your name and contact details (helpful if we need more information to
investigate) or leave them blank to report anonymously.</p>
<p class="small text-muted">Note on anonymity: this form itself does not record or transmit your name, email, or
IP address anywhere in our application unless you choose to provide them. We cannot, however, control or
guarantee against incidental logging that may occur at the web server or network level outside this
application — if you need stronger anonymity, consider using a personal device/network and email address not
otherwise linked to your identity.</p>

<h5 class="mt-4">4. Report a concern</h5>
<?php if ($sent): ?>
    <div class="alert alert-success">Thank you — your disclosure has been submitted and will be reviewed
    confidentially.</div>
<?php else: ?>
    <form method="post" class="card"><div class="card-body">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">Category</label>
            <select name="category" class="form-select" required>
                <option value="">Choose one…</option>
                <option>Fraud or financial misconduct</option>
                <option>Corruption or bribery</option>
                <option>Unlawful conduct / regulatory breach</option>
                <option>Unfair discrimination</option>
                <option>Health or safety risk</option>
                <option>Other serious wrongdoing</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">What happened?</label>
            <textarea name="description" rows="6" class="form-control" required
                placeholder="Describe what you observed, when, and who was involved, as specifically as you can."></textarea>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Your name <span class="text-muted small">(optional — leave blank to stay anonymous)</span></label>
                <input type="text" name="name" class="form-control">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Your email <span class="text-muted small">(optional)</span></label>
                <input type="email" name="email" class="form-control">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Submit Disclosure</button>
    </div></form>
<?php endif; ?>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>

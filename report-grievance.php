<?php
require __DIR__ . '/includes/bootstrap.php';

$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $relationship = trim($_POST['relationship'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && $description !== '') {
        send_email(
            PRIVILEGED_RECRUITER_EMAIL,
            'Grievance reported by ' . $name,
            email_wrap(
                '<p><strong>From:</strong> ' . h($name) . ' (' . h($email) . ')</p>'
                . '<p><strong>Relationship to RVZ:</strong> ' . h($relationship) . '</p>'
                . '<p><strong>Grievance:</strong></p><p>' . nl2br(h($description)) . '</p>'
            ),
            $name
        );
        $sent = true;
    } else {
        flash('danger', 'Please fill in your name, a valid email, and a description of the grievance.');
    }
}

$pageTitle = 'Report a Grievance — ' . SITE_NAME;
$pageDescription = 'How to raise a grievance about your experience with RVZ Personnel Services, a recruiter, or another user of the Platform.';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-9">
<h1 class="mb-1">Report a Grievance</h1>
<p class="text-muted mb-4">Last updated: <?= date('F Y') ?></p>

<p>If you're unhappy with how you've been treated by <?= h(COMPANY_LEGAL_NAME) ?> ("RVZ"), by a recruiter or
company using the Platform, or by another user, this page explains how to raise it and what happens next. If
you're reporting suspected fraud, corruption, or other serious wrongdoing rather than a personal grievance, please
use our <a href="<?= h(base_url('whistleblowing-policy.php')) ?>">Whistleblowing</a> channel instead.</p>

<h5 class="mt-4">1. What counts as a grievance</h5>
<p>Examples include: unfair or disrespectful treatment during an application or hiring process, a dispute over
how a job posting or your account was handled, a service issue with RVZ's own team (including Response Handling),
or a concern about another user's conduct on the Platform.</p>

<h5 class="mt-4">2. How we handle it</h5>
<ol>
    <li>We acknowledge every grievance submitted through this page.</li>
    <li>We review the matter, which may involve contacting you for more detail and, where relevant, the other
    party involved.</li>
    <li>We aim to respond with an outcome or next steps within 10 business days.</li>
</ol>
<p>Submitting a grievance here does not affect your other legal rights — for example, a labour-related dispute
may still be referred to the CCMA, and a complaint about personal information handling may still be lodged with
the Information Regulator.</p>

<h5 class="mt-4">3. Submit a grievance</h5>
<?php if ($sent): ?>
    <div class="alert alert-success">Thank you — your grievance has been received and will be reviewed.</div>
<?php else: ?>
    <form method="post" class="card"><div class="card-body">
        <?= csrf_field() ?>
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" required></div>
            <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
        </div>
        <div class="mb-3">
            <label class="form-label">Your relationship to RVZ</label>
            <select name="relationship" class="form-select">
                <option>Candidate</option>
                <option>Recruiter / Employer</option>
                <option>Other user of the Platform</option>
                <option>Not currently a user</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Describe the grievance</label>
            <textarea name="description" rows="6" class="form-control" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Submit Grievance</button>
    </div></form>
<?php endif; ?>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>

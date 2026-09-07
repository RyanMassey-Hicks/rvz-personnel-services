<?php
require __DIR__ . '/includes/bootstrap.php';

$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && $message !== '') {
        send_email(
            PRIVILEGED_RECRUITER_EMAIL,
            'Contact form message from ' . $name,
            email_wrap('<p><strong>From:</strong> ' . h($name) . ' (' . h($email) . ')</p><p>' . nl2br(h($message)) . '</p>'),
            $name
        );
        $sent = true;
    } else {
        flash('danger', 'Please fill in your name, a valid email, and a message.');
    }
}

$pageTitle = 'Contact Us — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<h1 class="mb-4">Contact Us</h1>

<div class="row">
    <div class="col-md-7">
        <?php if ($sent): ?>
            <div class="alert alert-success">Thanks — your message has been sent. We'll get back to you shortly.</div>
        <?php else: ?>
            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Message</label><textarea name="message" rows="6" class="form-control" required></textarea></div>
                <button type="submit" class="btn btn-primary">Send Message</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="col-md-5">
        <div class="card"><div class="card-body">
            <h5>RVZ Personnel Services &amp; Labour Hiring Specialists</h5>
            <p class="text-muted small mb-2"><?= h(COMPANY_LEGAL_NAME) ?><br>Reg. <?= h(COMPANY_REG_NUMBER) ?></p>
            <p class="mb-1">Email: <a href="mailto:<?= h(PRIVILEGED_RECRUITER_EMAIL) ?>"><?= h(PRIVILEGED_RECRUITER_EMAIL) ?></a></p>
            <p class="mb-0">Website: <a href="https://www.rvzgroup.co.za" target="_blank">www.rvzgroup.co.za</a></p>
        </div></div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

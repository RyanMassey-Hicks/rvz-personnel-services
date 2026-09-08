<?php
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/index.php');
}
csrf_verify();

$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('danger', 'Please enter a valid email address.');
    redirect($_SERVER['HTTP_REFERER'] ?? '/index.php');
}

$user = current_user();
$stmt = db()->prepare('SELECT id FROM newsletter_subscribers WHERE email = ?');
$stmt->execute([$email]);
$existing = $stmt->fetch();

if ($existing) {
    db()->prepare('UPDATE newsletter_subscribers SET unsubscribed_at = NULL, user_id = COALESCE(user_id, ?) WHERE id = ?')
        ->execute([$user['id'] ?? null, $existing['id']]);
} else {
    db()->prepare('INSERT INTO newsletter_subscribers (user_id, email, token) VALUES (?, ?, ?)')
        ->execute([$user['id'] ?? null, $email, bin2hex(random_bytes(16))]);
}

if ($user && $user['role'] !== 'recruiter') {
    db()->prepare('UPDATE candidate_profiles SET opt_in_newsletter = 1 WHERE user_id = ?')->execute([$user['id']]);
}

send_email($email, 'You\'re subscribed to ' . SITE_NAME, email_wrap(
    '<p>Thanks for subscribing! We\'ll email you when new job vacancies and updates go live.</p>'
    . '<p class="small">Didn\'t sign up? You can unsubscribe any time from the link in future emails.</p>'
));

flash('success', 'Thanks — you\'re subscribed to job alerts.');
redirect($_SERVER['HTTP_REFERER'] ?? '/index.php');

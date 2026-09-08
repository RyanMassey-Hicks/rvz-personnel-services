<?php
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/index.php');
}
csrf_verify();

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') {
    flash('danger', 'Please fill in your name, a valid email, and a message.');
    redirect('/index.php#contact');
}

send_email(
    GENERAL_INFO_EMAIL,
    'Website enquiry from ' . $name,
    email_wrap(
        '<p><strong>From:</strong> ' . h($name) . ' (' . h($email) . ')' . ($phone !== '' ? ' &middot; ' . h($phone) : '') . '</p>'
        . '<p>' . nl2br(h($message)) . '</p>'
    ),
    $name
);

flash('success', 'Thanks for reaching out — we\'ll get back to you shortly.');
redirect('/index.php#contact');

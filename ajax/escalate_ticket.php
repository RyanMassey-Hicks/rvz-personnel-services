<?php
require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

$sent = $_POST['csrf_token'] ?? '';
if (!$sent || !hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid security token, please reload the page.']);
    exit;
}

$user = current_user();
$name = trim($_POST['name'] ?? ($user ? trim($user['first_name'] . ' ' . $user['last_name']) : ''));
$email = trim($_POST['email'] ?? ($user['email'] ?? ''));
$transcriptRaw = $_POST['transcript'] ?? '[]';

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Please provide your name and a valid email address.']);
    exit;
}

$transcript = json_decode($transcriptRaw, true);
if (!is_array($transcript)) {
    $transcript = [];
}
$transcript = array_slice(array_filter($transcript, function ($m) {
    return is_array($m) && in_array($m['role'] ?? '', ['user', 'assistant'], true) && isset($m['content']);
}), -30);

$transcriptText = '';
foreach ($transcript as $m) {
    $who = $m['role'] === 'user' ? ($name ?: 'Visitor') : 'Support bot';
    $transcriptText .= $who . ': ' . $m['content'] . "\n\n";
}
if ($transcriptText === '') {
    $transcriptText = '(No prior chat — direct escalation.)';
}

$subject = trim($_POST['subject'] ?? '') ?: 'Support request from ' . $name;

$stmt = db()->prepare(
    'INSERT INTO support_tickets (user_id, name, email, subject, transcript, status) VALUES (?, ?, ?, ?, ?, "open")'
);
$stmt->execute([$user['id'] ?? null, $name, $email, $subject, $transcriptText]);
$ticketId = (int) db()->lastInsertId();

send_email(
    PRIVILEGED_RECRUITER_EMAIL,
    'New support ticket #' . $ticketId . ': ' . $subject,
    email_wrap(
        '<p>From: ' . h($name) . ' (' . h($email) . ')</p>'
        . '<p><strong>' . h($subject) . '</strong></p>'
        . '<pre style="white-space:pre-wrap;font-family:inherit;">' . h($transcriptText) . '</pre>'
        . '<p><a href="' . h(base_url('support_tickets.php?id=' . $ticketId)) . '">View in Support Tickets</a></p>'
    )
);

send_email(
    $email,
    'We received your support request — ' . SITE_NAME,
    email_wrap(
        '<p>Hi ' . h($name) . ',</p>'
        . '<p>Thanks for reaching out — your request (ticket #' . $ticketId . ') has been received and our team will follow up by email shortly.</p>'
    ),
    $name
);

echo json_encode(['ok' => true, 'ticket_id' => $ticketId]);

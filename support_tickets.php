<?php
/** Support ticket queue — privileged account only (this is the site's one support inbox, not per-recruiter). */
require __DIR__ . '/includes/bootstrap.php';
require_recruiter();

$user = current_user();
if (!is_privileged_recruiter($user)) {
    http_response_code(403);
    die('This page is only available to the privileged account.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $ticketId = (int) ($_POST['ticket_id'] ?? 0);
    $note = trim($_POST['resolution_note'] ?? '');

    $stmt = db()->prepare('SELECT * FROM support_tickets WHERE id = ?');
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();

    if ($ticket) {
        db()->prepare('UPDATE support_tickets SET status = "resolved", resolved_at = NOW(), resolution_note = ? WHERE id = ?')
            ->execute([$note, $ticketId]);

        send_email(
            $ticket['email'],
            'Your support request has been resolved — ' . SITE_NAME,
            email_wrap(
                '<p>Hi ' . h($ticket['name']) . ',</p>'
                . '<p>Your support request (ticket #' . $ticketId . ', "' . h($ticket['subject']) . '") has been resolved.</p>'
                . ($note !== '' ? '<p>' . nl2br(h($note)) . '</p>' : '')
                . '<p>If this didn\'t fully solve it, just reply to let us know and we\'ll reopen it.</p>'
            ),
            $ticket['name']
        );
        if (!empty($ticket['user_id'])) {
            create_notification(
                (int) $ticket['user_id'],
                'support',
                'Support ticket resolved: ' . $ticket['subject'],
                $note !== '' ? $note : 'Your support request has been resolved.',
                base_url('index.php')
            );
        }
        flash('success', 'Ticket #' . $ticketId . ' marked resolved — customer notified.');
    }
    redirect('/support_tickets.php');
}

$filter = $_GET['status'] ?? 'open';
$stmt = db()->prepare(
    'SELECT * FROM support_tickets WHERE status = ? ORDER BY created_at DESC'
);
$stmt->execute([$filter === 'resolved' ? 'resolved' : 'open']);
$tickets = $stmt->fetchAll();

$pageTitle = 'Support Tickets';
require __DIR__ . '/includes/header.php';
?>
<h2 class="mb-1">Support Tickets</h2>
<p class="text-muted mb-4">Escalations from the AI support chatbot, plus direct contact form messages.</p>

<div class="mb-3">
    <a href="?status=open" class="btn btn-sm <?= $filter !== 'resolved' ? 'btn-primary' : 'btn-outline-primary' ?>">Open</a>
    <a href="?status=resolved" class="btn btn-sm <?= $filter === 'resolved' ? 'btn-primary' : 'btn-outline-primary' ?>">Resolved</a>
</div>

<?php if (!$tickets): ?>
    <p class="text-muted">No <?= h($filter === 'resolved' ? 'resolved' : 'open') ?> tickets.</p>
<?php endif; ?>

<?php foreach ($tickets as $ticket): ?>
    <div class="card mb-3 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                <div>
                    <h5 class="mb-1">#<?= (int) $ticket['id'] ?> — <?= h($ticket['subject']) ?></h5>
                    <p class="text-muted small mb-0"><?= h($ticket['name']) ?> &lt;<?= h($ticket['email']) ?>&gt; &middot; <?= h(date('M j, Y H:i', strtotime($ticket['created_at']))) ?></p>
                </div>
                <span class="badge <?= $ticket['status'] === 'open' ? 'bg-warning text-dark' : 'bg-success' ?>"><?= h(ucfirst($ticket['status'])) ?></span>
            </div>
            <pre class="small bg-light p-2 rounded" style="white-space:pre-wrap;max-height:220px;overflow-y:auto;"><?= h($ticket['transcript']) ?></pre>

            <?php if ($ticket['status'] === 'open'): ?>
                <form method="post" class="mt-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
                    <div class="mb-2">
                        <label class="form-label small">Resolution note (included in the email to the customer)</label>
                        <textarea name="resolution_note" rows="2" class="form-control form-control-sm"></textarea>
                    </div>
                    <button type="submit" class="btn btn-sm btn-success">Mark Resolved &amp; Notify Customer</button>
                </form>
            <?php else: ?>
                <p class="small text-muted mb-0 mt-2">Resolved <?= h(date('M j, Y H:i', strtotime($ticket['resolved_at']))) ?><?= $ticket['resolution_note'] ? ': ' . h($ticket['resolution_note']) : '' ?></p>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

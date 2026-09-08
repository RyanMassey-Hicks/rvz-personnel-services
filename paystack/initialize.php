<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/paystack.php';
require_login();
require_recruiter();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/subscribe.php');
}
csrf_verify();

$user = current_user();

if (is_privileged_recruiter($user)) {
    redirect('/dashboard.php');
}

$companyId = current_recruiter_company_id();
if (!$companyId) {
    flash('danger', 'Set up your company first.');
    redirect('/become_recruiter.php');
}

$seatsUsed = company_seats_used($companyId);
$seats = max($seatsUsed, 1, (int) ($_POST['seats'] ?? 1));

$stmt = db()->prepare('SELECT * FROM companies WHERE id = ?');
$stmt->execute([$companyId]);
$company = $stmt->fetch();

try {
    $checkout = paystack_initialize_seat_subscription($company, $user, $seats, base_url('paystack/callback.php'));
    redirect($checkout['authorization_url']);
} catch (Throwable $e) {
    flash('danger', 'Could not start checkout — please try again in a moment.');
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        flash('danger', $e->getMessage());
    }
    redirect('/subscribe.php');
}

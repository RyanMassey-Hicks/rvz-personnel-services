<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/paystack.php';
require_login();
require_recruiter();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pricing.php');
}
csrf_verify();

$user = current_user();
if (is_privileged_recruiter($user)) {
    redirect('/dashboard.php');
}

$code = trim($_POST['package'] ?? '');
$stmt = db()->prepare('SELECT * FROM recruiter_packages WHERE code = ? AND active = 1');
$stmt->execute([$code]);
$package = $stmt->fetch();

if (!$package) {
    flash('danger', 'Unknown package.');
    redirect('/pricing.php');
}

try {
    $checkout = paystack_initialize_package_purchase($package, $user, base_url('paystack/callback.php'));
    redirect($checkout['authorization_url']);
} catch (Throwable $e) {
    flash('danger', 'Could not start checkout — please try again in a moment.');
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        flash('danger', $e->getMessage());
    }
    redirect('/pricing.php');
}

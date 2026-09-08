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
$addonType = $_POST['addon_type'] ?? '';
$jobId = isset($_POST['job_id']) && $_POST['job_id'] !== '' ? (int) $_POST['job_id'] : null;

$addons = [
    'extend_listing' => 200000,       // R2,000 per listing
    'company_presentation' => 320000, // R3,200
];

if (!isset($addons[$addonType])) {
    flash('danger', 'Unknown add-on.');
    redirect('/account_billing.php');
}

if ($addonType === 'extend_listing') {
    if (!$jobId) {
        flash('danger', 'Choose a job listing to extend.');
        redirect('/dashboard.php');
    }
    $stmt = db()->prepare('SELECT id FROM jobs WHERE id = ? AND posted_by = ?');
    $stmt->execute([$jobId, $user['id']]);
    if (!$stmt->fetch()) {
        flash('danger', 'That job listing was not found on your account.');
        redirect('/dashboard.php');
    }
}

try {
    $checkout = paystack_initialize_addon_purchase($addonType, $addons[$addonType], $user, $jobId, base_url('paystack/callback.php'));
    redirect($checkout['authorization_url']);
} catch (Throwable $e) {
    flash('danger', 'Could not start checkout — please try again in a moment.');
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        flash('danger', $e->getMessage());
    }
    redirect($jobId ? '/job.php?id=' . $jobId : '/account_billing.php');
}

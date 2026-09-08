<?php
/**
 * The user lands here after paying (or cancelling) at Paystack's checkout.
 * This is the "did it actually work" check — Paystack's own webhook.php is
 * the one source of truth for renewals, but verifying here too means the
 * user isn't stuck waiting on a webhook just to see their dashboard unlock.
 */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/paystack.php';
require_login();

$reference = $_GET['reference'] ?? $_GET['trxref'] ?? '';
if (!$reference) {
    flash('danger', 'Missing payment reference.');
    redirect('/subscribe.php');
}

$stmt = db()->prepare('SELECT * FROM payment_transactions WHERE reference = ? AND user_id = ?');
$stmt->execute([$reference, current_user()['id']]);
$txn = $stmt->fetch();
if (!$txn) {
    flash('danger', 'We could not find that payment on your account.');
    redirect('/subscribe.php');
}

try {
    $result = paystack_verify_transaction($reference);
} catch (Throwable $e) {
    flash('danger', 'Could not verify payment right now — if you were charged, this will resolve automatically shortly.');
    redirect('/subscribe.php');
}

$status = $result['data']['status'] ?? 'failed';

$stmt = db()->prepare('UPDATE payment_transactions SET status = ?, raw_response = ? WHERE reference = ?');
$stmt->execute([$status === 'success' ? 'success' : 'failed', json_encode($result), $reference]);

if ($status === 'success') {
    $purpose = $result['data']['metadata']['purpose'] ?? '';

    if ($purpose === 'package_purchase') {
        $packageId = (int) ($result['data']['metadata']['package_id'] ?? 0);
        $stmt = db()->prepare('SELECT * FROM recruiter_packages WHERE id = ?');
        $stmt->execute([$packageId]);
        $package = $stmt->fetch();
        if ($package) {
            activate_package_purchase($reference, (int) current_user()['id'], $package);
        }
        flash('success', 'Payment successful — your ' . h($package['name'] ?? 'package') . ' package is now active.');

        $stmt = db()->prepare('SELECT id FROM recruiter_slas WHERE user_id = ?');
        $stmt->execute([current_user()['id']]);
        if (!$stmt->fetch()) {
            redirect('/sla_sign.php');
        }
        redirect('/dashboard.php');
    }

    if ($purpose === 'addon_purchase') {
        $addonType = $result['data']['metadata']['addon_type'] ?? '';
        $jobId = $result['data']['metadata']['job_id'] ?? null;
        activate_addon_purchase($reference, (int) current_user()['id'], $addonType, $jobId ? (int) $jobId : null, (int) ($result['data']['amount'] ?? 0));
        flash('success', 'Payment successful — your add-on is now active.');
        redirect($addonType === 'extend_listing' && $jobId ? '/job.php?id=' . (int) $jobId : '/account_billing.php');
    }

    // Legacy monthly-subscription flows (kept for any recruiter still on that plan).
    $companyId = (int) ($result['data']['metadata']['company_id'] ?? 0);
    $seats = (int) ($result['data']['metadata']['seats'] ?? 0);
    if ($companyId && $seats) {
        activate_company_subscription($companyId, $seats, $result['data']);
    } else {
        activate_subscription((int) current_user()['id'], $result['data']);
    }
    flash('success', 'Payment successful — recruiter access is now active.');

    $stmt = db()->prepare('SELECT id FROM recruiter_slas WHERE user_id = ?');
    $stmt->execute([current_user()['id']]);
    if (!$stmt->fetch()) {
        redirect('/sla_sign.php');
    }
    redirect('/dashboard.php');
}

flash('danger', 'Payment was not successful (' . $status . '). Please try again.');
redirect('/subscribe.php');

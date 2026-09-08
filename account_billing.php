<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/paystack.php';
require_recruiter();

$user = current_user();
$privileged = has_free_recruiter_access($user);
$companyId = current_recruiter_company_id();
$companySub = $privileged ? null : ($companyId ? get_company_subscription($companyId) : null);
$sub = (!$privileged && !$companySub) ? get_subscription((int) $user['id']) : null;
$isPaid = has_active_recruiter_subscription($user);
$postsThisMonth = $isPaid ? 0 : jobs_posted_this_month((int) $user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_subscription') {
    csrf_verify();

    if ($companySub && $companySub['status'] === 'active') {
        try {
            paystack_disable_company_subscription($companySub);
        } catch (Throwable $e) {
            error_log('Failed to disable Paystack subscription for company ' . $companyId . ': ' . $e->getMessage());
        }
        db()->prepare('UPDATE company_subscriptions SET status = "cancelled" WHERE company_id = ?')->execute([$companyId]);
        flash('success', 'Your team\'s subscription has been cancelled — you\'re now on the Free plan (' . FREE_TIER_JOB_LIMIT . ' job posts/month per user). You will not be charged again.');
    } elseif ($sub && $sub['status'] === 'active') {
        db()->prepare('UPDATE subscriptions SET status = "cancelled" WHERE user_id = ?')->execute([$user['id']]);
        flash('success', 'Your subscription has been cancelled — you\'re now on the Free plan (' . FREE_TIER_JOB_LIMIT . ' job posts/month). You will not be charged again.');
    }
    redirect('/account_billing.php');
}

$stmt = db()->prepare('SELECT * FROM payment_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
$stmt->execute([$user['id']]);
$transactions = $stmt->fetchAll();

$stmt = db()->prepare(
    'SELECT recruiter_purchases.*, recruiter_packages.name AS package_name
     FROM recruiter_purchases JOIN recruiter_packages ON recruiter_packages.id = recruiter_purchases.package_id
     WHERE recruiter_purchases.user_id = ? ORDER BY recruiter_purchases.created_at DESC'
);
$stmt->execute([$user['id']]);
$purchases = $stmt->fetchAll();
$totalCreditsRemaining = recruiter_total_credits_remaining((int) $user['id']);

$pageTitle = 'Account & Billing';
require __DIR__ . '/includes/header.php';
?>
<h2 class="mb-4">Account &amp; Billing</h2>

<div class="card mb-4"><div class="card-body">
    <h5 class="mb-3">Job-Listing Packages</h5>
    <?php if ($privileged): ?>
        <p class="text-muted mb-0">Free recruiter access is active on this account — packages aren't needed.</p>
    <?php else: ?>
        <p class="mb-1"><strong><?= $totalCreditsRemaining ?></strong> listing credit<?= $totalCreditsRemaining === 1 ? '' : 's' ?> remaining</p>
        <?php $accessUntil = recruiter_account_access_expires_at((int) $user['id']); ?>
        <p class="mb-3 text-muted small"><?= $accessUntil ? 'Account access active until ' . h(date('M j, Y', strtotime($accessUntil))) . '.' : 'No active package.' ?></p>
        <?php if ($purchases): ?>
            <div class="table-responsive mb-3">
            <table class="table table-sm">
                <thead><tr><th>Package</th><th>Bought</th><th>Credits</th><th>Access Until</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($purchases as $p): ?>
                    <tr>
                        <td><?= h($p['package_name']) ?></td>
                        <td><?= h(date('M j, Y', strtotime($p['created_at']))) ?></td>
                        <td><?= (int) $p['credits_remaining'] ?> / <?= (int) $p['credits_total'] ?></td>
                        <td><?= $p['access_expires_at'] ? h(date('M j, Y', strtotime($p['access_expires_at']))) : '—' ?></td>
                        <td><span class="badge <?= $p['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= h(ucfirst($p['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
        <a href="<?= h(base_url('pricing.php')) ?>" class="btn btn-primary btn-sm">Buy a Package</a>
    <?php endif; ?>
</div></div>

<div class="card mb-4"><div class="card-body">
    <h5 class="mb-3">Legacy Subscription <span class="badge bg-secondary">Previous plan</span></h5>
    <?php if ($privileged): ?>
        <div class="alert alert-success mb-0">Free recruiter access is active on this account (<?= h($user['email']) ?>) — no billing applies.</div>
    <?php elseif ($isPaid && $companySub && $companySub['status'] === 'active'): ?>
        <p class="mb-1"><strong>Plan:</strong> Team seats — <?= (int) $companySub['seat_quantity'] ?> &times; <?= h(format_zar(RECRUITER_MONTHLY_PRICE_ZAR)) ?>/month
            = <?= h(format_zar($companySub['amount_cents'] / 100)) ?>/month</p>
        <p class="mb-1"><strong>Status:</strong> <span class="badge bg-success">Paid seat</span></p>
        <p class="mb-3"><strong>Renews:</strong> <?= h(date('M j, Y', strtotime($companySub['current_period_end']))) ?></p>
        <a href="<?= h(base_url('subscribe.php')) ?>" class="btn btn-outline-primary btn-sm">Manage Seats</a>
        <form method="post" class="d-inline" onsubmit="return confirm('Cancel your team\'s subscription and downgrade to the Free plan? This takes effect immediately — you and your team will be limited to ' + <?= (int) FREE_TIER_JOB_LIMIT ?> + ' job posts/month per user, and your card will not be charged again.');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cancel_subscription">
            <button type="submit" class="btn btn-outline-danger btn-sm">Cancel &amp; Downgrade to Free</button>
        </form>
    <?php elseif ($companySub && $companySub['status'] === 'active'): ?>
        <div class="alert alert-info mb-3">
            Your team has <?= (int) $companySub['seat_quantity'] ?> paid seat<?= (int) $companySub['seat_quantity'] === 1 ? '' : 's' ?>, but your account
            isn't currently one of them — you're on the <strong>Free plan</strong>
            (<?= $postsThisMonth ?> of <?= FREE_TIER_JOB_LIMIT ?> job posts used this month).
        </div>
        <a href="<?= h(base_url('subscribe.php')) ?>" class="btn btn-primary btn-sm">Add More Seats</a>
    <?php elseif ($companySub && $companySub['status'] === 'past_due'): ?>
        <div class="alert alert-warning">Your team's last payment didn't go through.</div>
        <a href="<?= h(base_url('subscribe.php')) ?>" class="btn btn-primary btn-sm">Reactivate</a>
    <?php elseif ($companySub && $companySub['status'] === 'cancelled'): ?>
        <div class="alert alert-secondary">Your team's billing is cancelled.</div>
        <a href="<?= h(base_url('subscribe.php')) ?>" class="btn btn-primary btn-sm">Resubscribe</a>
    <?php elseif ($sub && $sub['status'] === 'active'): ?>
        <p class="mb-1"><strong>Plan:</strong> Recruiter — <?= h(format_zar(RECRUITER_MONTHLY_PRICE_ZAR)) ?>/month</p>
        <p class="mb-1"><strong>Status:</strong> <span class="badge bg-success">Active</span></p>
        <p class="mb-3"><strong>Renews:</strong> <?= h(date('M j, Y', strtotime($sub['current_period_end']))) ?></p>
        <form method="post" onsubmit="return confirm('Cancel your subscription and downgrade to the Free plan? This takes effect immediately, and your card will not be charged again.');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cancel_subscription">
            <button type="submit" class="btn btn-outline-danger btn-sm">Cancel &amp; Downgrade to Free</button>
        </form>
    <?php elseif ($sub && $sub['status'] === 'past_due'): ?>
        <div class="alert alert-warning">Your last payment didn't go through.</div>
        <a href="<?= h(base_url('subscribe.php')) ?>" class="btn btn-primary btn-sm">Reactivate</a>
    <?php elseif ($sub && $sub['status'] === 'cancelled'): ?>
        <div class="alert alert-secondary">Your subscription is cancelled.</div>
        <a href="<?= h(base_url('subscribe.php')) ?>" class="btn btn-primary btn-sm">Resubscribe</a>
    <?php else: ?>
        <p class="text-muted mb-0">No legacy subscription on this account — see Job-Listing Packages above.</p>
    <?php endif; ?>
</div></div>

<div class="card mb-4"><div class="card-body">
    <h5 class="mb-3">Account Details</h5>
    <p class="mb-1"><strong>Email:</strong> <?= h($user['email']) ?></p>
    <p class="mb-1"><strong>Username:</strong> <?= h($user['username']) ?></p>
    <p class="mb-0"><strong>Signed up:</strong> <?= h(date('M j, Y', strtotime($user['created_at']))) ?> via <?= h(ucfirst($user['signed_up_via'])) ?></p>
</div></div>

<div class="card"><div class="card-body">
    <h5 class="mb-3">Payment History</h5>
    <?php if (!$transactions): ?>
        <p class="text-muted mb-0">No payments on file yet.</p>
    <?php else: ?>
        <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>Date</th><th>Reference</th><th>Amount</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($transactions as $t): ?>
                <tr>
                    <td><?= h(date('M j, Y', strtotime($t['created_at']))) ?></td>
                    <td class="small text-muted"><?= h($t['reference']) ?></td>
                    <td><?= h(format_zar($t['amount_cents'] / 100)) ?></td>
                    <td>
                        <span class="badge <?= $t['status'] === 'success' ? 'bg-success' : ($t['status'] === 'pending' ? 'bg-warning text-dark' : 'bg-danger') ?>">
                            <?= h(ucfirst($t['status'])) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div></div>

<?php if ($companyId): ?>
<div class="card mt-4"><div class="card-body">
    <h5 class="mb-2">Team Invoice</h5>
    <p class="text-muted mb-3">Download a PDF billing statement covering every member on your team — job titles, subscription status, and the total monthly amount.</p>
    <a href="<?= h(base_url('team_invoice.php?download=1')) ?>" class="btn btn-outline-primary btn-sm">Download Team Invoice (PDF)</a>
</div></div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

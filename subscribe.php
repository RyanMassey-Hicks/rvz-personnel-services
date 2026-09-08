<?php
require __DIR__ . '/includes/bootstrap.php';
require_recruiter();

$user = current_user();

if (is_privileged_recruiter($user)) {
    redirect('/dashboard.php');
}

$companyId = current_recruiter_company_id();
$companySub = $companyId ? get_company_subscription($companyId) : null;
$seatsUsed = $companyId ? company_seats_used($companyId) : 1;
$currentSeats = (int) ($companySub['seat_quantity'] ?? 0);
$minSeats = max(1, $seatsUsed);
$defaultSeats = max($minSeats, $currentSeats ?: $minSeats);
$isChangingSeats = $companySub && $companySub['status'] === 'active';

$pageTitle = $isChangingSeats ? 'Manage Team Seats' : 'Activate Recruiter Access';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h2 class="mb-3"><?= $isChangingSeats ? 'Manage team seats' : 'Activate recruiter access' ?></h2>

            <?php if ($isChangingSeats): ?>
                <div class="alert alert-info">
                    You're currently billed for <strong><?= (int) $currentSeats ?></strong> seat<?= $currentSeats === 1 ? '' : 's' ?>
                    (<?= (int) $seatsUsed ?> in use). Change the quantity below to add or free up seats — you'll be
                    redirected to Paystack to confirm the new amount, and your previous plan is cancelled automatically
                    once the new one is active.
                </div>
            <?php else: ?>
                <p class="text-muted">
                    Post unlimited jobs, manage your applicant pipeline, and share to LinkedIn/Facebook —
                    billed monthly via Paystack. Invite teammates onto the same account for one combined bill instead
                    of billing each person separately.
                </p>
            <?php endif; ?>

            <form method="post" action="<?= h(base_url('paystack/initialize.php')) ?>">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Number of seats <span class="text-muted small">(you + any team members you'll invite)</span></label>
                    <input type="number" name="seats" id="rvzSeats" class="form-control" min="<?= $minSeats ?>" step="1"
                           value="<?= $defaultSeats ?>" required>
                    <?php if ($seatsUsed > 1): ?>
                        <div class="form-text small">Minimum <?= $minSeats ?> — you already have <?= $seatsUsed ?> team member(s)/pending invite(s) using seats.</div>
                    <?php endif; ?>
                </div>
                <div class="display-6 mb-3">
                    <span id="rvzSeatsTotal"><?= h(format_zar($defaultSeats * RECRUITER_MONTHLY_PRICE_ZAR)) ?></span>
                    <span class="fs-6 text-muted">/ month total</span>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <?= $isChangingSeats ? 'Update Seats with Paystack' : 'Subscribe with Paystack' ?>
                </button>
            </form>
            <p class="small text-muted mt-3 mb-0">
                You'll be redirected to Paystack's secure checkout. Cards are stored by Paystack, not by us.
            </p>
        </div>
    </div>
  </div>
</div>

<script>
(function () {
    var seatsInput = document.getElementById('rvzSeats');
    var totalEl = document.getElementById('rvzSeatsTotal');
    var rate = <?= (int) RECRUITER_MONTHLY_PRICE_ZAR ?>;
    seatsInput.addEventListener('input', function () {
        var n = Math.max(1, parseInt(seatsInput.value, 10) || 1);
        totalEl.textContent = 'R' + (n * rate).toLocaleString('en-ZA', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>

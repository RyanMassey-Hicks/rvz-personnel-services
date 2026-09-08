<?php
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$isRecruiter = $user && $user['role'] === 'recruiter';
$privileged = $isRecruiter && has_free_recruiter_access($user);

$stmt = db()->query('SELECT * FROM recruiter_packages WHERE active = 1 ORDER BY sort_order ASC');
$packages = $stmt->fetchAll();

$creditsRemaining = $isRecruiter ? recruiter_total_credits_remaining((int) $user['id']) : 0;

$pageTitle = 'Pricing — ' . SITE_NAME;
$pageDescription = 'Once-off job-listing packages from ' . SITE_NAME . ' — no monthly subscription, no demo account, just pay for what you post.';
require __DIR__ . '/includes/header.php';
?>
<div class="text-center mb-5">
    <h1 class="mb-2">Simple, once-off pricing</h1>
    <p class="text-muted">Buy a package of job listings — no subscription, no recurring charges.</p>
</div>

<?php if ($privileged): ?>
    <div class="alert alert-success text-center">Free recruiter access is active on this account — no purchase needed.</div>
<?php elseif ($isRecruiter): ?>
    <div class="alert alert-light border text-center">
        You currently have <strong><?= $creditsRemaining ?></strong> job-listing credit<?= $creditsRemaining === 1 ? '' : 's' ?> remaining.
        <?php $accessUntil = recruiter_account_access_expires_at((int) $user['id']); ?>
        <?php if ($accessUntil): ?> Account access active until <strong><?= h(date('M j, Y', strtotime($accessUntil))) ?></strong>.<?php endif; ?>
    </div>
<?php endif; ?>

<div class="row justify-content-center g-4 mb-5">
    <?php foreach ($packages as $package): ?>
        <div class="col-md-4">
            <div class="card h-100 shadow-sm <?= $package['code'] === 'standard' ? 'border-primary shadow' : '' ?>">
                <div class="card-body p-4 d-flex flex-column">
                    <h4 class="mb-1"><?= h($package['name']) ?></h4>
                    <p class="text-muted mb-3"><?= (int) $package['listing_credits'] ?> listing<?= (int) $package['listing_credits'] === 1 ? '' : 's' ?></p>
                    <div class="display-6 mb-1"><?= h(format_zar($package['price_cents'] / 100)) ?></div>
                    <p class="text-muted small mb-3">once-off &middot; <?= (int) $package['access_days'] ?>-day account access</p>
                    <ul class="mb-4 flex-grow-1">
                        <li>Listing duration: 30 days</li>
                        <li>Email alerts to relevant candidates</li>
                        <li>Dedicated contact person</li>
                        <li>User logon</li>
                        <li>30-day extension (optional)</li>
                        <li>Company logo (optional)</li>
                    </ul>
                    <?php if (!$user): ?>
                        <a href="<?= h(base_url('signup.php')) ?>" class="btn btn-primary w-100">Sign Up to Buy</a>
                    <?php elseif (!$isRecruiter): ?>
                        <a href="<?= h(base_url('become_recruiter.php')) ?>" class="btn btn-primary w-100">Start Hiring</a>
                    <?php elseif ($privileged): ?>
                        <button class="btn btn-outline-secondary w-100" disabled>Included Free</button>
                    <?php else: ?>
                        <form method="post" action="<?= h(base_url('paystack/initialize_package.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="package" value="<?= h($package['code']) ?>">
                            <button type="submit" class="btn btn-primary w-100">Buy <?= h($package['name']) ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card mb-5"><div class="card-body p-4">
    <h4 class="mb-3">Additional Online Features</h4>
    <div class="row g-3">
        <div class="col-md-6">
            <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                <span>Extend your listing for an additional 30 days</span>
                <strong><?= h(format_zar(2000)) ?> <span class="text-muted fw-normal small">per listing</span></strong>
            </div>
            <p class="text-muted small">Available from your <a href="<?= h(base_url('dashboard.php')) ?>">dashboard</a> on any active listing.</p>
        </div>
        <div class="col-md-6">
            <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                <span>Add your company presentation to your profile</span>
                <strong><?= h(format_zar(3200)) ?></strong>
            </div>
            <?php if ($isRecruiter): ?>
                <form method="post" action="<?= h(base_url('paystack/initialize_addon.php')) ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="addon_type" value="company_presentation">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Add to My Profile</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <p class="text-muted small mt-3 mb-0">Additional features can be added during the online ordering process.</p>
</div></div>

<div id="looking-for-more" class="card bg-light border-0"><div class="card-body p-4">
    <h4 class="mb-1">Looking for More?</h4>
    <p class="text-muted mb-3">These services are arranged directly with our team rather than sold as a self-serve add-on.</p>
    <div class="row">
        <div class="col-md-6">
            <ul class="mb-3 mb-md-0">
                <li>Dedicated Account Manager</li>
                <li>Online responses</li>
                <li>Regret or email applicants directly</li>
                <li>Link questionnaires for shortlisting</li>
            </ul>
        </div>
        <div class="col-md-6">
            <ul class="mb-3 mb-md-0">
                <li>Company Profile page</li>
                <li>Free training</li>
                <li>Response handling</li>
                <li>Set up candidate alerts</li>
                <li>Direct candidate search</li>
                <li>Set up and manage talent pools</li>
            </ul>
        </div>
    </div>
    <a href="<?= h(base_url('contact-us.php')) ?>" class="btn btn-primary">Talk to Us</a>
</div></div>

<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$isRecruiter = $user && $user['role'] === 'recruiter';
$isPaid = $isRecruiter && has_active_recruiter_subscription($user);

$pageTitle = 'Pricing — ' . SITE_NAME;
$pageDescription = 'Simple recruiter pricing: start free with ' . FREE_TIER_JOB_LIMIT . ' job posts a month, or go unlimited for '
    . format_zar(RECRUITER_MONTHLY_PRICE_ZAR) . '/user/month.';
require __DIR__ . '/includes/header.php';
?>
<div class="text-center mb-5">
    <h1 class="mb-2">Simple, per-user pricing</h1>
    <p class="text-muted">Start free. Upgrade any time for unlimited postings and premium tools.</p>
</div>

<div class="row justify-content-center g-4">
    <div class="col-md-5">
        <div class="card h-100 shadow-sm">
            <div class="card-body p-4">
                <h4 class="mb-1">Free</h4>
                <p class="text-muted mb-3">Get started at no cost</p>
                <div class="display-6 mb-3">R0 <span class="fs-6 text-muted">/ month</span></div>
                <ul class="mb-4">
                    <li><?= FREE_TIER_JOB_LIMIT ?> job posts per month, per user</li>
                    <li>Full applicant pipeline &amp; dashboard</li>
                    <li>Team invites</li>
                    <li class="text-muted">No AI ad generation</li>
                    <li class="text-muted">No website/career page embed</li>
                    <li class="text-muted">No Direct Search</li>
                </ul>
                <?php if (!$user): ?>
                    <a href="<?= h(base_url('signup.php')) ?>" class="btn btn-outline-primary w-100">Sign Up Free</a>
                <?php elseif (!$isRecruiter): ?>
                    <a href="<?= h(base_url('become_recruiter.php')) ?>" class="btn btn-outline-primary w-100">Start Hiring Free</a>
                <?php elseif (!$isPaid): ?>
                    <button class="btn btn-outline-secondary w-100" disabled>Your Current Plan</button>
                <?php else: ?>
                    <button class="btn btn-outline-secondary w-100" disabled>Included in Paid</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card h-100 shadow border-primary">
            <div class="card-body p-4">
                <h4 class="mb-1">Paid</h4>
                <p class="text-muted mb-3">Unlimited hiring power</p>
                <div class="display-6 mb-3"><?= h(format_zar(RECRUITER_MONTHLY_PRICE_ZAR)) ?> <span class="fs-6 text-muted">/ user / month</span></div>
                <ul class="mb-4">
                    <li>Unlimited job posts</li>
                    <li>Full applicant pipeline &amp; dashboard</li>
                    <li>AI-generated social ads for every job</li>
                    <li>Embed your jobs on your own website</li>
                    <li>Direct Search &amp; talent pool</li>
                    <li>One combined bill for your whole team</li>
                </ul>
                <?php if (!$user): ?>
                    <a href="<?= h(base_url('signup.php')) ?>" class="btn btn-primary w-100">Get Started</a>
                <?php elseif (!$isRecruiter): ?>
                    <a href="<?= h(base_url('become_recruiter.php')) ?>" class="btn btn-primary w-100">Start Hiring</a>
                <?php elseif ($isPaid): ?>
                    <a href="<?= h(base_url('account_billing.php')) ?>" class="btn btn-primary w-100">Manage Billing</a>
                <?php else: ?>
                    <a href="<?= h(base_url('subscribe.php')) ?>" class="btn btn-primary w-100">Upgrade Now</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<p class="text-center text-muted small mt-5">
    Building a team? Paid seats are billed as one combined monthly amount to the account holder —
    invite as many people as you like from <strong>Settings &rarr; Teams</strong>; anyone beyond your paid seats
    simply stays on the Free plan until you add more seats.
</p>

<?php require __DIR__ . '/includes/footer.php'; ?>

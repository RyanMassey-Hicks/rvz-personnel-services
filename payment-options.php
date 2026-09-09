<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Payment Options — ' . SITE_NAME;
$pageDescription = 'How payment works on RVZ Personnel Services — accepted methods, currency, security, and where to find pricing.';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-9">
<h1 class="mb-1">Payment Options</h1>
<p class="text-muted mb-4">Last updated: <?= date('F Y') ?></p>

<p>Browsing and applying for jobs on RVZ is always free for candidates — no payment method is ever needed to use
that side of the Platform. This page covers how payment works for recruiters buying job-listing packages or
add-ons. See <a href="<?= h(base_url('pricing.php')) ?>">Pricing</a> for what things cost.</p>

<h5 class="mt-4">1. Accepted payment methods</h5>
<p>All payments are processed securely by <strong>Paystack</strong>, which accepts:</p>
<ul>
    <li>Visa and Mastercard debit or credit cards</li>
    <li>Instant EFT from major South African banks</li>
</ul>
<p>We never see or store your card details — they're entered directly on Paystack's own secure checkout page.</p>

<h5 class="mt-4">2. Currency</h5>
<p>All prices on this Platform are in South African Rand (ZAR).</p>

<h5 class="mt-4">3. Once-off, not recurring</h5>
<p>Job-listing packages and add-ons are once-off purchases — you're charged once, for exactly what you selected,
with nothing billed automatically afterwards. There's no subscription to cancel.</p>

<h5 class="mt-4">4. Receipts and payment history</h5>
<p>Every payment appears in your <a href="<?= h(base_url('account_billing.php')) ?>">Account &amp; Billing</a>
page, along with the reference number, amount, and status — useful for your own records or expense claims.</p>

<h5 class="mt-4">5. Security</h5>
<p>Paystack is a licensed payment service provider, PCI-DSS compliant, and used by businesses across Africa. Our
own server never handles or stores raw card numbers.</p>

<h5 class="mt-4">6. Payment problems</h5>
<p>If a payment fails, you can simply try again from <a href="<?= h(base_url('pricing.php')) ?>">Pricing</a>. If
you were charged but didn't receive what you paid for, see our
<a href="<?= h(base_url('shipping-delivery-policy.php')) ?>">Shipping &amp; Delivery Policy</a> or
<a href="<?= h(base_url('contact-us.php')) ?>">contact us</a> directly with your payment reference.</p>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>

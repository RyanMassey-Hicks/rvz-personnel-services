<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Privacy Policy — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-9">
<h1 class="mb-1">Privacy Policy</h1>
<p class="text-muted mb-4">Last updated: <?= date('F Y') ?></p>

<p><?= h(COMPANY_LEGAL_NAME) ?> ("RVZ", "we", "us") is committed to protecting your personal information in
accordance with the Protection of Personal Information Act 4 of 2013 ("POPIA"). This Privacy Policy explains
what personal information we collect through this website and recruitment platform, why we collect it, and
how we use, store, and protect it.</p>

<h5 class="mt-4">1. Information we collect</h5>
<p>Depending on how you use the Platform, we may collect: your name, contact details, ID/passport number,
date of birth, employment history, education, skills, resume/CV, salary expectations, and (only where you
explicitly consent) Employment Equity information such as race and disability status. Recruiter accounts also
provide company details, job postings, and billing information processed by our payment provider, Paystack.</p>

<h5 class="mt-4">2. Why we process your information</h5>
<p>We process your personal information to: operate your account and candidate/recruiter profile; match
candidates to job opportunities; allow recruiters to review applications; send application-status and job-alert
notifications you've opted into; process recruiter subscription payments; and comply with our legal obligations.</p>

<h5 class="mt-4">3. Legal basis for processing</h5>
<p>We process your information based on your consent (given when you create an account, apply for a job, or
opt in to notifications), the necessity of processing to perform our contract with you, and our legitimate
interests in operating a functioning recruitment platform.</p>

<h5 class="mt-4">4. Sharing your information</h5>
<p>Your candidate profile and application details are shared only with the recruiter(s) whose jobs you apply
to, or whose Direct Search results include your profile. We do not sell your personal information. We use
third-party service providers (payment processing via Paystack, AI-assisted ad generation — via a free default
provider or, where a company has added its own API key, that company's chosen AI provider such as Google Gemini
or OpenAI — for recruiter-initiated ad creation, the on-site support chatbot which sends your typed message to
Google Gemini to generate a reply, Google Analytics for usage statistics, Google Ads for conversion tracking and
remarketing, and email delivery) strictly to operate the Platform. Google Analytics, Google Ads, and the AI
support chatbot are opt-in only, controlled via the "Cookie Preferences" link in the footer — see our
<a href="<?= h(base_url('cookie-policy.php')) ?>">Cookie Policy</a> for details, and our
<a href="<?= h(base_url('ai-policy.php')) ?>">AI Policy</a> for how AI features are used and what they never decide
on their own.</p>

<h5 class="mt-4">5. Special personal information</h5>
<p>Employment Equity fields (race/EE status, disability status) are special personal information under POPIA.
We only collect and process this information where you have given explicit, separate consent on your profile,
and only for Employment Equity reporting purposes where an employer requires it.</p>

<h5 class="mt-4">6. Cross-border transfers</h5>
<p>Some of our service providers process data outside South Africa — for example, Paystack's payment
infrastructure, Google (Gemini, Analytics, Ads), and OpenAI where a company configures it. Where personal
information is transferred across South Africa's borders, we only do so consistent with section 72 of POPIA:
the provider is bound by terms that require a comparable standard of protection to POPIA, or you have consented
to the transfer, or the transfer is necessary to perform our contract with you (for example, processing a
payment).</p>

<h5 class="mt-4">7. Data retention</h5>
<p>We retain your personal information for as long as your account is active, and for a reasonable period
afterwards to comply with legal, tax, and record-keeping obligations. You may request deletion of your account
and associated data at any time via <a href="<?= h(base_url('contact-us.php')) ?>">Contact Us</a>.</p>

<h5 class="mt-4">8. Your rights</h5>
<p>Under POPIA, you have the right to access, correct, or request deletion of your personal information, to
object to processing, and to withdraw consent at any time (for example, by unsubscribing from job alerts or
the newsletter). To exercise these rights, contact us using the details on our
<a href="<?= h(base_url('contact-us.php')) ?>">Contact Us</a> page.</p>

<h5 class="mt-4">9. Cookies</h5>
<p>See our <a href="<?= h(base_url('cookie-policy.php')) ?>">Cookie Policy</a> for details on how we use cookies.</p>

<h5 class="mt-4">10. Security</h5>
<p>We use reasonable technical and organisational measures — including password hashing, encrypted connections,
and access controls — to protect your personal information against loss, unauthorised access, and disclosure.</p>

<h5 class="mt-4">11. Contact / Information Officer</h5>
<p>Questions about this policy or your personal information can be directed to our Information Officer via
<a href="mailto:<?= h(PRIVILEGED_RECRUITER_EMAIL) ?>"><?= h(PRIVILEGED_RECRUITER_EMAIL) ?></a> or through our
<a href="<?= h(base_url('contact-us.php')) ?>">Contact Us</a> page. You also have the right to lodge a complaint
with the Information Regulator of South Africa (inforeg.co.za).</p>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>

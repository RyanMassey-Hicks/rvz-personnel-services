<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Disclaimer — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-9">
<h1 class="mb-1">Disclaimer</h1>
<p class="text-muted mb-4">Last updated: <?= date('F Y') ?></p>

<p>The information and services provided by <?= h(COMPANY_LEGAL_NAME) ?> ("RVZ") on this Platform are provided
on an "as is" and "as available" basis.</p>

<h5 class="mt-4">1. No guarantee of employment</h5>
<p>RVZ provides a platform connecting job seekers and employers. We do not guarantee that any candidate will
be hired, or that any recruiter will successfully fill a vacancy. RVZ is not a party to, and accepts no
liability arising from, any employment relationship, contract, or dispute between a candidate and a recruiter.</p>

<h5 class="mt-4">2. Job posting accuracy</h5>
<p>Job postings are created by recruiters and are not independently verified by RVZ. RVZ makes no
representations as to the accuracy, legality, or completeness of any job posting.</p>

<h5 class="mt-4">3. AI-generated content</h5>
<p>Some recruiter tools (such as the "Ads" feature) use AI image generation to create social media graphics based on
job posting content, using a free default provider or, where a company has configured its own API key, that
company's chosen AI provider (e.g. Google Gemini or OpenAI). RVZ does not guarantee the accuracy, appropriateness,
or fitness for purpose of AI-generated content, and recruiters are responsible for reviewing generated content
before publishing it. The on-site support chatbot also uses AI (Google Gemini, with an automatic fallback to a
built-in rule-based assistant) to generate its replies — its answers are provided for convenience only and are
not a substitute for official Platform terms or advice from RVZ directly.</p>

<h5 class="mt-4">4. Third-party services</h5>
<p>The Platform integrates third-party services including Paystack (payments), one or more AI image-generation
providers (AI ad generation — the default provider or, where configured, a company's own account with Google
Gemini or OpenAI), Google Analytics and Google Ads (both opt-in only, via Cookie Preferences), and
Google/LinkedIn/Facebook (sign-in). RVZ is not responsible for the availability, accuracy, or practices of these
third-party services, which are governed by their own terms and privacy policies.</p>

<h5 class="mt-4">5. Legal templates</h5>
<p>Legal pages on this Platform (including this Disclaimer, our Privacy Policy, Terms and Conditions, and
related policies) are provided as general information and do not constitute legal advice. RVZ recommends
seeking independent legal advice for matters specific to your circumstances.</p>

<h5 class="mt-4">6. Limitation of liability</h5>
<p>To the maximum extent permitted by law, RVZ shall not be liable for any direct, indirect, incidental, or
consequential loss or damage arising from your use of, or inability to use, the Platform.</p>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>

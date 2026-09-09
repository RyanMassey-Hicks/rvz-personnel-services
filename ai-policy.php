<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'AI Policy — ' . SITE_NAME;
$pageDescription = 'How RVZ Personnel Services uses artificial intelligence — what it\'s used for, what it never decides on its own, and how to opt out.';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-9">
<h1 class="mb-1">AI Policy</h1>
<p class="text-muted mb-4">Last updated: <?= date('F Y') ?></p>

<p>This policy explains where and how <?= h(COMPANY_LEGAL_NAME) ?> ("RVZ") uses artificial intelligence (AI) on
this Platform, what data it involves, and — most importantly — what AI is <strong>not</strong> allowed to decide
on its own.</p>

<h5 class="mt-4">1. Where AI is used on this Platform</h5>
<ul>
    <li><strong>Support chatbot</strong> — answers common questions using a built-in rule-based assistant by
    default. If you opt in via "AI Assistance" in Cookie Preferences, your typed messages are instead sent to
    Google Gemini to generate a more natural reply.</li>
    <li><strong>AI ad generation</strong> — a recruiter-initiated tool (<code>ads.php</code>) that creates a social
    media graphic for a job posting, using a free default image provider or, where a company has configured its
    own API key, that company's chosen provider (Google Gemini or OpenAI).</li>
</ul>
<p>AI is <strong>not</strong> currently used anywhere in this Platform to screen, score, shortlist, or reject
candidates automatically. Response Handling shortlisting is performed by RVZ's own recruitment team, not an
algorithm.</p>

<h5 class="mt-4">2. No solely-automated decisions with a legal or similarly significant effect</h5>
<p>Consistent with section 71 of the Protection of Personal Information Act 4 of 2013 (POPIA), RVZ does not
subject any candidate to a decision based solely on automated processing (including AI) that would affect their
legal rights or have a similarly significant effect on them — such as an automatic, unreviewed rejection from a
job. Every hiring decision is made by the recruiter (or, for Response Handling clients, by RVZ's human team), not
by software.</p>

<h5 class="mt-4">3. What data reaches an AI provider, and when</h5>
<p>Data only reaches a third-party AI provider (Google Gemini, OpenAI, or the default free image provider) at the
specific moment a feature is used, and only for that feature's purpose:</p>
<ul>
    <li>Chatbot: your typed message text, only if you've opted in to "AI Assistance".</li>
    <li>AI ads: the job posting's own title/description/company branding — never candidate personal information.</li>
</ul>
<p>See our <a href="<?= h(base_url('privacy-policy.php')) ?>">Privacy Policy</a> and
<a href="<?= h(base_url('cookie-policy.php')) ?>">Cookie Policy</a> for how this fits into our broader data
handling, and how to withdraw consent at any time.</p>

<h5 class="mt-4">4. Accuracy and human review</h5>
<p>AI-generated text and images can be inaccurate or inappropriate. Recruiters are responsible for reviewing any
AI-generated ad content before publishing it, and the chatbot's answers are provided for convenience only — they
are not a substitute for this Platform's actual legal pages or a direct answer from RVZ. See our
<a href="<?= h(base_url('disclaimer.php')) ?>">Disclaimer</a> for the full liability position.</p>

<h5 class="mt-4">5. Feedback or concerns</h5>
<p>If you believe an AI-assisted feature on this Platform has produced an inaccurate, unfair, or inappropriate
result, please <a href="<?= h(base_url('contact-us.php')) ?>">contact us</a> — a person will review it.</p>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>

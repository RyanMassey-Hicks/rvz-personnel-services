<?php
/**
 * Support chatbot backend — Gemini (a free-tier text model, see
 * includes/gemini_chat.php) is the "head" AI, personalised with the
 * logged-in user's OWN real account data queried server-side. If Gemini
 * is unconfigured, unreachable, or hits a quota limit, this transparently
 * falls back to a built-in rule-based engine (keyword/topic matching
 * against a written FAQ) so a user never sees a provider billing/quota
 * error in the chat widget — support chat simply keeps working either way.
 *
 * Gemini is only ever called if the visitor has opted into "AI Assistance"
 * via the cookie/consent banner (rvz_cookie_consent cookie, {"ai":true}) —
 * see chatbot_ai_consent_given() below. Without that consent, every reply
 * comes from the rule-based engine and no message data leaves the server.
 */
require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

$sent = $_POST['csrf_token'] ?? '';
if (!$sent || !hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid security token, please reload the page.']);
    exit;
}

$userMessage = trim($_POST['message'] ?? '');
if ($userMessage === '' || mb_strlen($userMessage) > 1000) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Please enter a message (up to 1000 characters).']);
    exit;
}

/** Real, server-queried facts about the current visitor's own account — never guessed, never another user's data. */
function chatbot_account_facts(): array
{
    $user = current_user();
    if (!$user) {
        return ['logged_in' => false];
    }

    if ($user['role'] === 'recruiter') {
        $companyId = current_recruiter_company_id();
        $stmt = db()->prepare('SELECT COUNT(*) AS c FROM jobs WHERE company_id = ? AND is_open = 1');
        $stmt->execute([$companyId]);
        $activeJobs = (int) $stmt->fetch()['c'];

        $stmt = db()->prepare(
            'SELECT COUNT(*) AS c FROM applications JOIN jobs ON jobs.id = applications.job_id WHERE jobs.company_id = ?'
        );
        $stmt->execute([$companyId]);
        $applicantCount = (int) $stmt->fetch()['c'];

        $isPaid = has_active_recruiter_subscription($user);
        $creditsRemaining = $isPaid ? null : recruiter_total_credits_remaining((int) $user['id']);

        return [
            'logged_in' => true,
            'role' => 'recruiter',
            'first_name' => $user['first_name'] ?: $user['username'],
            'is_paid' => $isPaid,
            'credits_remaining' => $creditsRemaining,
            'active_jobs' => $activeJobs,
            'applicant_count' => $applicantCount,
        ];
    }

    $stmt = db()->prepare('SELECT COUNT(*) AS c FROM applications WHERE candidate_id = ?');
    $stmt->execute([$user['id']]);
    $appCount = (int) $stmt->fetch()['c'];

    $stmt = db()->prepare(
        'SELECT jobs.title, applications.stage FROM applications
         JOIN jobs ON jobs.id = applications.job_id
         WHERE applications.candidate_id = ? ORDER BY applications.applied_on DESC LIMIT 3'
    );
    $stmt->execute([$user['id']]);
    $recent = $stmt->fetchAll();

    $stmt = db()->prepare('SELECT COUNT(*) AS c FROM saved_jobs WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $savedCount = (int) $stmt->fetch()['c'];

    return [
        'logged_in' => true,
        'role' => 'candidate',
        'first_name' => $user['first_name'] ?: $user['username'],
        'app_count' => $appCount,
        'recent_apps' => $recent,
        'saved_count' => $savedCount,
    ];
}

/** Matches the message against known support topics and returns a friendly, on-brand reply — never invents facts, only quotes chatbot_account_facts(). */
function chatbot_reply(string $message, array $facts): string
{
    $m = mb_strtolower($message);
    $greetName = ($facts['logged_in'] ?? false) ? ', ' . $facts['first_name'] : '';

    if (preg_match('/^\s*(hi|hello|hey|howzit|good (morning|afternoon|evening))\b/i', $m)) {
        return "Hi there{$greetName}! I'm the RVZ support assistant. Ask me about applying for jobs, your applications, recruiter billing, or anything else about the platform.";
    }
    if (preg_match('/\b(thanks|thank you|cheers|awesome|great)\b/i', $m)) {
        return "You're welcome! Anything else I can help with?";
    }

    if (preg_match('/\b(my applications?|application status|where.*my application|track.*application)\b/i', $m)) {
        if (($facts['role'] ?? '') === 'candidate') {
            $count = $facts['app_count'];
            if ($count === 0) {
                return "You haven't applied to any jobs yet. Browse open positions and click \"Apply Now\" on any listing — then track progress under \"My Applications\".";
            }
            $recentText = implode('; ', array_map(fn ($r) => $r['title'] . ' (' . ucfirst($r['stage']) . ')', $facts['recent_apps']));
            return "You have {$count} application(s) on file. Your most recent: {$recentText}. See the full list under \"My Applications\" in the menu.";
        }
        return 'Application tracking is for candidate accounts — sign in as a candidate to see "My Applications", or ask me about managing applicants in your recruiter Pipeline instead.';
    }

    if (preg_match('/\b(saved job|bookmark|watchlist)\b/i', $m)) {
        if (($facts['role'] ?? '') === 'candidate') {
            return "You currently have {$facts['saved_count']} saved job(s). Click the heart/star icon on any job card to save it, and find them all under \"Saved Jobs\".";
        }
        return 'The heart/star icon on a job card lets candidates bookmark a listing to apply later — find saved jobs under "Saved Jobs" in their account menu.';
    }

    if (preg_match('/\b(apply|application form|how.*apply)\b/i', $m)) {
        return 'Browse open positions, open the one you want, and click "Apply Now" — you\'ll need a candidate profile with at least a résumé/CV uploaded first (My Profile → Documents).';
    }

    if (preg_match('/\b(pipeline|stage|interview|shortlist|move.*candidate)\b/i', $m)) {
        return 'Recruiters manage applicants via the drag-and-drop Pipeline (Applied → Screening → Interview → Offer → Hired). Open a job\'s "Pipeline" button from your Dashboard, and drag cards between stages — you can also mark candidates Viewed/Contacted/Shortlisted there.';
    }

    if (preg_match('/\b(ads?|advertisement|social media (ad|graphic))\b/i', $m)) {
        return 'The "Ads" button on a job (Dashboard → your job → Ads) generates a branded social media ad graphic for that listing — arranged directly with our team, see Pricing → "Looking for More?".';
    }

    if (preg_match('/\b(direct search|talent pool|search candidates?|find candidates?)\b/i', $m)) {
        return 'Direct Search lets recruiters search the full candidate database by skills, location, or language, and save candidates to a Talent Pool — this is arranged directly with our team, not a self-serve package. See Pricing → "Looking for More?" or contact us.';
    }

    if (preg_match('/\b(team|invite|seat|colleague|co-?worker)\b/i', $m)) {
        return 'You can invite teammates from Settings → Teams. Each job listing is posted using a credit from your purchased package, regardless of which team member posts it.';
    }

    if (preg_match('/\b(free plan|upgrade|pricing|how much|cost|price)\b/i', $m)) {
        if (($facts['role'] ?? '') === 'recruiter') {
            if ($facts['is_paid']) {
                return "You're currently on our legacy monthly plan — unlimited job posts plus Ads, website embed, and Direct Search. Manage or cancel any time from Account & Billing.";
            }
            $credits = $facts['credits_remaining'] ?? 0;
            return "You have {$credits} job-listing credit(s) remaining. See the Pricing page for Basic/Standard/Premium once-off packages — no subscription required.";
        }
        return 'Recruiters buy once-off job-listing packages (Basic/Standard/Premium) — no subscription. See the Pricing page for full details, pricing and what\'s included.';
    }

    if (preg_match('/\b(subscri\w*|billing|cancel\w*|invoice|payment|charge|refund|downgrade)\b/i', $m)) {
        if (($facts['role'] ?? '') === 'recruiter') {
            $status = $facts['is_paid'] ? 'active on our legacy monthly plan' : (($facts['credits_remaining'] ?? 0) . ' job-listing credit(s) remaining');
            return "Your account currently has: {$status}. You can view payment history or buy more packages any time from Account & Billing / Pricing — packages are once-off, there's nothing to cancel.";
        }
        return 'Billing only applies to recruiter accounts — see Account & Billing (in the account menu) once you\'re signed in as a recruiter for package and payment details.';
    }

    if (preg_match('/\b(sla|service level agreement|sign.*agreement)\b/i', $m)) {
        return 'Recruiters sign a Service Level Agreement (SLA) once, right after setting up their workspace — a PDF copy is emailed to you and available any time from your Recruiter Profile.';
    }

    if (preg_match('/\b(privacy|terms and conditions|cookie|paia|legal|disclaimer)\b/i', $m)) {
        return 'You\'ll find our Privacy Policy, Terms and Conditions, Cookie Policy, PAIA Manual, and other legal pages linked in the footer at the bottom of every page.';
    }

    if (preg_match('/\b(i.?m hiring|become a recruiter|post a job|recruiter account)\b/i', $m)) {
        return 'Click "For Employers" or "I\'m hiring" in the menu, set up your company workspace, then buy a job-listing package from the Pricing page to start posting — once-off, no subscription.';
    }

    return "I'm not 100% sure about that one from what I've got on file. Try rephrasing, or click \"Escalate to Support\" below and our team will get back to you directly.";
}

/** True only if the visitor has explicitly opted into "AI Assistance" via the cookie/consent banner. */
function chatbot_ai_consent_given(): bool
{
    $raw = $_COOKIE['rvz_cookie_consent'] ?? '';
    if ($raw === '') {
        return false;
    }
    $data = json_decode($raw, true);
    return is_array($data) && !empty($data['ai']);
}

$facts = chatbot_account_facts();

if (chatbot_ai_consent_given()) {
    try {
        $reply = gemini_chat_reply($userMessage, $facts);
    } catch (GeminiChatException $e) {
        error_log('Gemini chat fallback: ' . $e->getMessage());
        $reply = chatbot_reply($userMessage, $facts);
    }
} else {
    // No AI consent yet (or declined) — never call Gemini at all, so no
    // message data leaves the server until the visitor opts in.
    $reply = chatbot_reply($userMessage, $facts);
}

echo json_encode(['ok' => true, 'reply' => $reply]);

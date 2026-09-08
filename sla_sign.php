<?php
require __DIR__ . '/includes/bootstrap.php';
require_recruiter();

$user = current_user();

$stmt = db()->prepare('SELECT * FROM recruiter_slas WHERE user_id = ? ORDER BY signed_at DESC LIMIT 1');
$stmt->execute([$user['id']]);
$latestSigned = $stmt->fetch();
$isCurrent = $latestSigned && (int) $latestSigned['sla_version'] >= CURRENT_SLA_VERSION;
$existing = $isCurrent ? $latestSigned : null; // only a CURRENT signature counts as "already signed" for this page

$stmt = db()->prepare(
    'SELECT recruiter_profiles.*, companies.name AS company_name FROM recruiter_profiles
     LEFT JOIN companies ON companies.id = recruiter_profiles.company_id
     WHERE recruiter_profiles.user_id = ?'
);
$stmt->execute([$user['id']]);
$recruiterProfile = $stmt->fetch();
$companyName = $recruiterProfile['company_name'] ?? 'the company on file';

/**
 * The full SLA text, shared between the on-screen preview and the generated
 * PDF so a signer is never shown a shorter version than what they sign.
 * Returns [ 'Section Title' => [paragraph, paragraph, ...] ].
 *
 * This is a thorough South African-context template covering the platform's
 * services, POPIA/PAIA data handling, the Labour Relations Act's Temporary
 * Employment Services provisions, warranty/liability limitation, and
 * indemnification — but it remains a template: RVZ should have it reviewed
 * by a qualified attorney before relying on it in a real dispute, and no
 * agreement can make any party immune from all liability under South
 * African law regardless of how it is drafted.
 */
function sla_document_sections(string $companyName): array
{
    $price = format_zar(RECRUITER_MONTHLY_PRICE_ZAR);
    return [
        '1. Parties and Definitions' => [
            'This Service Level Agreement ("Agreement") is entered into between ' . COMPANY_LEGAL_NAME . ', '
                . 'registration number ' . COMPANY_REG_NUMBER . ' ("RVZ", "we", "us", "our"), and ' . $companyName
                . ' together with its authorised users on the RVZ Personnel Services platform ("the Recruiter", '
                . '"you", "your").',
            '"Platform" means the RVZ Personnel Services & Labour Hiring Specialists website and application, '
                . 'including all features such as job posting, the Applicant Tracking System ("ATS") pipeline, '
                . 'Direct Search, Response Handling, AI-assisted ad generation, career sites, and embeddable widgets. '
                . '"Candidate" means any individual who creates an account, applies for a role, or is otherwise '
                . 'represented in the Platform\'s candidate database. "Personal Information" and "Processing" have '
                . 'the meanings given in the Protection of Personal Information Act 4 of 2013 ("POPIA").',
        ],
        '2. Nature of Services' => [
            'RVZ provides access to the Platform as a technology and recruitment-support service. Subject to an '
                . 'active subscription, the Recruiter may post job vacancies, manage applications through the ATS '
                . 'pipeline, search the candidate database directly, request RVZ\'s Response Handling service, '
                . 'generate AI-assisted advertising content, and maintain a branded career page.',
            'RVZ does not itself employ, place, or guarantee the suitability of any Candidate, and does not act as '
                . 'the Recruiter\'s agent for the purpose of making hiring decisions. All hiring, interviewing, '
                . 'vetting, remuneration, and employment decisions remain the sole responsibility of the Recruiter.',
        ],
        '3. No Guarantee of Outcomes' => [
            'RVZ makes no representation or warranty, express or implied, that the Platform will result in a '
                . 'successful placement, that any Candidate\'s information is accurate or complete, or that the '
                . 'Platform will be free of errors, uninterrupted, or available at all times. The Recruiter accepts '
                . 'that recruitment outcomes depend on factors outside RVZ\'s control.',
        ],
        '4. Temporary Employment Services / Labour Broking Notice' => [
            'Where the Recruiter engages RVZ, or represents itself as engaging RVZ, to provide workers to perform '
                . 'work for the Recruiter in circumstances that meet the definition of a "temporary employment '
                . 'service" under section 198 of the Labour Relations Act 66 of 1995 ("LRA"), a separate, specific '
                . 'Temporary Employment Services Agreement recording the parties\' respective statutory '
                . 'obligations (including under section 198A) must be concluded in writing before any such '
                . 'placement begins. This Agreement, on its own, does not constitute such a Temporary Employment '
                . 'Services Agreement and does not make RVZ the employer of any Candidate placed via the Platform '
                . 'unless expressly agreed in writing.',
            'The Recruiter warrants that it will comply with all applicable South African labour legislation in '
                . 'respect of any person it hires or places, including the LRA, the Basic Conditions of Employment '
                . 'Act 75 of 1997, the Employment Equity Act 55 of 1998, the National Minimum Wage Act 9 of 2018, '
                . 'and the Compensation for Occupational Injuries and Diseases Act 130 of 1993.',
        ],
        '5. Recruiter Obligations and Warranties' => [
            'The Recruiter warrants that: (a) all job postings are lawful, accurate, and not discriminatory on any '
                . 'ground prohibited by the Employment Equity Act or the Constitution of the Republic of South '
                . 'Africa; (b) it has the authority to post the vacancies it lists and to represent the company '
                . 'named on its account; (c) it will not use the Platform, including Direct Search or downloaded '
                . 'candidate data, for any purpose other than legitimate recruitment; and (d) it will treat all '
                . 'Candidate information as confidential and will not sell, rent, or otherwise disclose it to any '
                . 'third party except as reasonably necessary for the recruitment process.',
        ],
        '6. Fees and Payment' => [
            'The Platform offers a Free plan (a limited number of job posts per month, per user, with core '
                . 'pipeline features) at no cost, and a Paid plan at ' . $price . ' per seat per month via '
                . 'Paystack for unlimited job posts and additional tools including Direct Search, AI-generated '
                . 'ad graphics, and website embedding, except for any account RVZ designates in writing as having '
                . 'complimentary Paid-plan access. Where a Recruiter\'s team subscribes to the Paid plan, seats '
                . 'are billed as one combined amount to the account holder rather than individually; any team '
                . 'member beyond the seats purchased remains on the Free plan until further seats are added.',
            'A Recruiter on the Paid plan may cancel at any time, which takes effect immediately and downgrades '
                . 'the account to the Free plan with no further charges; no partial-period refund is given for the '
                . 'remainder of an already-billed month. Fees are exclusive of any applicable value-added tax '
                . 'unless stated otherwise, are billed in advance, and are otherwise non-refundable except as '
                . 'required by South African law, including the Consumer Protection Act 68 of 2008 where it '
                . 'applies. RVZ may amend its pricing on reasonable notice, effective from the Recruiter\'s next '
                . 'billing cycle.',
        ],
        '7. Data Protection and POPIA Compliance' => [
            'Both parties agree to Process Personal Information lawfully, in accordance with POPIA. In respect of '
                . 'Candidate Personal Information accessed via the Platform, RVZ acts as a Responsible Party for '
                . 'operating the Platform itself, and the Recruiter acts as an independent Responsible Party in '
                . 'respect of its own use of that data for recruitment purposes. The Recruiter shall Process '
                . 'Candidate Personal Information only for legitimate recruitment purposes connected with a role '
                . 'genuinely advertised on the Platform, shall implement reasonable security safeguards against '
                . 'loss or unauthorised access, and shall notify RVZ without undue delay if it becomes aware of any '
                . 'security compromise involving data obtained via the Platform.',
            'Special personal information (such as Employment Equity data) is only made available to the Recruiter '
                . 'where a Candidate has separately consented to its collection for that purpose, and must be used '
                . 'strictly for that purpose.',
        ],
        '8. Confidentiality' => [
            'Each party shall keep confidential all non-public information disclosed by the other in connection '
                . 'with this Agreement, and shall use it only for the purposes of this Agreement, except where '
                . 'disclosure is required by law or by a competent court or regulator.',
        ],
        '9. Intellectual Property' => [
            'The Platform, its software, design, and the RVZ name and logo are the property of RVZ or its '
                . 'licensors. The Recruiter is granted a limited, non-exclusive, non-transferable right to use the '
                . 'Platform for its own recruitment purposes for the duration of its subscription. Content the '
                . 'Recruiter uploads (job postings, its own logo, branding) remains its property, subject to the '
                . 'licence granted to RVZ under the Platform\'s Terms and Conditions to display it.',
        ],
        '10. Acceptable Use' => [
            'The Recruiter agrees to comply with the Platform\'s Terms and Conditions and Acceptable Use Policy, '
                . 'available at ' . base_url('terms-and-conditions.php') . ' and ' . base_url('acceptable-use.php')
                . ', which are incorporated into this Agreement by reference.',
        ],
        '11. Third-Party Services Disclaimer' => [
            'The Platform integrates third-party services including Paystack (payment processing), one or more AI '
                . 'image-generation providers (AI-assisted ad generation, via a free default provider or, where a '
                . 'company has added its own API key, that company\'s chosen provider), and Google, LinkedIn, and '
                . 'Facebook (sign-in). RVZ is not '
                . 'responsible for the acts, omissions, availability, or terms of any third-party service, and the '
                . 'Recruiter\'s use of such integrations is subject to that provider\'s own terms.',
        ],
        '12. AI-Generated Content Disclaimer' => [
            'Any advertisement, image, or text generated using the Platform\'s AI-assisted tools is provided "as '
                . 'is". The Recruiter is solely responsible for reviewing such content for accuracy, lawfulness, '
                . 'and appropriateness before publishing or distributing it, and RVZ accepts no liability for '
                . 'content the Recruiter chooses to use.',
        ],
        '13. Warranties Disclaimer' => [
            'Save as expressly stated in this Agreement, the Platform and all related services are provided "as '
                . 'is" and "as available", without warranty of any kind, whether express, implied, or statutory, '
                . 'including any implied warranty of merchantability, fitness for a particular purpose, or '
                . 'non-infringement, to the maximum extent permitted by South African law.',
        ],
        '14. Limitation of Liability' => [
            'To the maximum extent permitted by law, RVZ\'s total aggregate liability to the Recruiter arising out '
                . 'of or in connection with this Agreement, whether in contract, delict, or otherwise, shall not '
                . 'exceed the total subscription fees actually paid by the Recruiter to RVZ in the three (3) '
                . 'months immediately preceding the event giving rise to the claim.',
            'RVZ shall not be liable for any indirect, special, incidental, or consequential loss or damage, '
                . 'including loss of profit, loss of business, or loss of data, arising from the Recruiter\'s use '
                . 'of the Platform, any hiring or employment decision made by the Recruiter, any act or omission of '
                . 'a Candidate, or any interruption, error, or unavailability of the Platform, even where RVZ has '
                . 'been advised of the possibility of such loss. Nothing in this Agreement limits liability that '
                . 'cannot lawfully be excluded or limited under South African law, including liability for gross '
                . 'negligence or wilful misconduct.',
        ],
        '15. Indemnification' => [
            'The Recruiter indemnifies and holds RVZ, its directors, employees, and agents harmless against any '
                . 'claim, loss, liability, fine, penalty, or expense (including reasonable legal costs) arising '
                . 'from: (a) the Recruiter\'s job postings or use of the Platform; (b) any hiring, employment, '
                . 'disciplinary, or termination decision made by the Recruiter; (c) the Recruiter\'s breach of this '
                . 'Agreement, the Terms and Conditions, or any applicable law; or (d) any claim brought by a '
                . 'Candidate or third party arising from the Recruiter\'s conduct.',
        ],
        '16. Term and Termination' => [
            'This Agreement commences on the date of electronic signature and continues for as long as the '
                . 'Recruiter maintains an active subscription. Either party may terminate the subscription at any '
                . 'time; the Recruiter remains liable for fees already incurred. RVZ may suspend or terminate '
                . 'access immediately for breach of this Agreement, the Terms and Conditions, or the Acceptable '
                . 'Use Policy. Clauses which by their nature are intended to survive termination (including '
                . 'confidentiality, intellectual property, limitation of liability, and indemnification) shall '
                . 'survive.',
        ],
        '17. Force Majeure' => [
            'Neither party shall be liable for any delay or failure to perform its obligations (other than payment '
                . 'obligations) caused by circumstances beyond its reasonable control, including load shedding, '
                . 'internet or telecommunications failure, acts of government, natural disaster, or industrial '
                . 'action.',
        ],
        '18. Electronic Signature' => [
            'This Agreement is concluded and signed electronically in accordance with the Electronic '
                . 'Communications and Transactions Act 25 of 2002. By typing your full name below and confirming '
                . 'your agreement, you intend to and do sign this Agreement, and it is as binding as a handwritten '
                . 'signature. The signature block below records the signer\'s name, the company represented, the '
                . 'date and time, and the IP address from which the Agreement was signed, for evidentiary purposes.',
        ],
        '19. General' => [
            'This Agreement, together with the Terms and Conditions, Privacy Policy, and Acceptable Use Policy, '
                . 'constitutes the entire agreement between the parties regarding its subject matter. RVZ may amend '
                . 'this Agreement on reasonable notice; continued use of the Platform after such notice constitutes '
                . 'acceptance. Neither party may assign this Agreement without the other\'s written consent, except '
                . 'that RVZ may assign it in connection with a merger, acquisition, or sale of assets. If any '
                . 'provision of this Agreement is found unenforceable, the remaining provisions continue in full '
                . 'force.',
        ],
        '20. Governing Law and Disputes' => [
            'This Agreement is governed by the laws of the Republic of South Africa. The parties will first '
                . 'attempt to resolve any dispute in good faith through direct negotiation. Should this fail, '
                . 'either party may refer the dispute to the courts of South Africa having jurisdiction, without '
                . 'prejudice to any other remedy available at law.',
        ],
    ];
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing) {
    csrf_verify();
    $typedName = trim($_POST['typed_name'] ?? '');
    $agree = isset($_POST['agree']);

    if ($typedName === '') $errors[] = 'Please type your full name to sign.';
    if (!$agree) $errors[] = 'You must confirm you have read and agree to the SLA.';

    if (!$errors) {
        $signedAt = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $pdf = new SimplePdf();
        $pdf->addHeading('Service Level Agreement', 20);
        $pdf->addText(COMPANY_LEGAL_NAME . ' (Reg. ' . COMPANY_REG_NUMBER . ')', 11, true, [10, 31, 68]);
        $pdf->addText('RVZ Personnel Services & Labour Hiring Specialists — Recruiter Agreement', 10, false, [90, 90, 90]);
        $pdf->addSpacer(10);

        foreach (sla_document_sections($companyName) as $heading => $paragraphs) {
            $pdf->addSectionLabel($heading, [10, 31, 68]);
            foreach ($paragraphs as $para) {
                $pdf->addText($para, 10, false, [30, 30, 30]);
                $pdf->addSpacer(4);
            }
        }

        $pdf->addSectionLabel('Signature', [10, 31, 68]);
        $pdf->addText('Signed by: ' . $typedName, 11, true);
        $pdf->addText('On behalf of: ' . $companyName);
        $pdf->addText('Email: ' . $user['email']);
        $pdf->addText('Date/time: ' . $signedAt . ' (server time)');
        $pdf->addText('IP address: ' . $ip);
        $pdf->addText('Signed electronically in terms of the Electronic Communications and Transactions Act 25 of 2002.', 9, false, [90, 90, 90]);

        $pdf->addWatermarkToAllPages('RVZ CONFIDENTIAL');
        $pdf->addFooterToAllPages(
            SITE_NAME . '  •  ' . SITE_URL . '  •  © ' . date('Y') . ' ' . COMPANY_LEGAL_NAME . '  •  Confidential — Not for redistribution'
        );

        $filename = 'sla_' . $user['id'] . '_' . bin2hex(random_bytes(6)) . '.pdf';
        $destDir = UPLOAD_DIR . 'slas/';
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }
        file_put_contents($destDir . $filename, $pdf->output());

        $stmt = db()->prepare(
            'INSERT INTO recruiter_slas (user_id, signed_name, signed_at, ip_address, pdf_path, sla_version)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$user['id'], $typedName, $signedAt, $ip, 'slas/' . $filename, CURRENT_SLA_VERSION]);

        send_email(
            $user['email'],
            'Your signed RVZ Service Level Agreement',
            email_wrap('<p>Hi ' . h($typedName) . ',</p><p>Attached is a copy of the Service Level Agreement you just signed for your RVZ recruiter account.</p><p>You can download it again any time from your <a href="' . h(base_url('recruiter_profile.php')) . '">recruiter profile</a>.</p>'),
            $typedName,
            [['path' => $destDir . $filename, 'name' => 'RVZ-SLA.pdf', 'mime' => 'application/pdf']]
        );

        flash('success', 'SLA signed — a copy has been emailed to you and saved to your profile.');
        redirect('/dashboard.php');
    }
}

$pageTitle = 'Sign Service Level Agreement';
require __DIR__ . '/includes/header.php';
?>
<h2 class="mb-4">Sign your Service Level Agreement</h2>

<?php if ($existing): ?>
    <div class="alert alert-success">
        You signed this SLA as <strong><?= h($existing['signed_name']) ?></strong> on <?= h(date('M j, Y', strtotime($existing['signed_at']))) ?>.
        <a href="<?= h(UPLOAD_URL . $existing['pdf_path']) ?>" target="_blank">Download PDF</a>
    </div>
    <a href="<?= h(base_url('dashboard.php')) ?>" class="btn btn-primary">Go to Dashboard</a>
<?php else: ?>
    <?php if ($latestSigned): ?>
        <div class="alert alert-warning">
            We've updated our Service Level Agreement since you last signed it (as <?= h($latestSigned['signed_name']) ?>
            on <?= h(date('M j, Y', strtotime($latestSigned['signed_at']))) ?>). Please review and sign the current
            version below to continue using your recruiter account. Your previous signed copy is still available
            from your <a href="<?= h(base_url('recruiter_profile.php')) ?>">recruiter profile</a>.
        </div>
    <?php endif; ?>
    <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= h($e) ?></div><?php endforeach; ?>

    <div class="card mb-4"><div class="card-body" style="max-height:480px;overflow-y:auto;">
        <p class="text-muted small">RVZ Personnel Services &amp; Labour Hiring Specialists — Recruiter Service Level Agreement</p>
        <?php foreach (sla_document_sections($companyName) as $heading => $paragraphs): ?>
            <p class="mb-1"><strong><?= h($heading) ?></strong></p>
            <?php foreach ($paragraphs as $para): ?>
                <p class="small"><?= h($para) ?></p>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div></div>

    <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">Type your full name to sign</label>
            <input type="text" name="typed_name" class="form-control" required>
        </div>
        <div class="form-check mb-3">
            <input type="checkbox" name="agree" id="agree" class="form-check-input" required>
            <label class="form-check-label" for="agree">I have read and agree to this Service Level Agreement.</label>
        </div>
        <button type="submit" class="btn btn-primary">Sign and Continue</button>
    </form>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * Generates a downloadable PDF billing statement for the whole team/company —
 * every member's seat, job title, and monthly rate, plus a total. Built on
 * the same dependency-free SimplePdf engine as my_cv.php and sla_sign.php.
 */
require __DIR__ . '/includes/bootstrap.php';
require_recruiter_with_sla();

$user = current_user();
$companyId = current_recruiter_company_id();
if (!$companyId) {
    flash('danger', 'Set up your company first.');
    redirect('/become_recruiter.php');
}

$stmt = db()->prepare('SELECT * FROM companies WHERE id = ?');
$stmt->execute([$companyId]);
$company = $stmt->fetch();

$companySub = get_company_subscription($companyId);

$stmt = db()->prepare(
    "SELECT users.id, users.first_name, users.last_name, users.email, recruiter_profiles.job_title,
            subscriptions.status AS sub_status, subscriptions.current_period_end
     FROM recruiter_profiles
     JOIN users ON users.id = recruiter_profiles.user_id
     LEFT JOIN subscriptions ON subscriptions.user_id = users.id
     WHERE recruiter_profiles.company_id = ?
     ORDER BY users.created_at ASC"
);
$stmt->execute([$companyId]);
$members = $stmt->fetchAll();

function build_team_invoice_pdf(array $company, array $members, ?array $companySub): SimplePdf
{
    $NAVY = [10, 31, 68];
    $WHITE = [255, 255, 255];
    $SILVER = [210, 216, 226];
    $GRAY = [90, 90, 90];
    $DARK = [30, 30, 30];

    $pdf = new SimplePdf();
    $today = date('Y/m/d');
    $period = date('F Y');

    $pdf->addRect(0, 0, $pdf->pageWidth(), 100, $NAVY);
    $pdf->addTextAt(56, 42, 'Team Billing Statement', 22, true, $WHITE);
    $pdf->addTextAt(56, 62, $company['name'], 13, false, $SILVER);
    $pdf->addTextAt(56, 82, 'Billing period: ' . $period . '   |   Issued: ' . $today, 10, false, $SILVER);
    $pdf->setCursorY($pdf->pageHeight() - 100 - 24);

    $companyBilled = $companySub && $companySub['status'] === 'active';

    $pdf->addSectionLabel('Team Members', $NAVY);

    foreach ($members as $m) {
        $name = trim($m['first_name'] . ' ' . $m['last_name']) ?: 'Unnamed';
        $freeAccess = has_free_recruiter_access(['id' => $m['id'], 'email' => $m['email']]);

        if ($freeAccess) {
            $rateLabel = 'Free access';
        } elseif ($companyBilled) {
            $rateLabel = 'Included in team plan';
        } elseif ($m['sub_status'] === 'active') {
            $rateLabel = format_zar(RECRUITER_MONTHLY_PRICE_ZAR) . '/mo';
        } else {
            $rateLabel = 'No active subscription';
        }

        $pdf->addText($name . '  —  ' . ($m['job_title'] ?: 'Team member'), 11, true, $DARK);
        $pdf->addText($m['email'] . '   |   ' . $rateLabel, 9.5, false, $GRAY);
        $pdf->addSpacer(6);
    }

    $pdf->addSpacer(8);
    $pdf->addRuleUnderCursor([200, 200, 200]);
    $pdf->addSpacer(12);

    if ($companyBilled) {
        $pdf->addText(
            'Team plan: ' . (int) $companySub['seat_quantity'] . ' seat' . ((int) $companySub['seat_quantity'] === 1 ? '' : 's')
            . ' — total billed this period: ' . format_zar($companySub['amount_cents'] / 100) . '/mo',
            13, true, $NAVY
        );
    } else {
        $total = 0.0;
        foreach ($members as $m) {
            if (!has_free_recruiter_access(['id' => $m['id'], 'email' => $m['email']]) && $m['sub_status'] === 'active') {
                $total += (float) RECRUITER_MONTHLY_PRICE_ZAR;
            }
        }
        $pdf->addText('Total billed this period: ' . format_zar($total) . '/mo', 13, true, $NAVY);
    }

    $pdf->addSpacer(18);
    $pdf->addText(
        'This statement summarises current team billing status and is provided for your records. '
        . 'It is not a tax invoice. Contact info@rvzgroup.co.za for billing queries.',
        9, false, $GRAY
    );

    $pdf->addFooterToAllPages(
        SITE_NAME . '  •  ' . COMPANY_LEGAL_NAME . ' (Reg. ' . COMPANY_REG_NUMBER . ')  •  Confidential'
    );

    return $pdf;
}

if (($_GET['download'] ?? '') === '1') {
    $pdf = build_team_invoice_pdf($company, $members, $companySub);
    $bytes = $pdf->output();
    $filename = preg_replace('/[^A-Za-z0-9_-]+/', '_', $company['name']) . '_Team_Invoice_' . date('Y-m') . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($bytes));
    echo $bytes;
    exit;
}

redirect('/account_billing.php');

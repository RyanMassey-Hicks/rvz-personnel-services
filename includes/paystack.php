<?php
/**
 * Minimal Paystack API client — plain cURL, no SDK/Composer needed (same
 * approach as includes/http.php for the OAuth calls). Used for:
 *   - starting a subscription checkout (paystack/initialize.php)
 *   - verifying a transaction after the user returns (paystack/callback.php)
 *   - handling recurring-billing webhook events (paystack/webhook.php)
 *
 * Docs: https://paystack.com/docs/api/
 */

/** Low-level request helper. $endpoint is relative, e.g. '/transaction/initialize'. */
function paystack_request(string $method, string $endpoint, array $body = []): array
{
    if (!PAYSTACK_SECRET_KEY) {
        throw new RuntimeException('PAYSTACK_SECRET_KEY is not set in config/config.php.');
    }

    $ch = curl_init('https://api.paystack.co' . $endpoint);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
            'Content-Type: application/json',
        ],
    ];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode($body);
    } elseif ($method !== 'GET') {
        $options[CURLOPT_CUSTOMREQUEST] = $method;
        if ($body) {
            $options[CURLOPT_POSTFIELDS] = json_encode($body);
        }
    }

    curl_setopt_array($ch, $options);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('Paystack request failed: ' . $err);
    }

    $decoded = json_decode($raw, true);
    return ['status' => $status, 'body' => $decoded ?? []];
}

/**
 * Starts a Paystack checkout for the recruiter monthly subscription.
 * Returns the authorization_url to redirect the user to, plus the
 * reference we generated so callback.php/webhook.php can match it up.
 */
function paystack_initialize_subscription(array $user, string $callbackUrl): array
{
    $reference = 'rvz_sub_' . $user['id'] . '_' . bin2hex(random_bytes(8));
    $amountCents = (int) round(RECRUITER_MONTHLY_PRICE_ZAR * 100);

    $payload = [
        'email' => $user['email'],
        'amount' => $amountCents,
        'currency' => 'ZAR',
        'reference' => $reference,
        'callback_url' => $callbackUrl,
        'metadata' => [
            'user_id' => $user['id'],
            'purpose' => 'recruiter_subscription',
        ],
    ];
    // Attaching a plan turns this into a recurring subscription (Paystack
    // auto-charges the same card monthly going forward). If no plan has
    // been configured yet, we still take the first payment as a one-off so
    // testing/launch isn't blocked on setting up a Plan first — just note
    // that renewals then need to be repeated manually via subscribe.php
    // until PAYSTACK_PLAN_CODE is set.
    if (PAYSTACK_PLAN_CODE) {
        $payload['plan'] = PAYSTACK_PLAN_CODE;
    }

    $result = paystack_request('POST', '/transaction/initialize', $payload);

    $stmt = db()->prepare(
        'INSERT INTO payment_transactions (user_id, reference, amount_cents, status, raw_response) VALUES (?, ?, ?, "pending", ?)'
    );
    $stmt->execute([$user['id'], $reference, $amountCents, json_encode($result['body'])]);

    if (empty($result['body']['status']) || empty($result['body']['data']['authorization_url'])) {
        $message = $result['body']['message'] ?? 'Unknown error starting checkout.';
        throw new RuntimeException('Paystack initialize failed: ' . $message);
    }

    return [
        'reference' => $reference,
        'authorization_url' => $result['body']['data']['authorization_url'],
    ];
}

/** Verifies a transaction by reference after the user returns from Paystack. */
function paystack_verify_transaction(string $reference): array
{
    $result = paystack_request('GET', '/transaction/verify/' . rawurlencode($reference));
    return $result['body'];
}

/**
 * Activates (or extends) a user's recruiter subscription by one month from
 * now, and records the Paystack identifiers needed for future renewals.
 */
function activate_subscription(int $userId, array $data): void
{
    $customerCode = $data['customer']['customer_code'] ?? '';
    $authCode = $data['authorization']['authorization_code'] ?? '';
    $planCode = $data['plan'] ?? (PAYSTACK_PLAN_CODE ?: '');
    $periodEnd = date('Y-m-d H:i:s', strtotime('+1 month'));

    $stmt = db()->prepare('SELECT id FROM subscriptions WHERE user_id = ?');
    $stmt->execute([$userId]);

    if ($stmt->fetch()) {
        $stmt = db()->prepare(
            'UPDATE subscriptions SET paystack_customer_code = ?, paystack_authorization_code = ?, plan_code = ?,
             status = "active", current_period_end = ? WHERE user_id = ?'
        );
        $stmt->execute([$customerCode, $authCode, $planCode, $periodEnd, $userId]);
    } else {
        $stmt = db()->prepare(
            'INSERT INTO subscriptions (user_id, paystack_customer_code, paystack_authorization_code, plan_code, status, current_period_end)
             VALUES (?, ?, ?, ?, "active", ?)'
        );
        $stmt->execute([$userId, $customerCode, $authCode, $planCode, $periodEnd]);
    }
}

/**
 * Finds (or creates via the Paystack API) the shared monthly Plan for a given
 * seat count — one Plan per distinct seat quantity, reused by every company
 * that buys that many seats, so we're not creating a new Plan per company.
 */
function paystack_get_or_create_seat_plan(int $seats): string
{
    $stmt = db()->prepare('SELECT plan_code FROM paystack_seat_plans WHERE seats = ?');
    $stmt->execute([$seats]);
    $row = $stmt->fetch();
    if ($row) {
        return $row['plan_code'];
    }

    $amountCents = (int) round($seats * RECRUITER_MONTHLY_PRICE_ZAR * 100);
    $result = paystack_request('POST', '/plan', [
        'name' => 'RVZ Team — ' . $seats . ' seat' . ($seats === 1 ? '' : 's'),
        'amount' => $amountCents,
        'interval' => 'monthly',
        'currency' => 'ZAR',
    ]);

    $planCode = $result['body']['data']['plan_code'] ?? '';
    if (!$planCode) {
        $message = $result['body']['message'] ?? 'Unknown error creating Paystack plan.';
        throw new RuntimeException('Paystack plan creation failed: ' . $message);
    }

    db()->prepare('INSERT INTO paystack_seat_plans (seats, plan_code, amount_cents) VALUES (?, ?, ?)')
        ->execute([$seats, $planCode, $amountCents]);

    return $planCode;
}

/**
 * Starts a Paystack checkout for a company's combined team-seat subscription
 * — one recurring monthly charge covering $seats seats, billed to the head
 * account's card. Team members who fit within the purchased seat count get
 * free access; there is no individual billing.
 */
function paystack_initialize_seat_subscription(array $company, array $headUser, int $seats, string $callbackUrl): array
{
    $reference = 'rvz_seats_' . $company['id'] . '_' . bin2hex(random_bytes(8));
    $amountCents = (int) round($seats * RECRUITER_MONTHLY_PRICE_ZAR * 100);
    $planCode = paystack_get_or_create_seat_plan($seats);

    $payload = [
        'email' => $headUser['email'],
        'amount' => $amountCents,
        'currency' => 'ZAR',
        'reference' => $reference,
        'callback_url' => $callbackUrl,
        'plan' => $planCode,
        'metadata' => [
            'company_id' => $company['id'],
            'seats' => $seats,
            'purpose' => 'company_seat_subscription',
        ],
    ];

    $result = paystack_request('POST', '/transaction/initialize', $payload);

    $stmt = db()->prepare(
        'INSERT INTO payment_transactions (user_id, reference, amount_cents, status, raw_response) VALUES (?, ?, ?, "pending", ?)'
    );
    $stmt->execute([$headUser['id'], $reference, $amountCents, json_encode($result['body'])]);

    if (empty($result['body']['status']) || empty($result['body']['data']['authorization_url'])) {
        $message = $result['body']['message'] ?? 'Unknown error starting checkout.';
        throw new RuntimeException('Paystack initialize failed: ' . $message);
    }

    return [
        'reference' => $reference,
        'authorization_url' => $result['body']['data']['authorization_url'],
    ];
}

/**
 * Disables a company's currently-active Paystack subscription (looked up
 * live via the API by customer code) before switching them to a new seat
 * count/plan — prevents the old seat count from continuing to bill alongside
 * the new one. Safe to call even if there's nothing active to disable.
 */
function paystack_disable_company_subscription(array $companySub): void
{
    $customerCode = $companySub['paystack_customer_code'] ?? '';
    if (!$customerCode) {
        return;
    }
    $result = paystack_request('GET', '/subscription?customer=' . rawurlencode($customerCode));
    foreach ($result['body']['data'] ?? [] as $s) {
        if (($s['status'] ?? '') === 'active' && !empty($s['subscription_code']) && !empty($s['email_token'])) {
            paystack_request('POST', '/subscription/disable', [
                'code' => $s['subscription_code'],
                'token' => $s['email_token'],
            ]);
        }
    }
}

/**
 * Activates (or extends/upgrades) a company's combined seat subscription.
 * If the company was already on a different seat count, the old Paystack
 * subscription is disabled first so it doesn't keep billing alongside the
 * new one.
 */
function activate_company_subscription(int $companyId, int $seats, array $data): void
{
    $customerCode = $data['customer']['customer_code'] ?? '';
    $authCode = $data['authorization']['authorization_code'] ?? '';
    $planCode = $data['plan'] ?? '';
    $amountCents = (int) round($seats * RECRUITER_MONTHLY_PRICE_ZAR * 100);
    $periodEnd = date('Y-m-d H:i:s', strtotime('+1 month'));

    $existing = get_company_subscription($companyId);

    if ($existing && $existing['status'] === 'active' && $existing['plan_code'] !== '' && $existing['plan_code'] !== $planCode) {
        try {
            paystack_disable_company_subscription($existing);
        } catch (Throwable $e) {
            error_log('Failed to auto-disable previous company subscription for company ' . $companyId . ': ' . $e->getMessage());
            if (function_exists('send_email') && defined('PRIVILEGED_RECRUITER_EMAIL')) {
                send_email(
                    PRIVILEGED_RECRUITER_EMAIL,
                    'Action needed: old Paystack subscription may still be active (company #' . $companyId . ')',
                    email_wrap('<p>Switching company #' . $companyId . ' to a new seat plan failed to auto-disable its previous Paystack subscription. Please check the Paystack dashboard for customer ' . h($customerCode) . ' and disable the old subscription manually to avoid double-billing.</p>')
                );
            }
        }
    }

    if ($existing) {
        db()->prepare(
            'UPDATE company_subscriptions SET paystack_customer_code = ?, paystack_authorization_code = ?, plan_code = ?,
             seat_quantity = ?, amount_cents = ?, status = "active", current_period_end = ? WHERE company_id = ?'
        )->execute([$customerCode, $authCode, $planCode, $seats, $amountCents, $periodEnd, $companyId]);
    } else {
        db()->prepare(
            'INSERT INTO company_subscriptions (company_id, paystack_customer_code, paystack_authorization_code, plan_code, seat_quantity, amount_cents, status, current_period_end)
             VALUES (?, ?, ?, ?, ?, ?, "active", ?)'
        )->execute([$companyId, $customerCode, $authCode, $planCode, $seats, $amountCents, $periodEnd]);
    }

    db()->prepare('UPDATE companies SET seat_quantity = ? WHERE id = ?')->execute([$seats, $companyId]);
}

// --- Once-off listing packages (Basic/Standard/Premium) & add-ons ----------
// Replaces the Free/monthly-subscription model above for NEW purchases —
// see recruiter_packages/recruiter_purchases in schema.sql. The old
// subscription tables/functions above are left untouched so any recruiter
// who was already on the monthly plan keeps working exactly as before;
// nothing here cancels or migrates them automatically.

/** Starts a once-off Paystack checkout for a job-listing package. */
function paystack_initialize_package_purchase(array $package, array $user, string $callbackUrl): array
{
    $reference = 'rvz_pkg_' . $user['id'] . '_' . bin2hex(random_bytes(8));
    $amountCents = (int) $package['price_cents'];

    $payload = [
        'email' => $user['email'],
        'amount' => $amountCents,
        'currency' => 'ZAR',
        'reference' => $reference,
        'callback_url' => $callbackUrl,
        'metadata' => [
            'user_id' => $user['id'],
            'package_id' => $package['id'],
            'purpose' => 'package_purchase',
        ],
    ];
    $result = paystack_request('POST', '/transaction/initialize', $payload);

    $stmt = db()->prepare(
        'INSERT INTO payment_transactions (user_id, reference, amount_cents, status, raw_response) VALUES (?, ?, ?, "pending", ?)'
    );
    $stmt->execute([$user['id'], $reference, $amountCents, json_encode($result['body'])]);

    if (empty($result['body']['status']) || empty($result['body']['data']['authorization_url'])) {
        $message = $result['body']['message'] ?? 'Unknown error starting checkout.';
        throw new RuntimeException('Paystack initialize failed: ' . $message);
    }

    return ['reference' => $reference, 'authorization_url' => $result['body']['data']['authorization_url']];
}

/** Records a completed package purchase — grants listing credits + account access days. */
function activate_package_purchase(string $reference, int $userId, array $package): void
{
    $stmt = db()->prepare('SELECT id FROM recruiter_purchases WHERE reference = ?');
    $stmt->execute([$reference]);
    if ($stmt->fetch()) {
        return; // already activated (webhook + callback can both fire for the same reference)
    }
    $accessExpires = date('Y-m-d H:i:s', strtotime('+' . (int) $package['access_days'] . ' days'));
    db()->prepare(
        'INSERT INTO recruiter_purchases (user_id, package_id, reference, amount_cents, status, credits_total, credits_remaining, access_expires_at)
         VALUES (?, ?, ?, ?, "active", ?, ?, ?)'
    )->execute([
        $userId, $package['id'], $reference, $package['price_cents'],
        $package['listing_credits'], $package['listing_credits'], $accessExpires,
    ]);
}

/** Starts a once-off Paystack checkout for an "Additional Online Feature" add-on. */
function paystack_initialize_addon_purchase(string $addonType, int $amountCents, array $user, ?int $jobId, string $callbackUrl): array
{
    $reference = 'rvz_addon_' . $user['id'] . '_' . bin2hex(random_bytes(8));

    $payload = [
        'email' => $user['email'],
        'amount' => $amountCents,
        'currency' => 'ZAR',
        'reference' => $reference,
        'callback_url' => $callbackUrl,
        'metadata' => [
            'user_id' => $user['id'],
            'addon_type' => $addonType,
            'job_id' => $jobId,
            'purpose' => 'addon_purchase',
        ],
    ];
    $result = paystack_request('POST', '/transaction/initialize', $payload);

    $stmt = db()->prepare(
        'INSERT INTO payment_transactions (user_id, reference, amount_cents, status, raw_response) VALUES (?, ?, ?, "pending", ?)'
    );
    $stmt->execute([$user['id'], $reference, $amountCents, json_encode($result['body'])]);

    if (empty($result['body']['status']) || empty($result['body']['data']['authorization_url'])) {
        $message = $result['body']['message'] ?? 'Unknown error starting checkout.';
        throw new RuntimeException('Paystack initialize failed: ' . $message);
    }

    return ['reference' => $reference, 'authorization_url' => $result['body']['data']['authorization_url']];
}

/** Applies a completed add-on purchase — extends a listing by 30 days, or unlocks the company presentation. */
function activate_addon_purchase(string $reference, int $userId, string $addonType, ?int $jobId, int $amountCents): void
{
    $stmt = db()->prepare('SELECT id FROM addon_purchases WHERE reference = ?');
    $stmt->execute([$reference]);
    if ($stmt->fetch()) {
        return;
    }
    db()->prepare(
        'INSERT INTO addon_purchases (user_id, job_id, addon_type, reference, amount_cents, status) VALUES (?, ?, ?, ?, ?, "completed")'
    )->execute([$userId, $jobId, $addonType, $reference, $amountCents]);

    if ($addonType === 'extend_listing' && $jobId) {
        $stmt = db()->prepare('SELECT listing_expires_at FROM jobs WHERE id = ? AND posted_by = ?');
        $stmt->execute([$jobId, $userId]);
        $job = $stmt->fetch();
        if ($job) {
            $base = ($job['listing_expires_at'] && strtotime($job['listing_expires_at']) > time()) ? $job['listing_expires_at'] : date('Y-m-d H:i:s');
            $newExpiry = date('Y-m-d H:i:s', strtotime($base . ' +30 days'));
            db()->prepare('UPDATE jobs SET listing_expires_at = ? WHERE id = ?')->execute([$newExpiry, $jobId]);
        }
    } elseif ($addonType === 'company_presentation') {
        $companyId = current_recruiter_company_id_for_user($userId);
        if ($companyId) {
            db()->prepare('UPDATE companies SET presentation_purchased = 1 WHERE id = ?')->execute([$companyId]);
        }
    }
}

<?php
/**
 * Paystack webhook receiver — this is what actually keeps subscriptions
 * renewing month to month without the recruiter needing to do anything.
 *
 * Set this URL in the Paystack dashboard under Settings → API Keys & Webhooks:
 *   https://www.nhestate.co.za/paystack/webhook.php
 *
 * Paystack requires a 200 response and doesn't wait around, so this file
 * does the minimum work needed and exits fast. It does NOT use bootstrap's
 * session/CSRF machinery (there's no logged-in user here — it's a
 * server-to-server call from Paystack).
 */
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/mailer.php';
require __DIR__ . '/../includes/paystack.php';

http_response_code(200); // acknowledge immediately; Paystack retries on non-2xx

$raw = file_get_contents('php://input');

// --- Verify the request really came from Paystack ---------------------------
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
if (!PAYSTACK_SECRET_KEY || !$signature || !hash_equals(hash_hmac('sha512', $raw, PAYSTACK_SECRET_KEY), $signature)) {
    http_response_code(401);
    exit;
}

$event = json_decode($raw, true);
if (!is_array($event) || empty($event['event'])) {
    exit;
}

$type = $event['event'];
$data = $event['data'] ?? [];

function webhook_find_user_id(array $data): ?int
{
    if (!empty($data['metadata']['user_id'])) {
        return (int) $data['metadata']['user_id'];
    }
    $email = $data['customer']['email'] ?? null;
    if ($email) {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row) {
            return (int) $row['id'];
        }
    }
    return null;
}

/** Identifies which company a company-seat-billing webhook event belongs to — via metadata first, else the stored customer code (renewals don't always echo metadata back). */
function webhook_find_company_id(array $data): ?int
{
    if (!empty($data['metadata']['company_id'])) {
        return (int) $data['metadata']['company_id'];
    }
    $customerCode = $data['customer']['customer_code'] ?? null;
    if ($customerCode) {
        $stmt = db()->prepare('SELECT company_id FROM company_subscriptions WHERE paystack_customer_code = ?');
        $stmt->execute([$customerCode]);
        $row = $stmt->fetch();
        if ($row) {
            return (int) $row['company_id'];
        }
    }
    return null;
}

switch ($type) {
    // Covers both the first payment and every recurring monthly renewal
    // charge Paystack makes automatically against the saved card.
    case 'charge.success':
        $purpose = $data['metadata']['purpose'] ?? '';

        if ($purpose === 'package_purchase') {
            $userId = webhook_find_user_id($data);
            $packageId = (int) ($data['metadata']['package_id'] ?? 0);
            $reference = $data['reference'] ?? '';
            if ($userId && $packageId && $reference) {
                $stmt = db()->prepare('SELECT id FROM payment_transactions WHERE reference = ?');
                $stmt->execute([$reference]);
                if (!$stmt->fetch()) {
                    db()->prepare(
                        'INSERT INTO payment_transactions (user_id, reference, amount_cents, status, paystack_event, raw_response) VALUES (?, ?, ?, "success", ?, ?)'
                    )->execute([$userId, $reference, (int) ($data['amount'] ?? 0), $type, json_encode($event)]);
                }
                $stmt = db()->prepare('SELECT * FROM recruiter_packages WHERE id = ?');
                $stmt->execute([$packageId]);
                $package = $stmt->fetch();
                if ($package) {
                    activate_package_purchase($reference, $userId, $package);
                }
            }
            break;
        }

        if ($purpose === 'addon_purchase') {
            $userId = webhook_find_user_id($data);
            $reference = $data['reference'] ?? '';
            $addonType = $data['metadata']['addon_type'] ?? '';
            $jobId = $data['metadata']['job_id'] ?? null;
            if ($userId && $reference && $addonType) {
                $stmt = db()->prepare('SELECT id FROM payment_transactions WHERE reference = ?');
                $stmt->execute([$reference]);
                if (!$stmt->fetch()) {
                    db()->prepare(
                        'INSERT INTO payment_transactions (user_id, reference, amount_cents, status, paystack_event, raw_response) VALUES (?, ?, ?, "success", ?, ?)'
                    )->execute([$userId, $reference, (int) ($data['amount'] ?? 0), $type, json_encode($event)]);
                }
                activate_addon_purchase($reference, $userId, $addonType, $jobId ? (int) $jobId : null, (int) ($data['amount'] ?? 0));
            }
            break;
        }

        $companyId = webhook_find_company_id($data);
        $userId = $companyId ? null : webhook_find_user_id($data);

        if ($companyId || $userId) {
            $reference = $data['reference'] ?? ('webhook_' . bin2hex(random_bytes(6)));
            $amountCents = (int) ($data['amount'] ?? 0);
            $txnUserId = $companyId ? webhook_find_user_id($data) : $userId;

            $stmt = db()->prepare('SELECT id FROM payment_transactions WHERE reference = ?');
            $stmt->execute([$reference]);
            if (!$stmt->fetch() && $txnUserId) {
                $stmt = db()->prepare(
                    'INSERT INTO payment_transactions (user_id, reference, amount_cents, status, paystack_event, raw_response)
                     VALUES (?, ?, ?, "success", ?, ?)'
                );
                $stmt->execute([$txnUserId, $reference, $amountCents, $type, json_encode($event)]);
            }

            if ($companyId) {
                $seats = (int) ($data['metadata']['seats'] ?? 0);
                if (!$seats) {
                    // Renewal charges don't carry metadata — reuse the seat count already on file.
                    $existing = get_company_subscription($companyId);
                    $seats = (int) ($existing['seat_quantity'] ?? 0);
                }
                if ($seats) {
                    activate_company_subscription($companyId, $seats, $data);
                }
            } elseif ($userId) {
                activate_subscription($userId, $data);
            }
        }
        break;

    case 'subscription.not_renew':
    case 'subscription.disable':
    case 'invoice.payment_failed':
        $newStatus = $type === 'invoice.payment_failed' ? 'past_due' : 'cancelled';
        $companyId = webhook_find_company_id($data);
        if ($companyId) {
            db()->prepare('UPDATE company_subscriptions SET status = ? WHERE company_id = ?')->execute([$newStatus, $companyId]);
            break;
        }
        $userId = webhook_find_user_id($data);
        if ($userId) {
            $stmt = db()->prepare('UPDATE subscriptions SET status = ? WHERE user_id = ?');
            $stmt->execute([$newStatus, $userId]);
        }
        break;

    default:
        // Ignore anything we don't act on (e.g. transfer events) — still 200 OK above.
        break;
}

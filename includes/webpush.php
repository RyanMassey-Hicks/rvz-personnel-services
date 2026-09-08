<?php
/**
 * Web Push — sends real OS-level browser notifications even when the site
 * isn't open, via each browser's native push service (Chrome/Edge → FCM,
 * Firefox → Mozilla push, etc.). Plain PHP + OpenSSL, no Composer/SDK —
 * implements the two relevant standards directly:
 *   - RFC 8291 (Message Encryption for Web Push) — the "aes128gcm" scheme
 *   - RFC 8292 (VAPID) — the signed JWT that identifies this server to the
 *     push service, so it isn't just an open relay anyone could spam through
 *
 * One VAPID "application server" key pair identifies this whole site (see
 * VAPID_PUBLIC_KEY/VAPID_PRIVATE_KEY in config.php); a fresh ephemeral P-256
 * key pair is generated for every single message, per RFC 8291 — that one is
 * NOT the VAPID identity, it only exists to derive that one message's
 * encryption key via ECDH and is then discarded.
 */

class WebPushException extends RuntimeException {}

function webpush_b64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function webpush_b64url_decode(string $data): string
{
    $data = strtr($data, '-_', '+/');
    $pad = strlen($data) % 4;
    if ($pad) {
        $data .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode($data, true);
    if ($decoded === false) {
        throw new WebPushException('Invalid base64url input.');
    }
    return $decoded;
}

/** Wraps a raw 65-byte uncompressed P-256 point (0x04||X||Y) in a PEM SubjectPublicKeyInfo OpenSSL can load. */
function webpush_raw_point_to_public_pem(string $rawPoint): string
{
    if (strlen($rawPoint) !== 65 || $rawPoint[0] !== "\x04") {
        throw new WebPushException('Expected a 65-byte uncompressed P-256 point.');
    }
    // Fixed SEC1/X9.62 SubjectPublicKeyInfo header for a P-256 EC public key.
    $prefix = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
    $der = $prefix . $rawPoint;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/**
 * Wraps our own raw 32-byte VAPID private scalar (+ the already-known public
 * point from config, so no point-multiplication is needed to recover it) in
 * a PEM EC PRIVATE KEY (RFC 5915 / SEC1) OpenSSL can load for signing.
 */
function webpush_vapid_private_pem(): string
{
    $d = str_pad(webpush_b64url_decode(VAPID_PRIVATE_KEY), 32, "\x00", STR_PAD_LEFT);
    $pub = webpush_b64url_decode(VAPID_PUBLIC_KEY);
    if (strlen($pub) !== 65) {
        throw new WebPushException('VAPID_PUBLIC_KEY is not a valid 65-byte point.');
    }
    $x = substr($pub, 1, 32);
    $y = substr($pub, 33, 32);

    $version = "\x02\x01\x01";
    $privKeyOctet = "\x04\x20" . $d;
    $params = "\xA0\x0A\x06\x08\x2A\x86\x48\xCE\x3D\x03\x01\x07"; // [0] { OID prime256v1 }
    $pubBitString = "\x03\x42\x00\x04" . $x . $y;                  // BIT STRING: 0 unused bits, 0x04||X||Y
    $pubKeyExplicit = "\xA1\x44" . $pubBitString;                   // [1] { that BIT STRING }

    $inner = $version . $privKeyOctet . $params . $pubKeyExplicit;
    $der = "\x30" . chr(strlen($inner)) . $inner;

    return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
}

/** Converts an OpenSSL DER ECDSA signature (SEQUENCE of two INTEGERs) to the raw r||s (64 bytes) format JWS ES256 requires. */
function webpush_der_signature_to_raw(string $der): string
{
    $offset = 0;
    if (ord($der[$offset]) !== 0x30) {
        throw new WebPushException('Invalid ECDSA signature (not a DER SEQUENCE).');
    }
    $offset++;
    $seqLen = ord($der[$offset]);
    $offset++;
    if ($seqLen & 0x80) {
        $offset += $seqLen & 0x7F; // long-form length, never hit for a P-256 signature but handled for safety
    }

    if (ord($der[$offset]) !== 0x02) {
        throw new WebPushException('Invalid ECDSA signature (expected INTEGER r).');
    }
    $offset++;
    $rLen = ord($der[$offset]);
    $offset++;
    $r = substr($der, $offset, $rLen);
    $offset += $rLen;

    if (ord($der[$offset]) !== 0x02) {
        throw new WebPushException('Invalid ECDSA signature (expected INTEGER s).');
    }
    $offset++;
    $sLen = ord($der[$offset]);
    $offset++;
    $s = substr($der, $offset, $sLen);

    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
    return $r . $s;
}

function webpush_hkdf_extract(string $salt, string $ikm): string
{
    return hash_hmac('sha256', $ikm, $salt, true);
}

function webpush_hkdf_expand_one_block(string $prk, string $info): string
{
    // Every derivation this file needs fits in a single HKDF-Expand block
    // (<=32 bytes of output), so this is just the one HMAC call — no need
    // for a general multi-block HKDF-Expand loop.
    return hash_hmac('sha256', $info . "\x01", $prk, true);
}

/**
 * RFC 8291 message encryption. Returns the raw binary body (header + AEAD
 * ciphertext) ready to POST as the push message with Content-Encoding: aes128gcm.
 */
function webpush_encrypt_payload(string $payload, string $p256dhB64, string $authB64): string
{
    $uaPublicRaw = webpush_b64url_decode($p256dhB64);
    $authSecret = webpush_b64url_decode($authB64);
    if (strlen($uaPublicRaw) !== 65 || strlen($authSecret) !== 16) {
        throw new WebPushException('Invalid subscription keys.');
    }

    $ephemeral = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if (!$ephemeral) {
        throw new WebPushException('Could not generate ephemeral EC key: ' . openssl_error_string());
    }
    $ephDetails = openssl_pkey_get_details($ephemeral);
    $asPublicRaw = "\x04"
        . str_pad($ephDetails['ec']['x'], 32, "\x00", STR_PAD_LEFT)
        . str_pad($ephDetails['ec']['y'], 32, "\x00", STR_PAD_LEFT);

    $uaPublicKey = openssl_pkey_get_public(webpush_raw_point_to_public_pem($uaPublicRaw));
    if (!$uaPublicKey) {
        throw new WebPushException('Invalid subscriber public key.');
    }

    $ecdhSecret = openssl_pkey_derive($uaPublicKey, $ephemeral);
    if ($ecdhSecret === false || strlen($ecdhSecret) !== 32) {
        throw new WebPushException('ECDH derivation failed.');
    }

    // Combine the ECDH secret with the subscription's auth secret (RFC 8291 §3.3-3.4).
    $keyInfo = "WebPush: info\x00" . $uaPublicRaw . $asPublicRaw;
    $prkCombine = webpush_hkdf_extract($authSecret, $ecdhSecret);
    $ikm = webpush_hkdf_expand_one_block($prkCombine, $keyInfo);

    // RFC 8188 (aes128gcm) content encoding, keyed off that IKM.
    $salt = random_bytes(16);
    $prk = webpush_hkdf_extract($salt, $ikm);
    $cek = substr(webpush_hkdf_expand_one_block($prk, "Content-Encoding: aes128gcm\x00"), 0, 16);
    $nonce = substr(webpush_hkdf_expand_one_block($prk, "Content-Encoding: nonce\x00"), 0, 12);

    $plaintext = $payload . "\x02"; // delimiter: this is the only (so "last") record
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    if ($ciphertext === false) {
        throw new WebPushException('Payload encryption failed: ' . openssl_error_string());
    }
    $recordBody = $ciphertext . $tag;

    $header = $salt . pack('N', strlen($recordBody)) . chr(strlen($asPublicRaw)) . $asPublicRaw;
    return $header . $recordBody;
}

/** Builds the VAPID Authorization header value ("vapid t=<jwt>, k=<public key>") for one push endpoint. */
function webpush_vapid_auth_header(string $endpoint): string
{
    $aud = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);
    $header = webpush_b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $payload = webpush_b64url_encode(json_encode([
        'aud' => $aud,
        'exp' => time() + 12 * 3600,
        'sub' => VAPID_SUBJECT,
    ]));
    $signingInput = $header . '.' . $payload;

    $privKey = openssl_pkey_get_private(webpush_vapid_private_pem());
    if (!$privKey) {
        throw new WebPushException('Invalid VAPID private key: ' . openssl_error_string());
    }
    $der = '';
    if (!openssl_sign($signingInput, $der, $privKey, OPENSSL_ALGO_SHA256)) {
        throw new WebPushException('VAPID JWT signing failed: ' . openssl_error_string());
    }
    $jwt = $signingInput . '.' . webpush_b64url_encode(webpush_der_signature_to_raw($der));

    return 'vapid t=' . $jwt . ', k=' . VAPID_PUBLIC_KEY;
}

/**
 * Sends one push message. Returns ['success'=>bool,'status'=>int,'expired'=>bool,'error'=>string].
 * 'expired' means the push service says this subscription is gone for good
 * (404/410) — the caller should delete it. Never throws for a network/HTTP
 * failure (that's a normal, expected outcome); does throw WebPushException
 * for a genuine misconfiguration (bad keys, encryption failure).
 */
function webpush_send(string $endpoint, string $p256dhB64, string $authB64, string $payloadJson, int $ttl = 86400): array
{
    $body = webpush_encrypt_payload($payloadJson, $p256dhB64, $authB64);
    $authHeader = webpush_vapid_auth_header($endpoint);

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: ' . $ttl,
            'Authorization: ' . $authHeader,
        ],
        CURLOPT_POSTFIELDS => $body,
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return [
        'success' => $raw !== false && $status >= 200 && $status < 300,
        'status' => $status,
        'expired' => in_array($status, [404, 410], true),
        'error' => $err,
    ];
}

/**
 * Sends a push notification to every browser/device this user has granted
 * permission on. Called from create_notification() so every current and
 * future notification type gets this automatically — no per-feature wiring.
 * Best-effort: a failed or dead subscription never breaks the caller.
 */
function webpush_notify_user(int $userId, string $title, string $body, string $link = ''): void
{
    if (!defined('VAPID_PUBLIC_KEY') || VAPID_PUBLIC_KEY === '') {
        return;
    }
    $stmt = db()->prepare('SELECT id, endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?');
    $stmt->execute([$userId]);
    $subs = $stmt->fetchAll();
    if (!$subs) {
        return;
    }

    $payload = json_encode(['title' => $title, 'body' => $body, 'link' => $link]);

    foreach ($subs as $sub) {
        try {
            $result = webpush_send($sub['endpoint'], $sub['p256dh'], $sub['auth'], $payload);
            if ($result['expired']) {
                db()->prepare('DELETE FROM push_subscriptions WHERE id = ?')->execute([$sub['id']]);
            } elseif (!$result['success']) {
                error_log('[webpush] send failed for subscription ' . $sub['id'] . ': HTTP ' . $result['status'] . ' ' . $result['error']);
            }
        } catch (Throwable $e) {
            error_log('[webpush] exception for subscription ' . $sub['id'] . ': ' . $e->getMessage());
        }
    }
}

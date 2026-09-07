<?php
/**
 * A small hand-rolled SMTP client — no PHPMailer/Composer needed, just PHP's
 * built-in streams (matches includes/http.php's "no SDKs" approach). Talks
 * SMTP directly to the Xneelo mailbox configured in config.php.
 *
 * Until SMTP_USER/SMTP_PASS are filled in, send_email() logs the message
 * instead of sending it, so every caller can be wired up now without an
 * active mailbox blocking development (same "leave blank for now" pattern
 * config.sample.php already uses for Paystack/OAuth).
 */

class SmtpException extends RuntimeException {}

/**
 * $smtp, when given, overrides the site's own SMTP_* config for this one
 * send — used for companies with their own custom outbound email set up
 * (see get_company_smtp_config()). Keys: host, port, secure, user, pass,
 * from_email, from_name.
 */
function smtp_send_raw(string $to, string $toName, string $subject, string $htmlBody, array $attachments = [], ?array $smtp = null): void
{
    $smtpHost = $smtp['host'] ?? SMTP_HOST;
    $smtpPort = $smtp['port'] ?? SMTP_PORT;
    $smtpSecure = $smtp['secure'] ?? SMTP_SECURE;
    $smtpUser = $smtp['user'] ?? SMTP_USER;
    $smtpPass = $smtp['pass'] ?? SMTP_PASS;
    $fromEmail = $smtp['from_email'] ?? SMTP_FROM_EMAIL;
    $fromName = $smtp['from_name'] ?? SMTP_FROM_NAME;

    $host = $smtpSecure === 'ssl' ? 'ssl://' . $smtpHost : $smtpHost;
    $sock = @stream_socket_client($host . ':' . $smtpPort, $errno, $errstr, 15);
    if (!$sock) {
        throw new SmtpException("Could not connect to SMTP host: $errstr ($errno)");
    }

    $expect = function (int $wantCode) use ($sock) {
        $line = '';
        do {
            $line = fgets($sock, 515);
            if ($line === false) {
                throw new SmtpException('SMTP connection closed unexpectedly.');
            }
        } while (isset($line[3]) && $line[3] === '-'); // multi-line response, keep reading
        $code = (int) substr($line, 0, 3);
        if ($code !== $wantCode) {
            throw new SmtpException("SMTP error, expected $wantCode, got: " . trim($line));
        }
        return $line;
    };
    $send = function (string $line) use ($sock) {
        fwrite($sock, $line . "\r\n");
    };

    $expect(220);
    $send('EHLO ' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost'));
    $expect(250);

    if ($smtpSecure === 'tls') {
        $send('STARTTLS');
        $expect(220);
        if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new SmtpException('STARTTLS negotiation failed.');
        }
        $send('EHLO ' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost'));
        $expect(250);
    }

    $send('AUTH LOGIN');
    $expect(334);
    $send(base64_encode($smtpUser));
    $expect(334);
    $send(base64_encode($smtpPass));
    $expect(235);

    $send('MAIL FROM:<' . $fromEmail . '>');
    $expect(250);
    $send('RCPT TO:<' . $to . '>');
    $expect(250);
    $send('DATA');
    $expect(354);

    $boundary = 'rvz-' . bin2hex(random_bytes(12));
    $headers = [];
    $headers[] = 'From: ' . mime_header_encode($fromName) . ' <' . $fromEmail . '>';
    $headers[] = 'To: ' . ($toName !== '' ? mime_header_encode($toName) . ' <' . $to . '>' : $to);
    $headers[] = 'Subject: ' . mime_header_encode($subject);
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost') . '>';

    $plainBody = trim(strip_tags(preg_replace('/<br\s*\/?>|<\/p>/i', "\n", $htmlBody)));
    $altBoundary = 'alt-' . bin2hex(random_bytes(8));

    $body = '';
    if ($attachments) {
        $headers[] = "Content-Type: multipart/mixed; boundary=\"$boundary\"";
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: multipart/alternative; boundary=\"$altBoundary\"\r\n\r\n";
    } else {
        $headers[] = "Content-Type: multipart/alternative; boundary=\"$altBoundary\"";
    }

    $body .= "--$altBoundary\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($plainBody));
    $body .= "--$altBoundary\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($htmlBody));
    $body .= "--$altBoundary--\r\n";

    foreach ($attachments as $att) {
        $data = $att['data'] ?? (isset($att['path']) ? file_get_contents($att['path']) : false);
        if ($data === false) {
            continue;
        }
        $body .= "--$boundary\r\n";
        $body .= 'Content-Type: ' . ($att['mime'] ?? 'application/octet-stream') . '; name="' . $att['name'] . "\"\r\n";
        $body .= 'Content-Disposition: attachment; filename="' . $att['name'] . "\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($data));
    }
    if ($attachments) {
        $body .= "--$boundary--\r\n";
    }

    $send(implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.");
    $expect(250);
    $send('QUIT');
    fclose($sock);
}

function mime_header_encode(string $value): string
{
    if (preg_match('/^[\x20-\x7E]*$/', $value)) {
        return $value; // plain ASCII, no encoding needed
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

/**
 * Send an HTML email. Returns true on success. Never throws — logs and
 * returns false instead, so a mail hiccup never breaks the page that
 * triggered it (an application submit, a stage move, etc). $smtpOverride,
 * when given (see get_company_smtp_config()), sends via that company's own
 * SMTP mailbox instead of the site's shared one.
 */
function send_email(string $to, string $subject, string $htmlBody, string $toName = '', array $attachments = [], ?array $smtpOverride = null): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $smtpUser = $smtpOverride['user'] ?? SMTP_USER;
    $smtpPass = $smtpOverride['pass'] ?? SMTP_PASS;
    if ($smtpUser === '' || $smtpPass === '') {
        error_log("[mailer] SMTP not configured yet — would have sent \"$subject\" to $to");
        return false;
    }
    try {
        smtp_send_raw($to, $toName, $subject, $htmlBody, $attachments, $smtpOverride);
        return true;
    } catch (Throwable $e) {
        error_log('[mailer] send failed: ' . $e->getMessage());
        return false;
    }
}

/** Shared header/footer wrapper so every system email looks consistent. */
function email_wrap(string $bodyHtml): string
{
    $siteName = h(SITE_NAME);
    return '<div style="font-family:Segoe UI,Helvetica,Arial,sans-serif;max-width:560px;margin:0 auto;color:#1a1a2e;">'
        . '<div style="background:#0a1f44;padding:20px 24px;border-radius:8px 8px 0 0;">'
        . '<span style="color:#fff;font-size:18px;font-weight:700;letter-spacing:-.3px;">' . $siteName . '</span></div>'
        . '<div style="border:1px solid #e2e5ec;border-top:0;padding:24px;border-radius:0 0 8px 8px;">'
        . $bodyHtml
        . '</div>'
        . '<p style="color:#8a8f9c;font-size:12px;margin-top:16px;">' . $siteName . ' &middot; ' . h(COMPANY_LEGAL_NAME) . ' (Reg. ' . h(COMPANY_REG_NUMBER) . ')</p>'
        . '</div>';
}

function send_application_confirmation(array $user, array $job, string $companyName): bool
{
    $body = "<p>Hi " . h($user['first_name'] ?: $user['username']) . ",</p>"
        . "<p>Your application for <strong>" . h($job['title']) . "</strong> at <strong>" . h($companyName) . "</strong> has been received.</p>"
        . "<p>You can track its progress any time from <a href=\"" . h(base_url('my_applications.php')) . "\">My Applications</a>.</p>"
        . "<p>Good luck!</p>";
    $smtp = !empty($job['company_id']) ? get_company_smtp_config((int) $job['company_id']) : null;
    return send_email($user['email'], 'Application received: ' . $job['title'], email_wrap($body), trim($user['first_name'] . ' ' . $user['last_name']), [], $smtp);
}

function send_stage_change_notification(array $user, array $job, string $companyName, string $stageLabel): bool
{
    $body = "<p>Hi " . h($user['first_name'] ?: $user['username']) . ",</p>"
        . "<p>There's an update on your application for <strong>" . h($job['title']) . "</strong> at <strong>" . h($companyName) . "</strong>:</p>"
        . "<p style=\"font-size:18px;font-weight:700;color:#0a1f44;\">" . h($stageLabel) . "</p>"
        . "<p><a href=\"" . h(base_url('my_applications.php')) . "\">View your applications</a></p>";
    $smtp = !empty($job['company_id']) ? get_company_smtp_config((int) $job['company_id']) : null;
    return send_email($user['email'], 'Update on your application: ' . $job['title'], email_wrap($body), trim($user['first_name'] . ' ' . $user['last_name']), [], $smtp);
}

function send_new_application_notice_to_recruiter(array $recruiter, array $job, array $candidate): bool
{
    $body = "<p>Hi " . h($recruiter['first_name'] ?: $recruiter['username']) . ",</p>"
        . "<p><strong>" . h(trim($candidate['first_name'] . ' ' . $candidate['last_name']) ?: $candidate['username']) . "</strong> just applied for <strong>" . h($job['title']) . "</strong>.</p>"
        . "<p><a href=\"" . h(base_url('pipeline.php?id=' . $job['id'])) . "\">Review in your pipeline</a></p>";
    $smtp = !empty($job['company_id']) ? get_company_smtp_config((int) $job['company_id']) : null;
    return send_email($recruiter['email'], 'New application: ' . $job['title'], email_wrap($body), trim($recruiter['first_name'] . ' ' . $recruiter['last_name']), [], $smtp);
}
